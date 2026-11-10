import { useEffect, useState } from 'react'
import { api } from '../lib/api'
import { useAuth } from '../auth'
import { Button, Field, PageHeader, Panel, Tabs, controlClass } from '../components/ui'

const lists = [
  ['Departments', '/settings/departments', ['name', 'code', 'description']],
  ['Job titles', '/settings/job-titles', ['name', 'grade', 'departmentId']],
  ['Employment types', '/settings/employment-types', ['name', 'description']],
  ['Locations', '/settings/locations', ['name', 'branch', 'city', 'region', 'address']],
  ['Leave types', '/settings/leave-types', ['name', 'code', 'daysPerYear']],
  ['Shifts', '/settings/shifts', ['name', 'startTime', 'endTime']],
  ['Payroll components', '/settings/payroll-components', ['name', 'code', 'kind']],
  ['Document categories', '/settings/document-categories', ['name']],
] as const

function AccountSettings() {
  const { user } = useAuth()
  const [currentPassword, setCurrent] = useState('')
  const [newPassword, setNext] = useState('')
  const [message, setMessage] = useState('')
  const [error, setError] = useState('')

  return (
    <div>
      <PageHeader title="Settings" text="Your sign-in details. HR keeps the company settings." />
      <div className="grid gap-4 lg:grid-cols-2">
        <Panel>
          <h2 className="mb-3 font-semibold text-[#1a3c34]">Account</h2>
          <dl className="space-y-2 text-sm">
            <div className="flex justify-between gap-3"><dt className="text-[#6e675e]">Name</dt><dd className="font-semibold">{user?.firstName} {user?.lastName}</dd></div>
            <div className="flex justify-between gap-3"><dt className="text-[#6e675e]">Email</dt><dd className="font-semibold">{user?.email || '—'}</dd></div>
            <div className="flex justify-between gap-3"><dt className="text-[#6e675e]">Role</dt><dd className="font-semibold">Employee</dd></div>
          </dl>
        </Panel>
        <Panel>
          <h2 className="mb-1 font-semibold text-[#1a3c34]">Password</h2>
          <p className="mb-3 text-sm text-[#6e675e]">Use at least 8 characters, including a letter and a number.</p>
          <form className="space-y-3" onSubmit={async (event) => {
            event.preventDefault()
            setError('')
            setMessage('')
            try {
              await api('/auth/change-password', { method: 'POST', body: { currentPassword, newPassword } })
              setCurrent('')
              setNext('')
              setMessage('Password updated.')
            } catch (err) {
              setError(err instanceof Error ? err.message : 'Could not update the password')
            }
          }}>
            <Field label="Current password"><input className={controlClass} type="password" value={currentPassword} onChange={(event) => setCurrent(event.target.value)} autoComplete="current-password" required /></Field>
            <Field label="New password"><input className={controlClass} type="password" value={newPassword} onChange={(event) => setNext(event.target.value)} autoComplete="new-password" required /></Field>
            {error && <p className="text-sm text-[#9f2d2d]">{error}</p>}
            {message && <p className="text-sm text-[#1a3c34]">{message}</p>}
            <Button type="submit">Update password</Button>
          </form>
        </Panel>
      </div>
    </div>
  )
}

export function SettingsPage() {
  const { user } = useAuth()
  if (user?.role === 'EMPLOYEE') return <AccountSettings />
  return <HrSettingsPage />
}

function HrSettingsPage() {
  const { user } = useAuth()
  const [tab, setTab] = useState('Company')
  const [company, setCompany] = useState<Record<string, string>>({})
  const [message, setMessage] = useState('')
  const [rows, setRows] = useState<Array<Record<string, string>>>([])
  const [draft, setDraft] = useState<Record<string, string>>({})
  const [workflows, setWorkflows] = useState<Array<{ id: string; key: string; label: string; steps: string[] }>>([])
  const [notices, setNotices] = useState<Array<{ id: string; label: string; inApp: boolean; email: boolean }>>([])
  const [departments, setDepartments] = useState<Array<{ id: string; name: string }>>([])
  const [hours, setHours] = useState({ name: 'Head office calendar', workDays: [1, 2, 3, 4, 5], startTime: '08:00', endTime: '17:00', hoursPerDay: 8, hoursPerMonth: 176, graceMinutes: 15, overtimeWeekday: 1.5, overtimeWeekend: 2, latitude: '', longitude: '', radiusMeters: 200 })
  const [clockIps, setClockIps] = useState<Array<{ address: string; port: number; path: string }>>([])

  useEffect(() => {
    api<Record<string, string>>('/settings/company').then(setCompany).catch(() => undefined)
    api<{ items: Array<{ id: string; name: string }> }>('/settings/departments?pageSize=100').then((data) => setDepartments(data.items || [])).catch(() => undefined)
    api<typeof hours>('/settings/calendar').then((row) => row && setHours({
      ...row,
      startTime: String(row.startTime).slice(0, 5),
      endTime: String(row.endTime).slice(0, 5),
      graceMinutes: Number(row.graceMinutes),
      hoursPerDay: Number(row.hoursPerDay),
      hoursPerMonth: Number(row.hoursPerMonth),
      overtimeWeekday: Number(row.overtimeWeekday),
      overtimeWeekend: Number(row.overtimeWeekend),
      workDays: row.workDays,
      latitude: row.latitude == null ? '' : String(row.latitude),
      longitude: row.longitude == null ? '' : String(row.longitude),
      radiusMeters: Number(row.radiusMeters || 200),
    })).catch(() => undefined)
  }, [])

  useEffect(() => {
    if (tab !== 'Working hours' || user?.role !== 'SUPER_ADMIN') return
    api<{ addresses: string[]; port: number; path: string }>('/time/clock-address').then((row) => {
      setClockIps(row.addresses.map((address) => ({ address, port: row.port, path: row.path })))
    }).catch(() => undefined)
  }, [tab, user?.role])

  useEffect(() => {
    const match = lists.find((item) => item[0] === tab)
    if (!match) return
    api<{ items?: Array<Record<string, string>> } | Array<Record<string, string>>>(match[1]).then((data) => {
      setRows(Array.isArray(data) ? data : data.items || [])
    }).catch((err) => setMessage(err.message))
  }, [tab])

  useEffect(() => {
    if (tab === 'Workflows') api<typeof workflows>('/settings/workflows').then(setWorkflows).catch(() => undefined)
    if (tab === 'Notifications') api<typeof notices>('/settings/notification-settings').then(setNotices).catch(() => undefined)
  }, [tab])

  const current = lists.find((item) => item[0] === tab)

  return (
    <div>
      <PageHeader title="System settings" text="Company profile, organisation structure, leave rules, payroll components, workflows, and access." />
      <Tabs tabs={['Company', 'Working hours', ...lists.map((item) => item[0]), 'Workflows', 'Notifications', ...(user?.role === 'SUPER_ADMIN' ? ['Backup'] : [])]} value={tab} onChange={setTab} />
      {message && <p className="mb-3 text-sm text-[#9f2d2d]">{message}</p>}
      {tab === 'Company' && (
        <Panel>
          <form className="grid gap-3 sm:grid-cols-2" onSubmit={async (event) => {
            event.preventDefault()
            await api('/settings/company', { method: 'PUT', body: company })
            setMessage('Company profile saved.')
          }}>
            {['name', 'legalName', 'tin', 'ssnitEmployerNo', 'email', 'phone', 'address', 'city', 'country'].map((key) => (
              <Field key={key} label={key}><input className={controlClass} value={company[key] || ''} onChange={(event) => setCompany({ ...company, [key]: event.target.value })} /></Field>
            ))}
            <div className="sm:col-span-2"><Button type="submit">Save company</Button></div>
          </form>
        </Panel>
      )}
      {tab === 'Working hours' && (
        <Panel>
          <p className="mb-4 max-w-2xl text-sm text-[#6e675e]">The fingerprint clock records attendance. Reporting time is when staff are expected to scan in. Closing time is when the day ends. A scan after the grace period is late, and a scan out before closing time is an early departure.</p>
          <form className="grid gap-3 sm:grid-cols-3" onSubmit={async (event) => {
            event.preventDefault()
            const saved = await api<typeof hours>('/settings/calendar', {
              method: 'PUT',
              body: {
                ...hours,
                graceMinutes: Number(hours.graceMinutes),
                latitude: hours.latitude === '' ? null : Number(hours.latitude),
                longitude: hours.longitude === '' ? null : Number(hours.longitude),
                radiusMeters: Number(hours.radiusMeters),
              },
            })
            setHours({
              ...hours,
              ...saved,
              startTime: String(saved.startTime).slice(0, 5),
              endTime: String(saved.endTime).slice(0, 5),
              latitude: saved.latitude == null ? '' : String(saved.latitude),
              longitude: saved.longitude == null ? '' : String(saved.longitude),
            })
            setMessage('Working hours saved. New attendance is matched to these times.')
          }}>
            <Field label="Reporting time"><input className={controlClass} type="time" value={hours.startTime} onChange={(event) => setHours({ ...hours, startTime: event.target.value })} required /></Field>
            <Field label="Closing time"><input className={controlClass} type="time" value={hours.endTime} onChange={(event) => setHours({ ...hours, endTime: event.target.value })} required /></Field>
            <Field label="Grace minutes"><input className={controlClass} type="number" min={0} value={hours.graceMinutes} onChange={(event) => setHours({ ...hours, graceMinutes: Number(event.target.value) })} required /></Field>
            <div className="sm:col-span-3"><Button type="submit">Save working hours</Button></div>
          </form>
          {user?.role === 'SUPER_ADMIN' && (
            <div className="mt-6 border-t border-[#e6dfd4] pt-4">
              <h2 className="font-semibold text-[#1a3c34]">Fingerprint clock address</h2>
              <p className="mt-1 max-w-2xl text-sm text-[#6e675e]">Point the clock at one of these addresses on the office network. The first scan of the day is clock-in. The next is clock-out.</p>
              <ul className="mt-3 space-y-1 text-sm">
                {clockIps.map((item) => <li key={item.address} className="font-medium text-[#1a3c34]">http://{item.address}:{item.port}{item.path}</li>)}
                {!clockIps.length && <li className="text-[#6e675e]">No office network address is available on this computer.</li>}
              </ul>
            </div>
          )}
        </Panel>
      )}
      {current && (
        <Panel>
          <form className="mb-4 grid gap-2 sm:grid-cols-3" onSubmit={async (event) => {
            event.preventDefault()
            const body = { ...draft }
            if (body.daysPerYear) Object.assign(body, { daysPerYear: Number(body.daysPerYear), countMode: 'WORKING_DAYS', affectsBalance: true, paid: true })
            if (tab === 'Shifts') Object.assign(body, { workDays: [1, 2, 3, 4, 5], graceMinutes: 15 })
            if (tab === 'Payroll components') Object.assign(body, { kind: body.kind || 'ALLOWANCE', taxable: true })
            await api(current[1], { method: 'POST', body })
            setDraft({})
            const data = await api<{ items: Array<Record<string, string>> }>(current[1])
            setRows(data.items || [])
          }}>
            {current[2].map((field) => field === 'departmentId' ? (
              <select key={field} className={controlClass} value={draft.departmentId || ''} onChange={(event) => setDraft({ ...draft, departmentId: event.target.value })}>
                <option value="">Department</option>
                {departments.map((department) => <option key={department.id} value={department.id}>{department.name}</option>)}
              </select>
            ) : (
              <input key={field} className={controlClass} type={field === 'startTime' || field === 'endTime' ? 'time' : 'text'} placeholder={field === 'startTime' ? 'Reporting time' : field === 'endTime' ? 'Closing time' : field === 'daysPerYear' ? 'Days per year' : field} value={draft[field] || ''} onChange={(event) => setDraft({ ...draft, [field]: event.target.value })} />
            ))}
            <Button type="submit">Add</Button>
          </form>
          <ul className="space-y-2 text-sm">
            {rows.map((row) => (
              <li key={row.id} className="flex items-center justify-between gap-3 border-b border-[#efeae2] py-2">
                <span>{current[2].map((field) => field === 'departmentId' ? departments.find((department) => department.id === row.departmentId)?.name : row[field]).filter(Boolean).join(' · ')}</span>
                <Button small tone="danger" onClick={async () => {
                  if (!window.confirm('Remove this record?')) return
                  await api(`${current[1]}/${row.id}`, { method: 'DELETE' })
                  setRows(rows.filter((item) => item.id !== row.id))
                }}>Delete</Button>
              </li>
            ))}
          </ul>
        </Panel>
      )}
      {tab === 'Workflows' && (
        <Panel>
          {workflows.map((workflow) => (
            <form key={workflow.id} className="mb-3 flex flex-wrap items-end gap-2" onSubmit={async (event) => {
              event.preventDefault()
              const form = new FormData(event.currentTarget)
              await api(`/settings/workflows/${workflow.key}`, { method: 'PUT', body: { label: workflow.label, steps: String(form.get('steps')).split(',').map((step) => step.trim()) } })
              setMessage(`${workflow.label} updated.`)
            }}>
              <div className="min-w-40 text-sm font-semibold">{workflow.label}</div>
              <input className={`${controlClass} max-w-sm`} name="steps" defaultValue={workflow.steps.join(', ')} />
              <Button type="submit" small>Save</Button>
            </form>
          ))}
        </Panel>
      )}
      {tab === 'Notifications' && (
        <Panel>
          {notices.map((notice) => (
            <label key={notice.id} className="flex items-center justify-between border-b border-[#efeae2] py-2 text-sm">
              <span>{notice.label}</span>
              <span className="flex gap-3">
                <span><input type="checkbox" checked={notice.inApp} onChange={async (event) => {
                  const next = { ...notice, inApp: event.target.checked }
                  await api(`/settings/notification-settings/${notice.id}`, { method: 'PATCH', body: { inApp: next.inApp, email: next.email } })
                  setNotices(notices.map((item) => item.id === notice.id ? next : item))
                }} /> In app</span>
                <span><input type="checkbox" checked={notice.email} onChange={async (event) => {
                  const next = { ...notice, email: event.target.checked }
                  await api(`/settings/notification-settings/${notice.id}`, { method: 'PATCH', body: { inApp: next.inApp, email: next.email } })
                  setNotices(notices.map((item) => item.id === notice.id ? next : item))
                }} /> Email</span>
              </span>
            </label>
          ))}
        </Panel>
      )}
      {tab === 'Backup' && user?.role === 'SUPER_ADMIN' && (
        <Panel>
          <p className="mb-3 text-sm text-[#6e675e]">Writes a JSON snapshot of company, employee, leave, and payroll data to the server backup folder. For a full database copy, use pg_dump against DATABASE_URL.</p>
          <Button onClick={async () => {
            const result = await api<{ file: string; counts: { employees: number } }>('/settings/backup')
            setMessage(`Saved ${result.file} (${result.counts.employees} employees).`)
          }}>Create backup</Button>
        </Panel>
      )}
    </div>
  )
}
