import { prisma } from '../lib/prisma.js'
import { notify, usersByRoles } from '../lib/notify.js'
import { addDays, todayUtc } from '../lib/http.js'
import { fullName } from '../lib/employees.js'
import { ensureCurrentMonthPayroll } from '../routes/payroll.js'

export async function runReminders() {
  const today = todayUtc()
  const soon = addDays(today, 30)
  const hr = await usersByRoles(['HR_ADMIN', 'HR_MANAGER', 'SUPER_ADMIN'])

  const birthdays = await prisma.employee.findMany({
    where: { status: { in: ['ACTIVE', 'PROBATION', 'NOTICE'] } },
  })
  for (const employee of birthdays) {
    const dob = employee.dateOfBirth
    const next = new Date(Date.UTC(today.getUTCFullYear(), dob.getUTCMonth(), dob.getUTCDate()))
    if (next < today) next.setUTCFullYear(today.getUTCFullYear() + 1)
    const days = Math.round((next.getTime() - today.getTime()) / 86400000)
    if (days >= 0 && days <= 7) {
      await notify({
        userIds: hr,
        type: 'BIRTHDAY',
        title: 'Upcoming birthday',
        body: `${fullName(employee)} has a birthday on ${next.toISOString().slice(0, 10)}.`,
        link: `/employees/${employee.id}`,
        dedupeKey: `birthday:${employee.id}:${next.toISOString().slice(0, 10)}`,
      })
    }
  }

  const contracts = await prisma.contract.findMany({
    where: { status: 'ACTIVE', endDate: { gte: today, lte: addDays(today, 60) } },
    include: { employee: true },
  })
  for (const contract of contracts) {
    await notify({
      userIds: hr,
      type: 'CONTRACT_EXPIRY',
      title: 'Contract nearing expiration',
      body: `${fullName(contract.employee)} — ${contract.title} ends on ${contract.endDate?.toISOString().slice(0, 10)}.`,
      link: '/contracts',
      dedupeKey: `contract:${contract.id}:${contract.endDate?.toISOString().slice(0, 10)}`,
    })
  }

  const probation = await prisma.employee.findMany({
    where: { status: 'PROBATION', probationEndDate: { gte: today, lte: addDays(today, 14) } },
  })
  for (const employee of probation) {
    await notify({
      userIds: hr,
      type: 'PROBATION',
      title: 'Probation ending',
      body: `${fullName(employee)} completes probation on ${employee.probationEndDate?.toISOString().slice(0, 10)}.`,
      link: `/employees/${employee.id}`,
      dedupeKey: `probation:${employee.id}`,
    })
  }

  const certificates = await prisma.certification.findMany({
    where: { expiresOn: { gte: today, lte: soon } },
    include: { employee: true },
  })
  for (const certificate of certificates) {
    await notify({
      userIds: hr,
      type: 'CERTIFICATION',
      title: 'Certification expiring',
      body: `${certificate.name} for ${fullName(certificate.employee)} expires on ${certificate.expiresOn?.toISOString().slice(0, 10)}.`,
      link: '/training',
      dedupeKey: `cert:${certificate.id}`,
    })
  }

  const training = await prisma.trainingProgram.findMany({
    where: { status: { in: ['PLANNED', 'ONGOING'] }, startDate: { gte: today, lte: addDays(today, 3) } },
    include: { enrollments: true },
  })
  for (const program of training) {
    const enrolled = await prisma.user.findMany({
      where: { employeeId: { in: program.enrollments.map((row) => row.employeeId) } },
    })
    await notify({
      userIds: [...hr, ...enrolled.map((user) => user.id)],
      type: 'TRAINING',
      title: 'Training coming up',
      body: `${program.title} starts on ${program.startDate.toISOString().slice(0, 10)}.`,
      link: '/training',
      dedupeKey: `training:${program.id}`,
    })
  }

  const hearings = await prisma.disciplinaryCase.findMany({
    where: { status: { in: ['OPEN', 'INVESTIGATING', 'HEARING'] }, hearingAt: { gte: today, lte: addDays(today, 3) } },
  })
  for (const item of hearings) {
    await notify({
      userIds: hr,
      type: 'HEARING',
      title: 'Disciplinary hearing',
      body: `Case ${item.caseNo} is scheduled for ${item.hearingAt?.toISOString().slice(0, 16).replace('T', ' ')}.`,
      link: '/relations',
      dedupeKey: `hearing:${item.id}:${item.hearingAt?.toISOString()}`,
    })
  }

  const cycles = await prisma.performanceCycle.findMany({
    where: { status: 'OPEN', endDate: { gte: today, lte: addDays(today, 14) } },
  })
  for (const cycle of cycles) {
    await notify({
      userIds: hr,
      type: 'APPRAISAL',
      title: 'Appraisal deadline',
      body: `${cycle.name} closes on ${cycle.endDate.toISOString().slice(0, 10)}.`,
      link: '/performance',
      dedupeKey: `cycle:${cycle.id}`,
    })
  }

  const dueSeparations = await prisma.separation.findMany({
    where: { status: 'APPROVED', lastWorkingDay: { lte: today } },
    include: { employee: true },
  })
  for (const separation of dueSeparations) {
    const status =
      separation.type === 'RETIREMENT' ? 'RETIRED' : separation.type === 'TERMINATION' ? 'TERMINATED' : 'RESIGNED'
    if (separation.employee.status !== status) {
      await prisma.employee.update({ where: { id: separation.employeeId }, data: { status } })
      await prisma.employeeEvent.create({
        data: {
          employeeId: separation.employeeId,
          type: 'STATUS_CHANGE',
          title: 'Employment ended',
          description: separation.reason,
          effectiveDate: separation.lastWorkingDay,
          toValue: { status },
        },
      })
      const account = await prisma.user.findUnique({ where: { employeeId: separation.employeeId } })
      if (account) await prisma.user.update({ where: { id: account.id }, data: { isActive: false } })
    }
    await prisma.separation.update({ where: { id: separation.id }, data: { status: 'COMPLETED' } })
  }

  const confirmed = await prisma.employee.findMany({
    where: { status: 'PROBATION', probationEndDate: { lt: today } },
  })
  for (const employee of confirmed) {
    await prisma.employee.update({ where: { id: employee.id }, data: { status: 'ACTIVE' } })
    await prisma.employeeEvent.create({
      data: {
        employeeId: employee.id,
        type: 'CONFIRMATION',
        title: 'Probation completed',
        effectiveDate: employee.probationEndDate || today,
        toValue: { status: 'ACTIVE' },
      },
    })
  }

  await ensureCurrentMonthPayroll()
}
