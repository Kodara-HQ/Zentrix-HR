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

  return (
    <div className="grid min-h-screen place-items-center bg-[#f6f1e8] px-4 py-10">
      <div className="w-full max-w-md">
        <div className="mb-6 flex items-center justify-center gap-3">
          <span className="grid h-10 w-10 place-items-center rounded-md bg-[#1a3c34] text-lg font-bold text-[#f6f1e8]">G</span>
          <div>
            <div className="text-sm font-semibold text-[#1a3c34]">Zentrix Corporation</div>
            <div className="text-[11px] font-semibold tracking-[0.18em] text-[#c4a574]">HUMAN RESOURCES</div>
          </div>
        </div>
        <div className="rounded-2xl border border-[#e6dfd4] bg-[#fffcf8] p-7 shadow-sm">
          <h1 className="text-2xl font-semibold text-[#1a3c34]">Sign in</h1>
          <p className="mt-1 text-sm text-[#6e675e]">Use your username or work email.</p>
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
        </div>
      </div>
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
