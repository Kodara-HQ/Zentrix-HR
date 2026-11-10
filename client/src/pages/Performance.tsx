import { useEffect, useState } from 'react'
import { api } from '../lib/api'
import { formatDate, titleCase } from '../lib/format'
import { employeeLabel, employeeName, employeeNo, useLookups } from '../lib/lookups'
import { Badge, Button, PageHeader, Panel, Table, statusTone } from '../components/ui'
import { SimpleForm } from './Recruitment'

export function PerformancePage() {
  const lookups = useLookups()
  const [cycles, setCycles] = useState<Array<Record<string, unknown>>>([])
  const [objectives, setObjectives] = useState<Array<Record<string, unknown>>>([])
  const [appraisals, setAppraisals] = useState<Array<Record<string, unknown>>>([])
  const [open, setOpen] = useState('')

  async function load() {
    setCycles(await api('/talent/cycles'))
    setObjectives(await api('/talent/objectives'))
    setAppraisals(await api('/talent/appraisals'))
  }
  useEffect(() => { load().catch(() => undefined) }, [])

  return (
    <div>
      <PageHeader title="Performance" text="Objectives, self-assessment, and the supervisor rating sit on the same review.">
        <Button onClick={() => setOpen('cycle')}>New cycle</Button>
        <Button tone="ghost" onClick={() => setOpen('objective')}>Add objective</Button>
        <Button tone="ghost" onClick={() => setOpen('appraisal')}>Open appraisal</Button>
      </PageHeader>
      <Panel>
        <h2 className="mb-2 font-semibold">Cycles</h2>
        <Table columns={['Name', 'Type', 'Closes', 'Status']} rows={cycles.map((row) => [String(row.name), titleCase(String(row.periodType)), formatDate(String(row.endDate)), <Badge tone={statusTone(String(row.status))}>{titleCase(String(row.status))}</Badge>])} />
      </Panel>
      <Panel className="mt-4">
        <h2 className="mb-2 font-semibold">Objectives and KPIs</h2>
        <Table columns={['Title', 'KPI', 'Target', 'Score']} rows={objectives.map((row) => [String(row.title), String(row.kpi), String(row.target), String(row.score ?? '—')])} />
      </Panel>
      <Panel className="mt-4">
        <h2 className="mb-2 font-semibold">Appraisals</h2>
        <Table columns={['Emp ID', 'Emp name', 'Cycle', 'Status', '']} rows={appraisals.map((row) => {
          const employee = row.employee as { firstName: string; lastName: string; employeeNo?: string }
          const cycle = row.cycle as { name: string }
          return [employeeNo(employee), employeeName(employee), cycle?.name, titleCase(String(row.status)), <Button small onClick={async () => {
            const rating = window.prompt('Supervisor rating from 1 to 5')
            if (!rating) return
            await api(`/talent/appraisals/${row.id}`, { method: 'PATCH', body: { supervisorRating: Number(rating), finalRating: Number(rating), status: 'REVIEWED', recommendation: 'Retain' } })
            await load()
          }}>Rate</Button>]
        })} />
      </Panel>
      {open === 'cycle' && <SimpleForm title="Performance cycle" onClose={() => setOpen('')} onSubmit={async (body) => { await api('/talent/cycles', { method: 'POST', body }); setOpen(''); await load() }} fields={[['name', 'Name', 'text'], ['periodType', 'Period', 'select', ['QUARTERLY', 'HALF_YEARLY', 'ANNUAL'].map((item) => ({ value: item, label: titleCase(item) }))], ['startDate', 'Start', 'date'], ['endDate', 'End', 'date']]} />}
      {open === 'objective' && <SimpleForm title="Objective" onClose={() => setOpen('')} onSubmit={async (body) => { await api('/talent/objectives', { method: 'POST', body: { ...body, weight: Number(body.weight), employeeId: body.employeeId || undefined } }); setOpen(''); await load() }} fields={[
        ['cycleId', 'Cycle', 'select', cycles.map((row) => ({ value: String(row.id), label: String(row.name) }))],
        ['level', 'Level', 'select', [{ value: 'INDIVIDUAL', label: 'Individual' }, { value: 'DEPARTMENT', label: 'Department' }]],
        ['employeeId', 'Employee', 'select', lookups?.employees.map((item) => ({ value: item.id, label: employeeLabel(item) }))],
        ['title', 'Title', 'text'], ['kpi', 'KPI', 'text'], ['target', 'Target', 'text'], ['weight', 'Weight', 'number'],
      ]} />}
      {open === 'appraisal' && <SimpleForm title="Appraisal" onClose={() => setOpen('')} onSubmit={async (body) => { await api('/talent/appraisals', { method: 'POST', body }); setOpen(''); await load() }} fields={[
        ['cycleId', 'Cycle', 'select', cycles.map((row) => ({ value: String(row.id), label: String(row.name) }))],
        ['employeeId', 'Employee', 'select', lookups?.employees.map((item) => ({ value: item.id, label: employeeLabel(item) }))],
      ]} />}
    </div>
  )
}
