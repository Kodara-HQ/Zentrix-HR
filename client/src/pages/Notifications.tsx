import { useEffect, useState } from 'react'
import { Link } from 'react-router-dom'
import { api } from '../lib/api'
import { formatDate } from '../lib/format'
import { Button, PageHeader, Panel } from '../components/ui'

type Item = { id: string; title: string; body: string; link?: string; readAt?: string; createdAt: string }

export function NotificationsPage() {
  const [items, setItems] = useState<Item[]>([])
  async function load() {
    const data = await api<{ items: Item[] }>('/notifications?pageSize=50')
    setItems(data.items)
  }
  useEffect(() => { load().catch(() => undefined) }, [])
  return (
    <div>
      <PageHeader title="Notifications" text="In-app alerts for approvals, birthdays, contracts, probation, training, hearings, and payroll. Email is sent when SMTP is configured.">
        <Button tone="ghost" onClick={async () => { await api('/notifications/read-all', { method: 'POST' }); await load() }}>Mark all read</Button>
      </PageHeader>
      <div className="space-y-2">
        {items.map((item) => (
          <Panel key={item.id} className={item.readAt ? 'opacity-70' : ''}>
            <div className="flex items-start justify-between gap-3">
              <div>
                <h2 className="font-semibold">{item.title}</h2>
                <p className="text-sm text-[#4a524c]">{item.body}</p>
                <p className="mt-1 text-xs text-[#6e675e]">{formatDate(item.createdAt)}</p>
              </div>
              <div className="flex gap-2">
                {item.link && <Link className="text-sm text-[#c4622d]" to={item.link}>Open</Link>}
                {!item.readAt && <Button small tone="ghost" onClick={async () => { await api(`/notifications/${item.id}/read`, { method: 'POST' }); await load() }}>Read</Button>}
              </div>
            </div>
          </Panel>
        ))}
        {!items.length && <p className="text-sm text-[#6e675e]">No notifications yet.</p>}
      </div>
    </div>
  )
}
