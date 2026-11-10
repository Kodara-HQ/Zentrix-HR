import { useEffect, useState } from 'react'
import { api } from '../lib/api'
import { useLookups } from '../lib/lookups'
import { Button, PageHeader, Panel, SearchSelect, Table, controlClass } from '../components/ui'

const reports = [
  ['employees', 'Employee master list'],
  ['headcount', 'Headcount'],
  ['demographics', 'Demographics'],
  ['attendance', 'Attendance'],
  ['absenteeism', 'Absenteeism'],
  ['leave', 'Leave utilisation'],
  ['overtime', 'Overtime'],
  ['payroll', 'Payroll'],
  ['turnover', 'Turnover'],
  ['recruitment', 'Recruitment'],
  ['training', 'Training expenditure'],
  ['performance', 'Appraisals'],
  ['contracts', 'Contracts'],
  ['discipline', 'Discipline'],
  ['cost', 'Employee cost'],
  ['workforce', 'Workforce plan'],
]

export function ReportsPage() {
  const lookups = useLookups()
  const [name, setName] = useState('employees')
  const [departmentId, setDepartment] = useState('')
  const [from, setFrom] = useState('')
  const [to, setTo] = useState('')
  const [report, setReport] = useState<{ title: string; columns: Array<{ key: string; label: string }>; rows: Array<Record<string, string | number>> } | null>(null)

  async function load(next = name) {
    const params = new URLSearchParams()
    if (departmentId) params.set('departmentId', departmentId)
    if (from) params.set('from', from)
    if (to) params.set('to', to)
    setReport(await api(`/reports/${next}?${params}`))
  }
  useEffect(() => { load().catch(() => undefined) }, [])

  const query = new URLSearchParams()
  if (departmentId) query.set('departmentId', departmentId)
  if (from) query.set('from', from)
  if (to) query.set('to', to)

  return (
    <div>
      <PageHeader title="Reports and analytics" text="Filter, preview, then export the same figures to Excel or PDF.">
        <Button tone="ghost" onClick={() => window.print()}>Print</Button>
        <a className="rounded-lg border border-[#e4ddd2] px-3 py-2 text-sm font-semibold" href={`/api/reports/${name}?${query}&format=xlsx`}>Excel</a>
        <a className="rounded-lg border border-[#e4ddd2] px-3 py-2 text-sm font-semibold" href={`/api/reports/${name}?${query}&format=pdf`}>PDF</a>
      </PageHeader>
      <div className="mb-4 flex flex-wrap gap-2">
        <SearchSelect className="w-64" allowEmpty={false} value={name} onChange={(next) => { setName(next); load(next) }} options={reports.map(([value, label]) => ({ value, label }))} />
        <SearchSelect className="w-[220px]" value={departmentId} onChange={setDepartment} placeholder="All departments" options={lookups?.departments.map((item) => ({ value: item.id, label: item.name })) || []} />
        <input className={`${controlClass} max-w-[160px]`} type="date" value={from} onChange={(event) => setFrom(event.target.value)} />
        <input className={`${controlClass} max-w-[160px]`} type="date" value={to} onChange={(event) => setTo(event.target.value)} />
        <Button onClick={() => load()}>Apply filters</Button>
      </div>
      <Panel>
        <h2 className="mb-3 font-semibold">{report?.title}</h2>
        {report && <Table columns={report.columns.map((column) => column.label)} rows={report.rows.map((row) => report.columns.map((column) => String(row[column.key] ?? '')))} />}
      </Panel>
    </div>
  )
}
