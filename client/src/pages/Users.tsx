import { useEffect, useState, type FormEvent, type ReactNode } from 'react'
import { UserPlus, X } from 'lucide-react'
import { api } from '../lib/api'
import { useAuth } from '../auth'
import { Badge, Button, Confirm, ErrorNote, Field, PageHeader, Panel, controlClass } from '../components/ui'
import { roleLabel } from '../lib/format'

const roles = ['SUPER_ADMIN', 'HR_ADMIN', 'HR_MANAGER', 'DEPARTMENT_MANAGER', 'SUPERVISOR', 'PAYROLL_OFFICER', 'RECRUITMENT_OFFICER', 'EMPLOYEE']

type Account = {
  id: string
  email: string | null
  username: string | null
  role: string
  firstName: string
  lastName: string
  isActive: boolean
  lastLoginAt: string | null
}

type Draft = { fullName: string; username: string; email: string; role: string; password: string }

const emptyDraft: Draft = { fullName: '', username: '', email: '', role: 'SUPER_ADMIN', password: '' }

function personName(account: Account) {
  return account.firstName === account.lastName ? account.firstName : `${account.firstName} ${account.lastName}`
}

function loginDate(value?: string | null) {
  if (!value) return '—'
  return new Intl.DateTimeFormat('en-US', { month: 'short', day: '2-digit', year: 'numeric', timeZone: 'Africa/Accra' }).format(new Date(value))
}

function Dialog({ eyebrow, title, text, onClose, children }: { eyebrow: string; title: string; text: string; onClose: () => void; children: ReactNode }) {
  useEffect(() => {
    const onKey = (event: KeyboardEvent) => { if (event.key === 'Escape') onClose() }
    window.addEventListener('keydown', onKey)
    return () => window.removeEventListener('keydown', onKey)
  }, [onClose])
  return (
    <div className="fixed inset-0 z-50 flex items-end justify-center bg-[#1a3c34]/45 p-3 sm:items-center" onMouseDown={onClose}>
      <div className="max-h-[92vh] w-full max-w-2xl overflow-auto rounded-2xl border border-[#e6dfd4] bg-[#fffcf8] p-6 shadow-2xl" onMouseDown={(event) => event.stopPropagation()}>
        <div className="flex items-start justify-between gap-4">
          <div>
            <div className="text-xs font-semibold tracking-[0.16em] text-[#c4a574]">{eyebrow}</div>
            <h2 className="mt-2 text-2xl font-semibold text-[#1a3c34]">{title}</h2>
            <p className="mt-1 text-sm text-[#6e675e]">{text}</p>
          </div>
          <button className="rounded-xl border border-[#e4ddd2] p-2 text-[#1a3c34] hover:bg-[#f6f1e8]" onClick={onClose} aria-label="Close"><X size={16} /></button>
        </div>
        <div className="mt-6">{children}</div>
      </div>
    </div>
  )
}

export function UsersPage() {
  const { user } = useAuth()
  const [rows, setRows] = useState<Account[]>([])
  const [message, setMessage] = useState('')
  const [error, setError] = useState('')
  const [mode, setMode] = useState<'add' | 'edit' | 'password' | ''>('')
  const [draft, setDraft] = useState<Draft>(emptyDraft)
  const [selected, setSelected] = useState<Account | null>(null)
  const [confirmOff, setConfirmOff] = useState<Account | null>(null)

  async function load() {
    setRows(await api<Account[]>('/settings/users'))
  }

  useEffect(() => {
    if (user?.role === 'SUPER_ADMIN') load().catch((err) => setError(err.message))
  }, [user?.role])

  function openAdd() {
    setDraft(emptyDraft)
    setError('')
    setMode('add')
  }

  function openEdit(account: Account) {
    setSelected(account)
    setDraft({ fullName: personName(account), username: account.username || '', email: account.email || '', role: account.role, password: '' })
    setError('')
    setMode('edit')
  }

  function openPassword(account: Account) {
    setSelected(account)
    setDraft({ ...emptyDraft, password: '' })
    setError('')
    setMode('password')
  }

  async function saveAccount(event: FormEvent) {
    event.preventDefault()
    setError('')
    try {
      if (mode === 'add') {
        await api('/settings/users', { method: 'POST', body: draft })
        setMessage('Account created. Give the initial password to the user directly.')
      } else if (mode === 'edit' && selected) {
        await api(`/settings/users/${selected.id}`, { method: 'PATCH', body: { fullName: draft.fullName, username: draft.username, email: draft.email, role: draft.role } })
        setMessage('Account updated.')
      } else if (mode === 'password' && selected) {
        await api(`/settings/users/${selected.id}`, { method: 'PATCH', body: { password: draft.password } })
        setMessage('Password reset. The user will be asked to choose a new one at the next sign-in.')
      }
      setMode('')
      await load()
    } catch (err) {
      setError(err instanceof Error ? err.message : 'The account could not be saved.')
    }
  }

  if (user?.role !== 'SUPER_ADMIN') {
    return <Panel>User management is available to the system administrator.</Panel>
  }

  return (
    <div>
      <PageHeader title="User management" text="Create accounts, assign built-in roles, and manage access." />
      {message && <p className="mb-3 text-sm text-[#1a3c34]">{message}</p>}
      <ErrorNote text={mode ? '' : error} />
      <Panel>
        <div className="mb-4 flex flex-wrap items-start justify-between gap-3">
          <div>
            <h2 className="font-semibold text-[#1a3c34]">Application users</h2>
            <p className="text-sm text-[#6e675e]">Manage accounts and built-in role assignments.</p>
          </div>
          <Button onClick={openAdd}><UserPlus size={16} /> Add user</Button>
        </div>
        <div className="overflow-auto">
          <table className="w-full min-w-[760px] text-left text-sm">
            <thead className="text-xs uppercase tracking-[0.12em] text-[#8c6a32]">
              <tr>
                {['Name', 'Username', 'Email', 'Role', 'Status', 'Last login', 'Actions'].map((column) => (
                  <th key={column} className="border-b border-[#e6dfd4] px-3 py-2 font-semibold">{column}</th>
                ))}
              </tr>
            </thead>
            <tbody>
              {rows.map((account) => (
                <tr key={account.id}>
                  <td className="border-b border-[#efeae2] px-3 py-3 font-medium text-[#1a3c34]">{personName(account)}</td>
                  <td className="border-b border-[#efeae2] px-3 py-3">{account.username || '—'}</td>
                  <td className="border-b border-[#efeae2] px-3 py-3">{account.email || '—'}</td>
                  <td className="border-b border-[#efeae2] px-3 py-3">{roleLabel[account.role] || account.role}</td>
                  <td className="border-b border-[#efeae2] px-3 py-3">
                    <Badge tone={account.isActive ? 'green' : 'red'}>{account.isActive ? 'Active' : 'Inactive'}</Badge>
                  </td>
                  <td className="border-b border-[#efeae2] px-3 py-3">{loginDate(account.lastLoginAt)}</td>
                  <td className="border-b border-[#efeae2] px-3 py-3">
                    <div className="flex gap-3 text-sm font-semibold text-[#c4622d]">
                      <button onClick={() => openEdit(account)}>Edit</button>
                      <button onClick={() => openPassword(account)}>Reset password</button>
                      {account.id !== user.id && (
                        <button onClick={() => account.isActive ? setConfirmOff(account) : api(`/settings/users/${account.id}`, { method: 'PATCH', body: { isActive: true } }).then(load).catch((err) => setError(err.message))}>
                          {account.isActive ? 'Deactivate' : 'Activate'}
                        </button>
                      )}
                    </div>
                  </td>
                </tr>
              ))}
              {!rows.length && <tr><td className="px-3 py-8 text-center text-[#6e675e]" colSpan={7}>No accounts yet.</td></tr>}
            </tbody>
          </table>
        </div>
      </Panel>

      {(mode === 'add' || mode === 'edit') && (
        <Dialog
          eyebrow="User management"
          title={mode === 'add' ? 'Add user' : 'Edit user'}
          text={mode === 'add' ? 'Create an application account using one of the existing roles.' : 'Update the name, username, email, and role.'}
          onClose={() => setMode('')}
        >
          <form className="grid gap-4 sm:grid-cols-2" onSubmit={saveAccount}>
            <Field label="Full name *"><input className={controlClass} value={draft.fullName} onChange={(event) => setDraft({ ...draft, fullName: event.target.value })} required /></Field>
            <Field label="Username *"><input className={controlClass} value={draft.username} onChange={(event) => setDraft({ ...draft, username: event.target.value })} required /></Field>
            <Field label="Email"><input className={controlClass} type="email" value={draft.email} onChange={(event) => setDraft({ ...draft, email: event.target.value })} /></Field>
            <Field label="Role *">
              <select className={controlClass} value={draft.role} onChange={(event) => setDraft({ ...draft, role: event.target.value })} required>
                {roles.map((role) => <option key={role} value={role}>{roleLabel[role]}</option>)}
              </select>
            </Field>
            {mode === 'add' && (
              <div className="sm:col-span-2">
                <Field label="Initial password *"><input className={controlClass} type="password" minLength={12} value={draft.password} onChange={(event) => setDraft({ ...draft, password: event.target.value })} required /></Field>
                <p className="mt-2 text-sm text-[#6e675e]">Passwords must be at least 12 characters. Provide the initial password securely to the user.</p>
              </div>
            )}
            <div className="sm:col-span-2"><ErrorNote text={error} /></div>
            <div className="flex justify-end gap-2 border-t border-[#e6dfd4] pt-4 sm:col-span-2">
              <Button tone="ghost" onClick={() => setMode('')}>Cancel</Button>
              <Button type="submit">{mode === 'add' ? 'Create account' : 'Save changes'}</Button>
            </div>
          </form>
        </Dialog>
      )}

      {mode === 'password' && selected && (
        <Dialog eyebrow="User management" title="Reset password" text={`Set a new initial password for ${personName(selected)}.`} onClose={() => setMode('')}>
          <form onSubmit={saveAccount}>
            <Field label="Initial password *"><input className={controlClass} type="password" minLength={12} value={draft.password} onChange={(event) => setDraft({ ...draft, password: event.target.value })} required /></Field>
            <p className="mt-2 text-sm text-[#6e675e]">Passwords must be at least 12 characters. Provide the initial password securely to the user.</p>
            <div className="mt-4"><ErrorNote text={error} /></div>
            <div className="mt-4 flex justify-end gap-2 border-t border-[#e6dfd4] pt-4">
              <Button tone="ghost" onClick={() => setMode('')}>Cancel</Button>
              <Button type="submit">Reset password</Button>
            </div>
          </form>
        </Dialog>
      )}

      {confirmOff && (
        <Confirm
          title="Deactivate account"
          message={`${personName(confirmOff)} will no longer be able to sign in. You can activate the account again later.`}
          onCancel={() => setConfirmOff(null)}
          onConfirm={async () => {
            try {
              await api(`/settings/users/${confirmOff.id}`, { method: 'PATCH', body: { isActive: false } })
              setMessage(`${personName(confirmOff)} is inactive.`)
              setConfirmOff(null)
              await load()
            } catch (err) {
              setConfirmOff(null)
              setError(err instanceof Error ? err.message : 'The account could not be deactivated.')
            }
          }}
        />
      )}
    </div>
  )
}
