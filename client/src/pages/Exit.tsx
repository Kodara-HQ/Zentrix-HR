import { useEffect, useState } from 'react'
import { api } from '../lib/api'
import { formatDate, titleCase } from '../lib/format'
import { employeeLabel, employeeName, employeeNo, useLookups } from '../lib/lookups'
import { can, useAuth } from '../auth'
import { Badge, Button, PageHeader, Panel, Table, statusTone } from '../components/ui'
import { SimpleForm } from './Recruitment'

export function ExitPage() {
  const { user } = useAuth()
  const lookups = useLookups()
  const hr = can(user?.role, ['HR_ADMIN', 'HR_MANAGER'])
  const [rows, setRows] = useState<Array<Record<string, unknown>>>([])
  const [open, setOpen] = useState(false)
  async function load() { setRows(await api('/workplace/separations')) }
  useEffect(() => { load().catch(() => undefined) }, [])
  return (
    <div>
      <PageHeader title="Exit management" text="A resignation stays active until the last working day, then the employment status changes.">
        <Button onClick={() => setOpen(true)}>Submit resignation</Button>
      </PageHeader>
      <Panel>
        <Table columns={['Emp ID', 'Emp name', 'Type', 'Last day', 'Clearance', 'Status', '']} rows={rows.map((row) => {
          const employee = row.employee as { firstName: string; lastName: string; employeeNo?: string }
          const clearance = (row.clearance as Array<{ returned: boolean }>) || []
          const done = clearance.filter((item) => item.returned).length
          return [
            employeeNo(employee),
            employeeName(employee),
            titleCase(String(row.type)),
            formatDate(String(row.lastWorkingDay)),
            `${done}/${clearance.length}`,
            <Badge tone={statusTone(String(row.status))}>{titleCase(String(row.status))}</Badge>,
            row.status === 'PENDING' && hr ? <Button small onClick={async () => { await api(`/workplace/separations/${row.id}/decision`, { method: 'POST', body: { decision: 'APPROVED', finalPayNotes: 'Final pay will follow the next payroll, including accrued leave.' } }); await load() }}>Approve</Button> : null,
          ]
        })} />
      </Panel>
      {open && <SimpleForm title="Resignation" onClose={() => setOpen(false)} onSubmit={async (body) => {
        await api('/workplace/separations', { method: 'POST', body: { ...body, type: 'RESIGNATION', employeeId: hr ? body.employeeId : undefined } })
        setOpen(false); await load()
      }} fields={[
        ...(hr ? [['employeeId', 'Employee', 'select', lookups?.employees.map((item) => ({ value: item.id, label: employeeLabel(item) }))] as [string, string, string, Array<{ value: string; label: string }>]] : []),
        ['noticeDate', 'Notice date', 'date'],
        ['lastWorkingDay', 'Last working day', 'date'],
        ['reason', 'Reason', 'textarea'],
      ]} />}
    </div>
  )
}
