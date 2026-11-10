import { Navigate, Outlet, Route, Routes, useLocation } from 'react-router-dom'
import { useAuth } from './auth'
import { Layout } from './components/Layout'
import { LoginPage, PasswordPage } from './pages/Login'
import { DashboardPage } from './pages/Dashboard'
import { EmployeesPage } from './pages/Employees'
import { EmployeeProfilePage } from './pages/EmployeeProfile'
import { RecruitmentPage } from './pages/Recruitment'
import { AttendancePage } from './pages/Attendance'
import { LeavePage } from './pages/Leave'
import { PayrollPage } from './pages/Payroll'
import { PerformancePage } from './pages/Performance'
import { TrainingPage } from './pages/Training'
import { RelationsPage } from './pages/Relations'
import { WelfarePage } from './pages/Welfare'
import { ContractsPage } from './pages/Contracts'
import { WorkforcePage } from './pages/Workforce'
import { ExitPage } from './pages/Exit'
import { ReportsPage } from './pages/Reports'
import { NotificationsPage } from './pages/Notifications'
import { SettingsPage } from './pages/Settings'
import { UsersPage } from './pages/Users'

function employeeMayOpen(path: string, employeeId?: string | null) {
  if (path === '/' || path === '/attendance' || path === '/leave' || path === '/payroll' || path === '/notifications' || path === '/settings') return true
  return Boolean(employeeId && path === `/employees/${employeeId}`)
}

function Shell() {
  const { user, loading } = useAuth()
  const location = useLocation()
  if (loading) return <div className="grid min-h-screen place-items-center text-sm text-[#6e675e]">Loading the HRIS…</div>
  if (!user) return <Navigate to="/login" replace />
  if (user.mustChangePassword) return <PasswordPage />
  if (user.role === 'EMPLOYEE' && !employeeMayOpen(location.pathname, user.employeeId)) return <Navigate to="/" replace />
  return <Layout><Outlet /></Layout>
}

export function App() {
  return (
    <Routes>
      <Route path="/login" element={<LoginPage />} />
      <Route element={<Shell />}>
        <Route path="/" element={<DashboardPage />} />
        <Route path="/employees" element={<EmployeesPage />} />
        <Route path="/employees/:id" element={<EmployeeProfilePage />} />
        <Route path="/recruitment" element={<RecruitmentPage />} />
        <Route path="/attendance" element={<AttendancePage />} />
        <Route path="/clock" element={<Navigate to="/attendance" replace />} />
        <Route path="/leave" element={<LeavePage />} />
        <Route path="/payroll" element={<PayrollPage />} />
        <Route path="/performance" element={<PerformancePage />} />
        <Route path="/training" element={<TrainingPage />} />
        <Route path="/relations" element={<RelationsPage />} />
        <Route path="/welfare" element={<WelfarePage />} />
        <Route path="/contracts" element={<ContractsPage />} />
        <Route path="/workforce" element={<WorkforcePage />} />
        <Route path="/exit" element={<ExitPage />} />
        <Route path="/reports" element={<ReportsPage />} />
        <Route path="/notifications" element={<NotificationsPage />} />
        <Route path="/users" element={<UsersPage />} />
        <Route path="/settings" element={<SettingsPage />} />
      </Route>
    </Routes>
  )
}
