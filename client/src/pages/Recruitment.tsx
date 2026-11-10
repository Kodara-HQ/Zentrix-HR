import { useEffect, useState } from 'react'
import { api } from '../lib/api'
import { money, titleCase } from '../lib/format'
import { employeeLabel, useLookups } from '../lib/lookups'
import { can, useAuth } from '../auth'
import { Badge, Button, Field, Modal, PageHeader, Panel, SearchSelect, Table, Tabs, controlClass, statusTone } from '../components/ui'

export function RecruitmentPage() {
  const { user } = useAuth()
  const lookups = useLookups()
  const [tab, setTab] = useState('Requisitions')
  const [requisitions, setRequisitions] = useState<Array<Record<string, string>>>([])
  const [vacancies, setVacancies] = useState<Array<Record<string, any>>>([])
  const [applicants, setApplicants] = useState<Array<Record<string, any>>>([])
  const [metrics, setMetrics] = useState<Array<Record<string, string | number>>>([])
  const [open, setOpen] = useState('')
  const [message, setMessage] = useState('')
  const writer = can(user?.role, ['HR_ADMIN', 'HR_MANAGER', 'RECRUITMENT_OFFICER', 'DEPARTMENT_MANAGER'])

  async function load() {
    const [reqs, vacs, apps] = await Promise.all([
      api<{ items: Array<Record<string, string>> }>('/recruitment/requisitions'),
      api<{ items: Array<Record<string, never>> }>('/recruitment/vacancies'),
      api<{ items: Array<Record<string, never>> }>('/recruitment/applicants?pageSize=50'),
    ])
    setRequisitions(reqs.items)
    setVacancies(vacs.items)
    setApplicants(apps.items)
    if (can(user?.role, ['HR_ADMIN', 'HR_MANAGER', 'RECRUITMENT_OFFICER'])) {
      setMetrics(await api('/recruitment/metrics'))
    }
  }
  useEffect(() => { load().catch((err) => setMessage(err.message)) }, [])

  return (
    <div>
      <PageHeader title="Recruitment" text="From the manpower request to the employee record, with cost and time-to-hire.">
        {writer && <Button onClick={() => setOpen('requisition')}>New requisition</Button>}
        {can(user?.role, ['RECRUITMENT_OFFICER', 'HR_ADMIN', 'HR_MANAGER']) && <Button tone="ghost" onClick={() => setOpen('vacancy')}>New vacancy</Button>}
        {can(user?.role, ['RECRUITMENT_OFFICER', 'HR_ADMIN', 'HR_MANAGER']) && <Button tone="ghost" onClick={() => setOpen('applicant')}>Register applicant</Button>}
      </PageHeader>
      {message && <p className="mb-3 text-sm text-[#9f2d2d]">{message}</p>}
      <Tabs tabs={['Requisitions', 'Vacancies', 'Applicants', 'Metrics']} value={tab} onChange={setTab} />
      {tab === 'Requisitions' && (
        <Panel>
          <Table columns={['Headcount', 'Justification', 'Status', '']} rows={requisitions.map((row) => [
            row.headcount,
            row.justification,
            <Badge tone={statusTone(row.status)}>{titleCase(row.status)}</Badge>,
            row.status === 'PENDING' && can(user?.role, ['HR_MANAGER']) ? <Button small onClick={async () => { await api(`/recruitment/requisitions/${row.id}/decision`, { method: 'POST', body: { decision: 'APPROVED' } }); await load() }}>Approve</Button> : null,
          ])} />
        </Panel>
      )}
      {tab === 'Vacancies' && (
        <Panel>
          <Table columns={['Role', 'Department', 'Applicants', 'Cost', 'Status']} rows={vacancies.map((row) => {
            const vacancy = row as unknown as { id: string; title: string; status: string; advertisingCost: string; agencyCost: string; department?: { name: string }; _count?: { applicants: number } }
            return [vacancy.title, vacancy.department?.name, vacancy._count?.applicants ?? 0, money(Number(vacancy.advertisingCost) + Number(vacancy.agencyCost)), <Badge tone={statusTone(vacancy.status)}>{titleCase(vacancy.status)}</Badge>]
          })} />
        </Panel>
      )}
      {tab === 'Applicants' && (
        <Panel>
          <Table columns={['Candidate', 'Vacancy', 'Score', 'Status', '']} rows={applicants.map((row) => {
            const applicant = row as unknown as { id: string; firstName: string; lastName: string; status: string; screeningScore?: string; vacancy?: { title: string }; offers?: Array<{ id: string; status: string }> }
            const offer = applicant.offers?.find((item) => item.status === 'SENT')
            return [
              `${applicant.firstName} ${applicant.lastName}`,
              applicant.vacancy?.title,
              applicant.screeningScore || '—',
              <Badge tone={statusTone(applicant.status)}>{titleCase(applicant.status)}</Badge>,
              <span className="flex gap-2">
                <Button small tone="ghost" onClick={() => setOpen(`interview:${applicant.id}`)}>Interview</Button>
                <Button small tone="ghost" onClick={() => setOpen(`offer:${applicant.id}`)}>Offer</Button>
                {offer && <Button small onClick={async () => {
                  const result = await api<{ temporaryPassword: string }>(`/recruitment/offers/${offer.id}/accept`, { method: 'POST' })
                  setMessage(`Hired. Temporary password ${result.temporaryPassword}. Complete the date of birth and gender on the employee record.`)
                  await load()
                }}>Convert to employee</Button>}
              </span>,
            ]
          })} />
        </Panel>
      )}
      {tab === 'Metrics' && (
        <Panel>
          <Table columns={['Vacancy', 'Applicants', 'Cost', 'Days to hire', 'Status']} rows={metrics.map((row) => [row.title, row.applicants, money(Number(row.cost)), row.timeToHire ?? '—', titleCase(String(row.status))])} />
        </Panel>
      )}
      {open === 'requisition' && <SimpleForm title="Manpower requisition" onClose={() => setOpen('')} onSubmit={async (body) => {
        await api('/recruitment/requisitions', { method: 'POST', body: { ...body, headcount: Number(body.headcount) } })
        setOpen(''); await load()
      }} fields={[
        ['departmentId', 'Department', 'select', lookups?.departments.map((item) => ({ value: item.id, label: item.name }))],
        ['jobTitleId', 'Job title', 'select', lookups?.jobTitles.map((item) => ({ value: item.id, label: item.name }))],
        ['headcount', 'Headcount', 'number'],
        ['justification', 'Justification', 'textarea'],
      ]} />}
      {open === 'vacancy' && <SimpleForm title="Vacancy" onClose={() => setOpen('')} onSubmit={async (body) => {
        await api('/recruitment/vacancies', { method: 'POST', body: { ...body, openings: 1, advertisingCost: Number(body.advertisingCost || 0), requisitionId: body.requisitionId || undefined } })
        setOpen(''); await load()
      }} fields={[
        ['requisitionId', 'Approved requisition', 'select', requisitions.filter((row) => row.status === 'APPROVED').map((row) => ({ value: row.id, label: row.justification.slice(0, 40) }))],
        ['title', 'Title', 'text'],
        ['departmentId', 'Department', 'select', lookups?.departments.map((item) => ({ value: item.id, label: item.name }))],
        ['jobTitleId', 'Job title', 'select', lookups?.jobTitles.map((item) => ({ value: item.id, label: item.name }))],
        ['locationId', 'Location', 'select', lookups?.locations.map((item) => ({ value: item.id, label: `${item.name}, ${item.city}` }))],
        ['description', 'Job description', 'textarea'],
        ['requirements', 'Requirements', 'textarea'],
        ['advertisingCost', 'Advertising cost', 'number'],
      ]} />}
      {open === 'applicant' && <SimpleForm title="Applicant" onClose={() => setOpen('')} onSubmit={async (body) => {
        await api('/recruitment/applicants', { method: 'POST', body: { ...body, screeningScore: body.screeningScore ? Number(body.screeningScore) : undefined } })
        setOpen(''); await load()
      }} fields={[
        ['vacancyId', 'Vacancy', 'select', vacancies.map((row) => ({ value: String(row.id), label: String(row.title) }))],
        ['firstName', 'First name', 'text'],
        ['lastName', 'Last name', 'text'],
        ['email', 'Email', 'text'],
        ['phone', 'Phone', 'text'],
        ['source', 'Source', 'text'],
        ['screeningScore', 'Screening score', 'number'],
      ]} />}
      {open.startsWith('interview:') && <SimpleForm title="Schedule interview" onClose={() => setOpen('')} onSubmit={async (body) => {
        await api('/recruitment/interviews', { method: 'POST', body: { ...body, applicantId: open.split(':')[1], scheduledAt: new Date(body.scheduledAt).toISOString() } })
        setOpen(''); await load()
      }} fields={[
        ['scheduledAt', 'When', 'datetime'],
        ['mode', 'Mode', 'text'],
        ['location', 'Location', 'text'],
        ['interviewerId', 'Interviewer', 'select', lookups?.employees.map((item) => ({ value: item.id, label: employeeLabel(item) }))],
      ]} />}
      {open.startsWith('offer:') && <SimpleForm title="Job offer" onClose={() => setOpen('')} onSubmit={async (body) => {
        await api('/recruitment/offers', { method: 'POST', body: { ...body, applicantId: open.split(':')[1], salary: Number(body.salary) } })
        setOpen(''); await load()
      }} fields={[['salary', 'Salary', 'number'], ['startDate', 'Start date', 'date'], ['expiresOn', 'Offer expires', 'date']]} />}
    </div>
  )
}

type FieldSpec = [string, string, string, Array<{ value: string; label: string }>?]

export function SimpleForm({ title, fields, onClose, onSubmit }: { title: string; fields: FieldSpec[]; onClose: () => void; onSubmit: (values: Record<string, string>) => Promise<void> }) {
  const [values, setValues] = useState<Record<string, string>>({})
  const [error, setError] = useState('')
  return (
    <Modal title={title} onClose={onClose} wide>
      <form onSubmit={async (event) => {
        event.preventDefault()
        try { await onSubmit(values) } catch (err) { setError(err instanceof Error ? err.message : 'Could not save') }
      }}>
        <div className="grid gap-x-4 gap-y-3 sm:grid-cols-2">
          {fields.map(([name, label, type, options]) => (
            <div key={name} className={type === 'textarea' ? 'sm:col-span-2' : ''}>
              <Field label={label}>
                {type === 'textarea' ? <textarea className={controlClass} rows={3} value={values[name] || ''} onChange={(event) => setValues({ ...values, [name]: event.target.value })} required /> :
                  type === 'select' ? <SearchSelect required value={values[name] || ''} onChange={(next) => setValues({ ...values, [name]: next })} options={options || []} placeholder="Select" /> :
                  <input className={controlClass} type={type === 'datetime' ? 'datetime-local' : type === 'time' ? 'time' : type === 'number' ? 'number' : type === 'date' ? 'date' : 'text'} value={values[name] || ''} onChange={(event) => setValues({ ...values, [name]: event.target.value })} required={type !== 'number' && type !== 'time'} />}
              </Field>
            </div>
          ))}
        </div>
        {error && <p className="mt-3 text-sm text-[#9f2d2d]">{error}</p>}
        <div className="mt-4"><Button type="submit">Save</Button></div>
      </form>
    </Modal>
  )
}
