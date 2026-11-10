import { useEffect, useState } from 'react'
import { api } from '../lib/api'
import { formatDate, money, titleCase } from '../lib/format'
import { employeeLabel, employeeName, employeeNo } from '../lib/lookups'
import { can, useAuth } from '../auth'
import { Badge, Button, PageHeader, Panel, Table, Tabs, statusTone } from '../components/ui'
import { SimpleForm } from './Recruitment'

export function WelfarePage() {
  const { user } = useAuth()
  const hr = can(user?.role, ['HR_ADMIN', 'HR_MANAGER'])
  const [tab, setTab] = useState('Requests')
  const [requests, setRequests] = useState<Array<Record<string, unknown>>>([])
  const [surveys, setSurveys] = useState<Array<Record<string, unknown>>>([])
  const [awards, setAwards] = useState<Array<Record<string, unknown>>>([])
  const [activities, setActivities] = useState<Array<Record<string, unknown>>>([])
  const [suggestions, setSuggestions] = useState<Array<Record<string, unknown>>>([])
  const [open, setOpen] = useState('')
  async function load() {
    const [welfare, surveyRows, recognition, events, ideas] = await Promise.all([
      api<Array<Record<string, unknown>>>('/workplace/welfare'),
      api<Array<Record<string, unknown>>>('/workplace/surveys'),
      api<Array<Record<string, unknown>>>('/workplace/recognition'),
      api<Array<Record<string, unknown>>>('/workplace/activities'),
      api<Array<Record<string, unknown>>>('/workplace/suggestions'),
    ])
    setRequests(welfare); setSurveys(surveyRows); setAwards(recognition); setActivities(events); setSuggestions(ideas)
  }
  useEffect(() => { load().catch(() => undefined) }, [])
  return (
    <div>
      <PageHeader title="Welfare and engagement" text="Assistance requests, the pulse survey, recognition, and staff suggestions.">
        <Button onClick={() => setOpen('request')}>Welfare request</Button>
        <Button tone="ghost" onClick={() => setOpen('feedback')}>Give feedback</Button>
        <Button tone="ghost" onClick={() => setOpen('suggestion')}>Suggestion</Button>
      </PageHeader>
      <Tabs tabs={['Requests', 'Surveys', 'Recognition', 'Suggestions']} value={tab} onChange={setTab} />
      {tab === 'Requests' && <Panel><Table columns={['Emp ID', 'Emp name', 'Type', 'Amount', 'Status', '']} rows={requests.map((row) => {
        const employee = row.employee as { firstName: string; lastName: string; employeeNo?: string }
        return [employeeNo(employee), employeeName(employee), String(row.type), row.amount ? money(String(row.amount)) : '—', <Badge tone={statusTone(String(row.status))}>{titleCase(String(row.status))}</Badge>, hr && row.status === 'PENDING' ? <Button small onClick={async () => { await api(`/workplace/welfare/${row.id}`, { method: 'PATCH', body: { status: 'APPROVED', resolution: 'Approved from the welfare budget.' } }); await load() }}>Approve</Button> : null]
      })} /></Panel>}
      {tab === 'Surveys' && <Panel>{surveys.map((survey) => (
        <div key={String(survey.id)} className="mb-4">
          <h3 className="font-semibold">{String(survey.title)}</h3>
          <p className="text-sm text-[#6e675e]">{String(survey.description || '')}</p>
          <Button small onClick={async () => {
            const comment = window.prompt('What should we improve next?') || 'No comment'
            await api(`/workplace/surveys/${survey.id}/responses`, { method: 'POST', body: { answers: { workload: 4, support: 4, comment } } })
            await load()
          }}>Submit my response</Button>
        </div>
      ))}
      {activities.map((item) => <p key={String(item.id)} className="text-sm">{String(item.title)} · {formatDate(String(item.activityDate))} · {String(item.location || '')}</p>)}
      </Panel>}
      {tab === 'Recognition' && <Panel>{awards.map((item) => {
        const employee = item.employee as { firstName: string; lastName: string }
        return <p key={String(item.id)} className="text-sm">{employeeLabel(employee)} · {String(item.title)} · {formatDate(String(item.awardedOn))}</p>
      })}</Panel>}
      {tab === 'Suggestions' && <Panel>{suggestions.map((item) => <p key={String(item.id)} className="mb-2 text-sm">{String(item.message)} <span className="text-[#6e675e]">· {String(item.status)}</span></p>)}</Panel>}
      {open === 'request' && <SimpleForm title="Welfare request" onClose={() => setOpen('')} onSubmit={async (body) => { await api('/workplace/welfare', { method: 'POST', body: { ...body, amount: body.amount ? Number(body.amount) : undefined } }); setOpen(''); await load() }} fields={[['type', 'Type', 'text'], ['description', 'Description', 'textarea'], ['amount', 'Amount', 'number']]} />}
      {open === 'feedback' && <SimpleForm title="Staff feedback" onClose={() => setOpen('')} onSubmit={async (body) => { await api('/workplace/feedback', { method: 'POST', body: { ...body, anonymous: true } }); setOpen(''); await load() }} fields={[['category', 'Category', 'text'], ['message', 'Message', 'textarea']]} />}
      {open === 'suggestion' && <SimpleForm title="Suggestion" onClose={() => setOpen('')} onSubmit={async (body) => { await api('/workplace/suggestions', { method: 'POST', body }); setOpen(''); await load() }} fields={[['message', 'Suggestion', 'textarea']]} />}
    </div>
  )
}
