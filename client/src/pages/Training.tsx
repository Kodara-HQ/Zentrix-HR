import { useEffect, useState } from 'react'
import { api } from '../lib/api'
import { formatDate, money, titleCase } from '../lib/format'
import { employeeLabel, employeeName, employeeNo, useLookups } from '../lib/lookups'
import { Button, PageHeader, Panel, Table } from '../components/ui'
import { SimpleForm } from './Recruitment'

export function TrainingPage() {
  const lookups = useLookups()
  const [programs, setPrograms] = useState<Array<Record<string, unknown>>>([])
  const [certs, setCerts] = useState<Array<Record<string, unknown>>>([])
  const [needs, setNeeds] = useState<Array<Record<string, unknown>>>([])
  const [open, setOpen] = useState('')
  async function load() {
    setPrograms(await api('/talent/programs'))
    setCerts(await api('/talent/certifications'))
    setNeeds(await api('/talent/needs'))
  }
  useEffect(() => { load().catch(() => undefined) }, [])
  const spend = programs.reduce((sum, row) => sum + Number(row.cost || 0), 0)
  return (
    <div>
      <PageHeader title="Training and development" text={`Recorded programme cost ${money(spend)}. Certificates with an expiry date raise a reminder.`}>
        <Button onClick={() => setOpen('program')}>Add programme</Button>
        <Button tone="ghost" onClick={() => setOpen('need')}>Training need</Button>
        <Button tone="ghost" onClick={() => setOpen('certificate')}>Certificate</Button>
      </PageHeader>
      <Panel>
        <Table columns={['Programme', 'Dates', 'Cost', 'Enrolled', 'Status']} rows={programs.map((row) => [
          String(row.title), `${formatDate(String(row.startDate))} – ${formatDate(String(row.endDate))}`, money(String(row.cost)), ((row.enrollments as unknown[]) || []).length, titleCase(String(row.status)),
        ])} />
      </Panel>
      <div className="mt-4 grid gap-4 lg:grid-cols-2">
        <Panel>
          <h2 className="mb-2 font-semibold">Needs assessment</h2>
          {needs.map((row) => <p key={String(row.id)} className="text-sm">{String(row.skill)} · {String(row.priority)} · {String(row.status)}</p>)}
        </Panel>
        <Panel>
          <h2 className="mb-2 font-semibold">Certifications</h2>
          <Table columns={['Emp ID', 'Emp name', 'Certificate', 'Expires']} rows={certs.map((row) => {
            const employee = row.employee as { firstName: string; lastName: string; employeeNo?: string }
            return [employeeNo(employee), employeeName(employee), String(row.name), formatDate(row.expiresOn ? String(row.expiresOn) : null)]
          })} />
        </Panel>
      </div>
      {open === 'program' && <SimpleForm title="Training programme" onClose={() => setOpen('')} onSubmit={async (body) => { await api('/talent/programs', { method: 'POST', body: { ...body, cost: Number(body.cost || 0) } }); setOpen(''); await load() }} fields={[['title', 'Title', 'text'], ['startDate', 'Start', 'date'], ['endDate', 'End', 'date'], ['cost', 'Cost', 'number'], ['location', 'Location', 'text']]} />}
      {open === 'need' && <SimpleForm title="Training need" onClose={() => setOpen('')} onSubmit={async (body) => { await api('/talent/needs', { method: 'POST', body }); setOpen(''); await load() }} fields={[['skill', 'Skill', 'text'], ['priority', 'Priority', 'text'], ['justification', 'Why', 'textarea']]} />}
      {open === 'certificate' && <SimpleForm title="Certificate" onClose={() => setOpen('')} onSubmit={async (body) => { await api('/talent/certifications', { method: 'POST', body }); setOpen(''); await load() }} fields={[
        ['employeeId', 'Employee', 'select', lookups?.employees.map((item) => ({ value: item.id, label: employeeLabel(item) }))],
        ['name', 'Name', 'text'], ['issuer', 'Issuer', 'text'], ['expiresOn', 'Expires', 'date'],
      ]} />}
    </div>
  )
}
