import { networkInterfaces } from 'node:os'
import { Router, type Request } from 'express'
import { createHash } from 'node:crypto'
import { z } from 'zod'
import { prisma } from '../lib/prisma.js'
import { asyncHandler, asDate, dateKey, HttpError, pageParams, paged, parseBody, round2, todayUtc } from '../lib/http.js'
import { assertEmployeeVisible, canApproveAsManager, employeeWhere, isHr, requireRoles, requireUser } from '../lib/access.js'
import { activeCalendar, combineDateAndTime, countDays, holidaySet, minutesEarly, minutesLate, minutesOvertime } from '../lib/calendar.js'
import { ensureCurrentBalances, fullName } from '../lib/employees.js'
import { renderLeaveForm } from '../lib/letters.js'
import { notify, usersByRoles } from '../lib/notify.js'
import { audit } from '../lib/audit.js'
import { addDays } from '../lib/http.js'

const router = Router()
const optional = z.string().trim().optional().or(z.literal('')).transform((value) => value || undefined)

async function workflowSteps(key: string) {
  const config = await prisma.workflowConfig.findUnique({ where: { key } })
  return (config?.steps as string[] | null) || ['MANAGER', 'HR']
}

function sha256(value: string) {
  return createHash('sha256').update(value).digest('hex')
}

async function resolveShift(employeeId: string, date: Date) {
  const calendar = await activeCalendar()
  const assignment = await prisma.shiftAssignment.findFirst({
    where: {
      employeeId,
      effectiveFrom: { lte: date },
      OR: [{ effectiveTo: null }, { effectiveTo: { gte: date } }],
    },
    include: { shift: true },
    orderBy: { effectiveFrom: 'desc' },
  })
  if (!assignment) return calendar
  const days = assignment.shift.workDays as number[]
  return {
    ...calendar,
    startTime: assignment.shift.startTime,
    endTime: assignment.shift.endTime,
    graceMinutes: assignment.shift.graceMinutes,
    workDays: days,
  }
}

function clientIp(req: Request) {
  const forwarded = req.headers['x-forwarded-for']
  const raw = Array.isArray(forwarded) ? forwarded[0] : forwarded
  return String(raw?.split(',')[0] || req.ip || '').replace(/^::ffff:/, '')
}

function metresBetween(lat1: number, lon1: number, lat2: number, lon2: number) {
  const toRad = (value: number) => value * Math.PI / 180
  const dLat = toRad(lat2 - lat1)
  const dLon = toRad(lon2 - lon1)
  const a = Math.sin(dLat / 2) ** 2 + Math.cos(toRad(lat1)) * Math.cos(toRad(lat2)) * Math.sin(dLon / 2) ** 2
  return 2 * 6371000 * Math.asin(Math.sqrt(a))
}

async function assertInsideFence(latitude?: number, longitude?: number) {
  const calendar = await prisma.workingCalendar.findFirst({ where: { isActive: true } })
  if (calendar?.latitude == null || calendar.longitude == null) return
  if (latitude == null || longitude == null) {
    throw new HttpError(400, 'Allow location on your phone, then clock in from the office.')
  }
  const distance = metresBetween(Number(calendar.latitude), Number(calendar.longitude), latitude, longitude)
  const radius = calendar.radiusMeters || 200
  if (distance > radius) {
    throw new HttpError(403, `You are ${Math.round(distance)} m from the office. Clock-in is allowed within ${radius} m.`)
  }
}

async function applyPunch(employeeId: string, when: Date, source: 'CLOCK' | 'BIOMETRIC' | 'MANUAL', notes?: string, ip?: string) {
  const day = new Date(Date.UTC(when.getUTCFullYear(), when.getUTCMonth(), when.getUTCDate()))
  const shift = await resolveShift(employeeId, day)
  const expectedIn = combineDateAndTime(day, shift.startTime)
  const expectedOut = combineDateAndTime(day, shift.endTime)
  const existing = await prisma.attendanceRecord.findUnique({
    where: { employeeId_date: { employeeId, date: day } },
  })
  if (!existing || !existing.clockIn) {
    const late = minutesLate(when, expectedIn, shift.graceMinutes)
    return prisma.attendanceRecord.upsert({
      where: { employeeId_date: { employeeId, date: day } },
      update: { clockIn: when, status: late ? 'LATE' : 'PRESENT', lateMinutes: late, source, notes, clockInIp: ip },
      create: {
        employeeId,
        date: day,
        clockIn: when,
        status: late ? 'LATE' : 'PRESENT',
        lateMinutes: late,
        source,
        notes,
        clockInIp: ip,
      },
    })
  }
  const early = minutesEarly(when, expectedOut)
  const extra = minutesOvertime(when, expectedOut)
  const record = await prisma.attendanceRecord.update({
    where: { id: existing.id },
    data: {
      clockOut: when,
      earlyDepartureMinutes: early,
      overtimeMinutes: extra,
      source,
      notes: notes || existing.notes,
      clockOutIp: ip,
    },
  })
  if (extra >= 30) {
    const weekend = !shift.workDays.includes(day.getUTCDay())
    await prisma.overtimeRequest.create({
      data: {
        employeeId,
        date: day,
        hours: round2(extra / 60),
        multiplier: weekend ? shift.overtimeWeekend : shift.overtimeWeekday,
        reason: 'Recorded from clock-out beyond the scheduled end time.',
        status: 'PENDING_MANAGER',
      },
    })
  }
  return record
}

router.get('/attendance', asyncHandler(async (req, res) => {
  const user = requireUser(req)
  const { page, pageSize, skip } = pageParams(req)
  const employees = await prisma.employee.findMany({ where: employeeWhere(user), select: { id: true } })
  const where = {
    employeeId: req.query.employeeId ? String(req.query.employeeId) : { in: employees.map((item) => item.id) },
    ...(req.query.from || req.query.to ? {
      date: {
        ...(req.query.from ? { gte: asDate(String(req.query.from)) } : {}),
        ...(req.query.to ? { lte: asDate(String(req.query.to)) } : {}),
      },
    } : {}),
    ...(req.query.status ? { status: String(req.query.status) as never } : {}),
  }
  const [items, total] = await Promise.all([
    prisma.attendanceRecord.findMany({
      where,
      include: { employee: { select: { id: true, firstName: true, lastName: true, employeeNo: true, departmentId: true } } },
      orderBy: { date: 'desc' },
      skip,
      take: pageSize,
    }),
    prisma.attendanceRecord.count({ where }),
  ])
  const showIp = user.role === 'SUPER_ADMIN'
  res.json(paged(items.map((item) => showIp ? item : { ...item, clockInIp: null, clockOutIp: null }), total, page, pageSize))
}))

function officeAddresses() {
  const virtual = /vmware|virtualbox|hyper-v|vethernet|wsl|loopback|bluetooth|tunnel|tap-|vpn|pseudo/i
  const listed = Object.entries(networkInterfaces()).flatMap(([name, items]) => {
    if (virtual.test(name)) return []
    return (items || []).flatMap((item) => {
      if (!item || item.internal || (item.family !== 'IPv4' && item.family !== 4)) return []
      if (item.address.startsWith('169.254.')) return []
      return [item.address]
    })
  })
  return [...new Set(listed)]
}

router.get('/clock-address', requireRoles('SUPER_ADMIN'), asyncHandler(async (_req, res) => {
  const addresses = officeAddresses()
  res.json({
    addresses,
    port: Number(process.env.PORT || 4000),
    path: '/api/time/biometric/punch',
  })
}))

router.post('/attendance', requireRoles('HR_ADMIN', 'HR_MANAGER', 'DEPARTMENT_MANAGER', 'SUPERVISOR'), asyncHandler(async (req, res) => {
  const body = parseBody(z.object({
    employeeId: z.string(),
    date: z.string(),
    status: z.enum(['PRESENT', 'ABSENT', 'LATE', 'LEAVE', 'HOLIDAY', 'OFF', 'REMOTE']),
    clockIn: optional,
    clockOut: optional,
    notes: optional,
  }), req.body)
  const employee = await prisma.employee.findUnique({ where: { id: body.employeeId } })
  if (!employee) throw new HttpError(404, 'Employee not found.')
  assertEmployeeVisible(requireUser(req), employee)
  const saved = await prisma.attendanceRecord.upsert({
    where: { employeeId_date: { employeeId: body.employeeId, date: asDate(body.date) } },
    update: {
      status: body.status,
      clockIn: body.clockIn ? new Date(body.clockIn) : null,
      clockOut: body.clockOut ? new Date(body.clockOut) : null,
      notes: body.notes,
      source: 'MANUAL',
    },
    create: {
      employeeId: body.employeeId,
      date: asDate(body.date),
      status: body.status,
      clockIn: body.clockIn ? new Date(body.clockIn) : null,
      clockOut: body.clockOut ? new Date(body.clockOut) : null,
      notes: body.notes,
      source: 'MANUAL',
    },
  })
  await audit(req, 'UPDATE', 'Attendance', saved.id, `Attendance recorded for ${body.date}`)
  res.status(201).json(saved)
}))

function accraNow() {
  const parts = new Intl.DateTimeFormat('en-GB', {
    timeZone: 'Africa/Accra',
    year: 'numeric',
    month: '2-digit',
    day: '2-digit',
    hour: '2-digit',
    minute: '2-digit',
    second: '2-digit',
    hourCycle: 'h23',
  }).formatToParts(new Date())
  const value = (type: string) => Number(parts.find((part) => part.type === type)?.value)
  return new Date(Date.UTC(value('year'), value('month') - 1, value('day'), value('hour'), value('minute'), value('second')))
}

const locationBody = z.object({
  latitude: z.coerce.number().min(-90).max(90).optional(),
  longitude: z.coerce.number().min(-180).max(180).optional(),
})

router.post('/clock-in', asyncHandler(async (req, res) => {
  const user = requireUser(req)
  if (!user.employeeId) throw new HttpError(400, 'Your account is not linked to an employee.')
  const body = parseBody(locationBody, req.body || {})
  await assertInsideFence(body.latitude, body.longitude)
  const record = await applyPunch(user.employeeId, accraNow(), 'CLOCK', undefined, clientIp(req))
  res.json(record)
}))

router.post('/clock-out', asyncHandler(async (req, res) => {
  const user = requireUser(req)
  if (!user.employeeId) throw new HttpError(400, 'Your account is not linked to an employee.')
  const body = parseBody(locationBody, req.body || {})
  await assertInsideFence(body.latitude, body.longitude)
  const record = await applyPunch(user.employeeId, accraNow(), 'CLOCK', undefined, clientIp(req))
  res.json(record)
}))

router.post('/biometric/punch', asyncHandler(async (req, res) => {
  const body = parseBody(z.object({
    deviceKey: z.string().min(4),
    employeeNo: z.string(),
    timestamp: z.string(),
  }), req.body)
  const hash = sha256(body.deviceKey)
  const device = await prisma.biometricDevice.findFirst({ where: { apiKeyHash: hash, isActive: true } })
  const envKey = process.env.BIOMETRIC_DEVICE_KEY
  if (!device && (!envKey || sha256(envKey) !== hash)) {
    throw new HttpError(401, 'Unrecognised attendance device.')
  }
  const employee = await prisma.employee.findUnique({ where: { employeeNo: body.employeeNo } })
  if (!employee) throw new HttpError(404, 'Employee number not found.')
  const record = await applyPunch(employee.id, new Date(body.timestamp), 'BIOMETRIC', device?.name, clientIp(req))
  res.json(record)
}))

router.get('/corrections', asyncHandler(async (req, res) => {
  const user = requireUser(req)
  const employees = await prisma.employee.findMany({ where: employeeWhere(user), select: { id: true } })
  const items = await prisma.attendanceCorrection.findMany({
    where: { employeeId: { in: employees.map((item) => item.id) } },
    include: { employee: { select: { firstName: true, lastName: true, employeeNo: true } } },
    orderBy: { createdAt: 'desc' },
  })
  res.json(items)
}))

router.post('/corrections', asyncHandler(async (req, res) => {
  const user = requireUser(req)
  const body = parseBody(z.object({
    employeeId: optional,
    date: z.string(),
    reason: z.string().min(5),
    requestedClockIn: optional,
    requestedClockOut: optional,
  }), req.body)
  const employeeId = body.employeeId || user.employeeId
  if (!employeeId) throw new HttpError(400, 'Choose an employee.')
  const employee = await prisma.employee.findUnique({ where: { id: employeeId } })
  if (!employee) throw new HttpError(404, 'Employee not found.')
  assertEmployeeVisible(user, employee)
  const attendance = await prisma.attendanceRecord.findUnique({
    where: { employeeId_date: { employeeId, date: asDate(body.date) } },
  })
  const created = await prisma.attendanceCorrection.create({
    data: {
      employeeId,
      attendanceId: attendance?.id,
      date: asDate(body.date),
      reason: body.reason,
      requestedClockIn: body.requestedClockIn ? new Date(body.requestedClockIn) : null,
      requestedClockOut: body.requestedClockOut ? new Date(body.requestedClockOut) : null,
    },
  })
  const manager = employee.managerId ? await prisma.user.findUnique({ where: { employeeId: employee.managerId } }) : null
  await notify({
    userIds: [manager?.id, ...(await usersByRoles(['HR_ADMIN', 'HR_MANAGER']))],
    title: 'Attendance correction requested',
    body: `${fullName(employee)} requested a correction for ${body.date}.`,
    type: 'ATTENDANCE',
    link: '/attendance',
  })
  res.status(201).json(created)
}))

router.post('/corrections/:id/decision', requireRoles('SUPERVISOR', 'DEPARTMENT_MANAGER', 'HR_ADMIN', 'HR_MANAGER'), asyncHandler(async (req, res) => {
  const user = requireUser(req)
  const body = parseBody(z.object({ decision: z.enum(['APPROVED', 'REJECTED']), note: optional }), req.body)
  const correction = await prisma.attendanceCorrection.findUnique({
    where: { id: String(req.params.id) },
    include: { employee: true },
  })
  if (!correction) throw new HttpError(404, 'Request not found.')
  if (!canApproveAsManager(user, correction.employee) && !isHr(user.role)) throw new HttpError(403, 'You cannot review this request.')
  if (body.decision === 'APPROVED') {
    await prisma.attendanceRecord.upsert({
      where: { employeeId_date: { employeeId: correction.employeeId, date: correction.date } },
      update: {
        clockIn: correction.requestedClockIn,
        clockOut: correction.requestedClockOut,
        status: 'PRESENT',
        source: 'MANUAL',
        notes: correction.reason,
      },
      create: {
        employeeId: correction.employeeId,
        date: correction.date,
        clockIn: correction.requestedClockIn,
        clockOut: correction.requestedClockOut,
        status: 'PRESENT',
        source: 'MANUAL',
        notes: correction.reason,
      },
    })
  }
  const updated = await prisma.attendanceCorrection.update({
    where: { id: correction.id },
    data: { status: body.decision, decisionNote: body.note },
  })
  const account = await prisma.user.findUnique({ where: { employeeId: correction.employeeId } })
  await notify({
    userIds: [account?.id],
    title: `Attendance correction ${body.decision.toLowerCase()}`,
    body: body.note || correction.reason,
    type: 'ATTENDANCE',
    link: '/attendance',
  })
  res.json(updated)
}))

router.get('/overtime', asyncHandler(async (req, res) => {
  const user = requireUser(req)
  const employees = await prisma.employee.findMany({ where: employeeWhere(user), select: { id: true } })
  res.json(await prisma.overtimeRequest.findMany({
    where: { employeeId: { in: employees.map((item) => item.id) } },
    include: { employee: { select: { firstName: true, lastName: true, employeeNo: true } } },
    orderBy: { date: 'desc' },
  }))
}))

router.post('/overtime', asyncHandler(async (req, res) => {
  const user = requireUser(req)
  const body = parseBody(z.object({
    employeeId: optional,
    date: z.string(),
    hours: z.coerce.number().positive(),
    reason: z.string().min(5),
  }), req.body)
  const employeeId = body.employeeId || user.employeeId
  if (!employeeId) throw new HttpError(400, 'Choose an employee.')
  const calendar = await activeCalendar()
  const day = asDate(body.date)
  const weekend = !calendar.workDays.includes(day.getUTCDay())
  const created = await prisma.overtimeRequest.create({
    data: {
      employeeId,
      date: day,
      hours: body.hours,
      multiplier: weekend ? calendar.overtimeWeekend : calendar.overtimeWeekday,
      reason: body.reason,
    },
  })
  res.status(201).json(created)
}))

router.post('/overtime/:id/decision', requireRoles('SUPERVISOR', 'DEPARTMENT_MANAGER', 'HR_ADMIN', 'HR_MANAGER'), asyncHandler(async (req, res) => {
  const body = parseBody(z.object({ decision: z.enum(['APPROVED', 'REJECTED']), note: optional }), req.body)
  const updated = await prisma.overtimeRequest.update({
    where: { id: String(req.params.id) },
    data: { status: body.decision, decisionNote: body.note },
  })
  res.json(updated)
}))

router.post('/shifts', requireRoles('HR_ADMIN', 'HR_MANAGER', 'DEPARTMENT_MANAGER'), asyncHandler(async (req, res) => {
  const body = parseBody(z.object({
    employeeId: z.string(),
    shiftId: z.string(),
    effectiveFrom: z.string(),
    effectiveTo: optional,
  }), req.body)
  const created = await prisma.shiftAssignment.create({
    data: {
      employeeId: body.employeeId,
      shiftId: body.shiftId,
      effectiveFrom: asDate(body.effectiveFrom),
      effectiveTo: body.effectiveTo ? asDate(body.effectiveTo) : null,
    },
    include: { shift: true, employee: { select: { firstName: true, lastName: true, employeeNo: true } } },
  })
  res.status(201).json(created)
}))

router.get('/shifts', asyncHandler(async (req, res) => {
  const user = requireUser(req)
  const employees = await prisma.employee.findMany({ where: employeeWhere(user), select: { id: true } })
  res.json(await prisma.shiftAssignment.findMany({
    where: { employeeId: { in: employees.map((item) => item.id) } },
    include: { shift: true, employee: { select: { id: true, firstName: true, lastName: true, employeeNo: true } } },
    orderBy: { effectiveFrom: 'desc' },
  }))
}))

router.get('/leave/balances', asyncHandler(async (req, res) => {
  const user = requireUser(req)
  const employeeId = String(req.query.employeeId || user.employeeId || '')
  if (!employeeId) throw new HttpError(400, 'Choose an employee.')
  const employee = await prisma.employee.findUnique({ where: { id: employeeId } })
  if (!employee) throw new HttpError(404, 'Employee not found.')
  assertEmployeeVisible(user, employee)
  await ensureCurrentBalances(employee.id)
  const year = Number(req.query.year) || new Date().getUTCFullYear()
  res.json(await prisma.leaveBalance.findMany({
    where: { employeeId, year },
    include: { leaveType: true },
  }))
}))

router.get('/leave/requests', asyncHandler(async (req, res) => {
  const user = requireUser(req)
  const { page, pageSize, skip } = pageParams(req)
  const employees = await prisma.employee.findMany({ where: employeeWhere(user), select: { id: true } })
  const where = {
    employeeId: req.query.employeeId ? String(req.query.employeeId) : { in: employees.map((item) => item.id) },
    ...(req.query.status ? { status: String(req.query.status) as never } : {}),
  }
  const [items, total] = await Promise.all([
    prisma.leaveRequest.findMany({
      where,
      include: {
        leaveType: true,
        employee: { select: { id: true, firstName: true, lastName: true, employeeNo: true, departmentId: true, managerId: true } },
      },
      orderBy: { createdAt: 'desc' },
      skip,
      take: pageSize,
    }),
    prisma.leaveRequest.count({ where }),
  ])
  res.json(paged(items, total, page, pageSize))
}))

router.get('/leave/calendar', asyncHandler(async (req, res) => {
  const user = requireUser(req)
  const start = req.query.from ? asDate(String(req.query.from)) : todayUtc()
  const end = req.query.to ? asDate(String(req.query.to)) : addDays(start, 31)
  const employees = await prisma.employee.findMany({ where: employeeWhere(user), select: { id: true } })
  const items = await prisma.leaveRequest.findMany({
    where: {
      employeeId: { in: employees.map((item) => item.id) },
      status: 'APPROVED',
      startDate: { lte: end },
      endDate: { gte: start },
    },
    include: { employee: { select: { firstName: true, lastName: true, employeeNo: true } }, leaveType: true },
  })
  res.json(items)
}))

router.get('/leave/form', asyncHandler(async (req, res) => {
  const user = requireUser(req)
  const requestId = req.query.requestId ? String(req.query.requestId) : ''
  const request = requestId
    ? await prisma.leaveRequest.findUnique({ where: { id: requestId }, include: { leaveType: true } })
    : null
  if (requestId && !request) throw new HttpError(404, 'Leave request not found.')
  const employeeId = request?.employeeId || String(req.query.employeeId || user.employeeId || '')
  if (!employeeId) throw new HttpError(400, 'This sign-in is not linked to an employee.')
  const employee = await prisma.employee.findUnique({
    where: { id: employeeId },
    include: { department: true, jobTitle: true, location: true },
  })
  if (!employee) throw new HttpError(404, 'Employee not found.')
  assertEmployeeVisible(user, employee)
  await ensureCurrentBalances(employee.id)
  const year = new Date().getUTCFullYear()
  const [company, balances, types] = await Promise.all([
    prisma.company.findFirst(),
    prisma.leaveBalance.findMany({ where: { employeeId, year }, include: { leaveType: true }, orderBy: { leaveType: { name: 'asc' } } }),
    prisma.leaveType.findMany({ orderBy: { name: 'asc' } }),
  ])
  const buffer = await renderLeaveForm({
    company: company?.name || 'Zentrix HR',
    employeeNo: employee.employeeNo,
    name: fullName(employee),
    department: employee.department.name,
    job: employee.jobTitle.name,
    location: `${employee.location.name}, ${employee.location.city}`,
    phone: employee.phone,
    balances: balances.map((balance) => ({
      name: balance.leaveType.name,
      left: Math.round(Number(balance.entitled) - Number(balance.used) - Number(balance.pending)),
      used: Math.round(Number(balance.used)),
    })),
    types: types.filter((type) => !type.gender || type.gender === employee.gender).map((type) => type.name),
    request: request ? {
      type: request.leaveType.name,
      start: request.startDate,
      end: request.endDate,
      days: Math.round(Number(request.days)),
      reason: request.reason,
      status: request.status,
    } : undefined,
  })
  res.setHeader('Content-Type', 'application/pdf')
  res.setHeader('Content-Disposition', `inline; filename="leave-form-${employee.employeeNo}.pdf"`)
  res.send(buffer)
}))

router.post('/leave/requests', asyncHandler(async (req, res) => {
  const user = requireUser(req)
  const body = parseBody(z.object({
    employeeId: optional,
    leaveTypeId: z.string(),
    startDate: z.string(),
    endDate: z.string(),
    reason: optional,
  }), req.body)
  const employeeId = body.employeeId || user.employeeId
  if (!employeeId) throw new HttpError(400, 'Choose an employee.')
  const employee = await prisma.employee.findUnique({ where: { id: employeeId } })
  if (!employee) throw new HttpError(404, 'Employee not found.')
  if (user.employeeId !== employee.id && !isHr(user.role) && !canApproveAsManager(user, employee)) {
    throw new HttpError(403, 'You cannot apply for this employee.')
  }
  const leaveType = await prisma.leaveType.findUnique({ where: { id: body.leaveTypeId } })
  if (!leaveType) throw new HttpError(404, 'Leave type not found.')
  if (leaveType.gender && leaveType.gender !== employee.gender) {
    throw new HttpError(400, `${leaveType.name} does not apply to this employee.`)
  }
  const start = asDate(body.startDate)
  const end = asDate(body.endDate)
  if (end < start) throw new HttpError(400, 'The end date is before the start date.')
  const calendar = await activeCalendar()
  const holidays = await holidaySet(addDays(start, -1), addDays(end, 1))
  const days = countDays(start, end, leaveType.countMode, calendar.workDays, holidays)
  if (days <= 0) throw new HttpError(400, 'The selected dates contain no leave days.')
  const overlap = await prisma.leaveRequest.findFirst({
    where: {
      employeeId,
      status: { in: ['PENDING_MANAGER', 'PENDING_HR', 'APPROVED'] },
      startDate: { lte: end },
      endDate: { gte: start },
    },
  })
  if (overlap) throw new HttpError(409, 'These dates overlap an existing leave request.')
  const year = start.getUTCFullYear()
  await ensureCurrentBalances(employeeId)
  const steps = await workflowSteps('LEAVE')
  const status = steps[0] === 'HR' || !employee.managerId ? 'PENDING_HR' : 'PENDING_MANAGER'
  const created = await prisma.$transaction(async (tx) => {
    if (leaveType.affectsBalance) {
      const balance = await tx.leaveBalance.findUnique({
        where: { employeeId_leaveTypeId_year: { employeeId, leaveTypeId: leaveType.id, year } },
      })
      const remaining = Math.round(Number(balance?.entitled || 0) - Number(balance?.used || 0) - Number(balance?.pending || 0))
      if (remaining < days) throw new HttpError(400, `Only ${remaining} day(s) remain for ${leaveType.name}.`)
      await tx.leaveBalance.update({
        where: { employeeId_leaveTypeId_year: { employeeId, leaveTypeId: leaveType.id, year } },
        data: { pending: { increment: days } },
      })
    }
    return tx.leaveRequest.create({
      data: {
        employeeId,
        leaveTypeId: leaveType.id,
        startDate: start,
        endDate: end,
        days,
        reason: body.reason,
        status,
      },
      include: { leaveType: true },
    })
  })
  const manager = employee.managerId ? await prisma.user.findUnique({ where: { employeeId: employee.managerId } }) : null
  await notify({
    userIds: status === 'PENDING_HR' ? await usersByRoles(['HR_ADMIN', 'HR_MANAGER']) : [manager?.id],
    title: 'Leave application submitted',
    body: `${fullName(employee)} requested ${days} day(s) of ${leaveType.name}.`,
    type: 'LEAVE',
    link: '/leave',
  })
  res.status(201).json(created)
}))

async function releasePending(request: { employeeId: string; leaveTypeId: string; days: { toString(): string }; startDate: Date }, from: 'pending' | 'used') {
  const year = request.startDate.getUTCFullYear()
  await prisma.leaveBalance.update({
    where: { employeeId_leaveTypeId_year: { employeeId: request.employeeId, leaveTypeId: request.leaveTypeId, year } },
    data: { [from]: { decrement: Number(request.days) } },
  }).catch(() => undefined)
}

router.post('/leave/requests/:id/decision', requireRoles('SUPERVISOR', 'DEPARTMENT_MANAGER', 'HR_ADMIN', 'HR_MANAGER'), asyncHandler(async (req, res) => {
  const user = requireUser(req)
  const body = parseBody(z.object({ decision: z.enum(['APPROVED', 'REJECTED']), note: optional }), req.body)
  const request = await prisma.leaveRequest.findUnique({
    where: { id: String(req.params.id) },
    include: { employee: true, leaveType: true },
  })
  if (!request) throw new HttpError(404, 'Leave request not found.')
  if (request.status === 'APPROVED' || request.status === 'REJECTED' || request.status === 'CANCELLED') {
    throw new HttpError(400, 'This request is already closed.')
  }
  if (request.status === 'PENDING_MANAGER' && !canApproveAsManager(user, request.employee)) {
    throw new HttpError(403, 'A manager for this employee must review the request first.')
  }
  if (request.status === 'PENDING_HR' && !isHr(user.role)) {
    throw new HttpError(403, 'Human Resources must complete this approval.')
  }
  const steps = await workflowSteps('LEAVE')
  const approvals = Array.isArray(request.approvals) ? request.approvals : []
  approvals.push({ by: user.id, role: user.role, decision: body.decision, note: body.note, at: new Date().toISOString() })
  if (body.decision === 'REJECTED') {
    if (request.leaveType.affectsBalance) await releasePending(request, 'pending')
    const updated = await prisma.leaveRequest.update({
      where: { id: request.id },
      data: { status: 'REJECTED', decisionNote: body.note, approvals },
    })
    const account = await prisma.user.findUnique({ where: { employeeId: request.employeeId } })
    await notify({
      userIds: [account?.id],
      title: 'Leave rejected',
      body: body.note || 'Your leave request was rejected.',
      type: 'LEAVE',
      link: '/leave',
    })
    res.json(updated)
    return
  }
  const needsHr = steps.includes('HR') && request.status === 'PENDING_MANAGER' && !isHr(user.role)
  if (needsHr) {
    const updated = await prisma.leaveRequest.update({
      where: { id: request.id },
      data: { status: 'PENDING_HR', approvals },
    })
    await notify({
      userIds: await usersByRoles(['HR_ADMIN', 'HR_MANAGER']),
      title: 'Leave awaiting HR approval',
      body: `${fullName(request.employee)} — ${request.leaveType.name}.`,
      type: 'LEAVE',
      link: '/leave',
    })
    res.json(updated)
    return
  }
  if (request.leaveType.affectsBalance) {
    const year = request.startDate.getUTCFullYear()
    await prisma.leaveBalance.update({
      where: { employeeId_leaveTypeId_year: { employeeId: request.employeeId, leaveTypeId: request.leaveTypeId, year } },
      data: { pending: { decrement: Number(request.days) }, used: { increment: Number(request.days) } },
    })
  }
  const updated = await prisma.leaveRequest.update({
    where: { id: request.id },
    data: { status: 'APPROVED', decisionNote: body.note, approvals },
  })
  const account = await prisma.user.findUnique({ where: { employeeId: request.employeeId } })
  await notify({
    userIds: [account?.id],
    title: 'Leave approved',
    body: `${request.leaveType.name} from ${dateKey(request.startDate)} to ${dateKey(request.endDate)} is approved.`,
    type: 'LEAVE',
    link: '/leave',
  })
  await audit(req, 'UPDATE', 'LeaveRequest', request.id, `Approved leave for ${fullName(request.employee)}`)
  res.json(updated)
}))

router.post('/leave/requests/:id/cancel', asyncHandler(async (req, res) => {
  const user = requireUser(req)
  const request = await prisma.leaveRequest.findUnique({ where: { id: String(req.params.id) }, include: { leaveType: true } })
  if (!request) throw new HttpError(404, 'Leave request not found.')
  if (user.employeeId !== request.employeeId && !isHr(user.role)) throw new HttpError(403, 'You cannot cancel this request.')
  if (request.status === 'CANCELLED' || request.status === 'REJECTED') throw new HttpError(400, 'This request is already closed.')
  if (request.leaveType.affectsBalance) {
    await releasePending(request, request.status === 'APPROVED' ? 'used' : 'pending')
  }
  const updated = await prisma.leaveRequest.update({ where: { id: request.id }, data: { status: 'CANCELLED' } })
  res.json(updated)
}))

export default router
