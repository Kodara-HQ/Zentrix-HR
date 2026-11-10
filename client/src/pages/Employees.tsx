import { useEffect, useState } from 'react'
import { useNavigate, useSearchParams } from 'react-router-dom'
import { ApiError, api } from '../lib/api'
import { formatDate, fullName, money, titleCase } from '../lib/format'
import { useLookups, type Lookups } from '../lib/lookups'
import { can, useAuth } from '../auth'
import { Badge, Button, ErrorNote, Field, Modal, PageHeader, SearchSelect, controlClass, statusTone } from '../components/ui'

type Employee = {
  id: string
  employeeNo: string
  firstName: string
  lastName: string
  gender: string
  status: string
  email: string
  phone: string
  dateOfEmployment: string
  basicSalary?: number
  department?: { name: string }
  jobTitle?: { name: string }
  location?: { name: string; city: string }
}

const blank = {
  firstName: '', lastName: '', gender: 'FEMALE', dateOfBirth: '', email: '', phone: '',
  address: '', city: 'Accra', region: 'Greater Accra', departmentId: '', jobTitleId: '',
  employmentTypeId: '', locationId: '', managerId: '', dateOfEmployment: '', probationEndDate: '',
  basicSalary: '', contractEndDate: '', createLogin: true,
  emergencyName: '', emergencyPhone: '', emergencyRelation: 'Spouse',
  nextOfKinName: '', nextOfKinPhone: '', nextOfKinRelation: 'Parent',
}

export function EmployeesPage() {
  const navigate = useNavigate()
  const [params] = useSearchParams()
  const { user } = useAuth()
  const sharedLookups = useLookups()
  const [freshLookups, setFreshLookups] = useState<Lookups | null>(null)
  const lookups = freshLookups || sharedLookups
  const [search, setSearch] = useState(params.get('search') || '')
  const [departmentId, setDepartment] = useState('')
  const [status, setStatus] = useState('')
  const [rows, setRows] = useState<Employee[]>([])
  const [total, setTotal] = useState(0)
  const [page, setPage] = useState(1)
  const [error, setError] = useState('')
  const [open, setOpen] = useState(false)
  const [form, setForm] = useState(blank)
  const [message, setMessage] = useState('')

  async function load(nextPage = page) {
    const query = new URLSearchParams({ page: String(nextPage), pageSize: '12', search })
    if (departmentId) query.set('departmentId', departmentId)
    if (status) query.set('status', status)
    const data = await api<{ items: Employee[]; total: number }>(`/employees?${query}`)
    setRows(data.items)
    setTotal(data.total)
  }

  useEffect(() => { load(1).catch((err) => setError(err.message)) }, [departmentId, status])

  const writer = can(user?.role, ['HR_ADMIN', 'HR_MANAGER'])

  return (
    <div>
      <PageHeader title="Employees" text="One record for identity, job, pay, qualifications, and the history of every change.">
        {writer && <Button onClick={() => {
          setForm(blank)
          setMessage('')
          setOpen(true)
          api<Lookups>('/settings/lookups').then(setFreshLookups).catch((err) => setMessage(err instanceof Error ? err.message : 'Could not load the employee lists.'))
        }}>Add employee</Button>}
      </PageHeader>
      <div className="mb-3 flex flex-wrap gap-2">
        <input className={`${controlClass} max-w-xs`} value={search} placeholder="Name, number, or email" onChange={(event) => setSearch(event.target.value)} onKeyDown={(event) => { if (event.key === 'Enter') load(1) }} />
        <SearchSelect className="w-[220px]" value={departmentId} onChange={setDepartment} placeholder="All departments" options={lookups?.departments.map((item) => ({ value: item.id, label: item.name })) || []} />
        <SearchSelect className="w-[180px]" value={status} onChange={setStatus} placeholder="All statuses" options={['PROBATION', 'ACTIVE', 'NOTICE', 'SUSPENDED', 'INACTIVE', 'RESIGNED', 'RETIRED', 'TERMINATED'].map((item) => ({ value: item, label: titleCase(item) }))} />
        <Button tone="ghost" onClick={() => load(1)}>Search</Button>
      </div>
      <ErrorNote text={error} />
      <div className="overflow-auto rounded-2xl border border-[#e6dfd4] bg-white">
        <table className="w-full min-w-[760px] text-left text-sm">
          <thead className="text-xs uppercase tracking-wide text-[#6e675e]">
            <tr>{['Emp ID', 'Emp name', 'Job', 'Location', 'Hired', 'Status', 'Salary'].map((heading) => <th key={heading} className="px-3 py-2">{heading}</th>)}</tr>
          </thead>
          <tbody>
            {rows.map((row) => (
              <tr key={row.id} className="cursor-pointer border-t border-[#efeae2] hover:bg-[#f3efe6]" onClick={() => navigate(`/employees/${row.id}`)}>
                <td className="px-3 py-2.5">{row.employeeNo}</td>
                <td className="px-3 py-2.5">
                  <div className="font-semibold">{fullName(row)}</div>
                  <div className="text-xs text-[#6e675e]">{row.email}</div>
                </td>
                <td className="px-3 py-2.5">{row.jobTitle?.name}<div className="text-xs text-[#6e675e]">{row.department?.name}</div></td>
                <td className="px-3 py-2.5">{row.location?.city}</td>
                <td className="px-3 py-2.5">{formatDate(row.dateOfEmployment)}</td>
                <td className="px-3 py-2.5"><Badge tone={statusTone(row.status)}>{titleCase(row.status)}</Badge></td>
                <td className="px-3 py-2.5">{row.basicSalary == null ? 'Restricted' : money(row.basicSalary)}</td>
              </tr>
            ))}
          </tbody>
        </table>
      </div>
      <div className="mt-3 flex items-center justify-between text-sm text-[#6e675e]">
        <span>{total} employees</span>
        <div className="flex gap-2">
          <Button small tone="ghost" disabled={page <= 1} onClick={() => { const next = page - 1; setPage(next); load(next) }}>Previous</Button>
          <Button small tone="ghost" disabled={page * 12 >= total} onClick={() => { const next = page + 1; setPage(next); load(next) }}>Next</Button>
        </div>
      </div>
      {open && (
        <Modal title="New employee" wide onClose={() => setOpen(false)}>
          <form
            className="grid gap-3 sm:grid-cols-2"
            onSubmit={async (event) => {
              event.preventDefault()
              setMessage('')
              try {
                const created = await api<{ temporaryPassword?: string }>('/employees', {
                  method: 'POST',
                  body: { ...form, basicSalary: Number(form.basicSalary), managerId: form.managerId || undefined },
                })
                setMessage(created.temporaryPassword ? `Record created. Temporary password: ${created.temporaryPassword}` : 'Record created.')
                setOpen(false)
                await load(1)
              } catch (err) {
                const details = err instanceof ApiError && Array.isArray(err.details)
                  ? (err.details as Array<{ message?: string }>).map((item) => item.message).filter(Boolean).join(' ')
                  : ''
                setMessage(details || (err instanceof Error ? err.message : 'Could not save'))
              }
            }}
          >
            {([
              ['firstName', 'First name'], ['lastName', 'Last name'], ['email', 'Email'], ['phone', 'Phone'],
              ['dateOfBirth', 'Date of birth'], ['dateOfEmployment', 'Employment date'], ['probationEndDate', 'Probation ends'],
              ['basicSalary', 'Basic salary (GHS)'], ['contractEndDate', 'Contract end'],
            ] as const).map(([key, label]) => (
              <Field key={key} label={label}>
                <input className={controlClass} type={key.includes('date') || key.includes('Date') || key === 'probationEndDate' ? 'date' : key === 'basicSalary' ? 'number' : 'text'} value={form[key]} onChange={(event) => setForm((current) => ({ ...current, [key]: event.target.value }))} required={['firstName', 'lastName', 'email', 'phone', 'dateOfBirth', 'dateOfEmployment', 'basicSalary'].includes(key)} />
              </Field>
            ))}
            <Field label="Gender">
              <SearchSelect allowEmpty={false} value={form.gender} onChange={(gender) => setForm((current) => ({ ...current, gender }))} options={['FEMALE', 'MALE', 'OTHER', 'UNDISCLOSED'].map((item) => ({ value: item, label: titleCase(item) }))} />
            </Field>
            <Field label="Department">
              <SearchSelect required value={form.departmentId} onChange={(departmentId) => setForm((current) => ({ ...current, departmentId }))} options={lookups?.departments.map((item) => ({ value: item.id, label: item.name })) || []} />
            </Field>
            <Field label="Job title">
              <SearchSelect required value={form.jobTitleId} onChange={(jobTitleId) => setForm((current) => ({ ...current, jobTitleId }))} options={lookups?.jobTitles.map((item) => ({ value: item.id, label: item.name })) || []} />
            </Field>
            <Field label="Employment type">
              <SearchSelect required value={form.employmentTypeId} onChange={(employmentTypeId) => setForm((current) => ({ ...current, employmentTypeId }))} options={lookups?.employmentTypes.map((item) => ({ value: item.id, label: item.name })) || []} />
            </Field>
            <Field label="Location">
              <SearchSelect required value={form.locationId} onChange={(locationId) => setForm((current) => ({ ...current, locationId }))} options={lookups?.locations.map((item) => ({ value: item.id, label: `${item.name}, ${item.city}` })) || []} />
              {lookups && !lookups.locations.length && <p className="mt-1 text-xs text-[#9f2d2d]">Add a work location under System settings before saving an employee.</p>}
            </Field>
            <Field label="Reporting manager">
              <SearchSelect value={form.managerId} onChange={(managerId) => setForm((current) => ({ ...current, managerId }))} placeholder="None" options={lookups?.employees.map((item) => ({ value: item.id, label: `${item.firstName} ${item.lastName}` })) || []} />
            </Field>
            <label className="flex items-center gap-2 text-sm sm:col-span-2">
              <input type="checkbox" checked={form.createLogin} onChange={(event) => setForm((current) => ({ ...current, createLogin: event.target.checked }))} />
              Create a sign-in and show the temporary password once
            </label>
            {message && <div className="sm:col-span-2"><ErrorNote text={message} /></div>}
            <div className="sm:col-span-2"><Button type="submit">Save employee</Button></div>
          </form>
        </Modal>
      )}
      {message && !open && <p className="mt-3 text-sm text-[#c4622d]">{message}</p>}
    </div>
  )
}
