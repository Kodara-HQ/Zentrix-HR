import { Router } from 'express'
import { z } from 'zod'
import { prisma } from '../lib/prisma.js'
import { asyncHandler, asDate, HttpError, parseBody } from '../lib/http.js'
import { assertEmployeeVisible, employeeWhere, isHr, requireRoles, requireUser } from '../lib/access.js'
import { notify } from '../lib/notify.js'

const router = Router()
const hr = requireRoles('HR_ADMIN', 'HR_MANAGER', 'DEPARTMENT_MANAGER', 'SUPERVISOR')
const optional = z.string().trim().optional().or(z.literal('')).transform((value) => value || undefined)

router.get('/cycles', asyncHandler(async (_req, res) => {
  requireUser(_req)
  res.json(await prisma.performanceCycle.findMany({ orderBy: { startDate: 'desc' }, include: { _count: { select: { appraisals: true, objectives: true } } } }))
}))

router.post('/cycles', requireRoles('HR_ADMIN', 'HR_MANAGER'), asyncHandler(async (req, res) => {
  const body = parseBody(z.object({
    name: z.string().min(2),
    periodType: z.enum(['QUARTERLY', 'HALF_YEARLY', 'ANNUAL']),
    startDate: z.string(),
    endDate: z.string(),
    status: z.enum(['DRAFT', 'OPEN', 'CLOSED']).default('OPEN'),
  }), req.body)
  const created = await prisma.performanceCycle.create({
    data: { ...body, startDate: asDate(body.startDate), endDate: asDate(body.endDate) },
  })
  res.status(201).json(created)
}))

router.patch('/cycles/:id', requireRoles('HR_ADMIN', 'HR_MANAGER'), asyncHandler(async (req, res) => {
  const body = parseBody(z.object({ status: z.enum(['DRAFT', 'OPEN', 'CLOSED']) }), req.body)
  res.json(await prisma.performanceCycle.update({ where: { id: String(req.params.id) }, data: body }))
}))

router.get('/objectives', asyncHandler(async (req, res) => {
  const user = requireUser(req)
  const employees = await prisma.employee.findMany({ where: employeeWhere(user), select: { id: true } })
  res.json(await prisma.objective.findMany({
    where: {
      OR: [
        { employeeId: { in: employees.map((item) => item.id) } },
        { level: 'DEPARTMENT', ...(user.departmentId && !isHr(user.role) ? { departmentId: user.departmentId } : {}) },
      ],
    },
    include: { employee: { select: { firstName: true, lastName: true, employeeNo: true } }, department: true, cycle: true },
    orderBy: { createdAt: 'desc' },
  }))
}))

router.post('/objectives', hr, asyncHandler(async (req, res) => {
  const body = parseBody(z.object({
    cycleId: z.string(),
    level: z.enum(['INDIVIDUAL', 'DEPARTMENT']),
    employeeId: optional,
    departmentId: optional,
    title: z.string().min(2),
    description: optional,
    kpi: z.string().min(2),
    target: z.string().min(1),
    weight: z.coerce.number().positive(),
    actual: optional,
    score: z.coerce.number().optional(),
  }), req.body)
  const created = await prisma.objective.create({ data: body })
  res.status(201).json(created)
}))

router.patch('/objectives/:id', hr, asyncHandler(async (req, res) => {
  const body = parseBody(z.object({
    actual: optional,
    score: z.coerce.number().optional(),
    title: z.string().optional(),
    target: z.string().optional(),
  }), req.body)
  res.json(await prisma.objective.update({ where: { id: String(req.params.id) }, data: body }))
}))

router.delete('/objectives/:id', requireRoles('HR_ADMIN', 'HR_MANAGER'), asyncHandler(async (req, res) => {
  await prisma.objective.delete({ where: { id: String(req.params.id) } })
  res.status(204).end()
}))

router.get('/appraisals', asyncHandler(async (req, res) => {
  const user = requireUser(req)
  const employees = await prisma.employee.findMany({ where: employeeWhere(user), select: { id: true } })
  res.json(await prisma.appraisal.findMany({
    where: { employeeId: { in: employees.map((item) => item.id) } },
    include: { employee: { select: { firstName: true, lastName: true, employeeNo: true, departmentId: true, managerId: true, id: true } }, cycle: true, plans: true },
    orderBy: { updatedAt: 'desc' },
  }))
}))

router.post('/appraisals', hr, asyncHandler(async (req, res) => {
  const body = parseBody(z.object({
    cycleId: z.string(),
    employeeId: z.string(),
  }), req.body)
  const created = await prisma.appraisal.create({
    data: { ...body, reviewerId: req.user?.id, status: 'DRAFT' },
  })
  const account = await prisma.user.findUnique({ where: { employeeId: body.employeeId } })
  await notify({
    userIds: [account?.id],
    title: 'Performance review opened',
    body: 'Complete your self-assessment.',
    type: 'APPRAISAL',
    link: '/performance',
  })
  res.status(201).json(created)
}))

router.patch('/appraisals/:id', asyncHandler(async (req, res) => {
  const user = requireUser(req)
  const appraisal = await prisma.appraisal.findUnique({ where: { id: String(req.params.id) }, include: { employee: true } })
  if (!appraisal) throw new HttpError(404, 'Appraisal not found.')
  assertEmployeeVisible(user, appraisal.employee)
  const body = parseBody(z.object({
    selfRating: z.coerce.number().optional(),
    selfComments: optional,
    supervisorRating: z.coerce.number().optional(),
    supervisorComments: optional,
    finalRating: z.coerce.number().optional(),
    recommendation: optional,
    status: z.enum(['DRAFT', 'SELF_SUBMITTED', 'REVIEWED', 'ACKNOWLEDGED']).optional(),
  }), req.body)
  const isSelf = user.employeeId === appraisal.employeeId
  const data = isSelf && !isHr(user.role)
    ? { selfRating: body.selfRating, selfComments: body.selfComments, status: body.status === 'SELF_SUBMITTED' ? 'SELF_SUBMITTED' as const : appraisal.status }
    : body
  res.json(await prisma.appraisal.update({ where: { id: appraisal.id }, data }))
}))

router.post('/plans', hr, asyncHandler(async (req, res) => {
  const body = parseBody(z.object({
    appraisalId: optional,
    employeeId: z.string(),
    goals: z.string().min(5),
    support: optional,
    reviewDate: optional,
  }), req.body)
  const created = await prisma.improvementPlan.create({
    data: { ...body, reviewDate: body.reviewDate ? asDate(body.reviewDate) : null },
  })
  res.status(201).json(created)
}))

router.patch('/plans/:id', hr, asyncHandler(async (req, res) => {
  const body = parseBody(z.object({ status: z.string(), goals: optional }), req.body)
  res.json(await prisma.improvementPlan.update({ where: { id: String(req.params.id) }, data: body }))
}))

router.get('/providers', asyncHandler(async (_req, res) => {
  requireUser(_req)
  res.json(await prisma.trainingProvider.findMany({ orderBy: { name: 'asc' } }))
}))

router.post('/providers', requireRoles('HR_ADMIN', 'HR_MANAGER'), asyncHandler(async (req, res) => {
  const body = parseBody(z.object({
    name: z.string().min(2),
    contact: optional,
    email: optional,
    phone: optional,
  }), req.body)
  res.status(201).json(await prisma.trainingProvider.create({ data: body }))
}))

router.get('/programs', asyncHandler(async (_req, res) => {
  requireUser(_req)
  res.json(await prisma.trainingProgram.findMany({
    include: { provider: true, enrollments: { include: { employee: { select: { firstName: true, lastName: true, employeeNo: true } } } } },
    orderBy: { startDate: 'desc' },
  }))
}))

router.post('/programs', requireRoles('HR_ADMIN', 'HR_MANAGER'), asyncHandler(async (req, res) => {
  const body = parseBody(z.object({
    title: z.string().min(2),
    providerId: optional,
    description: optional,
    startDate: z.string(),
    endDate: z.string(),
    cost: z.coerce.number().default(0),
    location: optional,
    capacity: z.coerce.number().int().optional(),
    status: z.enum(['PLANNED', 'ONGOING', 'COMPLETED', 'CANCELLED']).default('PLANNED'),
  }), req.body)
  const created = await prisma.trainingProgram.create({
    data: { ...body, startDate: asDate(body.startDate), endDate: asDate(body.endDate) },
  })
  res.status(201).json(created)
}))

router.patch('/programs/:id', requireRoles('HR_ADMIN', 'HR_MANAGER'), asyncHandler(async (req, res) => {
  const body = parseBody(z.object({
    status: z.enum(['PLANNED', 'ONGOING', 'COMPLETED', 'CANCELLED']).optional(),
    cost: z.coerce.number().optional(),
    title: z.string().optional(),
  }), req.body)
  res.json(await prisma.trainingProgram.update({ where: { id: String(req.params.id) }, data: body }))
}))

router.post('/enrollments', requireRoles('HR_ADMIN', 'HR_MANAGER', 'DEPARTMENT_MANAGER'), asyncHandler(async (req, res) => {
  const body = parseBody(z.object({ programId: z.string(), employeeId: z.string() }), req.body)
  const program = await prisma.trainingProgram.findUnique({ where: { id: body.programId }, include: { enrollments: true } })
  if (!program) throw new HttpError(404, 'Programme not found.')
  if (program.capacity && program.enrollments.length >= program.capacity) throw new HttpError(400, 'This programme is full.')
  const created = await prisma.trainingEnrollment.create({ data: body })
  const account = await prisma.user.findUnique({ where: { employeeId: body.employeeId } })
  await notify({
    userIds: [account?.id],
    title: 'Training enrolment',
    body: `You are enrolled on ${program.title}.`,
    type: 'TRAINING',
    link: '/training',
  })
  res.status(201).json(created)
}))

router.patch('/enrollments/:id', requireRoles('HR_ADMIN', 'HR_MANAGER'), asyncHandler(async (req, res) => {
  const body = parseBody(z.object({
    status: z.enum(['ENROLLED', 'ATTENDED', 'ABSENT', 'COMPLETED']).optional(),
    score: z.coerce.number().optional(),
    feedback: optional,
    attended: z.boolean().optional(),
  }), req.body)
  res.json(await prisma.trainingEnrollment.update({ where: { id: String(req.params.id) }, data: body }))
}))

router.get('/needs', asyncHandler(async (req, res) => {
  requireUser(req)
  res.json(await prisma.trainingNeed.findMany({ orderBy: { createdAt: 'desc' } }))
}))

router.post('/needs', asyncHandler(async (req, res) => {
  const user = requireUser(req)
  const body = parseBody(z.object({
    employeeId: optional,
    skill: z.string().min(2),
    priority: z.string().min(2),
    justification: optional,
  }), req.body)
  const employeeId = body.employeeId || user.employeeId
  if (!employeeId) throw new HttpError(400, 'Choose an employee.')
  res.status(201).json(await prisma.trainingNeed.create({ data: { ...body, employeeId } }))
}))

router.patch('/needs/:id', requireRoles('HR_ADMIN', 'HR_MANAGER'), asyncHandler(async (req, res) => {
  const body = parseBody(z.object({ status: z.string() }), req.body)
  res.json(await prisma.trainingNeed.update({ where: { id: String(req.params.id) }, data: body }))
}))

router.get('/certifications', asyncHandler(async (req, res) => {
  const user = requireUser(req)
  const employees = await prisma.employee.findMany({ where: employeeWhere(user), select: { id: true } })
  res.json(await prisma.certification.findMany({
    where: { employeeId: { in: employees.map((item) => item.id) } },
    include: { employee: { select: { firstName: true, lastName: true, employeeNo: true } } },
    orderBy: { expiresOn: 'asc' },
  }))
}))

router.post('/certifications', asyncHandler(async (req, res) => {
  const user = requireUser(req)
  const body = parseBody(z.object({
    employeeId: optional,
    name: z.string().min(2),
    issuer: z.string().min(2),
    credentialNo: optional,
    issuedOn: optional,
    expiresOn: optional,
  }), req.body)
  const employeeId = body.employeeId || user.employeeId
  if (!employeeId) throw new HttpError(400, 'Choose an employee.')
  if (!isHr(user.role) && user.employeeId !== employeeId) throw new HttpError(403, 'You cannot add this certificate.')
  res.status(201).json(await prisma.certification.create({
    data: {
      employeeId,
      name: body.name,
      issuer: body.issuer,
      credentialNo: body.credentialNo,
      issuedOn: body.issuedOn ? asDate(body.issuedOn) : null,
      expiresOn: body.expiresOn ? asDate(body.expiresOn) : null,
    },
  }))
}))

router.delete('/certifications/:id', requireRoles('HR_ADMIN', 'HR_MANAGER'), asyncHandler(async (req, res) => {
  await prisma.certification.delete({ where: { id: String(req.params.id) } })
  res.status(204).end()
}))

router.get('/development-plans', asyncHandler(async (req, res) => {
  const user = requireUser(req)
  const employees = await prisma.employee.findMany({ where: employeeWhere(user), select: { id: true } })
  res.json(await prisma.developmentPlan.findMany({
    where: { employeeId: { in: employees.map((item) => item.id) } },
    include: { employee: { select: { firstName: true, lastName: true } } },
  }))
}))

router.post('/development-plans', hr, asyncHandler(async (req, res) => {
  const body = parseBody(z.object({
    employeeId: z.string(),
    title: z.string().min(2),
    goals: z.string().min(5),
    targetDate: optional,
  }), req.body)
  res.status(201).json(await prisma.developmentPlan.create({
    data: { ...body, targetDate: body.targetDate ? asDate(body.targetDate) : null },
  }))
}))

router.patch('/development-plans/:id', hr, asyncHandler(async (req, res) => {
  const body = parseBody(z.object({ status: z.string().optional(), goals: optional }), req.body)
  res.json(await prisma.developmentPlan.update({ where: { id: String(req.params.id) }, data: body }))
}))

export default router
