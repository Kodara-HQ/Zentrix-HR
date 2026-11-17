import { Router } from 'express'
import { z } from 'zod'
import { prisma } from '../lib/prisma.js'
import { asyncHandler, asDate, HttpError, parseBody, round2 } from '../lib/http.js'
import { canSeeCompensation, requireRoles, requireUser } from '../lib/access.js'
import { activeCalendar, holidaySet, isWorkingDay } from '../lib/calendar.js'
import { calculatePayroll, CURRENT_PAYE_BANDS, overtimeAmount, PREVIOUS_PAYE_BANDS, proRateFactor, type StatutoryRates, type TaxBand } from '../lib/payroll.js'
import { audit } from '../lib/audit.js'
import { notify, usersByRoles } from '../lib/notify.js'
import { renderPdf } from '../lib/letters.js'
import { fullName } from '../lib/employees.js'

const router = Router()
const payroll = requireRoles('HR_ADMIN', 'PAYROLL_OFFICER', 'HR_MANAGER')
const optional = z.string().trim().optional().or(z.literal('')).transform((value) => value || undefined)

async function ratesFor(year: number, month: number): Promise<StatutoryRates> {
  const on = new Date(Date.UTC(year, month - 1, 15))
  const config = await prisma.statutoryConfig.findFirst({
    where: {
      kind: 'GHANA_PAYROLL',
      effectiveFrom: { lte: on },
      OR: [{ effectiveTo: null }, { effectiveTo: { gte: on } }],
    },
    orderBy: { effectiveFrom: 'desc' },
  })
  if (!config?.bands) throw new HttpError(400, 'No statutory payroll rates are effective for this month.')
  return {
    employeeRate: Number(config.employeeRate || 0),
    employerRate: Number(config.employerRate || 0),
    tier1EmployerRate: Number(config.tier1EmployerRate || 0),
    tier2Rate: Number(config.tier2Rate || 0),
    bands: config.bands as TaxBand[],
  }
}

router.get('/loans', payroll, asyncHandler(async (_req, res) => {
  res.json(await prisma.loan.findMany({
    include: { employee: { select: { firstName: true, lastName: true, employeeNo: true } } },
    orderBy: { createdAt: 'desc' },
  }))
}))

router.post('/loans', payroll, asyncHandler(async (req, res) => {
  const body = parseBody(z.object({
    employeeId: z.string(),
    type: z.string().min(2),
    principal: z.coerce.number().positive(),
    monthlyDeduction: z.coerce.number().positive(),
    startedOn: z.string(),
    notes: optional,
  }), req.body)
  const created = await prisma.loan.create({
    data: {
      employeeId: body.employeeId,
      type: body.type,
      principal: body.principal,
      balance: body.principal,
      monthlyDeduction: body.monthlyDeduction,
      startedOn: asDate(body.startedOn),
      notes: body.notes,
    },
  })
  const now = new Date()
  await processPayrollMonth(now.getUTCFullYear(), now.getUTCMonth() + 1, { allowApproved: true, notes: 'Recalculated after a loan or advance.' })
  res.status(201).json(created)
}))

router.get('/adjustments', payroll, asyncHandler(async (req, res) => {
  const year = Number(req.query.year) || new Date().getUTCFullYear()
  const month = Number(req.query.month) || new Date().getUTCMonth() + 1
  res.json(await prisma.payAdjustment.findMany({
    where: { year, month },
    include: { employee: { select: { firstName: true, lastName: true, employeeNo: true } } },
  }))
}))

router.post('/adjustments', payroll, asyncHandler(async (req, res) => {
  const body = parseBody(z.object({
    employeeId: z.string(),
    year: z.coerce.number().int(),
    month: z.coerce.number().int().min(1).max(12),
    kind: z.enum(['BONUS', 'COMMISSION', 'ALLOWANCE', 'DEDUCTION']),
    label: z.string().min(2),
    amount: z.coerce.number(),
    taxable: z.boolean().default(true),
  }), req.body)
  const created = await prisma.payAdjustment.create({ data: body })
  await processPayrollMonth(body.year, body.month, { allowApproved: true, notes: 'Recalculated after a bonus or deduction.' })
  res.status(201).json(created)
}))

router.delete('/adjustments/:id', payroll, asyncHandler(async (req, res) => {
  const removed = await prisma.payAdjustment.delete({ where: { id: String(req.params.id) } })
  await processPayrollMonth(removed.year, removed.month, { allowApproved: true, notes: 'Recalculated after a bonus or deduction was removed.' })
  res.status(204).end()
}))

router.get('/runs', asyncHandler(async (req, res) => {
  const user = requireUser(req)
  if (!canSeeCompensation(user)) throw new HttpError(403, 'Payroll is restricted.')
  const runs = await prisma.payrollRun.findMany({ orderBy: [{ year: 'desc' }, { month: 'desc' }] })
  res.json(runs)
}))

router.get('/runs/:id', asyncHandler(async (req, res) => {
  const user = requireUser(req)
  if (!canSeeCompensation(user)) throw new HttpError(403, 'Payroll is restricted.')
  const run = await prisma.payrollRun.findUnique({
    where: { id: String(req.params.id) },
    include: {
      payslips: {
        include: { employee: { select: { firstName: true, lastName: true, employeeNo: true, department: { select: { name: true } } } } },
        orderBy: { employee: { lastName: 'asc' } },
      },
    },
  })
  if (!run) throw new HttpError(404, 'Payroll run not found.')
  res.json(run)
}))

export async function ensureStatutoryPayroll() {
  const existing = await prisma.statutoryConfig.count({ where: { kind: 'GHANA_PAYROLL' } })
  if (existing === 0) {
    await prisma.statutoryConfig.create({
      data: {
        name: 'Ghana payroll before September 2026',
        kind: 'GHANA_PAYROLL',
        effectiveFrom: new Date(Date.UTC(2024, 0, 1)),
        effectiveTo: new Date(Date.UTC(2026, 7, 31)),
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
        effectiveFrom: new Date(Date.UTC(2026, 8, 1)),
        employeeRate: 0.055,
        employerRate: 0.13,
        tier1EmployerRate: 0.08,
        tier2Rate: 0.05,
        bands: CURRENT_PAYE_BANDS,
        notes: 'GRA monthly bands effective 1 September 2026. Employee SSNIT is 5.5% of basic salary. Employer SSNIT is 13%, of which 5% is Tier 2.',
      },
    })
  }
  const components = [
    { name: 'Transport allowance', code: 'TRANSPORT', kind: 'ALLOWANCE' as const, taxable: true },
    { name: 'Rent allowance', code: 'RENT', kind: 'ALLOWANCE' as const, taxable: true },
    { name: 'Staff welfare deduction', code: 'WELFARE', kind: 'DEDUCTION' as const, taxable: false, description: 'Post-tax welfare contribution' },
  ]
  for (const component of components) {
    await prisma.payrollComponent.upsert({ where: { code: component.code }, update: {}, create: component })
  }
}

export async function processPayrollMonth(year: number, month: number, options?: { notes?: string; userId?: string; allowApproved?: boolean; approve?: boolean }) {
  await ensureStatutoryPayroll()
  const existing = await prisma.payrollRun.findUnique({ where: { year_month: { year, month } } })
  if (existing?.status === 'APPROVED' && !options?.allowApproved) throw new HttpError(400, 'This month has already been approved.')
  const wasApproved = existing?.status === 'APPROVED'
  const rates = await ratesFor(year, month)
  const calendar = await activeCalendar()
  const monthStart = new Date(Date.UTC(year, month - 1, 1))
  const monthEnd = new Date(Date.UTC(year, month, 0))
  const holidays = await holidaySet(monthStart, monthEnd)
  let monthDays = 0
  for (let cursor = new Date(monthStart); cursor <= monthEnd; cursor = new Date(cursor.getTime() + 86400000)) {
    if (isWorkingDay(cursor, calendar.workDays, holidays)) monthDays += 1
  }
  const employees = await prisma.employee.findMany({
    where: { status: { in: ['PROBATION', 'ACTIVE', 'NOTICE', 'SUSPENDED'] }, dateOfEmployment: { lte: monthEnd } },
    include: { payItems: { include: { component: true } }, loans: { where: { status: 'ACTIVE' } } },
  })
  const adjustments = await prisma.payAdjustment.findMany({ where: { year, month } })
  const overtime = await prisma.overtimeRequest.findMany({
    where: { status: 'APPROVED', date: { gte: monthStart, lte: monthEnd } },
  })
  const separations = await prisma.separation.findMany({
    where: { status: { in: ['APPROVED', 'COMPLETED'] }, lastWorkingDay: { gte: monthStart, lte: monthEnd } },
  })
  const payslips = employees.map((employee) => {
    const end = separations.find((item) => item.employeeId === employee.id)?.lastWorkingDay || monthEnd
    const start = employee.dateOfEmployment > monthStart ? employee.dateOfEmployment : monthStart
    let worked = 0
    for (let cursor = new Date(start); cursor <= end; cursor = new Date(cursor.getTime() + 86400000)) {
      if (isWorkingDay(cursor, calendar.workDays, holidays)) worked += 1
    }
    const factor = proRateFactor(worked, monthDays)
    const basic = round2(Number(employee.basicSalary) * factor)
    let taxableAllowances = 0
    let nonTaxableAllowances = 0
    let relief = 0
    let postTax = 0
    const lines: Array<{ label: string; kind: string; amount: number }> = []
    for (const item of employee.payItems) {
      const amount = round2(Number(item.amount) * factor)
      lines.push({ label: item.component.name, kind: item.component.kind, amount })
      if (item.component.kind === 'RELIEF') relief += amount
      else if (item.component.kind === 'DEDUCTION') postTax += amount
      else if (item.component.taxable) taxableAllowances += amount
      else nonTaxableAllowances += amount
    }
    const ownAdjustments = adjustments.filter((item) => item.employeeId === employee.id)
    let bonus = 0
    let commission = 0
    for (const item of ownAdjustments) {
      const amount = Number(item.amount)
      lines.push({ label: item.label, kind: item.kind, amount })
      if (item.kind === 'BONUS') bonus += amount
      else if (item.kind === 'COMMISSION') commission += amount
      else if (item.kind === 'DEDUCTION') postTax += amount
      else if (item.taxable) taxableAllowances += amount
      else nonTaxableAllowances += amount
    }
    const overtimePay = overtime
      .filter((item) => item.employeeId === employee.id)
      .reduce((sum, item) => sum + overtimeAmount(basic, calendar.hoursPerMonth, Number(item.hours), Number(item.multiplier)), 0)
    if (overtimePay) lines.push({ label: 'Overtime', kind: 'OVERTIME', amount: round2(overtimePay) })
    const loanDeduction = employee.loans.reduce((sum, loan) => sum + Math.min(Number(loan.balance), Number(loan.monthlyDeduction)), 0)
    const result = calculatePayroll({
      basic,
      taxableAllowances,
      nonTaxableAllowances,
      overtime: overtimePay,
      bonus,
      commission,
      relief,
      postTaxDeductions: postTax,
      loanDeduction,
      rates,
    })
    return { employeeId: employee.id, ...result, lines }
  })
  const totals = payslips.reduce((acc, slip) => {
    acc.gross += slip.grossPay
    acc.net += slip.netPay
    acc.paye += slip.paye
    acc.ssnit += slip.employeeSsnit + slip.employerSsnit
    return acc
  }, { gross: 0, net: 0, paye: 0, ssnit: 0 })
  const approve = Boolean(options?.approve || wasApproved)
  const run = await prisma.$transaction(async (tx) => {
    const saved = existing
      ? await tx.payrollRun.update({
          where: { id: existing.id },
          data: {
            status: approve ? 'APPROVED' : 'PENDING_APPROVAL',
            processedBy: options?.userId,
            approvedBy: approve ? existing?.approvedBy || options?.userId : null,
            notes: options?.notes ?? existing.notes,
            totalGross: round2(totals.gross),
            totalNet: round2(totals.net),
            totalPaye: round2(totals.paye),
            totalSsnit: round2(totals.ssnit),
          },
        })
      : await tx.payrollRun.create({
          data: {
            year,
            month,
            status: approve ? 'APPROVED' : 'PENDING_APPROVAL',
            processedBy: options?.userId,
            approvedBy: approve ? options?.userId : null,
            notes: options?.notes,
            totalGross: round2(totals.gross),
            totalNet: round2(totals.net),
            totalPaye: round2(totals.paye),
            totalSsnit: round2(totals.ssnit),
          },
        })
    await tx.payslip.deleteMany({ where: { payrollRunId: saved.id } })
    for (const slip of payslips) {
      await tx.payslip.create({
        data: {
          payrollRunId: saved.id,
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
        },
      })
    }
    return saved
  })
  if (!existing && approve) {
    const employeeUsers = await prisma.user.findMany({ where: { employeeId: { in: payslips.map((slip) => slip.employeeId) } } })
    await notify({
      userIds: employeeUsers.map((user) => user.id),
      title: 'Payslip available',
      body: `Your payslip for ${year}-${String(month).padStart(2, '0')} is ready.`,
      type: 'PAYROLL',
      link: '/payroll',
    })
  } else if (!approve) {
    await notify({
      userIds: await usersByRoles(['HR_ADMIN', 'HR_MANAGER', 'SUPER_ADMIN']),
      title: 'Payroll ready for approval',
      body: `Payroll for ${year}-${String(month).padStart(2, '0')} is waiting for approval.`,
      type: 'PAYROLL',
      link: '/payroll',
    })
  }
  return run
}

export async function ensureCurrentMonthPayroll() {
  await ensureStatutoryPayroll()
  const now = new Date()
  const year = now.getUTCFullYear()
  const month = now.getUTCMonth() + 1
  const existing = await prisma.payrollRun.findUnique({ where: { year_month: { year, month } } })
  if (existing) return existing
  return processPayrollMonth(year, month, { notes: 'Generated automatically for the month.', approve: true })
}

router.post('/runs/process', payroll, asyncHandler(async (req, res) => {
  const body = parseBody(z.object({
    year: z.coerce.number().int(),
    month: z.coerce.number().int().min(1).max(12),
    notes: optional,
  }), req.body)
  const run = await processPayrollMonth(body.year, body.month, { notes: body.notes, userId: req.user?.id })
  await audit(req, 'CREATE', 'PayrollRun', run.id, `Processed payroll ${body.year}-${body.month}`)
  res.status(201).json(run)
}))
router.post('/runs/:id/decision', requireRoles('HR_ADMIN', 'HR_MANAGER'), asyncHandler(async (req, res) => {
  const body = parseBody(z.object({ decision: z.enum(['APPROVED', 'CANCELLED']), note: optional }), req.body)
  const run = await prisma.payrollRun.findUnique({ where: { id: String(req.params.id) }, include: { payslips: true } })
  if (!run) throw new HttpError(404, 'Payroll run not found.')
  if (run.status === 'APPROVED') throw new HttpError(400, 'This payroll run is already approved.')
  if (body.decision === 'APPROVED') {
    await prisma.$transaction(async (tx) => {
      for (const slip of run.payslips) {
        if (Number(slip.loanDeduction) <= 0) continue
        const loans = await tx.loan.findMany({ where: { employeeId: slip.employeeId, status: 'ACTIVE' }, orderBy: { startedOn: 'asc' } })
        let remaining = Number(slip.loanDeduction)
        for (const loan of loans) {
          if (remaining <= 0) break
          const take = Math.min(remaining, Number(loan.balance))
          const balance = round2(Number(loan.balance) - take)
          await tx.loan.update({
            where: { id: loan.id },
            data: { balance, status: balance <= 0 ? 'CLOSED' : 'ACTIVE' },
          })
          remaining = round2(remaining - take)
        }
      }
      await tx.payrollRun.update({
        where: { id: run.id },
        data: { status: 'APPROVED', approvedBy: req.user?.id, notes: body.note || run.notes },
      })
    })
  } else {
    await prisma.payrollRun.update({ where: { id: run.id }, data: { status: 'CANCELLED', notes: body.note } })
  }
  const employeeUsers = await prisma.user.findMany({
    where: { employeeId: { in: run.payslips.map((slip) => slip.employeeId) } },
  })
  if (body.decision === 'APPROVED') {
    await notify({
      userIds: employeeUsers.map((user) => user.id),
      title: 'Payslip available',
      body: `Your payslip for ${run.year}-${String(run.month).padStart(2, '0')} is ready.`,
      type: 'PAYROLL',
      link: '/payroll',
    })
  }
  res.json(await prisma.payrollRun.findUnique({ where: { id: run.id } }))
}))

router.get('/payslips/mine', asyncHandler(async (req, res) => {
  const user = requireUser(req)
  if (!user.employeeId) throw new HttpError(400, 'Your account is not linked to an employee.')
  const slips = await prisma.payslip.findMany({
    where: { employeeId: user.employeeId, payrollRun: { status: 'APPROVED' } },
    include: { payrollRun: true },
    orderBy: { createdAt: 'desc' },
  })
  res.json(slips)
}))

router.get('/payslips/:id/pdf', asyncHandler(async (req, res) => {
  const user = requireUser(req)
  const slip = await prisma.payslip.findUnique({
    where: { id: String(req.params.id) },
    include: { employee: { include: { department: true, jobTitle: true } }, payrollRun: true },
  })
  if (!slip) throw new HttpError(404, 'Payslip not found.')
  if (!canSeeCompensation(user, slip.employeeId)) throw new HttpError(403, 'You cannot view this payslip.')
  if (slip.payrollRun.status !== 'APPROVED' && user.employeeId === slip.employeeId) {
    throw new HttpError(403, 'This payslip is not approved yet.')
  }
  const company = await prisma.company.findFirst()
  const lines = (slip.lines as Array<{ label: string; kind?: string; amount: number }>) || []
  const months = ['January', 'February', 'March', 'April', 'May', 'June', 'July', 'August', 'September', 'October', 'November', 'December']
  const periodName = `${months[slip.payrollRun.month - 1]} ${slip.payrollRun.year}`
  const money = (value: number) => `GHS ${value.toLocaleString('en-GH', { minimumFractionDigits: 2, maximumFractionDigits: 2 })}`
  const buffer = await renderPdf((doc) => {
    doc.page.margins.bottom = 0
    const left = 48
    const width = doc.page.width - left * 2
    doc.rect(0, 0, doc.page.width, 86).fill('#1a3c34')
    doc.rect(0, 86, doc.page.width, 4).fill('#c4a574')
    doc.roundedRect(left, 22, 36, 36, 6).fill('#c4622d')
    doc.fillColor('#ffffff').font('Helvetica-Bold').fontSize(18).text('G', left, 31, { width: 36, align: 'center', lineBreak: false })
    doc.fillColor('#c4a574').font('Helvetica').fontSize(8).text('PAYSLIP', left + 48, 24, { lineBreak: false })
    doc.fillColor('#f6f1e8').font('Helvetica-Bold').fontSize(16).text(company?.name || 'Zentrix Corporation', left + 48, 38, { width: width * 0.55, lineBreak: false })
    doc.font('Helvetica').fontSize(10).text(periodName, left, 58, { width, align: 'right', lineBreak: false })

    doc.rect(left, 108, width, 58).fill('#f6f1e8')
    doc.fillColor('#1a3c34').font('Helvetica-Bold').fontSize(14).text(fullName(slip.employee), left + 14, 118, { width: width - 28, lineBreak: false })
    doc.fillColor('#6e675e').font('Helvetica').fontSize(10).text(`${slip.employee.employeeNo}  ·  ${slip.employee.jobTitle.name}  ·  ${slip.employee.department.name}`, left + 14, 140, { width: width - 28, lineBreak: false })

    const earnings: Array<[string, number, boolean?]> = [
      ['Basic salary', Number(slip.basicSalary)],
      ['Allowances', Number(slip.allowances)],
      ['Overtime', Number(slip.overtimePay)],
      ['Bonus', Number(slip.bonus)],
      ['Commission', Number(slip.commission)],
      ['Gross pay', Number(slip.grossPay), true],
    ]
    const deductions: Array<[string, number, boolean?]> = [
      ['Employee SSNIT', Number(slip.employeeSsnit)],
      ['PAYE', Number(slip.paye)],
      ['Other deductions', Number(slip.otherDeductions)],
      ['Loan / advance', Number(slip.loanDeduction)],
      ['Total deductions', Number(slip.employeeSsnit) + Number(slip.paye) + Number(slip.otherDeductions) + Number(slip.loanDeduction), true],
    ]
    const col = (width - 16) / 2
    const paint = (title: string, rows: Array<[string, number, boolean?]>, x: number, y: number) => {
      doc.fillColor('#1a3c34').font('Helvetica-Bold').fontSize(11).text(title, x, y, { lineBreak: false })
      let rowY = y + 20
      doc.rect(x, rowY, col, 18).fill('#1a3c34')
      doc.fillColor('#f6f1e8').font('Helvetica').fontSize(8).text('ITEM', x + 8, rowY + 5, { lineBreak: false })
      doc.text('AMOUNT', x + col - 78, rowY + 5, { width: 70, align: 'right', lineBreak: false })
      rowY += 18
      rows.forEach((row, index) => {
        if (index % 2 === 0) doc.rect(x, rowY, col, 22).fill('#f6f1e8')
        doc.fillColor(row[2] ? '#1a3c34' : '#24312c').font(row[2] ? 'Helvetica-Bold' : 'Helvetica').fontSize(9)
        doc.text(row[0], x + 8, rowY + 6, { width: col - 96, lineBreak: false })
        doc.text(money(row[1]), x + col - 92, rowY + 6, { width: 80, align: 'right', lineBreak: false })
        rowY += 22
      })
      doc.rect(x, y + 20, col, 18 + rows.length * 22).lineWidth(0.6).strokeColor('#e6dfd4').stroke()
    }
    paint('Earnings', earnings, left, 186)
    paint('Deductions', deductions, left + col + 16, 186)

    const netY = 186 + 20 + 18 + earnings.length * 22 + 22
    doc.rect(left, netY, width, 52).fill('#1a3c34')
    doc.fillColor('#c4a574').font('Helvetica').fontSize(9).text('NET PAY', left + 16, netY + 10, { lineBreak: false })
    doc.fillColor('#f6f1e8').font('Helvetica-Bold').fontSize(18).text(money(Number(slip.netPay)), left, netY + 22, { width: width - 16, align: 'right', lineBreak: false })

    let y = netY + 68
    doc.fillColor('#6e675e').font('Helvetica').fontSize(9)
    doc.text(`Employer SSNIT  GHS ${Number(slip.employerSsnit).toFixed(2)}    ·    Tier 1  GHS ${Number(slip.tier1).toFixed(2)}    ·    Tier 2  GHS ${Number(slip.tier2).toFixed(2)}`, left, y, { width, lineBreak: false })
    y += 14
    doc.text(`Chargeable income  ${money(Number(slip.chargeableIncome))}`, left, y, { width, lineBreak: false })
    if (lines.length) {
      y += 22
      doc.fillColor('#1a3c34').font('Helvetica-Bold').fontSize(10).text('Included in this pay', left, y, { lineBreak: false })
      y += 16
      doc.fillColor('#24312c').font('Helvetica').fontSize(9)
      for (const line of lines) {
        doc.text(`${line.label}    ${money(Number(line.amount))}`, left, y, { width, lineBreak: false })
        y += 14
      }
    }

    doc.rect(0, doc.page.height - 28, doc.page.width, 28).fill('#1a3c34')
    const contact = [company?.phone, company?.email, company?.country].filter(Boolean).join('   ·   ')
    doc.fillColor('#c4a574').font('Helvetica').fontSize(8).text(contact || company?.name || 'Zentrix Corporation', left, doc.page.height - 18, { width, align: 'center', lineBreak: false })
  }, 0)
  const period = `${slip.payrollRun.year}-${String(slip.payrollRun.month).padStart(2, '0')}`
  res.setHeader('Content-Type', 'application/pdf')
  res.setHeader('Content-Disposition', `attachment; filename="payslip-${slip.employee.employeeNo}-${period}.pdf"`)
  res.send(buffer)
}))

export default router
