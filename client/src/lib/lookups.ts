import { useEffect, useState } from 'react'
import { api } from './api'

export type Lookups = {
  departments: Array<{ id: string; name: string; code?: string }>
  jobTitles: Array<{ id: string; name: string; departmentId?: string | null }>
  employmentTypes: Array<{ id: string; name: string }>
  locations: Array<{ id: string; name: string; city: string }>
  leaveTypes: Array<{ id: string; name: string; code: string }>
  shifts: Array<{ id: string; name: string; startTime: string; endTime: string }>
  components: Array<{ id: string; name: string; kind: string }>
  categories: Array<{ id: string; name: string }>
  employees: Array<{ id: string; firstName: string; lastName: string; employeeNo: string; departmentId: string }>
}

export function useLookups() {
  const [lookups, setLookups] = useState<Lookups | null>(null)
  useEffect(() => {
    api<Lookups>('/settings/lookups').then(setLookups).catch(() => undefined)
  }, [])
  return lookups
}

export function employeeName(person?: { firstName?: string; lastName?: string } | null) {
  if (!person?.firstName && !person?.lastName) return '—'
  return [person.firstName, person.lastName].filter(Boolean).join(' ')
}

export function employeeNo(person?: { employeeNo?: string | null } | null) {
  return person?.employeeNo || '—'
}

export function employeeLabel(person?: { firstName: string; lastName: string; employeeNo?: string }) {
  if (!person) return '—'
  return person.employeeNo ? `${person.employeeNo} · ${person.firstName} ${person.lastName}` : `${person.firstName} ${person.lastName}`
}
