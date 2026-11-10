import { useEffect, useState } from 'react'
import { api } from '../lib/api'
import { formatDate, formatTime, titleCase } from '../lib/format'
import { employeeLabel, employeeName, employeeNo, useLookups } from '../lib/lookups'
import { can, useAuth } from '../auth'
import { Badge, Button, ErrorNote, PageHeader, Panel, Table, statusTone } from '../components/ui'
import { SimpleForm } from './Recruitment'

type Row = {
  id: string
  date: string
  status: string
  lateMinutes: number
  source: string
  clockIn?: string | null
  clockOut?: string | null
  clockInIp?: string | null
  clockOutIp?: string | null
  employee?: { firstName: string; lastName: string; employeeNo: string }
}

function punch(date: string, time?: string) {
  if (!date || !time) return undefined
  return `${date}T${time.slice(0, 5)}:00.000Z`
}

export function AttendancePage() {
  const { user } = useAuth()
  const lookups = useLookups()
  const [rows, setRows] = useState<Row[]>([])
  const [corrections, setCorrections] = useState<Array<Record<string, unknown>>>([])
  const [overtime, setOvertime] = useState<Array<Record<string, unknown>>>([])
  const [shifts, setShifts] = useState<Array<Record<string, unknown>>>([])
  const [message, setMessage] = useState('')
  const [open, setOpen] = useState('')
  const [hours, setHours] = useState<{ startTime: string; endTime: string; graceMinutes: number } | null>(null)
  const [clockIps, setClockIps] = useState<Array<{ address: string; port: number; path: string }>>([])
  const admin = user?.role === 'SUPER_ADMIN'
  const desk = can(user?.role, ['HR_ADMIN', 'HR_MANAGER', 'DEPARTMENT_MANAGER', 'SUPERVISOR'])
  const own = user?.role === 'EMPLOYEE'

  async function load() {
    const data = await api<{ items: Row[] }>('/time/attendance?pageSize=40')
    setRows(data.items)
    setCorrections(await api('/time/corrections'))
    setOvertime(await api('/time/overtime'))
    if (user?.role !== 'EMPLOYEE') setShifts(await api('/time/shifts'))
    api<{ startTime: string; endTime: string; graceMinutes: number }>('/settings/calendar').then((row) => row && setHours({
      startTime: String(row.startTime).slice(0, 5),
      endTime: String(row.endTime).slice(0, 5),
      graceMinutes: Number(row.graceMinutes),
    })).catch(() => undefined)
    if (user?.role === 'SUPER_ADMIN') {
      api<{ addresses: string[]; port: number; path: string }>('/time/clock-address').then((row) => {
        setClockIps(row.addresses.map((address) => ({ address, port: row.port, path: row.path })))
      }).catch(() => undefined)
    }
  }
  useEffect(() => { load().catch((err) => setMessage(err.message)) }, [user?.role])

  return (
    <div>
      <PageHeader title={own ? 'My attendance' : 'Attendance'} text={own ? 'Clock-in and clock-out come from the fingerprint clock. Ask for a correction if a scan was missed.' : hours ? `Punches come from the fingerprint clock. Reporting time ${hours.startTime}, closing time ${hours.endTime}. A scan more than ${hours.graceMinutes} minutes after reporting time is late.` : 'Attendance is recorded by the fingerprint clock.'}>
        {desk && <Button tone="ghost" onClick={() => setOpen('manual')}>Manual entry</Button>}
        <Button tone="ghost" onClick={() => setOpen('correction')}>Correction</Button>
        <Button tone="ghost" onClick={() => setOpen('overtime')}>Overtime</Button>
      </PageHeader>
      <ErrorNote text={message} />
      {admin && (
        <Panel className="mb-4">
          <h2 className="font-semibold text-[#1a3c34]">Fingerprint clock address</h2>
          <ul className="mt-2 space-y-1 text-sm">
            {clockIps.map((item) => <li key={item.address}>http://{item.address}:{item.port}{item.path}</li>)}
            {!clockIps.length && <li className="text-[#6e675e]">No office network address is available on this computer.</li>}
          </ul>
        </Panel>
      )}
      <Panel>
        <Table columns={['Date', 'Emp ID', 'Emp name', 'Clock in', 'Clock out', ...(admin ? ['Clock-in IP', 'Clock-out IP'] : []), 'Status', 'Late', 'Source']} rows={rows.map((row) => [
          formatDate(row.date), employeeNo(row.employee), employeeName(row.employee), formatTime(row.clockIn), formatTime(row.clockOut), ...(admin ? [row.clockInIp || '—', row.clockOutIp || '—'] : []), <Badge tone={statusTone(row.status)}>{titleCase(row.status)}</Badge>, row.lateMinutes || '—', titleCase(row.source),
        ])} />
      </Panel>
      <div className="mt-4 grid gap-4 lg:grid-cols-2">
        <Panel>
          <h2 className="mb-2 font-semibold">Correction requests</h2>
          <Table columns={['Emp ID', 'Emp name', 'Date', 'Clock in', 'Clock out', 'Reason', '']} rows={corrections.map((row) => {
            const item = row as { id: string; date: string; reason: string; status: string; requestedClockIn?: string | null; requestedClockOut?: string | null; employee?: { firstName: string; lastName: string; employeeNo?: string } }
            return [employeeNo(item.employee), employeeName(item.employee), formatDate(item.date), formatTime(item.requestedClockIn), formatTime(item.requestedClockOut), item.reason, desk && item.status.startsWith('PENDING') ? <Button small onClick={async () => { await api(`/time/corrections/${item.id}/decision`, { method: 'POST', body: { decision: 'APPROVED' } }); await load() }}>Approve</Button> : titleCase(item.status)]
          })} />
        </Panel>
        <Panel>
          <h2 className="mb-2 font-semibold">Overtime</h2>
          <Table columns={['Emp ID', 'Emp name', 'Hours', 'Status', '']} rows={overtime.map((row) => {
            const item = row as { id: string; hours: string; status: string; employee?: { firstName: string; lastName: string; employeeNo?: string } }
            return [employeeNo(item.employee), employeeName(item.employee), item.hours, titleCase(item.status), desk && item.status.startsWith('PENDING') ? <Button small onClick={async () => { await api(`/time/overtime/${item.id}/decision`, { method: 'POST', body: { decision: 'APPROVED' } }); await load() }}>Approve</Button> : null]
          })} />
          {desk && (
            <>
              <h2 className="mb-2 mt-4 font-semibold">Shift roster</h2>
              {shifts.slice(0, 6).map((row) => {
                const item = row as { id: string; employee?: { firstName: string; lastName: string }; shift?: { name: string } }
                return <p key={item.id} className="text-sm">{employeeLabel(item.employee)} · {item.shift?.name}</p>
              })}
              <Button small tone="ghost" onClick={() => setOpen('shift')}>Assign shift</Button>
            </>
          )}
        </Panel>
      </div>
      {open === 'manual' && <SimpleForm title="Manual attendance" onClose={() => setOpen('')} onSubmit={async (body) => {
        await api('/time/attendance', { method: 'POST', body: { ...body, clockIn: punch(body.date, body.clockIn), clockOut: punch(body.date, body.clockOut) } })
        setOpen(''); await load()
      }} fields={[
        ['employeeId', 'Employee', 'select', lookups?.employees.map((item) => ({ value: item.id, label: employeeLabel(item) }))],
        ['date', 'Date', 'date'],
        ['clockIn', 'Clock in', 'time'],
        ['clockOut', 'Clock out', 'time'],
        ['status', 'Status', 'select', ['PRESENT', 'ABSENT', 'LATE', 'REMOTE', 'LEAVE'].map((item) => ({ value: item, label: titleCase(item) }))],
      ]} />}
      {open === 'correction' && <SimpleForm title="Attendance correction" onClose={() => setOpen('')} onSubmit={async (body) => {
        await api('/time/corrections', { method: 'POST', body: { date: body.date, reason: body.reason, requestedClockIn: punch(body.date, body.clockIn), requestedClockOut: punch(body.date, body.clockOut) } })
        setOpen(''); await load()
      }} fields={[['date', 'Date', 'date'], ['clockIn', 'Clock in', 'time'], ['clockOut', 'Clock out', 'time'], ['reason', 'Reason', 'textarea']]} />}
      {open === 'overtime' && <SimpleForm title="Overtime request" onClose={() => setOpen('')} onSubmit={async (body) => {
        await api('/time/overtime', { method: 'POST', body: { ...body, hours: Number(body.hours) } })
        setOpen(''); await load()
      }} fields={[['date', 'Date', 'date'], ['hours', 'Hours', 'number'], ['reason', 'Reason', 'textarea']]} />}
      {open === 'shift' && <SimpleForm title="Assign shift" onClose={() => setOpen('')} onSubmit={async (body) => {
        await api('/time/shifts', { method: 'POST', body })
        setOpen(''); await load()
      }} fields={[
        ['employeeId', 'Employee', 'select', lookups?.employees.map((item) => ({ value: item.id, label: employeeLabel(item) }))],
        ['shiftId', 'Shift', 'select', lookups?.shifts.map((item) => ({ value: item.id, label: `${item.name} ${item.startTime}–${item.endTime}` }))],
        ['effectiveFrom', 'From', 'date'],
      ]} />}
    </div>
  )
}
