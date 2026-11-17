import { Router } from 'express'
import { z } from 'zod'
import type { EmploymentStatus, Prisma } from '@prisma/client'
import { prisma } from '../lib/prisma.js'
import { asyncHandler, asDate, HttpError, pageParams, paged, parseBody, todayUtc } from '../lib/http.js'
import { assertEmployeeVisible, canSeeCompensation, employeeWhere, isHr, redactEmployee, requireRoles, requireUser } from '../lib/access.js'
import { audit } from '../lib/audit.js'
import { employeeInclude, fullName, grantLeaveBalances, nextEmployeeNo, recordEvent } from '../lib/employees.js'
import { issueLetter } from '../lib/letters.js'
import { hashPassword } from '../lib/auth.js'
import { notify } from '../lib/notify.js'

const router = Router()
const writers = requireRoles('HR_ADMIN', 'HR_MANAGER')
const optional = z.string().trim().optional().or(z.literal('')).transform((value) => value || undefined)

const employeeSchema = z.object({
  firstName: z.string().min(2),
  middleName: optional,
  lastName: z.string().min(2),
  gender: z.enum(['FEMALE', 'MALE', 'OTHER', 'UNDISCLOSED']),
  dateOfBirth: z.string(),
  email: z.string().email(),
  phone: z.string().min(7),
  altPhone: optional,
  address: optional,
  city: optional,
  region: optional,
  emergencyName: optional,
  emergencyPhone: optional,
  emergencyRelation: optional,
  nextOfKinName: optional,
  nextOfKinPhone: optional,
  nextOfKinRelation: optional,
  departmentId: z.string().min(1),
  jobTitleId: z.string().min(1),
  employmentTypeId: z.string().min(1),
  locationId: z.string().min(1),
  managerId: optional,
  dateOfEmployment: z.string(),
  probationEndDate: optional,
  basicSalary: z.coerce.number().nonnegative(),
  ssnitNumber: optional,
  tinNumber: optional,
  contractEndDate: optional,
  createLogin: z.boolean().optional(),
})

router.get('/', asyncHandler(async (req, res) => {
  const user = requireUser(req)
  const { page, pageSize, skip, search, sort, dir } = pageParams(req)
  const where: Prisma.EmployeeWhereInput = {
    AND: [
      employeeWhere(user),
      req.query.departmentId ? { departmentId: String(req.query.departmentId) } : {},
      req.query.locationId ? { locationId: String(req.query.locationId) } : {},
      req.query.status ? { status: String(req.query.status) as EmploymentStatus } : {},
      search
        ? {
            OR: [
              { firstName: { contains: search, mode: 'insensitive' as const } },
              { lastName: { contains: search, mode: 'insensitive' as const } },
              { employeeNo: { contains: search, mode: 'insensitive' as const } },
              { email: { contains: search, mode: 'insensitive' as const } },
            ],
          }
        : {},
    ],
  }
  const direction = dir as Prisma.SortOrder
  const orderBy: Prisma.EmployeeOrderByWithRelationInput =
    sort === 'employeeNo' ? { employeeNo: direction } :
    sort === 'dateOfEmployment' ? { dateOfEmployment: direction } :
    sort === 'basicSalary' ? { basicSalary: direction } :
    { lastName: direction }
  const [items, total] = await Promise.all([
    prisma.employee.findMany({ where, include: employeeInclude, orderBy, skip, take: pageSize }),
    prisma.employee.count({ where }),
  ])
  res.json(paged(items.map((item) => redactEmployee(user, item)), total, page, pageSize))
}))

router.post('/', writers, asyncHandler(async (req, res) => {
  const body = parseBody(employeeSchema, req.body)
  const [department, jobTitle, employmentType, location, manager] = await Promise.all([
    prisma.department.findUnique({ where: { id: body.departmentId } }),
    prisma.jobTitle.findUnique({ where: { id: body.jobTitleId } }),
    prisma.employmentType.findUnique({ where: { id: body.employmentTypeId } }),
    prisma.workLocation.findUnique({ where: { id: body.locationId } }),
    body.managerId ? prisma.employee.findUnique({ where: { id: body.managerId } }) : Promise.resolve(null),
  ])
  const missing = [
    !department && 'department',
    !jobTitle && 'job title',
    !employmentType && 'employment type',
    !location && 'work location',
  ].filter(Boolean)
  if (missing.length) {
    throw new HttpError(400, `That ${missing.join(', ')} is no longer in System settings. Choose it again, then save.`)
  }
  if (body.managerId && !manager) {
    throw new HttpError(400, 'That reporting manager is no longer on file. Clear the field and save again.')
  }
  const hireDate = asDate(body.dateOfEmployment)
  const probationEnd = body.probationEndDate ? asDate(body.probationEndDate) : null
  const employeeNo = await nextEmployeeNo()
  const status = probationEnd && probationEnd > todayUtc() ? 'PROBATION' : 'ACTIVE'
  const employee = await prisma.employee.create({
    data: {
      employeeNo,
      firstName: body.firstName,
      middleName: body.middleName,
      lastName: body.lastName,
      gender: body.gender,
      dateOfBirth: asDate(body.dateOfBirth),
      email: body.email.toLowerCase(),
      phone: body.phone,
      altPhone: body.altPhone,
      address: body.address,
      city: body.city,
      region: body.region,
      emergencyName: body.emergencyName,
      emergencyPhone: body.emergencyPhone,
      emergencyRelation: body.emergencyRelation,
      nextOfKinName: body.nextOfKinName,
      nextOfKinPhone: body.nextOfKinPhone,
      nextOfKinRelation: body.nextOfKinRelation,
      departmentId: body.departmentId,
      jobTitleId: body.jobTitleId,
      employmentTypeId: body.employmentTypeId,
      locationId: body.locationId,
      managerId: body.managerId,
      status,
      dateOfEmployment: hireDate,
      probationEndDate: probationEnd,
      basicSalary: body.basicSalary,
      ssnitNumber: body.ssnitNumber,
      tinNumber: body.tinNumber,
    },
    include: employeeInclude,
  })
  await grantLeaveBalances(employee.id, hireDate, employee.gender)
  await recordEvent({
    employeeId: employee.id,
    type: 'HIRE',
    title: 'Employee hired',
    effectiveDate: hireDate,
    createdById: req.user?.id,
    toValue: { employeeNo, status, jobTitleId: body.jobTitleId },
  })
  await prisma.contract.create({
    data: {
      employeeId: employee.id,
      type: 'Employment contract',
      title: `Employment contract — ${fullName(employee)}`,
      startDate: hireDate,
      endDate: body.contractEndDate ? asDate(body.contractEndDate) : null,
      status: 'ACTIVE',
    },
  })
  await issueLetter({
    employeeId: employee.id,
    type: 'APPOINTMENT',
    subject: 'Appointment letter',
    issuedOn: hireDate,
    uploadedById: req.user?.id,
    paragraphs: [
      `I am pleased to confirm your appointment with Zentrix HR. The particulars of this appointment are set out below.`,
      `You will serve as ${employee.jobTitle.name} in the ${employee.department.name} department, based at ${employee.location.name}, ${employee.location.city}. Your employment begins on ${hireDate.toLocaleDateString('en-GB', { day: 'numeric', month: 'long', year: 'numeric', timeZone: 'UTC' })}, and your employee number is ${employee.employeeNo}.`,
      `Please contact Human Resources if you need anything before your first day. We look forward to working with you.`,
    ],
  })
  let temporaryPassword: string | undefined
  if (body.createLogin) {
    temporaryPassword = `Welcome@${employee.employeeNo.slice(-4)}`
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
  }
  await audit(req, 'CREATE', 'Employee', employee.id, `Hired ${fullName(employee)}`)
  res.status(201).json({ ...employee, temporaryPassword })
}))

router.get('/:id', asyncHandler(async (req, res) => {
  const user = requireUser(req)
  const employee = await prisma.employee.findUnique({
    where: { id: String(req.params.id) },
    include: {
      ...employeeInclude,
      education: true,
      qualifications: true,
      skills: true,
      experience: true,
      bankAccounts: true,
      events: { orderBy: { effectiveDate: 'desc' } },
      payItems: { include: { component: true } },
      contracts: { orderBy: { startDate: 'desc' } },
      letters: { orderBy: { issuedOn: 'desc' } },
      leaveBalances: { include: { leaveType: true }, where: { year: new Date().getUTCFullYear() } },
      certifications: true,
    },
  })
  if (!employee) throw new HttpError(404, 'Employee not found.')
  assertEmployeeVisible(user, employee)
  const view = redactEmployee(user, employee)
  if (!canSeeCompensation(user, employee.id)) {
    res.json(view)
    return
  }
  res.json(view)
}))

const selfFields = ['phone', 'altPhone', 'address', 'city', 'region', 'emergencyName', 'emergencyPhone', 'emergencyRelation', 'nextOfKinName', 'nextOfKinPhone', 'nextOfKinRelation'] as const

router.patch('/:id', asyncHandler(async (req, res) => {
  const user = requireUser(req)
  const existing = await prisma.employee.findUnique({ where: { id: String(req.params.id) } })
  if (!existing) throw new HttpError(404, 'Employee not found.')
  assertEmployeeVisible(user, existing)
  const body = parseBody(employeeSchema.partial(), req.body)
  const data = isHr(user.role) ? body : Object.fromEntries(selfFields.filter((key) => key in body).map((key) => [key, body[key]]))
  if ('dateOfBirth' in data && data.dateOfBirth) data.dateOfBirth = asDate(String(data.dateOfBirth)).toISOString()
  if ('dateOfEmployment' in data && data.dateOfEmployment) data.dateOfEmployment = asDate(String(data.dateOfEmployment)).toISOString()
  if ('probationEndDate' in data && data.probationEndDate) data.probationEndDate = asDate(String(data.probationEndDate)).toISOString()
  delete (data as { contractEndDate?: string }).contractEndDate
  delete (data as { createLogin?: boolean }).createLogin
  const updated = await prisma.employee.update({
    where: { id: existing.id },
    data: data as never,
    include: employeeInclude,
  })
  await audit(req, 'UPDATE', 'Employee', existing.id, `Updated ${fullName(updated)}`)
  res.json(redactEmployee(user, updated))
}))

router.delete('/:id', writers, asyncHandler(async (req, res) => {
  const employee = await prisma.employee.findUnique({ where: { id: String(req.params.id) } })
  if (!employee) throw new HttpError(404, 'Employee not found.')
  const payslips = await prisma.payslip.count({ where: { employeeId: employee.id } })
  if (payslips > 0 || req.query.mode !== 'hard') {
    const updated = await prisma.employee.update({
      where: { id: employee.id },
      data: { status: 'INACTIVE' },
    })
    await recordEvent({
      employeeId: employee.id,
      type: 'STATUS_CHANGE',
      title: 'Employee deactivated',
      effectiveDate: todayUtc(),
      createdById: req.user?.id,
      toValue: { status: 'INACTIVE' },
    })
    const account = await prisma.user.findUnique({ where: { employeeId: employee.id } })
    if (account) await prisma.user.update({ where: { id: account.id }, data: { isActive: false } })
    await audit(req, 'UPDATE', 'Employee', employee.id, `Deactivated ${fullName(employee)}`)
    res.json(updated)
    return
  }
  await prisma.employee.delete({ where: { id: employee.id } })
  await audit(req, 'DELETE', 'Employee', employee.id, `Deleted ${fullName(employee)}`)
  res.status(204).end()
}))

router.post('/:id/actions', writers, asyncHandler(async (req, res) => {
  const body = parseBody(z.object({
    type: z.enum(['PROMOTE', 'TRANSFER', 'SUSPEND', 'REINSTATE', 'DEACTIVATE']),
    jobTitleId: optional,
    departmentId: optional,
    locationId: optional,
    managerId: optional,
    basicSalary: z.coerce.number().nonnegative().optional(),
    effectiveDate: z.string(),
    notes: optional,
  }), req.body)
  const employee = await prisma.employee.findUnique({
    where: { id: String(req.params.id) },
    include: { jobTitle: true, department: true },
  })
  if (!employee) throw new HttpError(404, 'Employee not found.')
  const effectiveDate = asDate(body.effectiveDate)
  if (body.type === 'PROMOTE') {
    if (!body.jobTitleId || body.basicSalary == null) throw new HttpError(400, 'A promotion needs a job title and salary.')
    const updated = await prisma.employee.update({
      where: { id: employee.id },
      data: { jobTitleId: body.jobTitleId, basicSalary: body.basicSalary },
      include: { jobTitle: true, department: true },
    })
    await recordEvent({
      employeeId: employee.id,
      type: 'PROMOTION',
      title: `Promoted to ${updated.jobTitle.name}`,
      description: body.notes,
      effectiveDate,
      createdById: req.user?.id,
      fromValue: { jobTitle: employee.jobTitle.name, basicSalary: Number(employee.basicSalary) },
      toValue: { jobTitle: updated.jobTitle.name, basicSalary: body.basicSalary },
    })
    await issueLetter({
      employeeId: employee.id,
      type: 'PROMOTION',
      subject: 'Promotion letter',
      issuedOn: effectiveDate,
      uploadedById: req.user?.id,
      paragraphs: [
        `You have been promoted from ${employee.jobTitle.name} to ${updated.jobTitle.name}, effective ${effectiveDate.toISOString().slice(0, 10)}.`,
        body.notes || 'Please contact Human Resources if you have questions about this change.',
      ],
    })
    await audit(req, 'UPDATE', 'Employee', employee.id, `Promoted ${fullName(employee)}`)
    res.json(updated)
    return
  }
  if (body.type === 'TRANSFER') {
    const updated = await prisma.employee.update({
      where: { id: employee.id },
      data: {
        departmentId: body.departmentId || employee.departmentId,
        locationId: body.locationId || employee.locationId,
        managerId: body.managerId === undefined ? employee.managerId : body.managerId,
      },
      include: employeeInclude,
    })
    await recordEvent({
      employeeId: employee.id,
      type: 'TRANSFER',
      title: `Transferred to ${updated.department.name}`,
      description: body.notes,
      effectiveDate,
      createdById: req.user?.id,
      fromValue: { departmentId: employee.departmentId, locationId: employee.locationId },
      toValue: { departmentId: updated.departmentId, locationId: updated.locationId },
    })
    await issueLetter({
      employeeId: employee.id,
      type: 'TRANSFER',
      subject: 'Transfer letter',
      issuedOn: effectiveDate,
      uploadedById: req.user?.id,
      paragraphs: [
        `Your duty station changes to ${updated.location.name}, ${updated.location.city}, in ${updated.department.name}, effective ${effectiveDate.toISOString().slice(0, 10)}.`,
      ],
    })
    const account = await prisma.user.findUnique({ where: { employeeId: employee.id } })
    if (account) {
      await prisma.user.update({
        where: { id: account.id },
        data: { departmentId: updated.departmentId, locationId: updated.locationId },
      })
    }
    res.json(updated)
    return
  }
  const status = body.type === 'SUSPEND' ? 'SUSPENDED' : body.type === 'REINSTATE' ? 'ACTIVE' : 'INACTIVE'
  const updated = await prisma.employee.update({ where: { id: employee.id }, data: { status } })
  await recordEvent({
    employeeId: employee.id,
    type: body.type === 'SUSPEND' ? 'SUSPENSION' : body.type === 'REINSTATE' ? 'REINSTATEMENT' : 'STATUS_CHANGE',
    title: body.type === 'SUSPEND' ? 'Suspended' : body.type === 'REINSTATE' ? 'Reinstated' : 'Deactivated',
    description: body.notes,
    effectiveDate,
    createdById: req.user?.id,
    toValue: { status },
  })
  if (status === 'INACTIVE') {
    const account = await prisma.user.findUnique({ where: { employeeId: employee.id } })
    if (account) await prisma.user.update({ where: { id: account.id }, data: { isActive: false } })
  }
  await notify({
    userIds: [(await prisma.user.findUnique({ where: { employeeId: employee.id } }))?.id],
    title: 'Employment status updated',
    body: `${fullName(employee)} is now ${status.toLowerCase()}.`,
    type: 'EMPLOYEE_STATUS',
    link: `/employees/${employee.id}`,
  })
  res.json(updated)
}))

function nested(resource: 'employeeEducation' | 'employeeQualification' | 'employeeSkill' | 'employeeExperience' | 'employeeBankAccount', schema: z.ZodTypeAny) {
  const path = resource === 'employeeEducation' ? 'education'
    : resource === 'employeeQualification' ? 'qualifications'
    : resource === 'employeeSkill' ? 'skills'
    : resource === 'employeeExperience' ? 'experience'
    : 'bank-accounts'
  router.get(`/:id/${path}`, asyncHandler(async (req, res) => {
    const user = requireUser(req)
    const employee = await prisma.employee.findUnique({ where: { id: String(req.params.id) } })
    if (!employee) throw new HttpError(404, 'Employee not found.')
    assertEmployeeVisible(user, employee)
    if (path === 'bank-accounts' && !canSeeCompensation(user, employee.id)) {
      throw new HttpError(403, 'Bank details are restricted.')
    }
    const model = prisma[resource] as unknown as { findMany: (args: unknown) => Promise<unknown[]> }
    res.json(await model.findMany({ where: { employeeId: employee.id } }))
  }))
  router.post(`/:id/${path}`, asyncHandler(async (req, res) => {
    const user = requireUser(req)
    const employee = await prisma.employee.findUnique({ where: { id: String(req.params.id) } })
    if (!employee) throw new HttpError(404, 'Employee not found.')
    if (!(isHr(user.role) || user.employeeId === employee.id)) throw new HttpError(403, 'You cannot edit this record.')
    if (path === 'bank-accounts' && !canSeeCompensation(user, employee.id)) throw new HttpError(403, 'Bank details are restricted.')
    const body = parseBody(schema, req.body) as Record<string, unknown>
    if (typeof body.startDate === 'string') body.startDate = asDate(body.startDate)
    if (typeof body.endDate === 'string') body.endDate = asDate(body.endDate)
    if (typeof body.expiresOn === 'string') body.expiresOn = asDate(body.expiresOn)
    const model = prisma[resource] as unknown as { create: (args: { data: unknown }) => Promise<unknown> }
    const created = await model.create({ data: { ...body, employeeId: employee.id } })
    res.status(201).json(created)
  }))
  router.delete(`/:id/${path}/:itemId`, writers, asyncHandler(async (req, res) => {
    const model = prisma[resource] as unknown as { delete: (args: { where: { id: string } }) => Promise<unknown> }
    await model.delete({ where: { id: String(req.params.itemId) } })
    res.status(204).end()
  }))
}

nested('employeeEducation', z.object({
  institution: z.string().min(2),
  qualification: z.string().min(2),
  field: optional,
  startYear: z.coerce.number().int().optional(),
  endYear: z.coerce.number().int().optional(),
  grade: optional,
}))
nested('employeeQualification', z.object({
  name: z.string().min(2),
  institution: optional,
  year: z.coerce.number().int().optional(),
  licenceNo: optional,
  expiresOn: optional,
}))
nested('employeeSkill', z.object({
  name: z.string().min(2),
  level: z.string().min(2),
  years: z.coerce.number().optional(),
}))
nested('employeeExperience', z.object({
  employer: z.string().min(2),
  role: z.string().min(2),
  startDate: optional,
  endDate: optional,
  description: optional,
}))
nested('employeeBankAccount', z.object({
  bankName: z.string().min(2),
  branch: optional,
  accountName: z.string().min(2),
  accountNumber: z.string().min(6),
  isPrimary: z.boolean().default(true),
}))

router.post('/:id/pay-items', requireRoles('HR_ADMIN', 'PAYROLL_OFFICER', 'HR_MANAGER'), asyncHandler(async (req, res) => {
  const body = parseBody(z.object({
    componentId: z.string(),
    amount: z.coerce.number(),
  }), req.body)
  const item = await prisma.employeePayItem.upsert({
    where: { employeeId_componentId: { employeeId: String(req.params.id), componentId: body.componentId } },
    update: { amount: body.amount },
    create: { employeeId: String(req.params.id), componentId: body.componentId, amount: body.amount },
    include: { component: true },
  })
  res.status(201).json(item)
}))

router.delete('/:id/pay-items/:itemId', requireRoles('HR_ADMIN', 'PAYROLL_OFFICER', 'HR_MANAGER'), asyncHandler(async (req, res) => {
  await prisma.employeePayItem.delete({ where: { id: String(req.params.itemId) } })
  res.status(204).end()
}))

export default router
