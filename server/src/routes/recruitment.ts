import { Router } from 'express'
import { z } from 'zod'
import { prisma } from '../lib/prisma.js'
import { asyncHandler, asDate, HttpError, pageParams, paged, parseBody, todayUtc } from '../lib/http.js'
import { isHr, requireRoles, requireUser } from '../lib/access.js'
import { audit } from '../lib/audit.js'
import { fullName, grantLeaveBalances, nextEmployeeNo, recordEvent } from '../lib/employees.js'
import { issueLetter } from '../lib/letters.js'
import { hashPassword } from '../lib/auth.js'
import { notify, usersByRoles } from '../lib/notify.js'

const router = Router()
const recruiters = requireRoles('RECRUITMENT_OFFICER', 'HR_ADMIN', 'HR_MANAGER')
const optional = z.string().trim().optional().or(z.literal('')).transform((value) => value || undefined)

router.get('/requisitions', asyncHandler(async (req, res) => {
  requireUser(req)
  const { page, pageSize, skip } = pageParams(req)
  const where = req.query.status ? { status: String(req.query.status) as never } : {}
  const [items, total] = await Promise.all([
    prisma.manpowerRequisition.findMany({ where, orderBy: { createdAt: 'desc' }, skip, take: pageSize }),
    prisma.manpowerRequisition.count({ where }),
  ])
  res.json(paged(items, total, page, pageSize))
}))

router.post('/requisitions', requireRoles('DEPARTMENT_MANAGER', 'HR_ADMIN', 'HR_MANAGER', 'RECRUITMENT_OFFICER'), asyncHandler(async (req, res) => {
  const user = requireUser(req)
  const body = parseBody(z.object({
    departmentId: z.string(),
    jobTitleId: z.string(),
    locationId: optional,
    headcount: z.coerce.number().int().positive(),
    justification: z.string().min(5),
  }), req.body)
  const created = await prisma.manpowerRequisition.create({
    data: { ...body, requestedById: user.id },
  })
  await notify({
    userIds: await usersByRoles(['HR_MANAGER', 'SUPER_ADMIN']),
    title: 'Manpower requisition submitted',
    body: body.justification,
    type: 'RECRUITMENT',
    link: '/recruitment',
  })
  res.status(201).json(created)
}))

router.post('/requisitions/:id/decision', requireRoles('HR_MANAGER'), asyncHandler(async (req, res) => {
  const body = parseBody(z.object({
    decision: z.enum(['APPROVED', 'REJECTED']),
    note: optional,
  }), req.body)
  const updated = await prisma.manpowerRequisition.update({
    where: { id: String(req.params.id) },
    data: { status: body.decision, decisionNote: body.note },
  })
  const requester = updated.requestedById
  await notify({
    userIds: [requester],
    title: `Requisition ${body.decision.toLowerCase()}`,
    body: body.note || 'A manpower requisition was reviewed.',
    type: 'RECRUITMENT',
    link: '/recruitment',
  })
  res.json(updated)
}))

router.get('/vacancies', asyncHandler(async (req, res) => {
  requireUser(req)
  const { page, pageSize, skip, search } = pageParams(req)
  const where = {
    ...(req.query.status ? { status: String(req.query.status) as never } : {}),
    ...(search ? { title: { contains: search, mode: 'insensitive' as const } } : {}),
  }
  const [items, total] = await Promise.all([
    prisma.vacancy.findMany({
      where,
      include: { department: true, location: true, _count: { select: { applicants: true } } },
      orderBy: { createdAt: 'desc' },
      skip,
      take: pageSize,
    }),
    prisma.vacancy.count({ where }),
  ])
  res.json(paged(items, total, page, pageSize))
}))

router.post('/vacancies', recruiters, asyncHandler(async (req, res) => {
  const body = parseBody(z.object({
    requisitionId: optional,
    title: z.string().min(2),
    departmentId: z.string(),
    jobTitleId: z.string(),
    locationId: z.string(),
    description: z.string().min(5),
    requirements: z.string().min(5),
    openings: z.coerce.number().int().positive().default(1),
    salaryMin: z.coerce.number().optional(),
    salaryMax: z.coerce.number().optional(),
    advertisingCost: z.coerce.number().default(0),
    agencyCost: z.coerce.number().default(0),
    status: z.enum(['DRAFT', 'OPEN', 'ON_HOLD', 'CLOSED', 'FILLED']).default('OPEN'),
  }), req.body)
  if (body.requisitionId) {
    const requisition = await prisma.manpowerRequisition.findUnique({ where: { id: body.requisitionId } })
    if (!requisition || requisition.status !== 'APPROVED') {
      throw new HttpError(400, 'Open a vacancy from an approved requisition.')
    }
  }
  const created = await prisma.vacancy.create({
    data: {
      ...body,
      openedOn: body.status === 'OPEN' ? todayUtc() : null,
    },
  })
  await audit(req, 'CREATE', 'Vacancy', created.id, `Opened vacancy ${created.title}`)
  res.status(201).json(created)
}))

router.patch('/vacancies/:id', recruiters, asyncHandler(async (req, res) => {
  const body = parseBody(z.object({
    title: z.string().min(2).optional(),
    description: z.string().optional(),
    requirements: z.string().optional(),
    status: z.enum(['DRAFT', 'OPEN', 'ON_HOLD', 'CLOSED', 'FILLED']).optional(),
    advertisingCost: z.coerce.number().optional(),
    agencyCost: z.coerce.number().optional(),
    openings: z.coerce.number().int().optional(),
  }), req.body)
  const updated = await prisma.vacancy.update({
    where: { id: String(req.params.id) },
    data: {
      ...body,
      ...(body.status === 'OPEN' ? { openedOn: todayUtc() } : {}),
      ...(body.status === 'CLOSED' || body.status === 'FILLED' ? { closedOn: todayUtc() } : {}),
    },
  })
  res.json(updated)
}))

router.get('/applicants', asyncHandler(async (req, res) => {
  requireUser(req)
  const { page, pageSize, skip, search } = pageParams(req)
  const where = {
    ...(req.query.vacancyId ? { vacancyId: String(req.query.vacancyId) } : {}),
    ...(req.query.status ? { status: String(req.query.status) as never } : {}),
    ...(search ? { OR: [
      { firstName: { contains: search, mode: 'insensitive' as const } },
      { lastName: { contains: search, mode: 'insensitive' as const } },
      { email: { contains: search, mode: 'insensitive' as const } },
    ] } : {}),
  }
  const [items, total] = await Promise.all([
    prisma.applicant.findMany({
      where,
      include: { vacancy: true, interviews: true, offers: true },
      orderBy: { createdAt: 'desc' },
      skip,
      take: pageSize,
    }),
    prisma.applicant.count({ where }),
  ])
  res.json(paged(items, total, page, pageSize))
}))

router.post('/applicants', recruiters, asyncHandler(async (req, res) => {
  const body = parseBody(z.object({
    vacancyId: z.string(),
    firstName: z.string().min(2),
    lastName: z.string().min(2),
    email: z.string().email(),
    phone: z.string().min(7),
    source: optional,
    notes: optional,
    screeningScore: z.coerce.number().optional(),
  }), req.body)
  const created = await prisma.applicant.create({ data: { ...body, email: body.email.toLowerCase() } })
  res.status(201).json(created)
}))

router.patch('/applicants/:id', recruiters, asyncHandler(async (req, res) => {
  const body = parseBody(z.object({
    status: z.enum(['APPLIED', 'SCREENED', 'SHORTLISTED', 'INTERVIEW', 'OFFERED', 'HIRED', 'REJECTED', 'WITHDRAWN']).optional(),
    screeningScore: z.coerce.number().optional(),
    notes: optional,
  }), req.body)
  const updated = await prisma.applicant.update({ where: { id: String(req.params.id) }, data: body })
  res.json(updated)
}))

router.get('/compare', asyncHandler(async (req, res) => {
  requireUser(req)
  const ids = String(req.query.ids || '').split(',').filter(Boolean)
  const applicants = await prisma.applicant.findMany({
    where: { id: { in: ids } },
    include: { vacancy: true, interviews: true },
  })
  res.json(applicants)
}))

router.post('/interviews', recruiters, asyncHandler(async (req, res) => {
  const body = parseBody(z.object({
    applicantId: z.string(),
    scheduledAt: z.string(),
    interviewerId: optional,
    mode: z.string().min(2),
    location: optional,
  }), req.body)
  const created = await prisma.interview.create({
    data: { ...body, scheduledAt: new Date(body.scheduledAt) },
  })
  await prisma.applicant.update({ where: { id: body.applicantId }, data: { status: 'INTERVIEW' } })
  const interviewer = body.interviewerId
    ? await prisma.user.findFirst({ where: { OR: [{ id: body.interviewerId }, { employeeId: body.interviewerId }] } })
    : null
  await notify({
    userIds: [interviewer?.id],
    title: 'Interview scheduled',
    body: `An interview is set for ${body.scheduledAt}.`,
    type: 'RECRUITMENT',
    link: '/recruitment',
  })
  res.status(201).json(created)
}))

router.patch('/interviews/:id', recruiters, asyncHandler(async (req, res) => {
  const body = parseBody(z.object({
    score: z.coerce.number().optional(),
    comments: optional,
    recommendation: optional,
    scheduledAt: z.string().optional(),
  }), req.body)
  const updated = await prisma.interview.update({
    where: { id: String(req.params.id) },
    data: {
      score: body.score,
      comments: body.comments,
      recommendation: body.recommendation,
      scheduledAt: body.scheduledAt ? new Date(body.scheduledAt) : undefined,
    },
  })
  res.json(updated)
}))

router.post('/offers', recruiters, asyncHandler(async (req, res) => {
  const body = parseBody(z.object({
    applicantId: z.string(),
    salary: z.coerce.number().positive(),
    startDate: z.string(),
    expiresOn: optional,
    notes: optional,
    status: z.enum(['DRAFT', 'SENT']).default('SENT'),
  }), req.body)
  const offer = await prisma.jobOffer.create({
    data: {
      applicantId: body.applicantId,
      salary: body.salary,
      startDate: asDate(body.startDate),
      expiresOn: body.expiresOn ? asDate(body.expiresOn) : null,
      notes: body.notes,
      status: body.status,
    },
  })
  await prisma.applicant.update({ where: { id: body.applicantId }, data: { status: 'OFFERED' } })
  res.status(201).json(offer)
}))

router.post('/offers/:id/accept', requireRoles('RECRUITMENT_OFFICER', 'HR_ADMIN', 'HR_MANAGER'), asyncHandler(async (req, res) => {
  const offer = await prisma.jobOffer.findUnique({
    where: { id: String(req.params.id) },
    include: { applicant: { include: { vacancy: true } } },
  })
  if (!offer) throw new HttpError(404, 'Offer not found.')
  if (offer.status === 'ACCEPTED') throw new HttpError(400, 'This offer is already accepted.')
  const vacancy = offer.applicant.vacancy
  const employmentType = await prisma.employmentType.findFirst({ where: { name: 'Full-time' } })
  if (!employmentType) throw new HttpError(400, 'Add a Full-time employment type before hiring.')
  const employeeNo = await nextEmployeeNo()
  const probationEnd = new Date(offer.startDate)
  probationEnd.setUTCMonth(probationEnd.getUTCMonth() + 6)
  const employee = await prisma.employee.create({
    data: {
      employeeNo,
      firstName: offer.applicant.firstName,
      lastName: offer.applicant.lastName,
      gender: 'UNDISCLOSED',
      dateOfBirth: asDate('1995-01-01'),
      email: offer.applicant.email,
      phone: offer.applicant.phone,
      departmentId: vacancy.departmentId,
      jobTitleId: vacancy.jobTitleId,
      employmentTypeId: employmentType.id,
      locationId: vacancy.locationId,
      status: 'PROBATION',
      dateOfEmployment: offer.startDate,
      probationEndDate: probationEnd,
      basicSalary: offer.salary,
    },
  })
  await prisma.jobOffer.update({ where: { id: offer.id }, data: { status: 'ACCEPTED' } })
  await prisma.applicant.update({
    where: { id: offer.applicantId },
    data: { status: 'HIRED', employeeId: employee.id },
  })
  const filled = vacancy.filled + 1
  await prisma.vacancy.update({
    where: { id: vacancy.id },
    data: { filled, status: filled >= vacancy.openings ? 'FILLED' : vacancy.status, closedOn: filled >= vacancy.openings ? todayUtc() : null },
  })
  await grantLeaveBalances(employee.id, offer.startDate, employee.gender)
  await recordEvent({
    employeeId: employee.id,
    type: 'HIRE',
    title: 'Hired from recruitment',
    description: vacancy.title,
    effectiveDate: offer.startDate,
    createdById: req.user?.id,
  })
  await prisma.contract.create({
    data: {
      employeeId: employee.id,
      type: 'Employment contract',
      title: `Employment contract — ${fullName(employee)}`,
      startDate: offer.startDate,
      status: 'ACTIVE',
    },
  })
  await issueLetter({
    employeeId: employee.id,
    type: 'APPOINTMENT',
    subject: 'Appointment letter',
    issuedOn: offer.startDate,
    uploadedById: req.user?.id,
    paragraphs: [
      `I am pleased to confirm your appointment with Zentrix Corporation, following your application for ${vacancy.title}. The particulars of this appointment are set out below.`,
      `Please contact Human Resources if you need anything before your first day. We look forward to working with you.`,
    ],
  })
  const temporaryPassword = `Welcome@${employee.employeeNo.slice(-4)}`
  await prisma.user.create({
    data: {
      email: employee.email,
      firstName: employee.firstName,
      lastName: employee.lastName,
      role: 'EMPLOYEE',
      passwordHash: await hashPassword(temporaryPassword),
      employeeId: employee.id,
      departmentId: employee.departmentId,
      locationId: employee.locationId,
      mustChangePassword: true,
    },
  })
  await audit(req, 'CREATE', 'Employee', employee.id, `Converted applicant ${fullName(employee)}`)
  res.json({ employee, temporaryPassword })
}))

router.get('/metrics', asyncHandler(async (req, res) => {
  const user = requireUser(req)
  if (!isHr(user.role) && user.role !== 'RECRUITMENT_OFFICER') throw new HttpError(403, 'Recruitment metrics are restricted.')
  const vacancies = await prisma.vacancy.findMany({ include: { applicants: { include: { offers: true } } } })
  const rows = vacancies.map((vacancy) => {
    const hired = vacancy.applicants.find((applicant) => applicant.status === 'HIRED')
    const accepted = hired?.offers.find((offer) => offer.status === 'ACCEPTED')
    const opened = vacancy.openedOn ? vacancy.openedOn.getTime() : null
    const hiredOn = accepted?.updatedAt.getTime()
    const days = opened && hiredOn ? Math.max(0, Math.round((hiredOn - opened) / 86400000)) : null
    return {
      id: vacancy.id,
      title: vacancy.title,
      applicants: vacancy.applicants.length,
      cost: Number(vacancy.advertisingCost) + Number(vacancy.agencyCost),
      timeToHire: days,
      status: vacancy.status,
    }
  })
  res.json(rows)
}))

export default router
