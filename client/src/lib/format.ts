export function formatDate(value?: string | null) {
  if (!value) return '—'
  const [year, month, day] = value.slice(0, 10).split('-')
  const months = ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec']
  if (!year || !month || !day) return value
  return `${Number(day)} ${months[Number(month) - 1]} ${year}`
}

export function formatTime(value?: string | null) {
  if (!value) return '—'
  const date = new Date(value)
  if (Number.isNaN(date.getTime())) return '—'
  return `${String(date.getUTCHours()).padStart(2, '0')}:${String(date.getUTCMinutes()).padStart(2, '0')}`
}

export function wholeDays(value?: number | string | null) {
  const amount = Number(value || 0)
  if (!Number.isFinite(amount)) return '0'
  return String(Math.round(amount))
}

export function money(value?: number | string | null) {
  const amount = Number(value || 0)
  return new Intl.NumberFormat('en-GH', { style: 'currency', currency: 'GHS', maximumFractionDigits: 2 }).format(amount)
}

export function titleCase(value?: string | null) {
  if (!value) return '—'
  return value.toLowerCase().replaceAll('_', ' ').replace(/\b\w/g, (letter) => letter.toUpperCase())
}

export function fullName(person?: { firstName?: string; lastName?: string; middleName?: string | null } | null) {
  if (!person) return '—'
  return [person.firstName, person.middleName, person.lastName].filter(Boolean).join(' ')
}

export const roleLabel: Record<string, string> = {
  SUPER_ADMIN: 'Super administrator',
  HR_ADMIN: 'HR administrator',
  HR_MANAGER: 'HR manager',
  DEPARTMENT_MANAGER: 'Department manager',
  SUPERVISOR: 'Supervisor',
  PAYROLL_OFFICER: 'Payroll officer',
  EMPLOYEE: 'Employee',
  RECRUITMENT_OFFICER: 'Recruitment officer',
}
