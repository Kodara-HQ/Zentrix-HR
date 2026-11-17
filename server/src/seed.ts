import { createHash } from 'node:crypto'
import type { EmploymentStatus, Gender, Role } from '@prisma/client'
import { prisma } from './lib/prisma.js'
import { hashPassword } from './lib/auth.js'
import { grantLeaveBalances } from './lib/employees.js'
import { CURRENT_PAYE_BANDS, PREVIOUS_PAYE_BANDS, calculatePayroll } from './lib/payroll.js'

function utc(year: number, month: number, day: number) {
  return new Date(Date.UTC(year, month - 1, day))
}

function shift(days: number) {
  const now = new Date()
  return new Date(Date.UTC(now.getUTCFullYear(), now.getUTCMonth(), now.getUTCDate() + days))
}

function birthday(daysAhead: number, year: number) {
  const date = shift(daysAhead)
  return utc(year, date.getUTCMonth() + 1, date.getUTCDate())
}

export async function seed() {
  const passwordHash = await hashPassword('Hris@2026')
  const year = new Date().getUTCFullYear()
  const company = await prisma.company.create({
    data: {
      name: 'Zentrix Corporation',
      legalName: 'Zentrix Corporation',
      tin: 'C0012345678',
      ssnitEmployerNo: 'ER-ML-20418',
      email: 'hr@meridian.gh',
      phone: '+233 30 200 0100',
      address: '14 Liberation Road, Airport City',
      city: 'Accra',
      country: 'Ghana',
      currency: 'GHS',
      nextEmployeeNo: 19,
    },
  })

  const departments = Object.fromEntries(await Promise.all([
    ['HR', 'Human Resources'],
    ['FIN', 'Finance'],
    ['OPS', 'Operations'],
    ['IT', 'Information Technology'],
    ['COM', 'Commercial'],
    ['WH', 'Warehouse'],
  ].map(async ([code, name]) => {
    const department = await prisma.department.create({
      data: { code, name, description: `${name} department` },
    })
    return [code, department]
  }))) as Record<string, { id: string; name: string }>

  const titles = Object.fromEntries(await Promise.all([
    ['HR Director', 'HR', 'M3'],
    ['HR Manager', 'HR', 'M2'],
    ['HR Officer', 'HR', 'P2'],
    ['Recruitment Officer', 'HR', 'P2'],
    ['Finance Manager', 'FIN', 'M2'],
    ['Payroll Officer', 'FIN', 'P2'],
    ['Accountant', 'FIN', 'P2'],
    ['Operations Manager', 'OPS', 'M2'],
    ['Operations Supervisor', 'OPS', 'P3'],
    ['Operations Officer', 'OPS', 'P1'],
    ['IT Manager', 'IT', 'M2'],
    ['IT Support Officer', 'IT', 'P1'],
    ['Commercial Manager', 'COM', 'M2'],
    ['Sales Executive', 'COM', 'P2'],
    ['Warehouse Supervisor', 'WH', 'P3'],
    ['Warehouse Assistant', 'WH', 'P1'],
    ['Driver', 'OPS', 'P1'],
  ].map(async ([name, code, grade]) => {
    const title = await prisma.jobTitle.create({
      data: { name, grade, departmentId: departments[code].id },
    })
    return [name, title]
  }))) as Record<string, { id: string }>

  const fullTime = await prisma.employmentType.create({ data: { name: 'Full-time', description: 'Open or fixed-term full-time employment' } })
  await prisma.employmentType.create({ data: { name: 'Contract', description: 'Fixed-term contract' } })
  const casual = await prisma.employmentType.create({ data: { name: 'Casual', description: 'Casual engagement' } })

  const locations = Object.fromEntries(await Promise.all([
    ['Airport City', 'Head office', 'Accra', 'Greater Accra', '14 Liberation Road, Airport City'],
    ['Tema Port', 'Tema branch', 'Tema', 'Greater Accra', 'Harbour Area, Tema'],
    ['Kumasi Depot', 'Kumasi branch', 'Kumasi', 'Ashanti', 'Asafo Depot, Kumasi'],
  ].map(async ([name, branch, city, region, address]) => {
    const location = await prisma.workLocation.create({ data: { name, branch, city, region, address } })
    return [name, location]
  }))) as Record<string, { id: string }>

  const people: Array<{
    no: string
    first: string
    last: string
    gender: Gender
    role: Role
    dept: string
    title: string
    location: string
    manager?: string
    salary: number
    status: EmploymentStatus
    phone: string
    dob: Date
    hired: Date
    probation?: Date
    type?: 'casual'
  }> = [
    { no: 'EMP-0001', first: 'Efua', last: 'Boateng', gender: 'FEMALE', role: 'SUPER_ADMIN', dept: 'HR', title: 'HR Director', location: 'Airport City', salary: 18500, status: 'ACTIVE', phone: '+233244100001', dob: birthday(40, 1982), hired: utc(2016, 3, 1) },
    { no: 'EMP-0002', first: 'Kojo', last: 'Mensah', gender: 'MALE', role: 'HR_MANAGER', dept: 'HR', title: 'HR Manager', location: 'Airport City', manager: 'EMP-0001', salary: 12800, status: 'ACTIVE', phone: '+233244100002', dob: birthday(18, 1986), hired: utc(2018, 7, 15) },
    { no: 'EMP-0003', first: 'Akosua', last: 'Darko', gender: 'FEMALE', role: 'HR_ADMIN', dept: 'HR', title: 'HR Officer', location: 'Airport City', manager: 'EMP-0002', salary: 7200, status: 'ACTIVE', phone: '+233244100003', dob: birthday(3, 1993), hired: utc(2021, 2, 1) },
    { no: 'EMP-0004', first: 'Abena', last: 'Sarpong', gender: 'FEMALE', role: 'RECRUITMENT_OFFICER', dept: 'HR', title: 'Recruitment Officer', location: 'Airport City', manager: 'EMP-0002', salary: 6900, status: 'ACTIVE', phone: '+233244100004', dob: birthday(55, 1994), hired: utc(2022, 5, 9) },
    { no: 'EMP-0005', first: 'Adwoa', last: 'Frimpong', gender: 'FEMALE', role: 'DEPARTMENT_MANAGER', dept: 'FIN', title: 'Finance Manager', location: 'Airport City', salary: 14200, status: 'ACTIVE', phone: '+233244100005', dob: birthday(70, 1984), hired: utc(2017, 1, 10) },
    { no: 'EMP-0006', first: 'Yaw', last: 'Owusu', gender: 'MALE', role: 'PAYROLL_OFFICER', dept: 'FIN', title: 'Payroll Officer', location: 'Airport City', manager: 'EMP-0005', salary: 8100, status: 'ACTIVE', phone: '+233244100006', dob: birthday(22, 1990), hired: utc(2019, 9, 2) },
    { no: 'EMP-0007', first: 'Nana', last: 'Yeboah', gender: 'MALE', role: 'EMPLOYEE', dept: 'FIN', title: 'Accountant', location: 'Airport City', manager: 'EMP-0005', salary: 7600, status: 'ACTIVE', phone: '+233244100007', dob: birthday(90, 1991), hired: utc(2020, 4, 6) },
    { no: 'EMP-0008', first: 'Kofi', last: 'Asante', gender: 'MALE', role: 'DEPARTMENT_MANAGER', dept: 'OPS', title: 'Operations Manager', location: 'Airport City', salary: 13600, status: 'ACTIVE', phone: '+233244100008', dob: birthday(8, 1983), hired: utc(2015, 11, 2) },
    { no: 'EMP-0009', first: 'Ama', last: 'Adjei', gender: 'FEMALE', role: 'SUPERVISOR', dept: 'OPS', title: 'Operations Supervisor', location: 'Airport City', manager: 'EMP-0008', salary: 8400, status: 'ACTIVE', phone: '+233244100009', dob: birthday(10, 1990), hired: utc(2019, 3, 18) },
    { no: 'EMP-0010', first: 'Kwame', last: 'Boateng', gender: 'MALE', role: 'EMPLOYEE', dept: 'OPS', title: 'Operations Officer', location: 'Airport City', manager: 'EMP-0009', salary: 5200, status: 'ACTIVE', phone: '+233244100010', dob: birthday(3, 1996), hired: utc(2023, 1, 9) },
    { no: 'EMP-0011', first: 'Esi', last: 'Quaye', gender: 'FEMALE', role: 'EMPLOYEE', dept: 'OPS', title: 'Operations Officer', location: 'Kumasi Depot', manager: 'EMP-0009', salary: 5000, status: 'ACTIVE', phone: '+233244100011', dob: birthday(10, 1997), hired: utc(2023, 6, 1) },
    { no: 'EMP-0012', first: 'Kojo', last: 'Appiah', gender: 'MALE', role: 'DEPARTMENT_MANAGER', dept: 'IT', title: 'IT Manager', location: 'Airport City', salary: 13100, status: 'ACTIVE', phone: '+233244100012', dob: birthday(100, 1988), hired: utc(2018, 8, 20) },
    { no: 'EMP-0013', first: 'Linda', last: 'Osei', gender: 'FEMALE', role: 'EMPLOYEE', dept: 'IT', title: 'IT Support Officer', location: 'Airport City', manager: 'EMP-0012', salary: 4800, status: 'PROBATION', phone: '+233244100013', dob: birthday(14, 1999), hired: shift(-70), probation: shift(12) },
    { no: 'EMP-0014', first: 'Samuel', last: 'Tetteh', gender: 'MALE', role: 'DEPARTMENT_MANAGER', dept: 'COM', title: 'Commercial Manager', location: 'Airport City', salary: 12500, status: 'ACTIVE', phone: '+233244100014', dob: birthday(120, 1985), hired: utc(2017, 5, 2) },
    { no: 'EMP-0015', first: 'Priscilla', last: 'Aidoo', gender: 'FEMALE', role: 'EMPLOYEE', dept: 'COM', title: 'Sales Executive', location: 'Kumasi Depot', manager: 'EMP-0014', salary: 6100, status: 'ACTIVE', phone: '+233244100015', dob: birthday(25, 1995), hired: utc(2024, 2, 12) },
    { no: 'EMP-0016', first: 'Ibrahim', last: 'Fuseini', gender: 'MALE', role: 'SUPERVISOR', dept: 'WH', title: 'Warehouse Supervisor', location: 'Tema Port', manager: 'EMP-0008', salary: 7800, status: 'ACTIVE', phone: '+233244100016', dob: birthday(45, 1989), hired: utc(2019, 10, 1) },
    { no: 'EMP-0017', first: 'Rashid', last: 'Mensah', gender: 'MALE', role: 'EMPLOYEE', dept: 'WH', title: 'Warehouse Assistant', location: 'Tema Port', manager: 'EMP-0016', salary: 3200, status: 'ACTIVE', phone: '+233244100017', dob: birthday(6, 2000), hired: utc(2024, 8, 5), type: 'casual' },
    { no: 'EMP-0018', first: 'Yaw', last: 'Darko', gender: 'MALE', role: 'EMPLOYEE', dept: 'OPS', title: 'Driver', location: 'Tema Port', manager: 'EMP-0008', salary: 3400, status: 'RESIGNED', phone: '+233244100018', dob: birthday(200, 1987), hired: utc(2018, 1, 15) },
  ]

  const byNo = new Map<string, { id: string; salary: number; status: EmploymentStatus; dept: string }>()
  for (const person of people) {
    const employee = await prisma.employee.create({
      data: {
        employeeNo: person.no,
        firstName: person.first,
        lastName: person.last,
        gender: person.gender,
        dateOfBirth: person.dob,
        email: `${person.first}.${person.last}@meridian.gh`.toLowerCase(),
        phone: person.phone,
        address: person.location === 'Tema Port' ? 'Community 5, Tema' : person.location === 'Kumasi Depot' ? 'Asafo, Kumasi' : 'East Legon, Accra',
        city: person.location === 'Tema Port' ? 'Tema' : person.location === 'Kumasi Depot' ? 'Kumasi' : 'Accra',
        region: person.location === 'Kumasi Depot' ? 'Ashanti' : 'Greater Accra',
        emergencyName: `${person.last} family`,
        emergencyPhone: person.phone.replace('100', '200'),
        emergencyRelation: 'Sibling',
        nextOfKinName: `${person.first} ${person.last} Snr`,
        nextOfKinPhone: person.phone.replace('100', '300'),
        nextOfKinRelation: 'Parent',
        departmentId: departments[person.dept].id,
        jobTitleId: titles[person.title].id,
        employmentTypeId: person.type === 'casual' ? casual.id : fullTime.id,
        locationId: locations[person.location].id,
        status: person.status,
        dateOfEmployment: person.hired,
        probationEndDate: person.probation,
        basicSalary: person.salary,
        ssnitNumber: `C${person.no.slice(-4)}${person.dob.getUTCFullYear()}`,
        tinNumber: `P00${person.no.slice(-4)}392`,
      },
    })
    byNo.set(person.no, { id: employee.id, salary: person.salary, status: person.status, dept: person.dept })
    await prisma.user.create({
      data: {
        email: employee.email,
        passwordHash,
        role: person.role,
        firstName: person.first,
        lastName: person.last,
        employeeId: employee.id,
        departmentId: departments[person.dept].id,
        locationId: locations[person.location].id,
        isActive: person.status !== 'RESIGNED',
      },
    })
    await prisma.employeeEvent.create({
      data: {
        employeeId: employee.id,
        type: 'HIRE',
        title: 'Joined Zentrix Corporation',
        effectiveDate: person.hired,
        toValue: { employeeNo: person.no, title: person.title },
      },
    })
    await prisma.employeeEducation.create({
      data: {
        employeeId: employee.id,
        institution: person.dept === 'IT' ? 'Kwame Nkrumah University of Science and Technology' : 'University of Ghana',
        qualification: person.salary > 10000 ? 'MBA' : 'Bachelor\'s degree',
        field: person.dept === 'FIN' ? 'Accounting' : person.dept === 'IT' ? 'Computer Science' : 'Business Administration',
        endYear: person.dob.getUTCFullYear() + 23,
      },
    })
    await prisma.employeeSkill.create({
      data: { employeeId: employee.id, name: person.dept === 'IT' ? 'Systems support' : 'Customer operations', level: 'Proficient', years: 4 },
    })
    await prisma.employeeBankAccount.create({
      data: {
        employeeId: employee.id,
        bankName: person.no.endsWith('6') || person.no.endsWith('7') ? 'GCB Bank' : 'Ecobank Ghana',
        branch: 'Airport City',
        accountName: `${person.first} ${person.last}`,
        accountNumber: `144100${person.no.slice(-4)}`,
        isPrimary: true,
      },
    })
    const endDate = person.no === 'EMP-0015' ? shift(20) : person.no === 'EMP-0016' ? shift(45) : person.no === 'EMP-0017' ? shift(200) : null
    await prisma.contract.create({
      data: {
        employeeId: employee.id,
        type: endDate ? 'Fixed-term contract' : 'Employment contract',
        title: `${endDate ? 'Fixed-term contract' : 'Employment contract'} — ${person.first} ${person.last}`,
        startDate: person.hired,
        endDate,
        status: person.status === 'RESIGNED' ? 'ENDED' : 'ACTIVE',
      },
    })
  }

  for (const person of people) {
    if (!person.manager) continue
    await prisma.employee.update({
      where: { id: byNo.get(person.no)!.id },
      data: { managerId: byNo.get(person.manager)!.id },
    })
  }

  await prisma.department.update({ where: { id: departments.HR.id }, data: { headId: byNo.get('EMP-0001')!.id } })
  await prisma.department.update({ where: { id: departments.FIN.id }, data: { headId: byNo.get('EMP-0005')!.id } })
  await prisma.department.update({ where: { id: departments.OPS.id }, data: { headId: byNo.get('EMP-0008')!.id } })

  const transport = await prisma.payrollComponent.create({
    data: { name: 'Transport allowance', code: 'TRANSPORT', kind: 'ALLOWANCE', taxable: true },
  })
  const rent = await prisma.payrollComponent.create({
    data: { name: 'Rent allowance', code: 'RENT', kind: 'ALLOWANCE', taxable: true },
  })
  await prisma.payrollComponent.create({
    data: { name: 'Staff welfare deduction', code: 'WELFARE', kind: 'DEDUCTION', taxable: false, description: 'Post-tax welfare contribution' },
  })
  for (const person of people) {
    if (person.status === 'RESIGNED') continue
    await prisma.employeePayItem.create({
      data: { employeeId: byNo.get(person.no)!.id, componentId: transport.id, amount: person.salary > 10000 ? 800 : 400 },
    })
    if (person.salary > 10000) {
      await prisma.employeePayItem.create({
        data: { employeeId: byNo.get(person.no)!.id, componentId: rent.id, amount: 1200 },
      })
    }
  }

  await prisma.statutoryConfig.create({
    data: {
      name: 'Ghana payroll before September 2026',
      kind: 'GHANA_PAYROLL',
      effectiveFrom: utc(2024, 1, 1),
      effectiveTo: utc(2026, 8, 31),
      employeeRate: 0.055,
      employerRate: 0.13,
      tier1EmployerRate: 0.08,
      tier2Rate: 0.05,
      bands: PREVIOUS_PAYE_BANDS,
      notes: 'Monthly resident bands in force before the Income Tax (Amendment) Act, 2026 (Act 1178).',
    },
  })
  await prisma.statutoryConfig.create({
    data: {
      name: 'Ghana payroll from September 2026',
      kind: 'GHANA_PAYROLL',
      effectiveFrom: utc(2026, 9, 1),
      employeeRate: 0.055,
      employerRate: 0.13,
      tier1EmployerRate: 0.08,
      tier2Rate: 0.05,
      bands: CURRENT_PAYE_BANDS,
      notes: 'GRA monthly bands effective 1 September 2026. Employee SSNIT is 5.5% of basic salary. Employer SSNIT is 13%, of which 5% is Tier 2.',
    },
  })

  const leaveTypes = [
    ['Annual leave', 'ANNUAL', 25, 'WORKING_DAYS', true, true, null, false, '#0f766e'],
    ['Sick leave', 'SICK', 5, 'WORKING_DAYS', true, true, null, true, '#b45309'],
    ['Maternity leave', 'MATERNITY', 84, 'CALENDAR_DAYS', true, true, 'FEMALE', true, '#9d174d'],
    ['Paternity leave', 'PATERNITY', 10, 'WORKING_DAYS', true, true, 'MALE', true, '#1d4e89'],
    ['Study leave', 'STUDY', 5, 'WORKING_DAYS', true, true, null, true, '#0f766e'],
  ] as const
  for (const [name, code, days, mode, affects, paid, gender, reason, color] of leaveTypes) {
    await prisma.leaveType.create({
      data: {
        name, code, daysPerYear: days, countMode: mode, affectsBalance: affects, paid, gender, requiresReason: reason, color,
      },
    })
  }

  for (const person of people) {
    await grantLeaveBalances(byNo.get(person.no)!.id, person.hired, person.gender)
  }

  const annual = await prisma.leaveType.findUnique({ where: { code: 'ANNUAL' } })
  const sick = await prisma.leaveType.findUnique({ where: { code: 'SICK' } })
  const kwame = byNo.get('EMP-0010')!
  const esi = byNo.get('EMP-0011')!
  if (annual && sick) {
    await prisma.leaveRequest.create({
      data: {
        employeeId: kwame.id,
        leaveTypeId: annual.id,
        startDate: shift(7),
        endDate: shift(9),
        days: 3,
        reason: 'Family travel to Cape Coast.',
        status: 'PENDING_MANAGER',
      },
    })
    await prisma.leaveBalance.update({
      where: { employeeId_leaveTypeId_year: { employeeId: kwame.id, leaveTypeId: annual.id, year } },
      data: { pending: 3 },
    })
    await prisma.leaveRequest.create({
      data: {
        employeeId: esi.id,
        leaveTypeId: sick.id,
        startDate: shift(0),
        endDate: shift(1),
        days: 2,
        reason: 'Medical review at Komfo Anokye Teaching Hospital.',
        status: 'APPROVED',
        decisionNote: 'Approved with the medical note on file.',
      },
    })
    await prisma.leaveBalance.update({
      where: { employeeId_leaveTypeId_year: { employeeId: esi.id, leaveTypeId: sick.id, year } },
      data: { used: 2 },
    })
  }

  const dayShift = await prisma.shiftPattern.create({
    data: { name: 'General day', startTime: '08:00', endTime: '17:00', graceMinutes: 15, workDays: [1, 2, 3, 4, 5] },
  })
  const warehouse = await prisma.shiftPattern.create({
    data: { name: 'Warehouse morning', startTime: '07:00', endTime: '15:00', graceMinutes: 10, workDays: [1, 2, 3, 4, 5, 6] },
  })
  await prisma.workingCalendar.create({
    data: {
      name: 'Head office calendar',
      workDays: [1, 2, 3, 4, 5],
      startTime: '08:00',
      endTime: '17:00',
      hoursPerDay: 8,
      hoursPerMonth: 176,
      graceMinutes: 15,
      overtimeWeekday: 1.5,
      overtimeWeekend: 2,
      isActive: true,
    },
  })
  for (const person of people) {
    if (person.status === 'RESIGNED') continue
    await prisma.shiftAssignment.create({
      data: {
        employeeId: byNo.get(person.no)!.id,
        shiftId: person.dept === 'WH' ? warehouse.id : dayShift.id,
        effectiveFrom: person.hired,
      },
    })
  }

  const holidayDates: Array<[string, Date]> = [
    ["New Year's Day", utc(year, 1, 1)],
    ['Constitution Day', utc(year, 1, 7)],
    ['Independence Day', utc(year, 3, 6)],
    ['May Day', utc(year, 5, 1)],
    ["Founders' Day", utc(year, 8, 4)],
    ['Kwame Nkrumah Memorial Day', utc(year, 9, 21)],
    ['Christmas Day', utc(year, 12, 25)],
    ['Boxing Day', utc(year, 12, 26)],
  ]
  if (year === 2026) {
    holidayDates.push(['Good Friday', utc(2026, 4, 3)], ['Easter Monday', utc(2026, 4, 6)], ["Farmers' Day", utc(2026, 12, 4)])
  }
  for (const [name, date] of holidayDates) {
    await prisma.holiday.create({ data: { name, date, recurring: true } })
  }

  for (const person of people) {
    if (person.status !== 'ACTIVE' && person.status !== 'PROBATION') continue
    for (let offset = -28; offset <= -1; offset += 1) {
      const date = shift(offset)
      const dayOfWeek = date.getUTCDay()
      if (dayOfWeek === 0 || dayOfWeek === 6) continue
      const seedKey = `${person.no}-${offset}`
      const roll = seedKey.split('').reduce((sum, char) => sum + char.charCodeAt(0), 0) % 20
      const status = roll === 0 ? 'ABSENT' : roll < 3 ? 'LATE' : 'PRESENT'
      const startHour = person.dept === 'WH' ? 7 : 8
      await prisma.attendanceRecord.create({
        data: {
          employeeId: byNo.get(person.no)!.id,
          date,
          status,
          lateMinutes: status === 'LATE' ? 25 : 0,
          clockIn: status === 'ABSENT' ? null : new Date(Date.UTC(date.getUTCFullYear(), date.getUTCMonth(), date.getUTCDate(), startHour, status === 'LATE' ? 35 : 5)),
          clockOut: status === 'ABSENT' ? null : new Date(Date.UTC(date.getUTCFullYear(), date.getUTCMonth(), date.getUTCDate(), startHour + 9, 2)),
          source: 'MANUAL',
        },
      })
    }
  }

  const payrollAnchor = new Date(Date.UTC(new Date().getUTCFullYear(), new Date().getUTCMonth() - 1, 1))
  const payrollYear = payrollAnchor.getUTCFullYear()
  const payrollMonth = payrollAnchor.getUTCMonth() + 1
  const useCurrentBands = payrollYear > 2026 || (payrollYear === 2026 && payrollMonth >= 9)
  const rates = {
    employeeRate: 0.055,
    employerRate: 0.13,
    tier1EmployerRate: 0.08,
    tier2Rate: 0.05,
    bands: useCurrentBands ? CURRENT_PAYE_BANDS : PREVIOUS_PAYE_BANDS,
  }
  await prisma.loan.create({
    data: {
      employeeId: kwame.id,
      type: 'Salary advance',
      principal: 2000,
      balance: 1800,
      monthlyDeduction: 200,
      startedOn: utc(payrollYear, payrollMonth, 1),
      notes: 'One installment already recovered on the previous payroll.',
    },
  })
  await prisma.overtimeRequest.create({
    data: {
      employeeId: kwame.id,
      date: utc(payrollYear, payrollMonth, 12),
      hours: 4,
      multiplier: 1.5,
      reason: 'Tema vessel discharge ran past the shift.',
      status: 'APPROVED',
    },
  })
  const slips = []
  for (const person of people) {
    if (person.status === 'RESIGNED') continue
    const allowance = (person.salary > 10000 ? 800 : 400) + (person.salary > 10000 ? 1200 : 0)
    const overtime = person.no === 'EMP-0010' ? calculateOvertime(person.salary, 4, 1.5) : 0
    const result = calculatePayroll({
      basic: person.salary,
      taxableAllowances: allowance,
      nonTaxableAllowances: 0,
      overtime,
      bonus: 0,
      commission: 0,
      relief: 0,
      postTaxDeductions: 0,
      loanDeduction: person.no === 'EMP-0010' ? 200 : 0,
      rates,
    })
    slips.push({ employeeId: byNo.get(person.no)!.id, ...result, lines: [{ label: 'Transport allowance', amount: person.salary > 10000 ? 800 : 400 }] })
  }
  const totals = slips.reduce((sum, slip) => ({
    gross: sum.gross + slip.grossPay,
    net: sum.net + slip.netPay,
    paye: sum.paye + slip.paye,
    ssnit: sum.ssnit + slip.employeeSsnit + slip.employerSsnit,
  }), { gross: 0, net: 0, paye: 0, ssnit: 0 })
  await prisma.payrollRun.create({
    data: {
      year: payrollYear,
      month: payrollMonth,
      status: 'APPROVED',
      notes: 'Monthly payroll approved by HR.',
      totalGross: totals.gross,
      totalNet: totals.net,
      totalPaye: totals.paye,
      totalSsnit: totals.ssnit,
      payslips: {
        create: slips.map((slip) => ({
          employeeId: slip.employeeId,
          basicSalary: slip.basicSalary,
          allowances: slip.allowances,
          overtimePay: slip.overtimePay,
          bonus: slip.bonus,
          commission: slip.commission,
          grossPay: slip.grossPay,
          employeeSsnit: slip.employeeSsnit,
          employerSsnit: slip.employerSsnit,
          tier1: slip.tier1,
          tier2: slip.tier2,
          reliefs: slip.reliefs,
          chargeableIncome: slip.chargeableIncome,
          paye: slip.paye,
          otherDeductions: slip.otherDeductions,
          loanDeduction: slip.loanDeduction,
          netPay: slip.netPay,
          lines: slip.lines,
        })),
      },
    },
  })

  const itTitle = titles['IT Support Officer'].id
  await prisma.manpowerRequisition.create({
    data: {
      departmentId: departments.IT.id,
      jobTitleId: itTitle,
      locationId: locations['Airport City'].id,
      headcount: 1,
      justification: 'The service desk cannot cover Airport City and Kumasi with one officer.',
      status: 'PENDING',
    },
  })
  const salesReq = await prisma.manpowerRequisition.create({
    data: {
      departmentId: departments.COM.id,
      jobTitleId: titles['Sales Executive'].id,
      locationId: locations['Tema Port'].id,
      headcount: 1,
      justification: 'Tema port clients need a resident commercial officer.',
      status: 'APPROVED',
    },
  })
  const vacancy = await prisma.vacancy.create({
    data: {
      requisitionId: salesReq.id,
      title: 'Sales Executive, Tema',
      departmentId: departments.COM.id,
      jobTitleId: titles['Sales Executive'].id,
      locationId: locations['Tema Port'].id,
      description: 'Own customer accounts at Tema Port and grow contract logistics revenue.',
      requirements: 'A degree in business or a related field, a valid driver\'s licence, and two years in sales or customer operations.',
      openings: 1,
      status: 'OPEN',
      salaryMin: 5500,
      salaryMax: 7000,
      advertisingCost: 1800,
      agencyCost: 0,
      openedOn: shift(-21),
    },
  })
  const shortlisted = await prisma.applicant.create({
    data: {
      vacancyId: vacancy.id,
      firstName: 'Joseph',
      lastName: 'Owusu',
      email: 'joseph.owusu@example.com',
      phone: '+233201110001',
      source: 'Company website',
      status: 'SHORTLISTED',
      screeningScore: 81,
    },
  })
  await prisma.applicant.create({
    data: {
      vacancyId: vacancy.id,
      firstName: 'Akua',
      lastName: 'Mensah',
      email: 'akua.mensah@example.com',
      phone: '+233201110002',
      source: 'Referral',
      status: 'SCREENED',
      screeningScore: 72,
    },
  })
  const offered = await prisma.applicant.create({
    data: {
      vacancyId: vacancy.id,
      firstName: 'Mavis',
      lastName: 'Asare',
      email: 'mavis.asare@example.com',
      phone: '+233201110003',
      source: 'Job board',
      status: 'OFFERED',
      screeningScore: 88,
      notes: 'Strong port-sales experience in Tema.',
    },
  })
  await prisma.interview.create({
    data: {
      applicantId: shortlisted.id,
      scheduledAt: new Date(shift(2).getTime() + 10 * 60 * 60 * 1000),
      mode: 'In person',
      location: 'Airport City, boardroom 2',
      interviewerId: byNo.get('EMP-0014')!.id,
    },
  })
  await prisma.jobOffer.create({
    data: {
      applicantId: offered.id,
      salary: 6400,
      startDate: shift(21),
      status: 'SENT',
      expiresOn: shift(14),
      notes: 'Offer includes transport allowance under the current payroll structure.',
    },
  })

  const cycle = await prisma.performanceCycle.create({
    data: {
      name: `${year} second-half review`,
      periodType: 'HALF_YEARLY',
      startDate: shift(-50),
      endDate: shift(12),
      status: 'OPEN',
    },
  })
  await prisma.objective.create({
    data: {
      cycleId: cycle.id,
      level: 'DEPARTMENT',
      departmentId: departments.OPS.id,
      title: 'On-time delivery',
      kpi: 'Deliveries arriving within the promised window',
      target: '95%',
      weight: 40,
    },
  })
  await prisma.objective.create({
    data: {
      cycleId: cycle.id,
      level: 'INDIVIDUAL',
      employeeId: kwame.id,
      title: 'Customer claims',
      description: 'Reduce avoidable delivery claims on Airport City routes.',
      kpi: 'Open claims older than 14 days',
      target: 'Fewer than 3',
      weight: 30,
      actual: '4 open claims',
      score: 3,
    },
  })
  await prisma.appraisal.create({
    data: {
      cycleId: cycle.id,
      employeeId: kwame.id,
      status: 'DRAFT',
    },
  })
  await prisma.improvementPlan.create({
    data: {
      employeeId: kwame.id,
      goals: 'Close delivery claims within 10 days and complete the claims clinic.',
      support: 'Weekly review with the operations supervisor.',
      reviewDate: shift(30),
    },
  })
  await prisma.ratingScale.create({
    data: {
      name: 'Five-point performance scale',
      min: 1,
      max: 5,
      isDefault: true,
      labels: { '1': 'Unsatisfactory', '2': 'Needs improvement', '3': 'Meets expectations', '4': 'Exceeds expectations', '5': 'Outstanding' },
    },
  })

  const provider = await prisma.trainingProvider.create({
    data: { name: 'Ghana Institute of Freight and Logistics', contact: 'Training desk', email: 'training@gifl.example', phone: '+233302555010' },
  })
  const program = await prisma.trainingProgram.create({
    data: {
      title: 'Supervisory skills for shift leaders',
      providerId: provider.id,
      description: 'Coaching, incident reporting, and shift handover.',
      startDate: shift(2),
      endDate: shift(3),
      cost: 4500,
      location: 'Airport City training room',
      capacity: 12,
      status: 'PLANNED',
    },
  })
  await prisma.trainingEnrollment.create({ data: { programId: program.id, employeeId: byNo.get('EMP-0009')!.id } })
  await prisma.trainingNeed.create({
    data: { employeeId: kwame.id, skill: 'Claims negotiation', priority: 'High', justification: 'Open claims are above the team target.' },
  })
  await prisma.certification.create({
    data: {
      employeeId: byNo.get('EMP-0013')!.id,
      name: 'ITIL Foundation',
      issuer: 'PeopleCert',
      credentialNo: 'ITIL-20491',
      issuedOn: shift(-300),
      expiresOn: shift(20),
    },
  })
  await prisma.developmentPlan.create({
    data: {
      employeeId: byNo.get('EMP-0009')!.id,
      title: 'Ready for operations manager',
      goals: 'Lead the Kumasi depot review and complete the supervisory programme.',
      targetDate: shift(120),
    },
  })

  await prisma.disciplinaryCase.create({
    data: {
      caseNo: 'DC-0001',
      employeeId: esi.id,
      incidentDate: shift(-12),
      description: 'A customer delivery left the Kumasi depot without the required checklist.',
      status: 'HEARING',
      hearingAt: new Date(shift(2).getTime() + 9 * 60 * 60 * 1000),
      confidentialNotes: 'Witness statements are stored with HR. Do not circulate beyond the case officers.',
    },
  })
  await prisma.warning.create({
    data: {
      employeeId: esi.id,
      type: 'WRITTEN',
      content: 'The missing checklist is a breach of the depot procedure. A written warning is recorded.',
      issuedOn: shift(-10),
    },
  })
  await prisma.grievance.create({
    data: {
      employeeId: byNo.get('EMP-0017')!.id,
      category: 'Shift pattern',
      description: 'Saturday warehouse shifts were extended without a published roster.',
      status: 'INVESTIGATING',
    },
  })

  await prisma.welfareRequest.create({
    data: {
      employeeId: kwame.id,
      type: 'Medical support',
      description: 'Request for support with a specialist review not fully covered by the clinic.',
      amount: 800,
      status: 'PENDING',
    },
  })
  await prisma.staffFeedback.create({
    data: { anonymous: true, category: 'Tools', message: 'The depot handhelds lose signal inside the Tema shed.' },
  })
  const survey = await prisma.survey.create({
    data: {
      title: 'October workplace pulse',
      description: 'A short check on workload and management support.',
      questions: [
        { id: 'workload', prompt: 'How manageable was your workload this month?', kind: 'rating' },
        { id: 'support', prompt: 'Did you get the support you needed from your supervisor?', kind: 'rating' },
        { id: 'comment', prompt: 'What should we improve next?', kind: 'text' },
      ],
      isOpen: true,
      closesOn: shift(20),
    },
  })
  await prisma.surveyResponse.create({
    data: { surveyId: survey.id, employeeId: byNo.get('EMP-0009')!.id, answers: { workload: 4, support: 5, comment: 'Handover notes are clearer this month.' } },
  })
  await prisma.recognition.create({
    data: {
      employeeId: byNo.get('EMP-0009')!.id,
      title: 'Depot star',
      description: 'Kept the Airport City evening handover accurate through a busy import week.',
      awardedOn: shift(-5),
    },
  })
  await prisma.engagementActivity.create({
    data: { title: 'End-of-year staff durbar', activityDate: shift(18), location: 'Airport City courtyard', description: 'Awards, long-service recognition, and team lunch.' },
  })
  await prisma.suggestion.create({
    data: { employeeId: kwame.id, message: 'Publish the Tema shuttle times on the staff notice board and in the HRIS.', status: 'NEW' },
  })

  await prisma.separation.create({
    data: {
      employeeId: byNo.get('EMP-0018')!.id,
      type: 'RESIGNATION',
      reason: 'Relocated outside Greater Accra.',
      noticeDate: shift(-70),
      lastWorkingDay: shift(-40),
      status: 'COMPLETED',
      interviewNotes: 'Yaw left on good terms. He suggested a spare driver roster for Tema.',
      finalPayNotes: 'Final pay, accrued leave, and SSNIT were settled with the last payroll before exit.',
      clearance: { create: [
        { item: 'Staff identity card', returned: true },
        { item: 'Vehicle keys', returned: true },
        { item: 'Fuel card', returned: true },
      ] },
    },
  })

  for (const [code, headcount, cost] of [
    ['HR', 4, 540000],
    ['FIN', 3, 420000],
    ['OPS', 5, 610000],
    ['IT', 3, 390000],
    ['COM', 3, 360000],
    ['WH', 4, 280000],
  ] as const) {
    await prisma.manpowerBudget.create({
      data: {
        year,
        departmentId: departments[code].id,
        approvedHeadcount: headcount,
        estimatedCost: cost,
        notes: 'Approved establishment for the current year.',
      },
    })
  }
  await prisma.successionPlan.create({
    data: {
      positionTitle: 'Operations Manager',
      departmentId: departments.OPS.id,
      incumbentId: byNo.get('EMP-0008')!.id,
      successorId: byNo.get('EMP-0009')!.id,
      readiness: 'Ready in 12 months',
      critical: true,
      notes: 'Ama should complete the supervisory programme and lead one depot review.',
    },
  })

  const categories = [
    ['Employment contract', false],
    ['Appointment letter', false],
    ['Promotion letter', false],
    ['Transfer letter', false],
    ['Warning letter', true],
    ['Confirmation letter', false],
    ['Termination letter', true],
    ['Identification', true],
    ['Academic certificate', false],
    ['Professional licence', false],
    ['Curriculum vitae', false],
  ] as const
  for (const [name, confidential] of categories) {
    await prisma.documentCategory.create({ data: { name, confidential } })
  }

  for (const [key, label, steps] of [
    ['LEAVE', 'Leave approval', ['MANAGER', 'HR']],
    ['PAYROLL', 'Payroll approval', ['PAYROLL_OFFICER', 'HR_MANAGER']],
    ['REQUISITION', 'Manpower requisition', ['HR_MANAGER']],
    ['SEPARATION', 'Separation', ['HR_MANAGER']],
    ['OVERTIME', 'Overtime', ['MANAGER']],
    ['ATTENDANCE', 'Attendance correction', ['MANAGER']],
  ] as const) {
    await prisma.workflowConfig.create({ data: { key, label, steps } })
  }

  for (const [eventKey, label] of [
    ['LEAVE', 'Leave decisions'],
    ['ATTENDANCE', 'Attendance corrections'],
    ['RECRUITMENT', 'Recruitment updates'],
    ['PAYROLL', 'Payroll processing'],
    ['APPRAISAL', 'Appraisal deadlines'],
    ['TRAINING', 'Training schedules'],
    ['BIRTHDAY', 'Birthdays'],
    ['CONTRACT_EXPIRY', 'Contract expiration'],
    ['PROBATION', 'Probation completion'],
    ['CERTIFICATION', 'Certification expiration'],
    ['HEARING', 'Disciplinary hearings'],
    ['GRIEVANCE', 'Grievances'],
    ['WELFARE', 'Welfare requests'],
    ['RECOGNITION', 'Recognition'],
    ['SEPARATION', 'Exits'],
    ['DISCIPLINE', 'Warnings'],
    ['EMPLOYEE_STATUS', 'Employment status'],
  ] as const) {
    await prisma.notificationSetting.create({ data: { eventKey, label, inApp: true, email: false } })
  }

  await prisma.biometricDevice.create({
    data: {
      name: 'Airport City lobby',
      locationId: locations['Airport City'].id,
      apiKeyHash: createHash('sha256').update(process.env.BIOMETRIC_DEVICE_KEY || 'device-demo-key').digest('hex'),
    },
  })

  console.log(`Seeded ${company.name} with ${people.length} employees.`)
}

function calculateOvertime(basic: number, hours: number, multiplier: number) {
  return Math.round((hours * (basic / 176) * multiplier) * 100) / 100
}
