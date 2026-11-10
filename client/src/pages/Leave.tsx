import { useEffect, useState } from 'react'
import { api } from '../lib/api'
import { formatDate, titleCase, wholeDays } from '../lib/format'
import { employeeLabel, employeeName, employeeNo, useLookups } from '../lib/lookups'
import { can, useAuth } from '../auth'
import { Badge, Button, PageHeader, Panel, Table, statusTone } from '../components/ui'
import { SimpleForm } from './Recruitment'

type Request = {
  id: string
  startDate: string
  endDate: string
  days: string
  status: string
  reason?: string
  leaveType?: { name: string }
  employee?: { firstName: string; lastName: string; employeeNo?: string }
}
type Balance = { id: string; entitled: string; used: string; pending: string; leaveType: { name: string } }

export function LeavePage() {
  const { user } = useAuth()
  const approver = can(user?.role, ['HR_ADMIN', 'HR_MANAGER', 'DEPARTMENT_MANAGER', 'SUPERVISOR'])
  const own = user?.role === 'EMPLOYEE'
  const lookups = useLookups()
  const [requests, setRequests] = useState<Request[]>([])
  const [balances, setBalances] = useState<Balance[]>([])
  const [calendar, setCalendar] = useState<Request[]>([])
  const [open, setOpen] = useState(false)
  const [message, setMessage] = useState('')

  async function load() {
    const data = await api<{ items: Request[] }>('/time/leave/requests?pageSize=40')
    setRequests(data.items)
    if (user?.employeeId) setBalances(await api(`/time/leave/balances?employeeId=${user.employeeId}`))
    const start = new Date()
    const from = start.toISOString().slice(0, 10)
    const end = new Date(start.getTime() + 31 * 86400000).toISOString().slice(0, 10)
    setCalendar(await api(`/time/leave/calendar?from=${from}&to=${end}`))
  }
  useEffect(() => { load().catch((err) => setMessage(err.message)) }, [])

  return (
    <div>
      <PageHeader title={own ? 'My leave' : 'Leave'} text={own ? 'Your balances and the leave you have applied for. Print a form to sign.' : 'Balances update when a request is approved, rejected, or cancelled.'}>
        {user?.employeeId && (
          <a className="inline-flex items-center justify-center rounded-lg border border-[#e4ddd2] bg-white px-3.5 py-2 text-sm font-semibold text-[#24312c] hover:bg-[#fbf8f3]" href="/api/time/leave/form" target="_blank" rel="noreferrer">Print leave form</a>
        )}
        <Button onClick={() => setOpen(true)}>Apply for leave</Button>
      </PageHeader>
      {message && <p className="mb-3 text-sm text-[#9f2d2d]">{message}</p>}
      <div className="mb-4 grid gap-3 sm:grid-cols-2 lg:grid-cols-4">
        {balances.map((balance) => (
          <Panel key={balance.id}>
            <div className="text-sm font-semibold">{balance.leaveType.name}</div>
            <div className="text-2xl font-semibold text-[#1a3c34]">{wholeDays(Number(balance.entitled) - Number(balance.used) - Number(balance.pending))}</div>
            <div className="text-xs text-[#6e675e]">{wholeDays(balance.used)} used · {wholeDays(balance.pending)} pending</div>
          </Panel>
        ))}
      </div>
      <Panel>
        <Table columns={['Emp ID', 'Emp name', 'Type', 'Dates', 'Days', 'Status', '']} rows={requests.map((row) => [
          employeeNo(row.employee),
          employeeName(row.employee),
          row.leaveType?.name,
          `${formatDate(row.startDate)} – ${formatDate(row.endDate)}`,
          wholeDays(row.days),
          <Badge tone={statusTone(row.status)}>{titleCase(row.status)}</Badge>,
          <span className="flex gap-2">
            <a className="text-sm font-semibold text-[#c4622d]" href={`/api/time/leave/form?requestId=${row.id}`} target="_blank" rel="noreferrer">Print</a>
            {approver && row.status.startsWith('PENDING') && (
              <>
                <Button small onClick={async () => { await api(`/time/leave/requests/${row.id}/decision`, { method: 'POST', body: { decision: 'APPROVED' } }); await load() }}>Approve</Button>
                <Button small tone="danger" onClick={async () => { const note = window.prompt('Reason for rejection') || 'Does not meet the leave policy.'; await api(`/time/leave/requests/${row.id}/decision`, { method: 'POST', body: { decision: 'REJECTED', note } }); await load() }}>Reject</Button>
              </>
            )}
            {row.status === 'APPROVED' && row.employee && <Button small tone="ghost" onClick={async () => { await api(`/time/leave/requests/${row.id}/cancel`, { method: 'POST' }); await load() }}>Cancel</Button>}
          </span>,
        ])} />
      </Panel>
      {!own && <Panel className="mt-4">
        <h2 className="mb-2 font-semibold">Leave calendar, next 31 days</h2>
        {calendar.map((row) => <p key={row.id} className="text-sm">{row.employee?.firstName} {row.employee?.lastName} · {row.leaveType?.name} · {formatDate(row.startDate)} to {formatDate(row.endDate)}</p>)}
        {!calendar.length && <p className="text-sm text-[#6e675e]">No approved leave in this window.</p>}
      </Panel>}
      {open && <SimpleForm title="Leave application" onClose={() => setOpen(false)} onSubmit={async (body) => {
        if (!own && !body.employeeId) throw new Error('Choose an employee.')
        await api('/time/leave/requests', { method: 'POST', body: own ? body : { ...body, employeeId: body.employeeId } })
        setOpen(false); await load()
      }} fields={[
        ...(!own ? [['employeeId', 'Employee', 'select', lookups?.employees.map((item) => ({ value: item.id, label: employeeLabel(item) }))] as [string, string, string, Array<{ value: string; label: string }>] ] : []),
        ['leaveTypeId', 'Leave type', 'select', lookups?.leaveTypes.map((item) => ({ value: item.id, label: item.name }))],
        ['startDate', 'Start', 'date'],
        ['endDate', 'End', 'date'],
        ['reason', 'Reason', 'textarea'],
      ]} />}
    </div>
  )
}
