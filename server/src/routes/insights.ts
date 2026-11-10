import { Router } from 'express'
import multer from 'multer'
import ExcelJS from 'exceljs'
import { z } from 'zod'
import { prisma } from '../lib/prisma.js'
import { asyncHandler, asDate, dateKey, HttpError, pageParams, paged, parseBody, todayUtc, addDays } from '../lib/http.js'
import { canSeeCompensation, canSeeDiscipline, employeeWhere, isHr, requireRoles, requireUser } from '../lib/access.js'
import { activeCalendar, holidaySet, isWorkingDay } from '../lib/calendar.js'
import { fullName } from '../lib/employees.js'
import { readStored, removeStored, saveStream } from '../lib/storage.js'
import { renderReportPdf } from '../lib/letters.js'
import { randomUUID } from 'node:crypto'
import { Readable } from 'node:stream'

const router = Router()
const upload = multer({
  storage: multer.memoryStorage(),
  limits: { fileSize: 10 * 1024 * 1024 },
})

const activeStatuses = ['PROBATION', 'ACTIVE', 'NOTICE', 'SUSPENDED'] as const

router.get('/dashboard', asyncHandler(async (req, res) => {
  const user = requireUser(req)
  const from = req.query.from ? asDate(String(req.query.from)) : addDays(todayUtc(), -365)
  const to = req.query.to ? asDate(String(req.query.to)) : todayUtc()
  const departmentId = req.query.departmentId ? String(req.query.departmentId) : undefined
  const scoped = { AND: [employeeWhere(user), departmentId ? { departmentId } : {}] }
  const employees = await prisma.employee.findMany({
    where: scoped,
    include: { department: true },
  })
  const today = todayUtc()
  const ids = employees.map((employee) => employee.id)
  const [onLeave, pendingLeave, pendingRecruitment, contractsSoon, payrollPending, corrections, overtime, separations, welfare] = await Promise.all([
    prisma.leaveRequest.findMany({
      where: { employeeId: { in: ids }, status: 'APPROVED', startDate: { lte: today }, endDate: { gte: today } },
    }),
    prisma.leaveRequest.count({ where: { employeeId: { in: ids }, status: { in: ['PENDING_MANAGER', 'PENDING_HR'] } } }),
    prisma.manpowerRequisition.count({ where: { status: 'PENDING' } }),
    prisma.contract.findMany({
      where: { employeeId: { in: ids }, status: 'ACTIVE', endDate: { gte: today, lte: addDays(today, 60) } },
      include: { employee: true },
      orderBy: { endDate: 'asc' },
      take: 8,
    }),
    prisma.payrollRun.count({ where: { status: 'PENDING_APPROVAL' } }),
    prisma.attendanceCorrection.count({ where: { employeeId: { in: ids }, status: { in: ['PENDING_MANAGER', 'PENDING_HR'] } } }),
    prisma.overtimeRequest.count({ where: { employeeId: { in: ids }, status: { in: ['PENDING_MANAGER', 'PENDING_HR'] } } }),
    prisma.separation.count({ where: { employeeId: { in: ids }, status: 'PENDING' } }),
    prisma.welfareRequest.count({ where: { employeeId: { in: ids }, status: 'PENDING' } }),
  ])
  const hires = employees.filter((employee) => employee.dateOfEmployment >= from && employee.dateOfEmployment <= to).length
  const exits = await prisma.separation.count({
    where: { employeeId: { in: ids }, status: { in: ['APPROVED', 'COMPLETED'] }, lastWorkingDay: { gte: from, lte: to } },
  })
  const headcount = employees.filter((employee) => activeStatuses.includes(employee.status as typeof activeStatuses[number])).length
  const average = Math.max(1, (headcount + exits) / 2)
  const calendar = await activeCalendar()
  const windowStart = addDays(today, -30)
  const holidays = await holidaySet(windowStart, today)
  let expected = 0
  for (let cursor = new Date(windowStart); cursor < today; cursor = addDays(cursor, 1)) {
    if (isWorkingDay(cursor, calendar.workDays, holidays)) expected += headcount
  }
  const attendance = await prisma.attendanceRecord.findMany({
    where: { employeeId: { in: ids }, date: { gte: windowStart, lt: today } },
  })
  const present = attendance.filter((row) => ['PRESENT', 'LATE', 'REMOTE'].includes(row.status)).length
  const absent = attendance.filter((row) => row.status === 'ABSENT').length
  const byDepartmentMap = new Map<string, number>()
  const genderMap = new Map<string, number>()
  for (const employee of employees.filter((item) => activeStatuses.includes(item.status as typeof activeStatuses[number]))) {
    byDepartmentMap.set(employee.department.name, (byDepartmentMap.get(employee.department.name) || 0) + 1)
    genderMap.set(employee.gender, (genderMap.get(employee.gender) || 0) + 1)
  }
  const trendMap = new Map<string, { present: number; total: number }>()
  for (const row of attendance) {
    const key = dateKey(row.date)
    const bucket = trendMap.get(key) || { present: 0, total: 0 }
    bucket.total += 1
    if (['PRESENT', 'LATE', 'REMOTE'].includes(row.status)) bucket.present += 1
    trendMap.set(key, bucket)
  }
  const birthdays = employees
    .filter((employee) => activeStatuses.includes(employee.status as typeof activeStatuses[number]))
    .map((employee) => {
      const next = new Date(Date.UTC(today.getUTCFullYear(), employee.dateOfBirth.getUTCMonth(), employee.dateOfBirth.getUTCDate()))
      if (next < today) next.setUTCFullYear(today.getUTCFullYear() + 1)
      return { id: employee.id, name: fullName(employee), date: dateKey(next), days: Math.round((next.getTime() - today.getTime()) / 86400000) }
    })
    .filter((item) => item.days <= 30)
    .sort((a, b) => a.days - b.days)
    .slice(0, 6)
  const mine = user.employeeId ? employees.find((employee) => employee.id === user.employeeId) : null
  res.json({
    totals: {
      employees: employees.length,
      active: employees.filter((employee) => employee.status === 'ACTIVE').length,
      inactive: employees.filter((employee) => ['INACTIVE', 'RESIGNED', 'RETIRED', 'TERMINATED'].includes(employee.status)).length,
      newHires: hires,
      onLeave: onLeave.length,
      probation: employees.filter((employee) => employee.status === 'PROBATION').length,
      contractsExpiring: contractsSoon.length,
      turnover: Number(((exits / average) * 100).toFixed(1)),
      attendanceRate: expected ? Number(((present / expected) * 100).toFixed(1)) : 0,
      absenteeismRate: expected ? Number(((absent / expected) * 100).toFixed(1)) : 0,
    },
    gender: [...genderMap.entries()].map(([name, value]) => ({ name, value })),
    byDepartment: [...byDepartmentMap.entries()].map(([name, count]) => ({ name, count })),
    attendanceTrend: [...trendMap.entries()].sort(([a], [b]) => a.localeCompare(b)).map(([date, bucket]) => ({
      date: date.slice(5),
      rate: bucket.total ? Math.round((bucket.present / bucket.total) * 100) : 0,
    })),
    birthdays,
    contracts: contractsSoon.map((contract) => ({
      id: contract.id,
      employee: fullName(contract.employee),
      title: contract.title,
      endDate: contract.endDate ? dateKey(contract.endDate) : null,
    })),
    pending: {
      leave: pendingLeave,
      recruitment: isHr(user.role) ? pendingRecruitment : 0,
      payroll: canSeeCompensation(user) ? payrollPending : 0,
      corrections,
      overtime,
      separations,
      welfare,
    },
    personal: mine ? { employeeNo: mine.employeeNo, status: mine.status, name: fullName(mine) } : null,
  })
}))

function clockLabel(value: Date | null) {
  if (!value) return ''
  return `${String(value.getUTCHours()).padStart(2, '0')}:${String(value.getUTCMinutes()).padStart(2, '0')}`
}

type ReportRow = Record<string, string | number>
type Report = { title: string; columns: Array<{ key: string; label: string }>; rows: ReportRow[] }

async function buildReport(name: string, query: Record<string, unknown>): Promise<Report> {
  const from = query.from ? asDate(String(query.from)) : addDays(todayUtc(), -365)
  const to = query.to ? asDate(String(query.to)) : todayUtc()
  const departmentId = query.departmentId ? String(query.departmentId) : undefined
  const locationId = query.locationId ? String(query.locationId) : undefined
  const employeeId = query.employeeId ? String(query.employeeId) : undefined
  const employeeFilter = {
    ...(departmentId ? { departmentId } : {}),
    ...(locationId ? { locationId } : {}),
    ...(employeeId ? { id: employeeId } : {}),
  }
  if (name === 'employees' || name === 'demographics' || name === 'headcount') {
    const rows = await prisma.employee.findMany({
      where: name === 'headcount' ? { ...employeeFilter, status: { in: [...activeStatuses] } } : employeeFilter,
      include: { department: true, jobTitle: true, location: true, employmentType: true },
      orderBy: { lastName: 'asc' },
    })
    if (name === 'headcount') {
      const map = new Map<string, number>()
      for (const row of rows) map.set(row.department.name, (map.get(row.department.name) || 0) + 1)
      return {
        title: 'Employee headcount',
        columns: [{ key: 'department', label: 'Department' }, { key: 'headcount', label: 'Headcount' }],
        rows: [...map.entries()].map(([department, headcount]) => ({ department, headcount })),
      }
    }
    return {
      title: name === 'demographics' ? 'Employee demographics' : 'Employee master list',
      columns: [
        { key: 'employeeNo', label: 'Employee No' },
        { key: 'name', label: 'Name' },
        { key: 'gender', label: 'Gender' },
        { key: 'department', label: 'Department' },
        { key: 'title', label: 'Job title' },
        { key: 'location', label: 'Location' },
        { key: 'status', label: 'Status' },
        { key: 'hired', label: 'Hired' },
      ],
      rows: rows.map((row) => ({
        employeeNo: row.employeeNo,
        name: fullName(row),
        gender: row.gender,
        department: row.department.name,
        title: row.jobTitle.name,
        location: `${row.location.name}, ${row.location.city}`,
        status: row.status,
        hired: dateKey(row.dateOfEmployment),
      })),
    }
  }
  if (name === 'attendance' || name === 'absenteeism') {
    const rows = await prisma.attendanceRecord.findMany({
      where: {
        date: { gte: from, lte: to },
        ...(name === 'absenteeism' ? { status: 'ABSENT' as const } : {}),
        employee: employeeFilter,
      },
      include: { employee: { include: { department: true } } },
      orderBy: { date: 'desc' },
      take: 2000,
    })
    return {
      title: name === 'absenteeism' ? 'Absenteeism report' : 'Attendance report',
      columns: [
        { key: 'date', label: 'Date' },
        { key: 'employee', label: 'Employee' },
        { key: 'department', label: 'Department' },
        { key: 'status', label: 'Status' },
        { key: 'clockIn', label: 'Clock in' },
        { key: 'clockOut', label: 'Clock out' },
        { key: 'late', label: 'Late minutes' },
      ],
      rows: rows.map((row) => ({
        date: dateKey(row.date),
        employee: fullName(row.employee),
        department: row.employee.department.name,
        status: row.status,
        clockIn: clockLabel(row.clockIn),
        clockOut: clockLabel(row.clockOut),
        late: row.lateMinutes,
      })),
    }
  }
  if (name === 'leave') {
    const rows = await prisma.leaveRequest.findMany({
      where: { startDate: { lte: to }, endDate: { gte: from }, employee: employeeFilter },
      include: { employee: true, leaveType: true },
    })
    return {
      title: 'Leave utilisation',
      columns: [
        { key: 'employee', label: 'Employee' },
        { key: 'type', label: 'Type' },
        { key: 'start', label: 'Start' },
        { key: 'end', label: 'End' },
        { key: 'days', label: 'Days' },
        { key: 'status', label: 'Status' },
      ],
      rows: rows.map((row) => ({
        employee: fullName(row.employee),
        type: row.leaveType.name,
        start: dateKey(row.startDate),
        end: dateKey(row.endDate),
        days: Number(row.days),
        status: row.status,
      })),
    }
  }
  if (name === 'overtime') {
    const rows = await prisma.overtimeRequest.findMany({
      where: { date: { gte: from, lte: to }, employee: employeeFilter },
      include: { employee: true },
    })
    return {
      title: 'Overtime report',
      columns: [
        { key: 'employee', label: 'Employee' },
        { key: 'date', label: 'Date' },
        { key: 'hours', label: 'Hours' },
        { key: 'multiplier', label: 'Multiplier' },
        { key: 'status', label: 'Status' },
      ],
      rows: rows.map((row) => ({
        employee: fullName(row.employee),
        date: dateKey(row.date),
        hours: Number(row.hours),
        multiplier: Number(row.multiplier),
        status: row.status,
      })),
    }
  }
  if (name === 'payroll' || name === 'cost') {
    const slips = await prisma.payslip.findMany({
      where: {
        payrollRun: { status: 'APPROVED', createdAt: { gte: from, lte: new Date(to.getTime() + 86400000) } },
        employee: employeeFilter,
      },
      include: { employee: { include: { department: true } }, payrollRun: true },
    })
    return {
      title: name === 'cost' ? 'Employee cost analysis' : 'Payroll report',
      columns: [
        { key: 'period', label: 'Period' },
        { key: 'employee', label: 'Employee' },
        { key: 'department', label: 'Department' },
        { key: 'gross', label: 'Gross' },
        { key: 'paye', label: 'PAYE' },
        { key: 'net', label: 'Net' },
        { key: 'employer', label: 'Employer SSNIT' },
      ],
      rows: slips.map((slip) => ({
        period: `${slip.payrollRun.year}-${String(slip.payrollRun.month).padStart(2, '0')}`,
        employee: fullName(slip.employee),
        department: slip.employee.department.name,
        gross: Number(slip.grossPay),
        paye: Number(slip.paye),
        net: Number(slip.netPay),
        employer: Number(slip.employerSsnit),
      })),
    }
  }
  if (name === 'turnover') {
    const rows = await prisma.separation.findMany({
      where: { lastWorkingDay: { gte: from, lte: to }, employee: employeeFilter },
      include: { employee: { include: { department: true } } },
    })
    return {
      title: 'Employee turnover',
      columns: [
        { key: 'employee', label: 'Employee' },
        { key: 'department', label: 'Department' },
        { key: 'type', label: 'Type' },
        { key: 'lastDay', label: 'Last day' },
        { key: 'status', label: 'Status' },
      ],
      rows: rows.map((row) => ({
        employee: fullName(row.employee),
        department: row.employee.department.name,
        type: row.type,
        lastDay: dateKey(row.lastWorkingDay),
        status: row.status,
      })),
    }
  }
  if (name === 'recruitment') {
    const rows = await prisma.vacancy.findMany({
      where: { ...(departmentId ? { departmentId } : {}), ...(locationId ? { locationId } : {}) },
      include: { department: true, applicants: true },
    })
    return {
      title: 'Recruitment report',
      columns: [
        { key: 'title', label: 'Vacancy' },
        { key: 'department', label: 'Department' },
        { key: 'status', label: 'Status' },
        { key: 'applicants', label: 'Applicants' },
        { key: 'cost', label: 'Cost' },
      ],
      rows: rows.map((row) => ({
        title: row.title,
        department: row.department.name,
        status: row.status,
        applicants: row.applicants.length,
        cost: Number(row.advertisingCost) + Number(row.agencyCost),
      })),
    }
  }
  if (name === 'training') {
    const rows = await prisma.trainingProgram.findMany({
      where: { startDate: { lte: to }, endDate: { gte: from } },
      include: { enrollments: true, provider: true },
    })
    return {
      title: 'Training expenditure',
      columns: [
        { key: 'title', label: 'Programme' },
        { key: 'provider', label: 'Provider' },
        { key: 'cost', label: 'Cost' },
        { key: 'enrolled', label: 'Enrolled' },
        { key: 'status', label: 'Status' },
      ],
      rows: rows.map((row) => ({
        title: row.title,
        provider: row.provider?.name || '',
        cost: Number(row.cost),
        enrolled: row.enrollments.length,
        status: row.status,
      })),
    }
  }
  if (name === 'performance') {
    const rows = await prisma.appraisal.findMany({
      where: { employee: employeeFilter },
      include: { employee: { include: { department: true } }, cycle: true },
    })
    return {
      title: 'Performance appraisals',
      columns: [
        { key: 'employee', label: 'Employee' },
        { key: 'cycle', label: 'Cycle' },
        { key: 'self', label: 'Self' },
        { key: 'supervisor', label: 'Supervisor' },
        { key: 'final', label: 'Final' },
        { key: 'recommendation', label: 'Recommendation' },
      ],
      rows: rows.map((row) => ({
        employee: fullName(row.employee),
        cycle: row.cycle.name,
        self: row.selfRating ? Number(row.selfRating) : '',
        supervisor: row.supervisorRating ? Number(row.supervisorRating) : '',
        final: row.finalRating ? Number(row.finalRating) : '',
        recommendation: row.recommendation || '',
      })),
    }
  }
  if (name === 'contracts') {
    const rows = await prisma.contract.findMany({
      where: { employee: employeeFilter, ...(query.expiring ? { endDate: { gte: todayUtc(), lte: addDays(todayUtc(), 60) } } : {}) },
      include: { employee: true },
    })
    return {
      title: 'Contract expiration',
      columns: [
        { key: 'employee', label: 'Employee' },
        { key: 'title', label: 'Contract' },
        { key: 'start', label: 'Start' },
        { key: 'end', label: 'End' },
        { key: 'status', label: 'Status' },
      ],
      rows: rows.map((row) => ({
        employee: fullName(row.employee),
        title: row.title,
        start: dateKey(row.startDate),
        end: row.endDate ? dateKey(row.endDate) : '',
        status: row.status,
      })),
    }
  }
  if (name === 'discipline') {
    const rows = await prisma.disciplinaryCase.findMany({
      where: { incidentDate: { gte: from, lte: to }, employee: employeeFilter },
      include: { employee: true },
    })
    return {
      title: 'Disciplinary report',
      columns: [
        { key: 'caseNo', label: 'Case' },
        { key: 'employee', label: 'Employee' },
        { key: 'date', label: 'Incident' },
        { key: 'status', label: 'Status' },
        { key: 'outcome', label: 'Outcome' },
      ],
      rows: rows.map((row) => ({
        caseNo: row.caseNo,
        employee: fullName(row.employee),
        date: dateKey(row.incidentDate),
        status: row.status,
        outcome: row.outcome || '',
      })),
    }
  }
  const budgets = await prisma.manpowerBudget.findMany({
    where: { year: to.getUTCFullYear(), ...(departmentId ? { departmentId } : {}) },
    include: { department: true },
  })
  const staff = await prisma.employee.groupBy({
    by: ['departmentId'],
    where: { status: { in: [...activeStatuses] } },
    _count: true,
  })
  return {
    title: 'Workforce planning',
    columns: [
      { key: 'department', label: 'Department' },
      { key: 'approved', label: 'Approved' },
      { key: 'actual', label: 'Actual' },
      { key: 'gap', label: 'Gap' },
      { key: 'cost', label: 'Estimated annual cost' },
    ],
    rows: budgets.map((budget) => {
      const actual = staff.find((item) => item.departmentId === budget.departmentId)?._count || 0
      return {
        department: budget.department.name,
        approved: budget.approvedHeadcount,
        actual,
        gap: budget.approvedHeadcount - actual,
        cost: Number(budget.estimatedCost),
      }
    }),
  }
}

router.get('/reports/:name', asyncHandler(async (req, res) => {
  const user = requireUser(req)
  const name = String(req.params.name)
  if (name === 'discipline' && !canSeeDiscipline(user)) throw new HttpError(403, 'This report is restricted to HR.')
  if ((name === 'payroll' || name === 'cost') && !canSeeCompensation(user)) throw new HttpError(403, 'This report is restricted.')
  const report = await buildReport(name, req.query as Record<string, unknown>)
  const format = String(req.query.format || 'json')
  if (format === 'xlsx') {
    const workbook = new ExcelJS.Workbook()
    const sheet = workbook.addWorksheet(report.title.slice(0, 31))
    sheet.addRow(report.columns.map((column) => column.label))
    for (const row of report.rows) sheet.addRow(report.columns.map((column) => row[column.key] ?? ''))
    sheet.getRow(1).font = { bold: true }
    res.setHeader('Content-Type', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet')
    res.setHeader('Content-Disposition', `attachment; filename="${name}.xlsx"`)
    await workbook.xlsx.write(res)
    res.end()
    return
  }
  if (format === 'pdf') {
    const company = await prisma.company.findFirst()
    const buffer = await renderReportPdf(report, company)
    res.setHeader('Content-Type', 'application/pdf')
    res.setHeader('Content-Disposition', `inline; filename="${name}.pdf"`)
    res.send(buffer)
    return
  }
  res.json(report)
}))

router.get('/notifications', asyncHandler(async (req, res) => {
  const user = requireUser(req)
  const { page, pageSize, skip } = pageParams(req)
  const where = { userId: user.id, ...(req.query.unread === '1' ? { readAt: null } : {}) }
  const [items, total, unread] = await Promise.all([
    prisma.notification.findMany({ where, orderBy: { createdAt: 'desc' }, skip, take: pageSize }),
    prisma.notification.count({ where }),
    prisma.notification.count({ where: { userId: user.id, readAt: null } }),
  ])
  res.json({ ...paged(items, total, page, pageSize), unread })
}))

router.post('/notifications/:id/read', asyncHandler(async (req, res) => {
  const user = requireUser(req)
  const item = await prisma.notification.findUnique({ where: { id: String(req.params.id) } })
  if (!item || item.userId !== user.id) throw new HttpError(404, 'Notification not found.')
  res.json(await prisma.notification.update({ where: { id: item.id }, data: { readAt: new Date() } }))
}))

router.post('/notifications/read-all', asyncHandler(async (req, res) => {
  const user = requireUser(req)
  await prisma.notification.updateMany({ where: { userId: user.id, readAt: null }, data: { readAt: new Date() } })
  res.json({ ok: true })
}))

router.get('/files', asyncHandler(async (req, res) => {
  const user = requireUser(req)
  const ownerType = String(req.query.ownerType || 'EMPLOYEE')
  const ownerId = String(req.query.ownerId || '')
  if (!ownerId) throw new HttpError(400, 'Choose a record.')
  if (ownerType === 'EMPLOYEE') {
    const employee = await prisma.employee.findUnique({ where: { id: ownerId } })
    if (!employee) throw new HttpError(404, 'Employee not found.')
    const visible = employeeWhere(user)
    const allowed = await prisma.employee.findFirst({ where: { AND: [{ id: ownerId }, visible] } })
    if (!allowed) throw new HttpError(403, 'You cannot view these documents.')
  } else if (!isHr(user.role) && user.role !== 'RECRUITMENT_OFFICER') {
    throw new HttpError(403, 'You cannot view these documents.')
  }
  const files = await prisma.fileAsset.findMany({ where: { ownerType, ownerId }, orderBy: { createdAt: 'desc' } })
  res.json(files.filter((file) => !file.confidential || canSeeDiscipline(user) || user.employeeId === ownerId))
}))

router.post('/files', upload.single('file'), asyncHandler(async (req, res) => {
  const user = requireUser(req)
  if (!isHr(user.role) && user.role !== 'RECRUITMENT_OFFICER' && user.role !== 'EMPLOYEE') {
    throw new HttpError(403, 'You cannot upload documents.')
  }
  const body = parseBody(z.object({
    ownerType: z.string().min(2),
    ownerId: z.string().min(2),
    categoryId: z.string().optional(),
    confidential: z.enum(['true', 'false']).optional(),
  }), req.body)
  if (!req.file) throw new HttpError(400, 'Choose a file to upload.')
  const allowed = ['application/pdf', 'image/jpeg', 'image/png', 'image/webp', 'application/vnd.openxmlformats-officedocument.wordprocessingml.document']
  if (!allowed.includes(req.file.mimetype)) throw new HttpError(400, 'Upload a PDF, image, or Word document.')
  if (body.ownerType === 'EMPLOYEE' && user.role === 'EMPLOYEE' && user.employeeId !== body.ownerId) {
    throw new HttpError(403, 'You can only upload to your own record.')
  }
  const isPhoto = req.query.photo === '1' && body.ownerType === 'EMPLOYEE'
  if (isPhoto) {
    const images = ['image/jpeg', 'image/png', 'image/webp']
    if (!images.includes(req.file.mimetype)) throw new HttpError(400, 'Use a JPEG, PNG, or WebP image.')
    if (req.file.size > 5 * 1024 * 1024) throw new HttpError(400, 'The picture must be 5 MB or smaller.')
    if (!isHr(user.role) && user.employeeId !== body.ownerId) throw new HttpError(403, 'You can update your own profile picture.')
  }
  const id = randomUUID()
  const storageKey = `${body.ownerType.toLowerCase()}/${body.ownerId}/${id}`
  await saveStream(storageKey, Readable.from(req.file.buffer), req.file.mimetype)
  const file = await prisma.fileAsset.create({
    data: {
      id,
      storageKey,
      fileName: req.file.originalname,
      mimeType: req.file.mimetype,
      size: req.file.size,
      categoryId: body.categoryId,
      ownerType: body.ownerType,
      ownerId: body.ownerId,
      confidential: body.confidential === 'true',
      uploadedById: user.id,
    },
  })
  if (isPhoto) {
    await prisma.employee.update({ where: { id: body.ownerId }, data: { photoFileId: file.id } })
  }
  res.status(201).json(file)
}))

router.get('/files/:id', asyncHandler(async (req, res) => {
  const user = requireUser(req)
  const file = await prisma.fileAsset.findUnique({ where: { id: String(req.params.id) } })
  if (!file) throw new HttpError(404, 'File not found.')
  if (file.confidential && !canSeeDiscipline(user) && user.employeeId !== file.ownerId) {
    throw new HttpError(403, 'This document is confidential.')
  }
  if (file.ownerType === 'EMPLOYEE') {
    const allowed = await prisma.employee.findFirst({ where: { AND: [{ id: file.ownerId }, employeeWhere(user)] } })
    if (!allowed && !isHr(user.role)) throw new HttpError(403, 'You cannot download this document.')
  }
  const contents = await readStored(file.storageKey)
  res.setHeader('Content-Type', file.mimeType)
  res.setHeader('Content-Disposition', `inline; filename="${file.fileName.replace(/"/g, '')}"`)
  res.send(contents)
}))

router.delete('/files/:id', requireRoles('HR_ADMIN', 'HR_MANAGER', 'RECRUITMENT_OFFICER'), asyncHandler(async (req, res) => {
  const file = await prisma.fileAsset.findUnique({ where: { id: String(req.params.id) } })
  if (!file) throw new HttpError(404, 'File not found.')
  await removeStored(file.storageKey)
  await prisma.fileAsset.delete({ where: { id: file.id } })
  res.status(204).end()
}))

export default router
