import type { Employee, Role } from '@prisma/client'
import type { NextFunction, Request, Response } from 'express'
import { HttpError } from './http.js'

export type AuthUser = {
  id: string
  email: string | null
  role: Role
  firstName: string
  lastName: string
  employeeId: string | null
  departmentId: string | null
  locationId: string | null
  mustChangePassword: boolean
}

export function requireUser(req: Request) {
  if (!req.user) throw new HttpError(401, 'Sign in required.')
  return req.user
}

export function requireRoles(...roles: Role[]) {
  return (req: Request, _res: Response, next: NextFunction) => {
    const user = requireUser(req)
    if (user.role === 'SUPER_ADMIN' || roles.includes(user.role)) {
      next()
      return
    }
    next(new HttpError(403, 'You do not have access to this action.'))
  }
}

export function isHr(role: Role) {
  return role === 'SUPER_ADMIN' || role === 'HR_ADMIN' || role === 'HR_MANAGER'
}

export function canSeeCompensation(user: AuthUser, employeeId?: string | null) {
  if (user.role === 'SUPER_ADMIN' || user.role === 'HR_ADMIN' || user.role === 'HR_MANAGER' || user.role === 'PAYROLL_OFFICER') {
    return true
  }
  return Boolean(employeeId && user.employeeId === employeeId)
}

export function canSeeDiscipline(user: AuthUser) {
  return isHr(user.role)
}

export function employeeWhere(user: AuthUser) {
  if (
    user.role === 'SUPER_ADMIN' ||
    user.role === 'HR_ADMIN' ||
    user.role === 'HR_MANAGER' ||
    user.role === 'PAYROLL_OFFICER'
  ) {
    return {}
  }
  if (user.role === 'DEPARTMENT_MANAGER' && user.departmentId) {
    return { departmentId: user.departmentId }
  }
  if (user.role === 'SUPERVISOR' && user.employeeId) {
    return { OR: [{ managerId: user.employeeId }, { id: user.employeeId }] }
  }
  if (user.role === 'EMPLOYEE' && user.employeeId) {
    return { id: user.employeeId }
  }
  return { id: '__none__' }
}

export function assertEmployeeVisible(user: AuthUser, employee: Pick<Employee, 'id' | 'departmentId' | 'managerId'>) {
  if (
    user.role === 'SUPER_ADMIN' ||
    user.role === 'HR_ADMIN' ||
    user.role === 'HR_MANAGER' ||
    user.role === 'PAYROLL_OFFICER'
  ) {
    return
  }
  if (user.role === 'DEPARTMENT_MANAGER' && user.departmentId === employee.departmentId) return
  if (user.role === 'SUPERVISOR' && (employee.managerId === user.employeeId || employee.id === user.employeeId)) return
  if (user.role === 'EMPLOYEE' && employee.id === user.employeeId) return
  throw new HttpError(403, 'You do not have access to this employee.')
}

export function canApproveAsManager(user: AuthUser, employee: Pick<Employee, 'id' | 'departmentId' | 'managerId'>) {
  if (user.employeeId && user.employeeId === employee.id) return false
  if (isHr(user.role)) return true
  if (user.role === 'DEPARTMENT_MANAGER' && user.departmentId === employee.departmentId) return true
  if (user.role === 'SUPERVISOR' && user.employeeId && employee.managerId === user.employeeId) return true
  return false
}

const SENSITIVE = ['basicSalary', 'ssnitNumber', 'tinNumber', 'bankAccounts', 'payItems', 'loans', 'payslips'] as const

export function redactEmployee<T extends Record<string, unknown>>(user: AuthUser, employee: T) {
  const id = String(employee.id || '')
  if (canSeeCompensation(user, id)) return employee
  const copy: Record<string, unknown> = { ...employee }
  for (const key of SENSITIVE) delete copy[key]
  return copy as T
}
