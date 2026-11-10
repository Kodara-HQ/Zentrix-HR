import { createContext, useContext, useEffect, useState, type ReactNode } from 'react'
import { api } from './lib/api'

export type SessionUser = {
  id: string
  email: string | null
  role: string
  firstName: string
  lastName: string
  employeeId: string | null
  departmentId: string | null
  mustChangePassword: boolean
}

type AuthValue = {
  user: SessionUser | null
  loading: boolean
  login: (email: string, password: string) => Promise<void>
  logout: () => Promise<void>
  refresh: () => Promise<void>
}

const AuthContext = createContext<AuthValue | null>(null)

export function AuthProvider({ children }: { children: ReactNode }) {
  const [user, setUser] = useState<SessionUser | null>(null)
  const [loading, setLoading] = useState(true)

  async function refresh() {
    try {
      setUser(await api<SessionUser>('/auth/me'))
    } catch {
      setUser(null)
    }
  }

  useEffect(() => {
    refresh().finally(() => setLoading(false))
    const onUnauthorized = () => setUser(null)
    window.addEventListener('hris-unauthorized', onUnauthorized)
    return () => window.removeEventListener('hris-unauthorized', onUnauthorized)
  }, [])

  async function login(email: string, password: string) {
    const next = await api<SessionUser>('/auth/login', { method: 'POST', body: { email, password } })
    setUser(next)
  }

  async function logout() {
    await api('/auth/logout', { method: 'POST' })
    setUser(null)
  }

  return <AuthContext.Provider value={{ user, loading, login, logout, refresh }}>{children}</AuthContext.Provider>
}

export function useAuth() {
  const value = useContext(AuthContext)
  if (!value) throw new Error('Auth is unavailable')
  return value
}

export function can(role: string | undefined, roles: string[]) {
  if (!role) return false
  if (role === 'SUPER_ADMIN') return true
  return roles.includes(role)
}
