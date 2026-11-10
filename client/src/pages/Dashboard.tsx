import { useState } from 'react'
import { Link } from 'react-router-dom'
import { Bar, BarChart, CartesianGrid, Cell, Line, LineChart, Pie, PieChart, ResponsiveContainer, Tooltip, XAxis, YAxis } from 'recharts'
import { api } from '../lib/api'
import { formatDate, titleCase } from '../lib/format'
import { useEffect } from 'react'
import { useAuth } from '../auth'
import { PageHeader, Panel, SearchSelect, Stat } from '../components/ui'

type DashboardData = {
  totals: Record<string, number>
  gender: Array<{ name: string; value: number }>
  byDepartment: Array<{ name: string; count: number }>
  attendanceTrend: Array<{ date: string; rate: number }>
  birthdays: Array<{ id: string; name: string; date: string; days: number }>
  contracts: Array<{ id: string; employee: string; title: string; endDate: string }>
  pending: Record<string, number>
  personal: { employeeNo: string; status: string; name: string } | null
}

const colors = ['#1a3c34', '#c4622d', '#c4a574', '#8c4e3a', '#3d6b5a', '#e2c9a0']

function EmployeeHome() {
  const { user } = useAuth()
  const [profile, setProfile] = useState<{ employeeNo?: string; status?: string; photoFileId?: string; firstName?: string; lastName?: string; department?: { name: string }; jobTitle?: { name: string }; location?: { name: string; city: string } } | null>(null)

  useEffect(() => {
  if (!user?.employeeId) return

  api<NonNullable<typeof profile>>(
    `/employees/${user.employeeId}`
  )
    .then(setProfile)
    .catch(() => undefined)
}, [user?.employeeId])

  return (
    <div>
      <div className="mb-4 flex items-center gap-4">
        <Link to={user?.employeeId ? `/employees/${user.employeeId}` : '/'} title="Open your profile to change your photo" className="shrink-0">
          {profile?.photoFileId ? (
            <img src={`/api/files/${profile.photoFileId}`} alt="" className="h-16 w-16 rounded-full border border-[#e6dfd4] object-cover" />
          ) : (
            <div className="grid h-16 w-16 place-items-center rounded-full bg-[#1a3c34] text-lg font-semibold text-white">{(user?.firstName || ' ')[0]}{(user?.lastName || ' ')[0]}</div>
          )}
        </Link>
        <PageHeader title={`Hello, ${user?.firstName || 'there'}`} text="Employee Self Service Portal. Your attendance comes from the fingerprint clock. Request leave and download your payslips here." />
      </div>
      {!user?.employeeId && <p className="text-sm text-[#9f2d2d]">This sign-in is not linked to an employee record. Ask HR to connect it before you can apply for leave.</p>}
      {profile && (
        <div className="grid gap-3 sm:grid-cols-2 xl:grid-cols-4">
          <Stat label="Employee number" value={profile.employeeNo || '—'} />
          <Stat label="Status" value={titleCase(profile.status)} />
          <Stat label="Job" value={profile.jobTitle?.name || '—'} hint={profile.department?.name} />
          <Stat label="Location" value={profile.location?.city || '—'} hint={profile.location?.name} />
        </div>
      )}
      <div className="mt-4 grid gap-3 sm:grid-cols-2 lg:grid-cols-3">
        {[
          ['/attendance', 'My attendance', 'Punches recorded by the fingerprint clock.'],
          ['/leave', 'My leave', 'Your balances and your own leave requests.'],
          ['/payroll', 'My payslips', 'Payslips that have already been approved.'],
          user?.employeeId ? [`/employees/${user.employeeId}`, 'My profile', 'Your job, contact details, and documents.'] : null,
          ['/notifications', 'Notifications', 'Messages sent to you.'],
        ].filter((item) => item != null).map((item) => (
          <Link key={item[0]} to={item[0]} className="rounded-2xl border border-[#e6dfd4] bg-[#fffcf8] p-4 hover:border-[#c4a574]">
            <div className="font-semibold text-[#1a3c34]">{item[1]}</div>
            <p className="mt-1 text-sm text-[#6e675e]">{item[2]}</p>
          </Link>
        ))}
      </div>
    </div>
  )
}

export function DashboardPage() {
  const { user } = useAuth()
  const [from, setFrom] = useState('')
  const [to, setTo] = useState('')
  const [departmentId, setDepartmentId] = useState('')
  const [departments, setDepartments] = useState<Array<{ id: string; name: string }>>([])
  const [data, setData] = useState<DashboardData | null>(null)
  const [error, setError] = useState('')

  useEffect(() => {
    if (user?.role === 'EMPLOYEE') return
    api<{ departments: Array<{ id: string; name: string }> }>('/settings/lookups').then((rows) => setDepartments(rows.departments)).catch(() => undefined)
  }, [user?.role])

  useEffect(() => {
    if (user?.role === 'EMPLOYEE') return
    const params = new URLSearchParams()
    if (from) params.set('from', from)
    if (to) params.set('to', to)
    if (departmentId) params.set('departmentId', departmentId)
    api<DashboardData>(`/dashboard?${params.toString()}`).then(setData).catch((err) => setError(err.message))
  }, [from, to, departmentId, user?.role])

  const totals = data?.totals
  if (user?.role === 'EMPLOYEE') return <EmployeeHome />
  return (
    <div>
      <PageHeader title="Dashboard" text="Workforce position, attendance, and the approvals waiting on HR.">
        <input className="rounded-lg border border-[#e4ddd2] px-3 py-2 text-sm" type="date" value={from} onChange={(event) => setFrom(event.target.value)} />
        <input className="rounded-lg border border-[#e4ddd2] px-3 py-2 text-sm" type="date" value={to} onChange={(event) => setTo(event.target.value)} />
        <SearchSelect className="w-52" value={departmentId} onChange={setDepartmentId} placeholder="All departments" options={departments.map((department) => ({ value: department.id, label: department.name }))} />
      </PageHeader>
      {error && <p className="mb-3 text-sm text-[#9f2d2d]">{error}</p>}
      <div className="grid gap-3 sm:grid-cols-2 xl:grid-cols-4">
        <Stat label="Total employees" value={totals?.employees ?? '—'} />
        <Stat label="Active" value={totals?.active ?? '—'} />
        <Stat label="Inactive or exited" value={totals?.inactive ?? '—'} />
        <Stat label="New in period" value={totals?.newHires ?? '—'} />
        <Stat label="On leave today" value={totals?.onLeave ?? '—'} />
        <Stat label="On probation" value={totals?.probation ?? '—'} />
        <Stat label="Contracts expiring" value={totals?.contractsExpiring ?? '—'} hint="Within 60 days" />
        <Stat label="Turnover" value={totals ? `${totals.turnover}%` : '—'} hint="Exits against average headcount" />
        <Stat label="Attendance" value={totals ? `${totals.attendanceRate}%` : '—'} hint="Last 30 days" />
        <Stat label="Absenteeism" value={totals ? `${totals.absenteeismRate}%` : '—'} hint="Last 30 days" />
      </div>
      <div className="mt-4 grid gap-4 xl:grid-cols-3">
        <Panel className="xl:col-span-2">
          <h2 className="mb-3 font-semibold">Headcount by department</h2>
          <div className="h-64">
            <ResponsiveContainer width="100%" height="100%">
              <BarChart data={data?.byDepartment || []}>
                <CartesianGrid stroke="#e6dfd4" vertical={false} />
                <XAxis dataKey="name" tick={{ fontSize: 12 }} />
                <YAxis allowDecimals={false} />
                <Tooltip />
                <Bar dataKey="count" fill="#1a3c34" radius={[4, 4, 0, 0]} />
              </BarChart>
            </ResponsiveContainer>
          </div>
        </Panel>
        <Panel>
          <h2 className="mb-3 font-semibold">Gender distribution</h2>
          <div className="h-64">
            <ResponsiveContainer width="100%" height="100%">
              <PieChart>
                <Pie data={data?.gender || []} dataKey="value" nameKey="name" innerRadius={48} outerRadius={80}>
                  {(data?.gender || []).map((entry, index) => <Cell key={entry.name} fill={colors[index % colors.length]} />)}
                </Pie>
                <Tooltip />
              </PieChart>
            </ResponsiveContainer>
          </div>
        </Panel>
      </div>
      <div className="mt-4 grid gap-4 lg:grid-cols-3">
        <Panel className="lg:col-span-2">
          <h2 className="mb-3 font-semibold">Attendance rate</h2>
          <div className="h-56">
            <ResponsiveContainer width="100%" height="100%">
              <LineChart data={data?.attendanceTrend || []}>
                <CartesianGrid stroke="#e6dfd4" vertical={false} />
                <XAxis dataKey="date" tick={{ fontSize: 11 }} />
                <YAxis unit="%" />
                <Tooltip />
                <Line type="monotone" dataKey="rate" stroke="#c4622d" strokeWidth={2} dot={false} />
              </LineChart>
            </ResponsiveContainer>
          </div>
        </Panel>
        <Panel>
          <h2 className="mb-3 font-semibold">Pending approvals</h2>
          <ul className="space-y-2 text-sm">
            <li><Link to="/leave">Leave requests · {data?.pending.leave ?? 0}</Link></li>
            <li><Link to="/recruitment">Recruitment requests · {data?.pending.recruitment ?? 0}</Link></li>
            <li><Link to="/payroll">Payroll runs · {data?.pending.payroll ?? 0}</Link></li>
            <li><Link to="/attendance">Attendance corrections · {data?.pending.corrections ?? 0}</Link></li>
            <li><Link to="/attendance">Overtime · {data?.pending.overtime ?? 0}</Link></li>
            <li><Link to="/exit">Separations · {data?.pending.separations ?? 0}</Link></li>
            <li><Link to="/welfare">Welfare · {data?.pending.welfare ?? 0}</Link></li>
          </ul>
        </Panel>
      </div>
      <div className="mt-4 grid gap-4 md:grid-cols-2">
        <Panel>
          <h2 className="mb-3 font-semibold">Upcoming birthdays</h2>
          <ul className="space-y-2 text-sm">
            {(data?.birthdays || []).map((item) => (
              <li key={item.id} className="flex justify-between gap-3"><Link to={`/employees/${item.id}`}>{item.name}</Link><span className="text-[#6e675e]">{formatDate(item.date)}</span></li>
            ))}
            {!data?.birthdays.length && <li className="text-[#6e675e]">No birthdays in the next 30 days.</li>}
          </ul>
        </Panel>
        <Panel>
          <h2 className="mb-3 font-semibold">Contract renewals</h2>
          <ul className="space-y-2 text-sm">
            {(data?.contracts || []).map((item) => (
              <li key={item.id} className="flex justify-between gap-3"><span>{item.employee}</span><span className="text-[#6e675e]">{formatDate(item.endDate)}</span></li>
            ))}
            {!data?.contracts.length && <li className="text-[#6e675e]">No contracts expire in the next 60 days.</li>}
          </ul>
        </Panel>
      </div>
    </div>
  )
}
