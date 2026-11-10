import type { Gender, Prisma } from '@prisma/client'
import { prisma } from './prisma.js'

export function fullName(person: { firstName: string; middleName?: string | null; lastName: string }) {
  return [person.firstName, person.middleName, person.lastName].filter(Boolean).join(' ')
}

export async function nextEmployeeNo() {
  const company = await prisma.company.findFirst()
  if (!company) throw new Error('Company is not configured')
  const number = `EMP-${String(company.nextEmployeeNo).padStart(4, '0')}`
  await prisma.company.update({
    where: { id: company.id },
    data: { nextEmployeeNo: { increment: 1 } },
  })
  return number
}

export async function grantLeaveBalances(employeeId: string, _hireDate: Date, gender: Gender) {
  const types = await prisma.leaveType.findMany()
  const year = new Date().getUTCFullYear()
  for (const type of types) {
    if (type.gender && type.gender !== gender) continue
    const entitled = type.affectsBalance ? Math.round(Number(type.daysPerYear)) : 0
    await prisma.leaveBalance.upsert({
      where: { employeeId_leaveTypeId_year: { employeeId, leaveTypeId: type.id, year } },
      update: {},
      create: { employeeId, leaveTypeId: type.id, year, entitled, used: 0, pending: 0 },
    })
  }
}

export async function ensureCurrentBalances(employeeId: string) {
  const employee = await prisma.employee.findUnique({ where: { id: employeeId } })
  if (!employee) return
  await grantLeaveBalances(employee.id, employee.dateOfEmployment, employee.gender)
}

export const employeeInclude = {
  department: true,
  jobTitle: true,
  employmentType: true,
  location: true,
  manager: { select: { id: true, firstName: true, lastName: true, employeeNo: true } },
} satisfies Prisma.EmployeeInclude

export async function recordEvent(input: {
  employeeId: string
  type: Prisma.EmployeeEventCreateInput['type']
  title: string
  description?: string
  fromValue?: Prisma.InputJsonValue
  toValue?: Prisma.InputJsonValue
  effectiveDate: Date
  createdById?: string
}) {
  await prisma.employeeEvent.create({ data: input })
}
