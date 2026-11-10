import { useEffect, useState } from 'react'
import { Bar, BarChart, CartesianGrid, ResponsiveContainer, Tooltip, XAxis, YAxis } from 'recharts'
import { api } from '../lib/api'
import { money } from '../lib/format'
import { employeeLabel, useLookups } from '../lib/lookups'
import { Button, PageHeader, Panel, Table } from '../components/ui'
import { SimpleForm } from './Recruitment'

export function WorkforcePage() {
  const lookups = useLookups()
  const [rows, setRows] = useState<Array<{ department: string; current: number; approved: number; vacant: number; monthlyBasic: number }>>([])
  const [plans, setPlans] = useState<Array<Record<string, unknown>>>([])
  const [open, setOpen] = useState('')
  async function load() {
    setRows(await api('/workplace/summary'))
    setPlans(await api('/workplace/succession'))
  }
  useEffect(() => { load().catch(() => undefined) }, [])
  return (
    <div>
      <PageHeader title="Workforce planning" text="Approved establishment against the people currently in post, plus successors for critical jobs.">
        <Button onClick={() => setOpen('budget')}>Set budget</Button>
        <Button tone="ghost" onClick={() => setOpen('succession')}>Add successor</Button>
      </PageHeader>
      <Panel>
        <div className="h-64">
          <ResponsiveContainer width="100%" height="100%">
            <BarChart data={rows}>
              <CartesianGrid stroke="#e6dfd4" vertical={false} />
              <XAxis dataKey="department" tick={{ fontSize: 12 }} />
              <YAxis allowDecimals={false} />
              <Tooltip />
              <Bar dataKey="approved" fill="#c4a574" radius={[4, 4, 0, 0]} />
              <Bar dataKey="current" fill="#1a3c34" radius={[4, 4, 0, 0]} />
            </BarChart>
          </ResponsiveContainer>
        </div>
      </Panel>
      <Panel className="mt-4">
        <Table columns={['Department', 'Approved', 'Current', 'Vacant', 'Monthly basic']} rows={rows.map((row) => [row.department, row.approved, row.current, row.vacant, money(row.monthlyBasic)])} />
      </Panel>
      <Panel className="mt-4">
        <h2 className="mb-2 font-semibold">Succession</h2>
        {plans.map((plan) => {
          const incumbent = plan.incumbent as { firstName: string; lastName: string } | null
          const successor = plan.successor as { firstName: string; lastName: string } | null
          return <p key={String(plan.id)} className="text-sm">{String(plan.positionTitle)} · {employeeLabel(incumbent || undefined)} → {employeeLabel(successor || undefined)} · {String(plan.readiness)} {plan.critical ? '· Critical' : ''}</p>
        })}
      </Panel>
      {open === 'budget' && <SimpleForm title="Manpower budget" onClose={() => setOpen('')} onSubmit={async (body) => { await api('/workplace/budgets', { method: 'POST', body: { ...body, year: Number(body.year), approvedHeadcount: Number(body.approvedHeadcount), estimatedCost: Number(body.estimatedCost) } }); setOpen(''); await load() }} fields={[
        ['year', 'Year', 'number'],
        ['departmentId', 'Department', 'select', lookups?.departments.map((item) => ({ value: item.id, label: item.name }))],
        ['approvedHeadcount', 'Approved headcount', 'number'],
        ['estimatedCost', 'Estimated annual cost', 'number'],
      ]} />}
      {open === 'succession' && <SimpleForm title="Succession plan" onClose={() => setOpen('')} onSubmit={async (body) => { await api('/workplace/succession', { method: 'POST', body: { ...body, critical: true } }); setOpen(''); await load() }} fields={[
        ['positionTitle', 'Position', 'text'], ['readiness', 'Readiness', 'text'],
        ['incumbentId', 'Incumbent', 'select', lookups?.employees.map((item) => ({ value: item.id, label: employeeLabel(item) }))],
        ['successorId', 'Successor', 'select', lookups?.employees.map((item) => ({ value: item.id, label: employeeLabel(item) }))],
      ]} />}
    </div>
  )
}
