import { Router } from 'express'
import { z } from 'zod'
import { prisma } from '../lib/prisma.js'
import { asyncHandler, asDate, HttpError, parseBody, todayUtc } from '../lib/http.js'
import { canSeeDiscipline, employeeWhere, isHr, requireRoles, requireUser } from '../lib/access.js'
import { audit } from '../lib/audit.js'
import { fullName, recordEvent } from '../lib/employees.js'
import { issueLetter } from '../lib/letters.js'
import { notify, usersByRoles } from '../lib/notify.js'

const router = Router()
const hr = requireRoles('HR_ADMIN', 'HR_MANAGER')
const optional = z.string().trim().optional().or(z.literal('')).transform((value) => value || undefined)

router.get('/grievances', asyncHandler(async (req, res) => {
  const user = requireUser(req)
  const where = canSeeDiscipline(user) ? {} : { employeeId: user.employeeId || '__none__' }
  const items = await prisma.grievance.findMany({
    where,
    include: { employee: { select: { firstName: true, lastName: true, employeeNo: true } } },
    orderBy: { createdAt: 'desc' },
  })
  res.json(items.map((item) => canSeeDiscipline(user) ? item : { ...item, resolution: item.resolution }))
}))

router.post('/grievances', asyncHandler(async (req, res) => {
  const user = requireUser(req)
  const body = parseBody(z.object({
    employeeId: optional,
    againstEmployeeId: optional,
    category: z.string().min(2),
    description: z.string().min(5),
  }), req.body)
  const employeeId = body.employeeId || user.employeeId
  if (!employeeId) throw new HttpError(400, 'Choose an employee.')
  if (!isHr(user.role) && employeeId !== user.employeeId) throw new HttpError(403, 'You can only file your own grievance.')
  const created = await prisma.grievance.create({ data: { ...body, employeeId } })
  await notify({
    userIds: await usersByRoles(['HR_ADMIN', 'HR_MANAGER']),
    title: 'Grievance reported',
    body: body.category,
    type: 'GRIEVANCE',
    link: '/relations',
  })
  res.status(201).json(created)
}))

router.patch('/grievances/:id', hr, asyncHandler(async (req, res) => {
  const body = parseBody(z.object({
    status: z.enum(['OPEN', 'INVESTIGATING', 'HEARING', 'CLOSED']).optional(),
    resolution: optional,
  }), req.body)
  res.json(await prisma.grievance.update({ where: { id: String(req.params.id) }, data: body }))
}))

router.get('/cases', hr, asyncHandler(async (_req, res) => {
  res.json(await prisma.disciplinaryCase.findMany({
    include: { employee: { select: { firstName: true, lastName: true, employeeNo: true } }, warnings: true },
    orderBy: { createdAt: 'desc' },
  }))
}))

router.post('/cases', hr, asyncHandler(async (req, res) => {
  const body = parseBody(z.object({
    employeeId: z.string(),
    incidentDate: z.string(),
    description: z.string().min(5),
    hearingAt: optional,
    confidentialNotes: optional,
  }), req.body)
  const count = await prisma.disciplinaryCase.count()
  const created = await prisma.disciplinaryCase.create({
    data: {
      caseNo: `DC-${String(count + 1).padStart(4, '0')}`,
      employeeId: body.employeeId,
      incidentDate: asDate(body.incidentDate),
      description: body.description,
      hearingAt: body.hearingAt ? new Date(body.hearingAt) : null,
      confidentialNotes: body.confidentialNotes,
    },
  })
  await audit(req, 'CREATE', 'DisciplinaryCase', created.id, `Opened case ${created.caseNo}`)
  res.status(201).json(created)
}))

router.patch('/cases/:id', hr, asyncHandler(async (req, res) => {
  const body = parseBody(z.object({
    status: z.enum(['OPEN', 'INVESTIGATING', 'HEARING', 'CLOSED']).optional(),
    outcome: optional,
    hearingAt: optional,
    confidentialNotes: optional,
    suspend: z.boolean().optional(),
  }), req.body)
  const updated = await prisma.disciplinaryCase.update({
    where: { id: String(req.params.id) },
    data: {
      status: body.status,
      outcome: body.outcome,
      confidentialNotes: body.confidentialNotes,
      hearingAt: body.hearingAt ? new Date(body.hearingAt) : undefined,
    },
    include: { employee: true },
  })
  if (body.suspend) {
    await prisma.employee.update({ where: { id: updated.employeeId }, data: { status: 'SUSPENDED' } })
    await recordEvent({
      employeeId: updated.employeeId,
      type: 'SUSPENSION',
      title: `Suspended under case ${updated.caseNo}`,
      effectiveDate: todayUtc(),
      createdById: req.user?.id,
    })
  }
  res.json(updated)
}))

router.get('/warnings', asyncHandler(async (req, res) => {
  const user = requireUser(req)
  const where = canSeeDiscipline(user) ? {} : { employeeId: user.employeeId || '__none__' }
  res.json(await prisma.warning.findMany({
    where,
    include: { employee: { select: { firstName: true, lastName: true, employeeNo: true } }, case: { select: { caseNo: true } } },
    orderBy: { issuedOn: 'desc' },
  }))
}))

router.post('/warnings', hr, asyncHandler(async (req, res) => {
  const body = parseBody(z.object({
    caseId: optional,
    employeeId: z.string(),
    type: z.enum(['VERBAL', 'WRITTEN', 'FINAL']),
    content: z.string().min(5),
    issuedOn: z.string(),
  }), req.body)
  const created = await prisma.warning.create({
    data: { ...body, issuedOn: asDate(body.issuedOn), issuedById: req.user?.id },
  })
  await issueLetter({
    employeeId: body.employeeId,
    type: 'WARNING',
    subject: `${body.type[0]}${body.type.slice(1).toLowerCase()} warning`,
    issuedOn: asDate(body.issuedOn),
    uploadedById: req.user?.id,
    paragraphs: [body.content],
  })
  const account = await prisma.user.findUnique({ where: { employeeId: body.employeeId } })
  await notify({
    userIds: [account?.id],
    title: 'Warning issued',
    body: `A ${body.type.toLowerCase()} warning has been placed on your file.`,
    type: 'DISCIPLINE',
    link: '/relations',
  })
  res.status(201).json(created)
}))

router.get('/welfare', asyncHandler(async (req, res) => {
  const user = requireUser(req)
  const employees = await prisma.employee.findMany({ where: employeeWhere(user), select: { id: true } })
  const where = isHr(user.role) ? {} : { employeeId: { in: employees.map((item) => item.id) } }
  res.json(await prisma.welfareRequest.findMany({
    where,
    include: { employee: { select: { firstName: true, lastName: true, employeeNo: true } } },
    orderBy: { createdAt: 'desc' },
  }))
}))

router.post('/welfare', asyncHandler(async (req, res) => {
  const user = requireUser(req)
  const body = parseBody(z.object({
    employeeId: optional,
    type: z.string().min(2),
    description: z.string().min(5),
    amount: z.coerce.number().optional(),
  }), req.body)
  const employeeId = body.employeeId || user.employeeId
  if (!employeeId) throw new HttpError(400, 'Choose an employee.')
  const created = await prisma.welfareRequest.create({ data: { ...body, employeeId } })
  await notify({
    userIds: await usersByRoles(['HR_ADMIN', 'HR_MANAGER']),
    title: 'Welfare request',
    body: body.type,
    type: 'WELFARE',
    link: '/welfare',
  })
  res.status(201).json(created)
}))

router.patch('/welfare/:id', hr, asyncHandler(async (req, res) => {
  const body = parseBody(z.object({
    status: z.enum(['PENDING', 'APPROVED', 'REJECTED', 'FULFILLED']),
    resolution: optional,
  }), req.body)
  const updated = await prisma.welfareRequest.update({ where: { id: String(req.params.id) }, data: body })
  const account = await prisma.user.findUnique({ where: { employeeId: updated.employeeId } })
  await notify({
    userIds: [account?.id],
    title: `Welfare request ${body.status.toLowerCase()}`,
    body: body.resolution || updated.type,
    type: 'WELFARE',
    link: '/welfare',
  })
  res.json(updated)
}))

router.get('/feedback', hr, asyncHandler(async (_req, res) => {
  res.json(await prisma.staffFeedback.findMany({ orderBy: { createdAt: 'desc' } }))
}))

router.post('/feedback', asyncHandler(async (req, res) => {
  const user = requireUser(req)
  const body = parseBody(z.object({
    category: z.string().min(2),
    message: z.string().min(5),
    anonymous: z.boolean().default(false),
  }), req.body)
  res.status(201).json(await prisma.staffFeedback.create({
    data: { ...body, employeeId: body.anonymous ? null : user.employeeId },
  }))
}))

router.get('/surveys', asyncHandler(async (_req, res) => {
  requireUser(_req)
  res.json(await prisma.survey.findMany({ include: { _count: { select: { responses: true } } }, orderBy: { createdAt: 'desc' } }))
}))

router.post('/surveys', hr, asyncHandler(async (req, res) => {
  const body = parseBody(z.object({
    title: z.string().min(2),
    description: optional,
    questions: z.array(z.object({ id: z.string(), prompt: z.string(), kind: z.enum(['text', 'rating']) })),
    closesOn: optional,
  }), req.body)
  res.status(201).json(await prisma.survey.create({
    data: { title: body.title, description: body.description, questions: body.questions, closesOn: body.closesOn ? asDate(body.closesOn) : null },
  }))
}))

router.post('/surveys/:id/responses', asyncHandler(async (req, res) => {
  const user = requireUser(req)
  const body = parseBody(z.object({ answers: z.record(z.union([z.string(), z.number()])) }), req.body)
  if (!user.employeeId) throw new HttpError(400, 'Only employees can respond.')
  const created = await prisma.surveyResponse.upsert({
    where: { surveyId_employeeId: { surveyId: String(req.params.id), employeeId: user.employeeId } },
    update: { answers: body.answers },
    create: { surveyId: String(req.params.id), employeeId: user.employeeId, answers: body.answers },
  })
  res.status(201).json(created)
}))

router.get('/surveys/:id/results', hr, asyncHandler(async (req, res) => {
  const survey = await prisma.survey.findUnique({
    where: { id: String(req.params.id) },
    include: { responses: true },
  })
  if (!survey) throw new HttpError(404, 'Survey not found.')
  res.json(survey)
}))

router.get('/recognition', asyncHandler(async (_req, res) => {
  requireUser(_req)
  res.json(await prisma.recognition.findMany({
    include: { employee: { select: { firstName: true, lastName: true, employeeNo: true } } },
    orderBy: { awardedOn: 'desc' },
  }))
}))

router.post('/recognition', hr, asyncHandler(async (req, res) => {
  const body = parseBody(z.object({
    employeeId: z.string(),
    title: z.string().min(2),
    description: optional,
    awardedOn: z.string(),
  }), req.body)
  const created = await prisma.recognition.create({
    data: { ...body, awardedOn: asDate(body.awardedOn), awardedById: req.user?.id },
  })
  const account = await prisma.user.findUnique({ where: { employeeId: body.employeeId } })
  await notify({
    userIds: [account?.id],
    title: 'Recognition',
    body: body.title,
    type: 'RECOGNITION',
    link: '/welfare',
  })
  res.status(201).json(created)
}))

router.get('/activities', asyncHandler(async (_req, res) => {
  requireUser(_req)
  res.json(await prisma.engagementActivity.findMany({ orderBy: { activityDate: 'desc' } }))
}))

router.post('/activities', hr, asyncHandler(async (req, res) => {
  const body = parseBody(z.object({
    title: z.string().min(2),
    activityDate: z.string(),
    location: optional,
    description: optional,
  }), req.body)
  res.status(201).json(await prisma.engagementActivity.create({
    data: { ...body, activityDate: asDate(body.activityDate) },
  }))
}))

router.delete('/activities/:id', hr, asyncHandler(async (req, res) => {
  await prisma.engagementActivity.delete({ where: { id: String(req.params.id) } })
  res.status(204).end()
}))

router.get('/suggestions', asyncHandler(async (req, res) => {
  const user = requireUser(req)
  const where = isHr(user.role) ? {} : { employeeId: user.employeeId || '__none__' }
  res.json(await prisma.suggestion.findMany({ where, orderBy: { createdAt: 'desc' } }))
}))

router.post('/suggestions', asyncHandler(async (req, res) => {
  const user = requireUser(req)
  const body = parseBody(z.object({ message: z.string().min(5) }), req.body)
  res.status(201).json(await prisma.suggestion.create({ data: { message: body.message, employeeId: user.employeeId } }))
}))

router.patch('/suggestions/:id', hr, asyncHandler(async (req, res) => {
  const body = parseBody(z.object({ status: z.string(), response: optional }), req.body)
  res.json(await prisma.suggestion.update({ where: { id: String(req.params.id) }, data: body }))
}))

router.get('/contracts', asyncHandler(async (req, res) => {
  const user = requireUser(req)
  const employees = await prisma.employee.findMany({ where: employeeWhere(user), select: { id: true } })
  res.json(await prisma.contract.findMany({
    where: { employeeId: { in: employees.map((item) => item.id) } },
    include: { employee: { select: { firstName: true, lastName: true, employeeNo: true, department: { select: { name: true } } } } },
    orderBy: { endDate: 'asc' },
  }))
}))

router.post('/contracts', hr, asyncHandler(async (req, res) => {
  const body = parseBody(z.object({
    employeeId: z.string(),
    type: z.string().min(2),
    title: z.string().min(2),
    startDate: z.string(),
    endDate: optional,
    notes: optional,
  }), req.body)
  const created = await prisma.contract.create({
    data: {
      employeeId: body.employeeId,
      type: body.type,
      title: body.title,
      startDate: asDate(body.startDate),
      endDate: body.endDate ? asDate(body.endDate) : null,
      notes: body.notes,
    },
  })
  res.status(201).json(created)
}))

router.patch('/contracts/:id', hr, asyncHandler(async (req, res) => {
  const body = parseBody(z.object({
    status: z.string().optional(),
    endDate: optional,
    notes: optional,
  }), req.body)
  res.json(await prisma.contract.update({
    where: { id: String(req.params.id) },
    data: { status: body.status, notes: body.notes, endDate: body.endDate ? asDate(body.endDate) : undefined },
  }))
}))

router.get('/letters', asyncHandler(async (req, res) => {
  const user = requireUser(req)
  const employees = await prisma.employee.findMany({ where: employeeWhere(user), select: { id: true } })
  res.json(await prisma.hrLetter.findMany({
    where: { employeeId: { in: employees.map((item) => item.id) } },
    include: { employee: { select: { firstName: true, lastName: true, employeeNo: true } } },
    orderBy: { issuedOn: 'desc' },
  }))
}))

router.get('/separations', asyncHandler(async (req, res) => {
  const user = requireUser(req)
  const employees = await prisma.employee.findMany({ where: employeeWhere(user), select: { id: true } })
  res.json(await prisma.separation.findMany({
    where: { employeeId: { in: employees.map((item) => item.id) } },
    include: { employee: { select: { firstName: true, lastName: true, employeeNo: true, status: true } }, clearance: true },
    orderBy: { createdAt: 'desc' },
  }))
}))

router.post('/separations', asyncHandler(async (req, res) => {
  const user = requireUser(req)
  const body = parseBody(z.object({
    employeeId: optional,
    type: z.enum(['RESIGNATION', 'RETIREMENT', 'TERMINATION', 'END_OF_CONTRACT']),
    reason: z.string().min(5),
    noticeDate: z.string(),
    lastWorkingDay: z.string(),
    interviewNotes: optional,
  }), req.body)
  const employeeId = body.employeeId || user.employeeId
  if (!employeeId) throw new HttpError(400, 'Choose an employee.')
  if (!isHr(user.role) && employeeId !== user.employeeId) throw new HttpError(403, 'You cannot file this separation.')
  if (body.type !== 'RESIGNATION' && !isHr(user.role)) throw new HttpError(403, 'Only HR can record this separation type.')
  const created = await prisma.separation.create({
    data: {
      employeeId,
      type: body.type,
      reason: body.reason,
      noticeDate: asDate(body.noticeDate),
      lastWorkingDay: asDate(body.lastWorkingDay),
      interviewNotes: body.interviewNotes,
      clearance: {
        create: [
          { item: 'Staff identity card' },
          { item: 'Laptop and accessories' },
          { item: 'Access card and keys' },
          { item: 'Mobile device' },
          { item: 'Outstanding accountabilities' },
        ],
      },
    },
    include: { clearance: true },
  })
  await notify({
    userIds: await usersByRoles(['HR_ADMIN', 'HR_MANAGER']),
    title: 'Separation submitted',
    body: body.reason,
    type: 'SEPARATION',
    link: '/exit',
  })
  res.status(201).json(created)
}))

router.post('/separations/:id/decision', hr, asyncHandler(async (req, res) => {
  const body = parseBody(z.object({ decision: z.enum(['APPROVED', 'REJECTED']), note: optional, finalPayNotes: optional }), req.body)
  const separation = await prisma.separation.findUnique({ where: { id: String(req.params.id) }, include: { employee: true } })
  if (!separation) throw new HttpError(404, 'Separation not found.')
  const lastDay = separation.lastWorkingDay
  const due = lastDay <= todayUtc()
  let employeeStatus = separation.employee.status
  if (body.decision === 'APPROVED') {
    employeeStatus = due
      ? separation.type === 'RETIREMENT' ? 'RETIRED' : separation.type === 'TERMINATION' ? 'TERMINATED' : 'RESIGNED'
      : 'NOTICE'
    await prisma.employee.update({ where: { id: separation.employeeId }, data: { status: employeeStatus } })
    await recordEvent({
      employeeId: separation.employeeId,
      type: 'STATUS_CHANGE',
      title: due ? 'Employment ended' : 'Notice period started',
      description: separation.reason,
      effectiveDate: due ? lastDay : todayUtc(),
      createdById: req.user?.id,
      toValue: { status: employeeStatus },
    })
    if (due) {
      const account = await prisma.user.findUnique({ where: { employeeId: separation.employeeId } })
      if (account) await prisma.user.update({ where: { id: account.id }, data: { isActive: false } })
    }
    if (separation.type === 'TERMINATION') {
      await issueLetter({
        employeeId: separation.employeeId,
        type: 'TERMINATION',
        subject: 'Termination letter',
        issuedOn: todayUtc(),
        uploadedById: req.user?.id,
        paragraphs: [separation.reason, body.note || 'Your last working day is recorded on this separation.'],
      })
    }
  }
  const updated = await prisma.separation.update({
    where: { id: separation.id },
    data: {
      status: body.decision === 'APPROVED' && due ? 'COMPLETED' : body.decision === 'APPROVED' ? 'APPROVED' : 'REJECTED',
      decisionNote: body.note,
      finalPayNotes: body.finalPayNotes,
    },
  })
  await audit(req, 'UPDATE', 'Separation', separation.id, `${body.decision} separation for ${fullName(separation.employee)}`)
  res.json(updated)
}))

router.patch('/clearance/:id', hr, asyncHandler(async (req, res) => {
  const body = parseBody(z.object({ returned: z.boolean(), notes: optional }), req.body)
  res.json(await prisma.clearanceItem.update({
    where: { id: String(req.params.id) },
    data: { returned: body.returned, notes: body.notes, clearedById: req.user?.id },
  }))
}))

router.get('/budgets', asyncHandler(async (req, res) => {
  requireUser(req)
  const year = Number(req.query.year) || new Date().getUTCFullYear()
  res.json(await prisma.manpowerBudget.findMany({
    where: { year },
    include: { department: true, jobTitle: true },
    orderBy: { department: { name: 'asc' } },
  }))
}))

router.post('/budgets', hr, asyncHandler(async (req, res) => {
  const body = parseBody(z.object({
    year: z.coerce.number().int(),
    departmentId: z.string(),
    jobTitleId: optional,
    approvedHeadcount: z.coerce.number().int().nonnegative(),
    estimatedCost: z.coerce.number().nonnegative(),
    notes: optional,
  }), req.body)
  const jobTitleId = body.jobTitleId || null
  const existing = await prisma.manpowerBudget.findFirst({
    where: { year: body.year, departmentId: body.departmentId, jobTitleId },
  })
  const saved = existing
    ? await prisma.manpowerBudget.update({
        where: { id: existing.id },
        data: { approvedHeadcount: body.approvedHeadcount, estimatedCost: body.estimatedCost, notes: body.notes },
      })
    : await prisma.manpowerBudget.create({
        data: {
          year: body.year,
          departmentId: body.departmentId,
          jobTitleId,
          approvedHeadcount: body.approvedHeadcount,
          estimatedCost: body.estimatedCost,
          notes: body.notes,
        },
      })
  res.status(201).json(saved)
}))

router.get('/succession', asyncHandler(async (_req, res) => {
  requireUser(_req)
  res.json(await prisma.successionPlan.findMany({
    include: {
      incumbent: { select: { firstName: true, lastName: true, employeeNo: true } },
      successor: { select: { firstName: true, lastName: true, employeeNo: true } },
    },
  }))
}))

router.post('/succession', hr, asyncHandler(async (req, res) => {
  const body = parseBody(z.object({
    positionTitle: z.string().min(2),
    departmentId: optional,
    incumbentId: optional,
    successorId: optional,
    readiness: z.string().min(2),
    critical: z.boolean().default(false),
    notes: optional,
  }), req.body)
  res.status(201).json(await prisma.successionPlan.create({ data: body }))
}))

router.patch('/succession/:id', hr, asyncHandler(async (req, res) => {
  const body = parseBody(z.object({
    successorId: optional,
    readiness: optional,
    critical: z.boolean().optional(),
    notes: optional,
  }), req.body)
  res.json(await prisma.successionPlan.update({ where: { id: String(req.params.id) }, data: body }))
}))

router.delete('/succession/:id', hr, asyncHandler(async (req, res) => {
  await prisma.successionPlan.delete({ where: { id: String(req.params.id) } })
  res.status(204).end()
}))

router.get('/summary', asyncHandler(async (_req, res) => {
  requireUser(_req)
  const [departments, employees, vacancies, budgets] = await Promise.all([
    prisma.department.findMany(),
    prisma.employee.findMany({ where: { status: { in: ['ACTIVE', 'PROBATION', 'NOTICE', 'SUSPENDED'] } } }),
    prisma.vacancy.findMany({ where: { status: 'OPEN' } }),
    prisma.manpowerBudget.findMany({ where: { year: new Date().getUTCFullYear() }, include: { department: true } }),
  ])
  const rows = departments.map((department) => {
    const current = employees.filter((employee) => employee.departmentId === department.id).length
    const approved = budgets.filter((budget) => budget.departmentId === department.id).reduce((sum, budget) => sum + budget.approvedHeadcount, 0)
    const openVacancies = vacancies.filter((vacancy) => vacancy.departmentId === department.id).reduce((sum, vacancy) => sum + (vacancy.openings - vacancy.filled), 0)
    const cost = employees.filter((employee) => employee.departmentId === department.id).reduce((sum, employee) => sum + Number(employee.basicSalary), 0)
    return {
      department: department.name,
      departmentId: department.id,
      current,
      approved,
      vacant: Math.max(approved - current, 0) + openVacancies,
      monthlyBasic: cost,
    }
  })
  res.json(rows)
}))

export default router
