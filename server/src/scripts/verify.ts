import assert from 'node:assert/strict'

const base = process.env.API_URL || 'http://localhost:4000'

function session() {
  let cookie = ''
  return {
    cookie: () => cookie,
    async login(email: string) {
      const response = await fetch(`${base}/api/auth/login`, {
        method: 'POST',
        headers: { 'content-type': 'application/json' },
        body: JSON.stringify({ email, password: 'Hris@2026' }),
      })
      cookie = (response.headers.getSetCookie?.() || []).map((item) => item.split(';')[0]).join('; ')
      const body = await response.json()
      if (!response.ok) throw new Error(body.error || 'login failed')
      return body
    },
    async call(path: string, method = 'GET', payload?: unknown) {
      const response = await fetch(`${base}${path}`, {
        method,
        headers: { 'content-type': 'application/json', cookie },
        body: payload === undefined ? undefined : JSON.stringify(payload),
      })
      const text = await response.text()
      const body = text ? JSON.parse(text) : null
      return { status: response.status, body, type: response.headers.get('content-type') || '' }
    },
  }
}

const today = new Date()
const iso = (offset: number) => new Date(Date.UTC(today.getUTCFullYear(), today.getUTCMonth(), today.getUTCDate() + offset)).toISOString().slice(0, 10)

const hr = session()
await hr.login('akosua.darko@meridian.gh')
const lookups = await hr.call('/api/settings/lookups')
assert.equal(lookups.status, 200)
const department = lookups.body.departments.find((item: { code?: string; name: string }) => item.name === 'Operations')
const title = lookups.body.jobTitles.find((item: { name: string }) => item.name === 'Operations Officer')
const type = lookups.body.employmentTypes.find((item: { name: string }) => item.name === 'Full-time')
const location = lookups.body.locations[0]
const manager = lookups.body.employees[0]
const created = await hr.call('/api/employees', 'POST', {
  firstName: 'Ama',
  lastName: 'Verification',
  gender: 'FEMALE',
  dateOfBirth: '1994-04-12',
  email: `ama.verification.${Date.now()}@meridian.gh`,
  phone: '+233244009999',
  departmentId: department.id,
  jobTitleId: title.id,
  employmentTypeId: type.id,
  locationId: location.id,
  managerId: manager.id,
  dateOfEmployment: iso(-10),
  basicSalary: 5000,
  createLogin: false,
})
assert.equal(created.status, 201, JSON.stringify(created.body))
assert.ok(created.body.employeeNo)
const balances = await hr.call(`/api/time/leave/balances?employeeId=${created.body.id}`)
assert.ok(balances.body.some((item: { leaveType: { code: string }; entitled: string }) => item.leaveType.code === 'ANNUAL' && Number(item.entitled) > 0))

const promotedTitle = lookups.body.jobTitles.find((item: { name: string }) => item.name === 'Operations Supervisor')
const promoted = await hr.call(`/api/employees/${created.body.id}/actions`, 'POST', {
  type: 'PROMOTE',
  jobTitleId: promotedTitle.id,
  basicSalary: 8400,
  effectiveDate: iso(0),
  notes: 'Verification promotion',
})
assert.equal(promoted.status, 200, JSON.stringify(promoted.body))
assert.equal(Number(promoted.body.basicSalary), 8400)

const annual = lookups.body.leaveTypes.find((item: { code: string }) => item.code === 'ANNUAL')
const leave = await hr.call('/api/time/leave/requests', 'POST', {
  employeeId: created.body.id,
  leaveTypeId: annual.id,
  startDate: iso(30),
  endDate: iso(30),
  reason: 'Verification leave',
})
assert.equal(leave.status, 201, JSON.stringify(leave.body))
const before = await hr.call(`/api/time/leave/balances?employeeId=${created.body.id}`)
const pendingBefore = before.body.find((item: { leaveType: { code: string } }) => item.leaveType.code === 'ANNUAL')
assert.equal(Number(pendingBefore.pending) >= 1, true)

const managerSession = session()
await managerSession.login('kofi.asante@meridian.gh')
const managerDecision = await managerSession.call(`/api/time/leave/requests/${leave.body.id}/decision`, 'POST', { decision: 'APPROVED', note: 'Manager approved' })
assert.equal(managerDecision.status, 200, JSON.stringify(managerDecision.body))

const hrManager = session()
await hrManager.login('kojo.mensah@meridian.gh')
const finalDecision = await hrManager.call(`/api/time/leave/requests/${leave.body.id}/decision`, 'POST', { decision: 'APPROVED', note: 'HR approved' })
assert.equal(finalDecision.body.status, 'APPROVED')
const after = await hr.call(`/api/time/leave/balances?employeeId=${created.body.id}`)
const used = after.body.find((item: { leaveType: { code: string } }) => item.leaveType.code === 'ANNUAL')
assert.equal(Number(used.used) >= 1, true)
assert.equal(Number(used.pending), Number(pendingBefore.pending) - Number(leave.body.days))

const employee = session()
const kwame = await employee.login('kwame.boateng@meridian.gh')
const ownList = await employee.call('/api/employees')
assert.equal(ownList.body.items.length, 1)
const hidden = await employee.call(`/api/employees/${created.body.id}`)
assert.equal(hidden.status, 403)
const cases = await employee.call('/api/workplace/cases')
assert.equal(cases.status, 403)
const clock = await employee.call('/api/time/clock-in', 'POST')
assert.equal(clock.status, 200, JSON.stringify(clock.body))

const punch = await fetch(`${base}/api/time/biometric/punch`, {
  method: 'POST',
  headers: { 'content-type': 'application/json' },
  body: JSON.stringify({ deviceKey: 'device-demo-key', employeeNo: 'EMP-0010', timestamp: new Date().toISOString() }),
})
assert.equal(punch.status, 200)

const exitForNew = await hr.call('/api/workplace/separations', 'POST', {
  employeeId: created.body.id,
  type: 'RESIGNATION',
  reason: 'Verification of the authorised exit.',
  noticeDate: iso(0),
  lastWorkingDay: iso(0),
})
assert.equal(exitForNew.status, 201, JSON.stringify(exitForNew.body))
const approvedExit = await hrManager.call(`/api/workplace/separations/${exitForNew.body.id}/decision`, 'POST', {
  decision: 'APPROVED',
  finalPayNotes: 'No further pay is due.',
})
assert.equal(approvedExit.status, 200, JSON.stringify(approvedExit.body))
const exited = await hr.call(`/api/employees/${created.body.id}`)
assert.equal(exited.body.status, 'RESIGNED')

const month = today.getUTCMonth() + 1
const year = today.getUTCFullYear()
const payrollUser = session()
await payrollUser.login('yaw.owusu@meridian.gh')
const run = await payrollUser.call('/api/payroll/runs/process', 'POST', { year, month, notes: 'Verification run' })
assert.equal(run.status, 201, JSON.stringify(run.body))
const detail = await payrollUser.call(`/api/payroll/runs/${run.body.id}`)
const kwameSlip = detail.body.payslips.find((slip: { employee: { firstName: string } }) => slip.employee.firstName === 'Kwame')
assert.equal(Number(kwameSlip.basicSalary), 5200)
assert.equal(Number(kwameSlip.employeeSsnit), 286)
assert.equal(Number(kwameSlip.paye), 933)

const workbookResponse = await fetch(`${base}/api/reports/employees?format=xlsx`, { headers: { cookie: hr.cookie() } })
assert.equal(workbookResponse.status, 200)
assert.match(workbookResponse.headers.get('content-type') || '', /spreadsheet/)
await workbookResponse.arrayBuffer()

const offerList = await hr.call('/api/recruitment/applicants?search=Mavis')
const offer = offerList.body.items[0].offers.find((item: { status: string }) => item.status === 'SENT')
const hired = await hr.call(`/api/recruitment/offers/${offer.id}/accept`, 'POST')
assert.equal(hired.status, 200, JSON.stringify(hired.body))
assert.ok(hired.body.temporaryPassword)

console.log(`Verified hire, promotion, leave balance, access control, clock-in, exit, payroll, and recruitment for ${kwame.email}.`)
