import { useEffect, useState, type ReactNode } from 'react'
import { NavLink, useNavigate } from 'react-router-dom'
import {
  Bell, Briefcase, CalendarCheck, CalendarDays, ChartColumn, DoorOpen, FileText,
  GraduationCap, HeartHandshake, LayoutDashboard, LogOut, Menu, Network, Scale,
  Settings, UserRound, Users, Wallet, X,
} from 'lucide-react'
import { can, useAuth } from '../auth'
import { api } from '../lib/api'
import { roleLabel } from '../lib/format'

const items = [
  { to: '/', label: 'Dashboard', icon: LayoutDashboard, roles: ['HR_ADMIN', 'HR_MANAGER', 'DEPARTMENT_MANAGER', 'SUPERVISOR', 'PAYROLL_OFFICER', 'RECRUITMENT_OFFICER'] },
  { to: '/employees', label: 'Employees', icon: Users, roles: ['HR_ADMIN', 'HR_MANAGER', 'DEPARTMENT_MANAGER', 'SUPERVISOR', 'PAYROLL_OFFICER'] },
  { to: '/recruitment', label: 'Recruitment', icon: Briefcase, roles: ['HR_ADMIN', 'HR_MANAGER', 'RECRUITMENT_OFFICER', 'DEPARTMENT_MANAGER'] },
  { to: '/attendance', label: 'Attendance', icon: CalendarCheck, roles: ['HR_ADMIN', 'HR_MANAGER', 'DEPARTMENT_MANAGER', 'SUPERVISOR'] },
  { to: '/leave', label: 'Leave Management', icon: CalendarDays, roles: ['HR_ADMIN', 'HR_MANAGER', 'DEPARTMENT_MANAGER', 'SUPERVISOR'] },
  { to: '/payroll', label: 'Payroll', icon: Wallet, roles: ['HR_ADMIN', 'HR_MANAGER', 'PAYROLL_OFFICER'] },
  { to: '/performance', label: 'Performance', icon: ChartColumn, roles: ['HR_ADMIN', 'HR_MANAGER', 'DEPARTMENT_MANAGER', 'SUPERVISOR'] },
  { to: '/training', label: 'Training', icon: GraduationCap, roles: ['HR_ADMIN', 'HR_MANAGER', 'DEPARTMENT_MANAGER', 'SUPERVISOR'] },
  { to: '/relations', label: 'Employee Relations', icon: Scale, roles: ['HR_ADMIN', 'HR_MANAGER'] },
  { to: '/welfare', label: 'Employee Welfare', icon: HeartHandshake, roles: ['HR_ADMIN', 'HR_MANAGER', 'DEPARTMENT_MANAGER'] },
  { to: '/contracts', label: 'Contracts & Documents', icon: FileText, roles: ['HR_ADMIN', 'HR_MANAGER', 'DEPARTMENT_MANAGER'] },
  { to: '/workforce', label: 'Workforce Planning', icon: Network, roles: ['HR_ADMIN', 'HR_MANAGER', 'DEPARTMENT_MANAGER'] },
  { to: '/exit', label: 'Exit Management', icon: DoorOpen, roles: ['HR_ADMIN', 'HR_MANAGER', 'DEPARTMENT_MANAGER'] },
  { to: '/reports', label: 'Reports & Analytics', icon: ChartColumn, roles: ['HR_ADMIN', 'HR_MANAGER', 'DEPARTMENT_MANAGER', 'PAYROLL_OFFICER', 'SUPERVISOR'] },
  { to: '/notifications', label: 'Notifications', icon: Bell, roles: ['HR_ADMIN', 'HR_MANAGER', 'DEPARTMENT_MANAGER', 'SUPERVISOR', 'PAYROLL_OFFICER', 'RECRUITMENT_OFFICER'] },
  { to: '/users', label: 'User management', icon: UserRound, roles: ['SUPER_ADMIN'] },
  { to: '/settings', label: 'System Settings', icon: Settings, roles: ['HR_ADMIN', 'HR_MANAGER'] },
]

function employeeItems(employeeId?: string | null) {
  return [
    { to: '/', label: 'Home', icon: LayoutDashboard },
    ...(employeeId ? [{ to: `/employees/${employeeId}`, label: 'My profile', icon: UserRound }] : []),
    { to: '/attendance', label: 'My attendance', icon: CalendarCheck },
    { to: '/leave', label: 'My leave', icon: CalendarDays },
    { to: '/payroll', label: 'My payslips', icon: Wallet },
    { to: '/notifications', label: 'Notifications', icon: Bell },
  ]
}

export function Layout({ children }: { children: ReactNode }) {
  const { user, logout } = useAuth()
  const navigate = useNavigate()
  const [collapsed, setCollapsed] = useState(false)
  const [mobile, setMobile] = useState(false)
  const [unread, setUnread] = useState(0)
  const [query, setQuery] = useState('')
  const [company, setCompany] = useState('Zentrix Corporation')
  const staff = user?.role === 'EMPLOYEE'

  useEffect(() => {
    document.title = staff ? 'ESS — Employee Self Service Portal' : company
    const link = document.querySelector<HTMLLinkElement>("link[rel='icon']")
    if (!link) return
    if (!link.dataset.original) link.dataset.original = link.getAttribute('href') || ''
    if (staff) {
      const svg = `<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 64 64"><rect width="64" height="64" rx="12" fill="#1a3c34"/><text x="32" y="42" text-anchor="middle" font-family="Arial,sans-serif" font-size="22" font-weight="700" fill="#f6f1e8">ESS</text></svg>`
      link.href = `data:image/svg+xml,${encodeURIComponent(svg)}`
    } else if (link.dataset.original) {
      link.href = link.dataset.original
    }
  }, [staff, company])

  useEffect(() => {
    api<{ name: string }>('/settings/company').then((row) => row?.name && setCompany(row.name)).catch(() => undefined)
    const load = () => api<{ unread: number }>('/notifications?pageSize=1').then((row) => setUnread(row.unread)).catch(() => undefined)
    load()
    const timer = window.setInterval(load, 30000)
    return () => window.clearInterval(timer)
  }, [])

  const visible = staff ? employeeItems(user?.employeeId) : items.filter((item) => can(user?.role, item.roles))

  return (
    <div className="flex h-screen overflow-hidden">
      {mobile && <button className="fixed inset-0 z-30 bg-[#1a3c34]/40 md:hidden" onClick={() => setMobile(false)} />}
      <aside className={`fixed z-40 flex h-screen shrink-0 flex-col bg-[#1a3c34] text-[#f6f1e8] transition-all md:static ${collapsed ? 'w-[84px]' : 'w-[268px]'} ${mobile ? 'translate-x-0' : '-translate-x-full md:translate-x-0'}`}>
        <div className="flex items-center gap-3 px-4 py-5">
          <div className="grid h-9 w-9 shrink-0 place-items-center rounded-md bg-[#c4622d] font-bold text-white">G</div>
          {!collapsed && (
            <div>
              <div className="text-sm font-semibold leading-tight">{company}</div>
              {staff ? (
                <>
                  <div className="text-xs font-semibold tracking-[0.18em] text-[#c4a574]">ESS</div>
                  <div className="text-[11px] leading-snug text-[#e7dfd2]">Employee Self Service Portal</div>
                </>
              ) : (
                <div className="text-xs tracking-[0.14em] text-[#c4a574]">HUMAN RESOURCES</div>
              )}
            </div>
          )}
          {collapsed && staff && <div className="text-[10px] font-semibold tracking-[0.14em] text-[#c4a574]">ESS</div>}
        </div>
        <nav className="flex min-h-0 flex-1 flex-col px-2 pb-4">
          <div className="space-y-0.5 overflow-auto">
            {visible.map((item) => (
              <NavLink
                key={item.to}
                to={item.to}
                end={item.to === '/'}
                onClick={() => setMobile(false)}
                className={({ isActive }) => `flex items-center gap-3 rounded-lg px-3 py-2 text-sm ${isActive ? 'bg-[#c4622d] text-white' : 'text-[#e7dfd2] hover:bg-white/8'}`}
              >
                <item.icon size={18} />
                {!collapsed && <span>{item.label}</span>}
                {!collapsed && item.to === '/notifications' && unread > 0 && (
                  <span className="ml-auto rounded-full bg-[#f6f1e8] px-1.5 text-xs text-[#1a3c34]">{unread}</span>
                )}
              </NavLink>
            ))}
          </div>
          {staff && (
            <div className="mt-auto border-t border-white/10 pt-3">
              <NavLink
                to="/settings"
                onClick={() => setMobile(false)}
                className={({ isActive }) => `flex items-center gap-3 rounded-lg px-3 py-2 text-sm ${isActive ? 'bg-[#c4622d] text-white' : 'text-[#e7dfd2] hover:bg-white/8'}`}
              >
                <Settings size={18} />
                {!collapsed && <span>Settings</span>}
              </NavLink>
            </div>
          )}
        </nav>
      </aside>
      <div className="flex h-screen min-w-0 flex-1 flex-col">
        <header className="no-print z-20 flex h-16 shrink-0 items-center gap-3 border-b border-[#e6dfd4] bg-[#fffcf8]/95 px-4 backdrop-blur">
          <button className="rounded-lg p-2 text-[#1a3c34] hover:bg-[#f6f1e8] md:hidden" onClick={() => setMobile(true)}><Menu size={18} /></button>
          <button className="hidden rounded-lg p-2 text-[#1a3c34] hover:bg-[#f6f1e8] md:block" onClick={() => setCollapsed((value) => !value)}>
            {collapsed ? <Menu size={18} /> : <X size={18} />}
          </button>
          {staff && (
            <div className="min-w-0 leading-tight">
              <div className="text-xs font-semibold tracking-[0.18em] text-[#c4622d]">ESS</div>
              <div className="truncate text-sm font-semibold text-[#1a3c34]">Employee Self Service Portal</div>
            </div>
          )}
          {!staff && (
            <form
              className="hidden min-w-0 flex-1 md:block"
              onSubmit={(event) => {
                event.preventDefault()
                if (query.trim()) navigate(`/employees?search=${encodeURIComponent(query.trim())}`)
              }}
            >
              <input
                value={query}
                onChange={(event) => setQuery(event.target.value)}
                placeholder="Search employees by name or number"
                className="w-full max-w-md rounded-lg border border-[#e4ddd2] bg-[#f6f1e8] px-3 py-2 text-sm outline-none focus:border-[#c4622d]"
              />
            </form>
          )}
          <div className="ml-auto flex items-center gap-1 sm:gap-2">
            <button className="relative rounded-lg p-2 text-[#1a3c34] hover:bg-[#f6f1e8]" onClick={() => navigate('/notifications')} title="Notifications">
              <Bell size={18} />
              {unread > 0 && <span className="absolute right-1 top-1 h-2 w-2 rounded-full bg-[#c4622d]" />}
            </button>
            <div className="min-w-0 px-1 text-right leading-tight">
              <div className="truncate text-sm font-semibold text-[#1a3c34]">{user?.firstName} {user?.lastName}</div>
              <div className="text-xs text-[#6e675e]">{roleLabel[user?.role || ''] || user?.role}</div>
            </div>
            <button className="rounded-lg p-2 text-[#1a3c34] hover:bg-[#f6f1e8]" title="Sign out" onClick={() => logout().then(() => navigate('/login'))}>
              <LogOut size={18} />
            </button>
          </div>
        </header>
        <main className="min-h-0 min-w-0 flex-1 overflow-y-auto px-4 py-5 md:px-6">{children}</main>
      </div>
    </div>
  )
}
