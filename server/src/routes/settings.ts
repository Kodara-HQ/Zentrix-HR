import { Router } from 'express'
import { z } from 'zod'
import type { Prisma, Role } from '@prisma/client'
import { prisma } from '../lib/prisma.js'
import { asyncHandler, HttpError, pageParams, paged, parseBody, asDate } from '../lib/http.js'
import { employeeWhere, requireRoles, requireUser } from '../lib/access.js'
import { audit } from '../lib/audit.js'
import { hashPassword } from '../lib/auth.js'
import { mkdir, writeFile } from 'node:fs/promises'
import path from 'node:path'

const router = Router()
const hr = requireRoles('HR_ADMIN', 'HR_MANAGER', 'SUPER_ADMIN')
const admin = requireRoles('SUPER_ADMIN')

const optional = z.string().trim().optional().or(z.literal('')).transform((value) => value || undefined)

router.get('/company', asyncHandler(async (_req, res) => {
  const company = await prisma.company.findFirst()
  res.json(company)
}))

router.put('/company', hr, asyncHandler(async (req, res) => {
  const body = parseBody(z.object({
    name: z.string().min(2),
    legalName: z.string().min(2),
    tin: optional,
    ssnitEmployerNo: optional,
    email: optional,
    phone: optional,
    address: optional,
    city: optional,
    country: z.string().min(2).default('Ghana'),
    currency: z.string().min(3).default('GHS'),
  }), req.body)
  const existing = await prisma.company.findFirst()
  const company = existing
    ? await prisma.company.update({ where: { id: existing.id }, data: body })
    : await prisma.company.create({ data: { ...body, nextEmployeeNo: 1 } })
  await audit(req, 'UPDATE', 'Company', company.id, 'Company profile updated')
  res.json(company)
}))

function crud(path: string, delegate: keyof typeof prisma, schema: z.ZodTypeAny, roles = hr) {
  router.get(path, asyncHandler(async (req, res) => {
    requireUser(req)
    const { page, pageSize, skip, search } = pageParams(req)
    const model = prisma[delegate] as unknown as {
      findMany: (args: unknown) => Promise<unknown[]>
      count: (args: unknown) => Promise<number>
    }
    const where = search ? { name: { contains: search, mode: 'insensitive' } } : {}
    const [items, total] = await Promise.all([
      model.findMany({ where, skip, take: pageSize, orderBy: { name: 'asc' } }),
      model.count({ where }),
    ])
    res.json(paged(items, total, page, pageSize))
  }))

  router.post(path, roles, asyncHandler(async (req, res) => {
    const data = parseBody(schema, req.body)
    const model = prisma[delegate] as unknown as { create: (args: { data: unknown }) => Promise<{ id: string }> }
    const created = await model.create({ data })
    await audit(req, 'CREATE', String(delegate), created.id, `Created ${path}`)
    res.status(201).json(created)
  }))

  router.patch(`${path}/:id`, roles, asyncHandler(async (req, res) => {
    const data = parseBody(schema, req.body)
    const model = prisma[delegate] as unknown as {
      update: (args: { where: { id: string }; data: unknown }) => Promise<unknown>
    }
    const updated = await model.update({ where: { id: String(req.params.id) }, data })
    await audit(req, 'UPDATE', String(delegate), String(req.params.id), `Updated ${path}`)
    res.json(updated)
  }))

  router.delete(`${path}/:id`, roles, asyncHandler(async (req, res) => {
    const model = prisma[delegate] as unknown as { delete: (args: { where: { id: string } }) => Promise<unknown> }
    await model.delete({ where: { id: String(req.params.id) } })
    await audit(req, 'DELETE', String(delegate), String(req.params.id), `Deleted ${path}`)
    res.status(204).end()
  }))
}

crud('/departments', 'department', z.object({
  name: z.string().min(2),
  code: z.string().min(2),
  description: optional,
  headId: optional,
}))

crud('/job-titles', 'jobTitle', z.object({
  name: z.string().min(2),
  departmentId: optional,
  grade: optional,
  description: optional,
}))

crud('/employment-types', 'employmentType', z.object({
  name: z.string().min(2),
  description: optional,
}))

crud('/locations', 'workLocation', z.object({
  name: z.string().min(2),
  branch: z.string().min(2),
  city: z.string().min(2),
  region: z.string().min(2),
  address: optional,
}))

crud('/leave-types', 'leaveType', z.object({
  name: z.string().min(2),
  code: z.string().min(2),
  daysPerYear: z.coerce.number().min(0),
  countMode: z.enum(['WORKING_DAYS', 'CALENDAR_DAYS']),
  affectsBalance: z.boolean().default(true),
  paid: z.boolean().default(true),
  gender: z.enum(['FEMALE', 'MALE', 'OTHER', 'UNDISCLOSED']).nullable().optional(),
  requiresReason: z.boolean().default(false),
  color: z.string().default('#0f766e'),
}))

crud('/shifts', 'shiftPattern', z.object({
  name: z.string().min(2),
  startTime: z.string().regex(/^\d{2}:\d{2}$/),
  endTime: z.string().regex(/^\d{2}:\d{2}$/),
  graceMinutes: z.coerce.number().int().min(0).default(15),
  workDays: z.array(z.number().int().min(0).max(6)).min(1),
  isNight: z.boolean().default(false),
}))

router.get('/holidays', asyncHandler(async (_req, res) => {
  res.json(await prisma.holiday.findMany({ orderBy: { date: 'asc' } }))
}))

router.post('/holidays', hr, asyncHandler(async (req, res) => {
  const body = parseBody(z.object({
    name: z.string().min(2),
    date: z.string(),
    recurring: z.boolean().default(false),
  }), req.body)
  const holiday = await prisma.holiday.create({ data: { ...body, date: asDate(body.date) } })
  res.status(201).json(holiday)
}))

router.delete('/holidays/:id', hr, asyncHandler(async (req, res) => {
  await prisma.holiday.delete({ where: { id: String(req.params.id) } })
  res.status(204).end()
}))

router.get('/calendar', asyncHandler(async (_req, res) => {
  res.json(await prisma.workingCalendar.findFirst({ where: { isActive: true } }))
}))

function hoursBetween(start: string, end: string) {
  const [startHour, startMinute] = start.split(':').map(Number)
  const [endHour, endMinute] = end.split(':').map(Number)
  const minutes = (endHour * 60 + endMinute) - (startHour * 60 + startMinute)
  if (!Number.isFinite(minutes) || minutes <= 0) throw new HttpError(400, 'Closing time must be after the reporting time.')
  return Math.round((minutes / 60) * 100) / 100
}

router.put('/calendar', hr, asyncHandler(async (req, res) => {
  const body = parseBody(z.object({
    name: z.string().min(2),
    workDays: z.array(z.number()).min(1),
    startTime: z.string().regex(/^\d{2}:\d{2}$/),
    endTime: z.string().regex(/^\d{2}:\d{2}$/),
    hoursPerDay: z.coerce.number().positive(),
    hoursPerMonth: z.coerce.number().positive(),
    graceMinutes: z.coerce.number().int().min(0),
    overtimeWeekday: z.coerce.number().positive(),
    overtimeWeekend: z.coerce.number().positive(),
    latitude: z.preprocess((value) => (value === '' || value == null ? null : Number(value)), z.number().min(-90).max(90).nullable()),
    longitude: z.preprocess((value) => (value === '' || value == null ? null : Number(value)), z.number().min(-180).max(180).nullable()),
    radiusMeters: z.coerce.number().int().min(20).max(5000).optional(),
  }), req.body)
  const hoursPerDay = hoursBetween(body.startTime, body.endTime)
  const existing = await prisma.workingCalendar.findFirst({ where: { isActive: true } })
  const data = { ...body, hoursPerDay, hoursPerMonth: Math.round(hoursPerDay * 22 * 100) / 100 }
  const calendar = existing
    ? await prisma.workingCalendar.update({ where: { id: existing.id }, data })
    : await prisma.workingCalendar.create({ data: { ...data, isActive: true } })
  if (existing) {
    await prisma.shiftPattern.updateMany({
      where: { startTime: existing.startTime, endTime: existing.endTime },
      data: { startTime: body.startTime, endTime: body.endTime, graceMinutes: body.graceMinutes },
    })
  }
  res.json(calendar)
}))

crud('/payroll-components', 'payrollComponent', z.object({
  name: z.string().min(2),
  code: z.string().min(2),
  kind: z.enum(['ALLOWANCE', 'DEDUCTION', 'RELIEF']),
  taxable: z.boolean().default(true),
  description: optional,
}))

router.get('/statutory', asyncHandler(async (_req, res) => {
  res.json(await prisma.statutoryConfig.findMany({ orderBy: { effectiveFrom: 'desc' } }))
}))

router.post('/statutory', requireRoles('PAYROLL_OFFICER', 'SUPER_ADMIN'), asyncHandler(async (req, res) => {
  const body = parseBody(z.object({
    name: z.string().min(2),
    kind: z.string().min(2),
    effectiveFrom: z.string(),
    effectiveTo: optional,
    employeeRate: z.coerce.number().optional(),
    employerRate: z.coerce.number().optional(),
    tier1EmployerRate: z.coerce.number().optional(),
    tier2Rate: z.coerce.number().optional(),
    bands: z.array(z.object({ width: z.number().nullable(), rate: z.number() })).optional(),
    notes: optional,
  }), req.body)
  const created = await prisma.statutoryConfig.create({
    data: {
      ...body,
      effectiveFrom: asDate(body.effectiveFrom),
      effectiveTo: body.effectiveTo ? asDate(body.effectiveTo) : null,
      bands: body.bands as Prisma.InputJsonValue | undefined,
    },
  })
  res.status(201).json(created)
}))

router.get('/workflows', asyncHandler(async (_req, res) => {
  res.json(await prisma.workflowConfig.findMany({ orderBy: { key: 'asc' } }))
}))

router.put('/workflows/:key', hr, asyncHandler(async (req, res) => {
  const body = parseBody(z.object({
    label: z.string().min(2),
    steps: z.array(z.string()).min(1),
  }), req.body)
  const saved = await prisma.workflowConfig.upsert({
    where: { key: String(req.params.key) },
    update: { label: body.label, steps: body.steps },
    create: { key: String(req.params.key), label: body.label, steps: body.steps },
  })
  res.json(saved)
}))

crud('/rating-scales', 'ratingScale', z.object({
  name: z.string().min(2),
  min: z.coerce.number().int(),
  max: z.coerce.number().int(),
  labels: z.record(z.string()),
  isDefault: z.boolean().default(false),
}))

crud('/document-categories', 'documentCategory', z.object({
  name: z.string().min(2),
  description: optional,
  confidential: z.boolean().default(false),
}))

router.get('/notification-settings', hr, asyncHandler(async (_req, res) => {
  res.json(await prisma.notificationSetting.findMany({ orderBy: { label: 'asc' } }))
}))

router.patch('/notification-settings/:id', hr, asyncHandler(async (req, res) => {
  const body = parseBody(z.object({ inApp: z.boolean(), email: z.boolean() }), req.body)
  res.json(await prisma.notificationSetting.update({ where: { id: String(req.params.id) }, data: body }))
}))

const accountRoles = ['SUPER_ADMIN', 'HR_ADMIN', 'HR_MANAGER', 'DEPARTMENT_MANAGER', 'SUPERVISOR', 'PAYROLL_OFFICER', 'EMPLOYEE', 'RECRUITMENT_OFFICER'] as const

const accountSchema = z.object({
  fullName: z.string().trim().min(2),
  username: z.string().trim().min(2).max(80).regex(/^[\p{L}\p{N}][\p{L}\p{N} .'_-]*$/u, 'Use letters, numbers, and spaces.'),
  email: z.string().trim().email().or(z.literal('')).optional(),
  role: z.enum(accountRoles),
  password: z.string().min(12, 'Passwords must be at least 12 characters.').optional(),
  isActive: z.boolean().optional(),
})

function splitName(fullName: string) {
  const parts = fullName.trim().split(/\s+/).filter(Boolean)
  return { firstName: parts[0], lastName: parts.slice(1).join(' ') || parts[0] }
}

function accountEmail(email?: string) {
  const value = email?.trim().toLowerCase()
  return value || null
}

async function assertUsernameFree(username: string, exceptId?: string) {
  const taken = await prisma.user.findFirst({
    where: { username: { equals: username, mode: 'insensitive' }, ...(exceptId ? { id: { not: exceptId } } : {}) },
    select: { id: true },
  })
  if (taken) throw new HttpError(409, 'That username is already in use.')
}

const userSelect = {
  id: true, email: true, username: true, role: true, firstName: true, lastName: true,
  isActive: true, lastLoginAt: true, mustChangePassword: true,
} as const

router.get('/users', admin, asyncHandler(async (_req, res) => {
  const users = await prisma.user.findMany({ select: userSelect, orderBy: [{ lastName: 'asc' }, { firstName: 'asc' }] })
  res.json(users)
}))

router.post('/users', admin, asyncHandler(async (req, res) => {
  const body = parseBody(accountSchema.extend({ password: z.string().min(12, 'Passwords must be at least 12 characters.') }), req.body)
  await assertUsernameFree(body.username)
  const name = splitName(body.fullName)
  const created = await prisma.user.create({
    data: {
      ...name,
      username: body.username.trim(),
      email: accountEmail(body.email),
      role: body.role as Role,
      passwordHash: await hashPassword(body.password),
      mustChangePassword: true,
    },
    select: userSelect,
  })
  await audit(req, 'CREATE', 'User', created.id, `Created user ${created.username}`)
  res.status(201).json(created)
}))

router.patch('/users/:id', admin, asyncHandler(async (req, res) => {
  const actor = requireUser(req)
  const body = parseBody(accountSchema.partial(), req.body)
  const target = await prisma.user.findUnique({ where: { id: String(req.params.id) } })
  if (!target) throw new HttpError(404, 'Record not found.')
  const losingAdmin = target.role === 'SUPER_ADMIN' && ((body.role !== undefined && body.role !== 'SUPER_ADMIN') || body.isActive === false)
  if (losingAdmin) {
    const remaining = await prisma.user.count({ where: { role: 'SUPER_ADMIN', isActive: true, id: { not: target.id } } })
    if (remaining === 0) throw new HttpError(400, 'Keep at least one active super administrator.')
  }
  if (body.isActive === false && target.id === actor.id) throw new HttpError(400, 'You cannot deactivate the account you are signed in with.')
  if (body.username) await assertUsernameFree(body.username, target.id)
  const name = body.fullName ? splitName(body.fullName) : {}
  const updated = await prisma.user.update({
    where: { id: target.id },
    data: {
      ...name,
      username: body.username?.trim(),
      email: body.email === undefined ? undefined : accountEmail(body.email),
      role: body.role as Role | undefined,
      isActive: body.isActive,
      ...(body.password ? { passwordHash: await hashPassword(body.password), mustChangePassword: true } : {}),
    },
    select: userSelect,
  })
  await audit(req, 'UPDATE', 'User', updated.id, `Updated user ${updated.username || updated.email}`)
  res.json(updated)
}))

router.delete('/users/:id', admin, asyncHandler(async (req, res) => {
  const actor = requireUser(req)
  const target = await prisma.user.findUnique({ where: { id: String(req.params.id) } })
  if (!target) throw new HttpError(404, 'Record not found.')
  if (target.id === actor.id) throw new HttpError(400, 'You cannot delete the account you are signed in with.')
  if (target.role === 'SUPER_ADMIN') {
    const remaining = await prisma.user.count({ where: { role: 'SUPER_ADMIN', isActive: true, id: { not: target.id } } })
    if (remaining === 0) throw new HttpError(400, 'Keep at least one active super administrator.')
  }
  await prisma.user.delete({ where: { id: target.id } })
  await audit(req, 'DELETE', 'User', target.id, `Deleted user ${target.username || target.email || target.firstName}`)
  res.status(204).end()
}))

router.get('/audit', admin, asyncHandler(async (req, res) => {
  const { page, pageSize, skip } = pageParams(req)
  const [items, total] = await Promise.all([
    prisma.auditLog.findMany({ orderBy: { createdAt: 'desc' }, skip, take: pageSize }),
    prisma.auditLog.count(),
  ])
  res.json(paged(items, total, page, pageSize))
}))

router.get('/lookups', asyncHandler(async (_req, res) => {
  const user = requireUser(_req)
  const [departments, jobTitles, employmentTypes, locations, leaveTypes, shifts, components, categories, employees, scales] = await Promise.all([
    prisma.department.findMany({ orderBy: { name: 'asc' } }),
    prisma.jobTitle.findMany({ orderBy: { name: 'asc' } }),
    prisma.employmentType.findMany({ orderBy: { name: 'asc' } }),
    prisma.workLocation.findMany({ orderBy: { name: 'asc' } }),
    prisma.leaveType.findMany({ orderBy: { name: 'asc' } }),
    prisma.shiftPattern.findMany({ orderBy: { name: 'asc' } }),
    prisma.payrollComponent.findMany({ orderBy: { name: 'asc' } }),
    prisma.documentCategory.findMany({ orderBy: { name: 'asc' } }),
    prisma.employee.findMany({
      where: { AND: [{ status: { in: ['ACTIVE', 'PROBATION', 'NOTICE', 'SUSPENDED'] } }, employeeWhere(user)] },
      select: { id: true, firstName: true, lastName: true, employeeNo: true, departmentId: true },
      orderBy: { firstName: 'asc' },
    }),
    prisma.ratingScale.findMany(),
  ])
  res.json({ departments, jobTitles, employmentTypes, locations, leaveTypes, shifts, components, categories, employees, scales })
}))

router.get('/backup', admin, asyncHandler(async (_req, res) => {
  const snapshot = {
    exportedAt: new Date().toISOString(),
    company: await prisma.company.findMany(),
    departments: await prisma.department.findMany(),
    employees: await prisma.employee.findMany(),
    users: await prisma.user.findMany({ select: { id: true, email: true, role: true, firstName: true, lastName: true, isActive: true } }),
    contracts: await prisma.contract.findMany(),
    leaveRequests: await prisma.leaveRequest.findMany(),
    payrollRuns: await prisma.payrollRun.findMany({ include: { payslips: true } }),
  }
  const dir = path.resolve(process.cwd(), 'backups')
  await mkdir(dir, { recursive: true })
  const file = path.join(dir, `hris-backup-${snapshot.exportedAt.replace(/[:.]/g, '-')}.json`)
  await writeFile(file, JSON.stringify(snapshot, null, 2))
  res.json({ file: path.basename(file), counts: {
    employees: snapshot.employees.length,
    leaveRequests: snapshot.leaveRequests.length,
    payrollRuns: snapshot.payrollRuns.length,
  } })
}))

export default router
