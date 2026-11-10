import { useEffect, useState } from 'react'
import { api } from '../lib/api'
import { formatDate, titleCase } from '../lib/format'
import { employeeLabel, employeeName, employeeNo, useLookups } from '../lib/lookups'
import { can, useAuth } from '../auth'
import { Badge, Button, PageHeader, Panel, Table, statusTone } from '../components/ui'
import { SimpleForm } from './Recruitment'

export function RelationsPage() {
  const { user } = useAuth()
  const lookups = useLookups()
  const hr = can(user?.role, ['HR_ADMIN', 'HR_MANAGER'])
  const [grievances, setGrievances] = useState<Array<Record<string, unknown>>>([])
  const [cases, setCases] = useState<Array<Record<string, unknown>>>([])
  const [warnings, setWarnings] = useState<Array<Record<string, unknown>>>([])
  const [open, setOpen] = useState('')
  async function load() {
    setGrievances(await api('/workplace/grievances'))
    setWarnings(await api('/workplace/warnings'))
    if (hr) setCases(await api('/workplace/cases'))
  }
  useEffect(() => { load().catch(() => undefined) }, [])
  return (
    <div>
      <PageHeader title="Employee relations" text="Grievances and disciplinary files are limited to HR, except for a person's own warnings.">
        <Button onClick={() => setOpen('grievance')}>Report a grievance</Button>
        {hr && <Button tone="ghost" onClick={() => setOpen('case')}>Open a case</Button>}
        {hr && <Button tone="ghost" onClick={() => setOpen('warning')}>Issue a warning</Button>}
      </PageHeader>
      <Panel>
        <h2 className="mb-2 font-semibold">Grievances</h2>
        <Table columns={['Emp ID', 'Emp name', 'Category', 'Status', '']} rows={grievances.map((row) => {
          const employee = row.employee as { firstName: string; lastName: string; employeeNo?: string }
          return [employeeNo(employee), employeeName(employee), String(row.category), <Badge tone={statusTone(String(row.status))}>{titleCase(String(row.status))}</Badge>, hr ? <Button small onClick={async () => { await api(`/workplace/grievances/${row.id}`, { method: 'PATCH', body: { status: 'CLOSED', resolution: 'Reviewed with the employee and the supervisor.' } }); await load() }}>Close</Button> : null]
        })} />
      </Panel>
      {hr && (
        <Panel className="mt-4">
          <h2 className="mb-2 font-semibold">Disciplinary cases</h2>
          <Table columns={['Case', 'Emp ID', 'Emp name', 'Hearing', 'Status']} rows={cases.map((row) => {
            const employee = row.employee as { firstName: string; lastName: string; employeeNo?: string }
            return [String(row.caseNo), employeeNo(employee), employeeName(employee), row.hearingAt ? formatDate(String(row.hearingAt)) : '—', titleCase(String(row.status))]
          })} />
        </Panel>
      )}
      <Panel className="mt-4">
        <h2 className="mb-2 font-semibold">Warnings</h2>
        <Table columns={['Emp ID', 'Emp name', 'Type', 'Issued', 'Content']} rows={warnings.map((row) => {
          const employee = row.employee as { firstName: string; lastName: string; employeeNo?: string }
          return [employeeNo(employee), employeeName(employee), titleCase(String(row.type)), formatDate(String(row.issuedOn)), String(row.content)]
        })} />
      </Panel>
      {open === 'grievance' && <SimpleForm title="Grievance" onClose={() => setOpen('')} onSubmit={async (body) => { await api('/workplace/grievances', { method: 'POST', body }); setOpen(''); await load() }} fields={[['category', 'Category', 'text'], ['description', 'What happened', 'textarea']]} />}
      {open === 'case' && <SimpleForm title="Disciplinary case" onClose={() => setOpen('')} onSubmit={async (body) => { await api('/workplace/cases', { method: 'POST', body }); setOpen(''); await load() }} fields={[
        ['employeeId', 'Employee', 'select', lookups?.employees.map((item) => ({ value: item.id, label: employeeLabel(item) }))],
        ['incidentDate', 'Incident date', 'date'], ['description', 'Description', 'textarea'], ['confidentialNotes', 'Confidential notes', 'textarea'],
      ]} />}
      {open === 'warning' && <SimpleForm title="Warning" onClose={() => setOpen('')} onSubmit={async (body) => { await api('/workplace/warnings', { method: 'POST', body }); setOpen(''); await load() }} fields={[
        ['employeeId', 'Employee', 'select', lookups?.employees.map((item) => ({ value: item.id, label: employeeLabel(item) }))],
        ['type', 'Type', 'select', ['VERBAL', 'WRITTEN', 'FINAL'].map((item) => ({ value: item, label: titleCase(item) }))],
        ['issuedOn', 'Date', 'date'], ['content', 'Content', 'textarea'],
      ]} />}
    </div>
  )
}
