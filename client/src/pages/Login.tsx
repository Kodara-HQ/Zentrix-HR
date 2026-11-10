import { useState } from 'react'
import { Navigate } from 'react-router-dom'
import { api } from '../lib/api'
import { useAuth } from '../auth'
import { Button, ErrorNote, controlClass } from '../components/ui'

export function LoginPage() {
  const { user, login } = useAuth()
  const [email, setEmail] = useState('')
  const [password, setPassword] = useState('')
  const [error, setError] = useState('')
  const [busy, setBusy] = useState(false)

  if (user) return <Navigate to="/" replace />

  const issues = [
    'Approvals sitting with a manager or with HR',
    'Payroll, PAYE, and SSNIT on one employee record',
    'Contracts, probation, and certificates nearing their dates',
    'Attendance, leave, and overtime that do not agree',
    'Open grievances and disciplinary cases',
  ]

  return (
    <div className="flex min-h-screen flex-col bg-[#f6f1e8] lg:grid lg:grid-cols-[minmax(0,1.15fr)_minmax(400px,0.85fr)]">
      <section className="order-2 flex flex-col justify-between px-6 py-8 sm:px-12 lg:order-1 lg:px-16 lg:py-12">
        <div>
          <div className="flex items-center gap-3">
            <span className="grid h-10 w-10 place-items-center rounded-md bg-[#1a3c34] text-lg font-bold text-[#f6f1e8]">G</span>
            <div>
              <div className="text-sm font-semibold text-[#1a3c34]">Galen Corporation</div>
              <div className="text-[11px] font-semibold tracking-[0.18em] text-[#c4a574]">ACCRA</div>
            </div>
          </div>
          <h1 className="mt-10 max-w-xl text-4xl font-semibold leading-[1.05] tracking-tight text-[#1a3c34] sm:text-5xl">The record, the approval, and the payroll figure.</h1>
          <p className="mt-5 max-w-xl text-[#4a524c]">Recruitment, attendance, leave, Ghana PAYE and SSNIT, performance, contracts, and workforce plans share the same employee record.</p>
          <div className="mt-8">
            <div className="text-xs font-semibold tracking-[0.16em] text-[#c4a574]">KEY ISSUES</div>
            <ul className="mt-4 grid max-w-xl gap-2 sm:grid-cols-2">
              {issues.map((issue) => (
                <li key={issue} className="rounded-xl border border-[#e6dfd4] bg-[#fffcf8] px-3 py-3 text-sm text-[#24312c]">
                  <span className="mb-2 block h-0.5 w-6 bg-[#c4622d]" />
                  {issue}
                </li>
              ))}
            </ul>
          </div>
        </div>
        <p className="mt-10 text-xs font-semibold tracking-[0.16em] text-[#c4a574]">AIRPORT CITY · TEMA · KUMASI</p>
      </section>
      <section className="order-1 flex items-center bg-[#1a3c34] px-6 py-10 sm:px-10 lg:order-2 lg:px-12">
        <div className="mx-auto w-full max-w-md rounded-2xl bg-[#fffcf8] p-7 shadow-2xl">
          <div className="text-xs font-semibold tracking-[0.18em] text-[#c4a574]">HUMAN RESOURCES</div>
          <h2 className="mt-3 text-2xl font-semibold text-[#1a3c34]">Sign in</h2>
          <p className="mt-1 text-sm text-[#6e675e]">Use your username or work email. Access follows your role and department.</p>
          <form
            className="mt-6 space-y-4"
            onSubmit={async (event) => {
              event.preventDefault()
              setBusy(true)
              setError('')
              try { await login(email, password) } catch (err) { setError(err instanceof Error ? err.message : 'Sign-in failed') }
              finally { setBusy(false) }
            }}
          >
            <label className="block text-sm">
              <span className="mb-1 block font-semibold text-[#4a524c]">Username or email</span>
              <input className={controlClass} value={email} onChange={(event) => setEmail(event.target.value)} autoComplete="username" required />
            </label>
            <label className="block text-sm">
              <span className="mb-1 block font-semibold text-[#4a524c]">Password</span>
              <input className={controlClass} type="password" value={password} onChange={(event) => setPassword(event.target.value)} autoComplete="current-password" required />
            </label>
            <ErrorNote text={error} />
            <div className="[&>button]:w-full">
              <Button type="submit" disabled={busy}>{busy ? 'Signing in…' : 'Sign in'}</Button>
            </div>
          </form>
          <p className="mt-6 border-t border-[#e6dfd4] pt-4 text-sm leading-snug text-[#1a3c34]">One employee record, from the offer letter through the final payslip.</p>
        </div>
      </section>
    </div>
  )
}

export function PasswordPage() {
  const { refresh, logout } = useAuth()
  const [currentPassword, setCurrent] = useState('')
  const [newPassword, setNext] = useState('')
  const [error, setError] = useState('')
  return (
    <div className="mx-auto mt-16 max-w-md rounded-2xl border border-[#e6dfd4] bg-[#fffcf8] p-6 shadow-sm">
      <h1 className="text-xl font-semibold text-[#1a3c34]">Choose a new password</h1>
      <p className="mt-1 text-sm text-[#6e675e]">Use at least 8 characters, including a letter and a number.</p>
      <form
        className="mt-4 space-y-3"
        onSubmit={async (event) => {
          event.preventDefault()
          try {
            await api('/auth/change-password', { method: 'POST', body: { currentPassword, newPassword } })
            await refresh()
          } catch (err) { setError(err instanceof Error ? err.message : 'Could not update the password') }
        }}
      >
        <input className={controlClass} type="password" value={currentPassword} onChange={(event) => setCurrent(event.target.value)} placeholder="Current password" />
        <input className={controlClass} type="password" value={newPassword} onChange={(event) => setNext(event.target.value)} placeholder="New password" />
        <ErrorNote text={error} />
        <div className="flex gap-2">
          <Button type="submit">Update password</Button>
          <Button tone="ghost" onClick={() => logout()}>Sign out</Button>
        </div>
      </form>
    </div>
  )
}
