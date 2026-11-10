import { useEffect, useState } from 'react'
import { api } from '../lib/api'
import { formatDate } from '../lib/format'
import { employeeLabel, employeeName, employeeNo, useLookups } from '../lib/lookups'
import { Button, PageHeader, Panel, Table } from '../components/ui'
import { SimpleForm } from './Recruitment'

export function ContractsPage() {
  const lookups = useLookups()
  const [contracts, setContracts] = useState<Array<Record<string, unknown>>>([])
  const [letters, setLetters] = useState<Array<Record<string, unknown>>>([])
  const [open, setOpen] = useState(false)
  async function load() {
    setContracts(await api('/workplace/contracts'))
    setLetters(await api('/workplace/letters'))
  }
  useEffect(() => { load().catch(() => undefined) }, [])
  return (
    <div>
      <PageHeader title="Contracts and documents" text="Contracts with an end date appear on the dashboard and in the reminder run.">
        <Button onClick={() => setOpen(true)}>Add contract</Button>
      </PageHeader>
      <Panel>
        <Table columns={['Emp ID', 'Emp name', 'Contract', 'Start', 'End', 'Status']} rows={contracts.map((row) => {
          const employee = row.employee as { firstName: string; lastName: string; employeeNo?: string }
          return [employeeNo(employee), employeeName(employee), String(row.title), formatDate(String(row.startDate)), formatDate(row.endDate ? String(row.endDate) : null), String(row.status)]
        })} />
      </Panel>
      <Panel className="mt-4">
        <h2 className="mb-2 font-semibold">Letters</h2>
        <ul className="space-y-1 text-sm">
          {letters.map((letter) => <li key={String(letter.id)}>{String(letter.subject)} · {formatDate(String(letter.issuedOn))} {letter.fileAssetId ? <a className="text-[#c4622d]" href={`/api/files/${String(letter.fileAssetId)}`}>PDF</a> : null}</li>)}
        </ul>
      </Panel>
      {open && <SimpleForm title="Contract" onClose={() => setOpen(false)} onSubmit={async (body) => { await api('/workplace/contracts', { method: 'POST', body }); setOpen(false); await load() }} fields={[
        ['employeeId', 'Employee', 'select', lookups?.employees.map((item) => ({ value: item.id, label: employeeLabel(item) }))],
        ['type', 'Type', 'text'], ['title', 'Title', 'text'], ['startDate', 'Start', 'date'], ['endDate', 'End', 'date'],
      ]} />}
    </div>
  )
}
