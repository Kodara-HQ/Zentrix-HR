import { useEffect, useState } from 'react'
import { api } from '../lib/api'
import { money } from '../lib/format'
import { employeeLabel, employeeName, employeeNo, useLookups } from '../lib/lookups'
import { can, useAuth } from '../auth'
import { titleCase } from '../lib/format'
import { Badge, Button, PageHeader, Panel, Table, statusTone } from '../components/ui'
import { SimpleForm } from './Recruitment'

type Run = { id: string; year: number; month: number; status: string; totalGross: string; totalNet: string; totalPaye: string }
type Slip = { id: string; netPay: string; grossPay: string; paye: string; payrollRun: { year: number; month: number; status: string } }

export function PayrollPage() {
  const { user } = useAuth()
  const lookups = useLookups()
  const officer = can(user?.role, ['PAYROLL_OFFICER', 'HR_MANAGER'])
  const [runs, setRuns] = useState<Run[]>([])
  const [mine, setMine] = useState<Slip[]>([])
  const [open, setOpen] = useState('')
  const [message, setMessage] = useState('')
  const [detail, setDetail] = useState<Record<string, unknown> | null>(null)

  async function load() {
    if (officer) setRuns(await api('/payroll/runs'))
    if (user?.employeeId) setMine(await api('/payroll/payslips/mine'))
  }
  useEffect(() => { load().catch((err) => setMessage(err.message)) }, [])

  return (
    <div>
      <PageHeader title={officer ? 'Payroll' : 'My payslips'} text={officer ? 'Monthly pay uses the statutory rates in force for that month, including Ghana PAYE and the SSNIT split.' : 'Payslips appear here after payroll has been approved.'}>
        {officer && <Button onClick={() => setOpen('run')}>Process month</Button>}
        {officer && <Button tone="ghost" onClick={() => setOpen('adjustment')}>Bonus or deduction</Button>}
        {officer && <Button tone="ghost" onClick={() => setOpen('loan')}>Loan or advance</Button>}
      </PageHeader>
      {message && <p className="mb-3 text-sm text-[#9f2d2d]">{message}</p>}
      {officer && (
        <Panel>
          <Table columns={['Period', 'Gross', 'PAYE', 'Net', 'Status', '']} rows={runs.map((run) => [
            `${run.year}-${String(run.month).padStart(2, '0')}`,
            money(run.totalGross),
            money(run.totalPaye),
            money(run.totalNet),
            <Badge tone={statusTone(run.status)}>{titleCase(run.status)}</Badge>,
            <span className="flex gap-2">
              <Button small tone="ghost" onClick={async () => setDetail(await api(`/payroll/runs/${run.id}`))}>Open</Button>
              {run.status === 'PENDING_APPROVAL' && can(user?.role, ['HR_MANAGER']) && <Button small onClick={async () => { await api(`/payroll/runs/${run.id}/decision`, { method: 'POST', body: { decision: 'APPROVED' } }); await load() }}>Approve</Button>}
            </span>,
          ])} />
        </Panel>
      )}
      <Panel className={officer ? 'mt-4' : ''}>
        <h2 className="mb-3 font-semibold">{officer ? 'My approved payslips' : 'Approved payslips'}</h2>
        <div className="grid gap-3">
          {mine.map((slip) => {
            const period = `${slip.payrollRun.year}-${String(slip.payrollRun.month).padStart(2, '0')}`
            return (
              <div key={slip.id} className="flex flex-wrap items-center justify-between gap-3 rounded-xl border border-[#e6dfd4] bg-[#f6f1e8] px-4 py-3">
                <div>
                  <div className="font-semibold text-[#1a3c34]">{period}</div>
                  <div className="text-sm text-[#6e675e]">Gross {money(slip.grossPay)} · PAYE {money(slip.paye)} · Net {money(slip.netPay)}</div>
                </div>
                <a className="inline-flex items-center justify-center rounded-lg bg-[#c4622d] px-3.5 py-2 text-sm font-semibold text-white hover:bg-[#a85122]" href={`/api/payroll/payslips/${slip.id}/pdf`} download={`payslip-${period}.pdf`}>Download</a>
              </div>
            )
          })}
          {!mine.length && <p className="text-sm text-[#6e675e]">No approved payslips yet. A payslip can be downloaded after that month’s payroll is approved.</p>}
        </div>
      </Panel>
      {detail && (
        <Panel className="mt-4">
          <div className="mb-2 flex justify-between"><h2 className="font-semibold">Payslips</h2><Button small tone="ghost" onClick={() => setDetail(null)}>Close</Button></div>
          <Table columns={['Emp ID', 'Emp name', 'Gross', 'SSNIT', 'PAYE', 'Net']} rows={((detail.payslips as Array<Record<string, unknown>>) || []).map((slip) => {
            const employee = slip.employee as { firstName: string; lastName: string; employeeNo?: string }
            return [employeeNo(employee), employeeName(employee), money(String(slip.grossPay)), money(String(slip.employeeSsnit)), money(String(slip.paye)), money(String(slip.netPay))]
          })} />
        </Panel>
      )}
      {open === 'run' && <SimpleForm title="Process payroll" onClose={() => setOpen('')} onSubmit={async (body) => {
        await api('/payroll/runs/process', { method: 'POST', body: { year: Number(body.year), month: Number(body.month) } })
        setOpen(''); await load()
      }} fields={[['year', 'Year', 'number'], ['month', 'Month', 'number']]} />}
      {open === 'adjustment' && <SimpleForm title="Pay adjustment" onClose={() => setOpen('')} onSubmit={async (body) => {
        await api('/payroll/adjustments', { method: 'POST', body: { ...body, year: Number(body.year), month: Number(body.month), amount: Number(body.amount), taxable: true } })
        setOpen(''); await load()
      }} fields={[
        ['employeeId', 'Employee', 'select', lookups?.employees.map((item) => ({ value: item.id, label: employeeLabel(item) }))],
        ['year', 'Year', 'number'], ['month', 'Month', 'number'],
        ['kind', 'Kind', 'select', ['BONUS', 'COMMISSION', 'ALLOWANCE', 'DEDUCTION'].map((item) => ({ value: item, label: titleCase(item) }))],
        ['label', 'Label', 'text'], ['amount', 'Amount', 'number'],
      ]} />}
      {open === 'loan' && <SimpleForm title="Loan or salary advance" onClose={() => setOpen('')} onSubmit={async (body) => {
        await api('/payroll/loans', { method: 'POST', body: { ...body, principal: Number(body.principal), monthlyDeduction: Number(body.monthlyDeduction) } })
        setOpen(''); await load()
      }} fields={[
        ['employeeId', 'Employee', 'select', lookups?.employees.map((item) => ({ value: item.id, label: employeeLabel(item) }))],
        ['type', 'Type', 'text'], ['principal', 'Principal', 'number'], ['monthlyDeduction', 'Monthly deduction', 'number'], ['startedOn', 'Start', 'date'],
      ]} />}
    </div>
  )
}
