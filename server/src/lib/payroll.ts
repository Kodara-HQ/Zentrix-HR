import { round2 } from './http.js'

export type TaxBand = { width: number | null; rate: number }

export type StatutoryRates = {
  employeeRate: number
  employerRate: number
  tier1EmployerRate: number
  tier2Rate: number
  bands: TaxBand[]
}

export const CURRENT_PAYE_BANDS: TaxBand[] = [
  { width: 588, rate: 0 },
  { width: 80, rate: 0.05 },
  { width: 100, rate: 0.1 },
  { width: 2900, rate: 0.175 },
  { width: 16000, rate: 0.25 },
  { width: 30332, rate: 0.3 },
  { width: null, rate: 0.35 },
]

export const PREVIOUS_PAYE_BANDS: TaxBand[] = [
  { width: 490, rate: 0 },
  { width: 110, rate: 0.05 },
  { width: 130, rate: 0.1 },
  { width: 3000, rate: 0.175 },
  { width: 16395, rate: 0.25 },
  { width: 29875, rate: 0.3 },
  { width: null, rate: 0.35 },
]

function toCents(value: number) {
  return Math.round((value + Number.EPSILON) * 100)
}

export function calculatePaye(chargeableIncome: number, bands: TaxBand[]) {
  let remaining = Math.max(0, toCents(chargeableIncome))
  let taxCents = 0
  for (const band of bands) {
    if (remaining <= 0) break
    const width = band.width == null ? remaining : toCents(band.width)
    const slice = Math.min(remaining, width)
    taxCents += Math.round(slice * band.rate)
    remaining -= slice
  }
  return taxCents / 100
}

export function bonusTaxSplit(bonus: number, monthlyBasic: number) {
  const threshold = round2(monthlyBasic * 12 * 0.15)
  if (bonus <= 0) return { flatTax: 0, taxableExcess: 0, threshold }
  if (bonus <= threshold) return { flatTax: round2(bonus * 0.05), taxableExcess: 0, threshold }
  return { flatTax: round2(threshold * 0.05), taxableExcess: round2(bonus - threshold), threshold }
}

export type PayrollInput = {
  basic: number
  taxableAllowances: number
  nonTaxableAllowances: number
  overtime: number
  bonus: number
  commission: number
  relief: number
  postTaxDeductions: number
  loanDeduction: number
  rates: StatutoryRates
  unpaid?: boolean
}

export type PayrollResult = {
  basicSalary: number
  allowances: number
  overtimePay: number
  bonus: number
  commission: number
  grossPay: number
  employeeSsnit: number
  employerSsnit: number
  tier1: number
  tier2: number
  reliefs: number
  chargeableIncome: number
  paye: number
  bonusTax: number
  otherDeductions: number
  loanDeduction: number
  netPay: number
  employerCost: number
}

export function calculatePayroll(input: PayrollInput): PayrollResult {
  if (input.unpaid) {
    return {
      basicSalary: 0,
      allowances: 0,
      overtimePay: 0,
      bonus: 0,
      commission: 0,
      grossPay: 0,
      employeeSsnit: 0,
      employerSsnit: 0,
      tier1: 0,
      tier2: 0,
      reliefs: 0,
      chargeableIncome: 0,
      paye: 0,
      bonusTax: 0,
      otherDeductions: 0,
      loanDeduction: 0,
      netPay: 0,
      employerCost: 0,
    }
  }
  const basic = round2(input.basic)
  const allowances = round2(input.taxableAllowances + input.nonTaxableAllowances)
  const overtimePay = round2(input.overtime)
  const bonus = round2(input.bonus)
  const commission = round2(input.commission)
  const grossPay = round2(basic + allowances + overtimePay + bonus + commission)
  const employeeSsnit = round2(basic * input.rates.employeeRate)
  const employerSsnit = round2(basic * input.rates.employerRate)
  const tier2 = round2(basic * input.rates.tier2Rate)
  const tier1Employer = round2(basic * input.rates.tier1EmployerRate)
  const tier1 = round2(employeeSsnit + tier1Employer)
  const reliefCap = round2(basic * 0.165)
  const reliefs = round2(Math.min(Math.max(0, input.relief), reliefCap))
  const bonusSplit = bonusTaxSplit(bonus, basic)
  const chargeableIncome = round2(
    Math.max(
      0,
      basic +
        input.taxableAllowances +
        overtimePay +
        commission +
        bonusSplit.taxableExcess -
        employeeSsnit -
        reliefs,
    ),
  )
  const graduated = calculatePaye(chargeableIncome, input.rates.bands)
  const paye = round2(graduated + bonusSplit.flatTax)
  const otherDeductions = round2(input.postTaxDeductions)
  const loanDeduction = round2(Math.max(0, input.loanDeduction))
  const netPay = round2(grossPay - employeeSsnit - paye - otherDeductions - loanDeduction)
  const employerCost = round2(grossPay + employerSsnit)
  return {
    basicSalary: basic,
    allowances,
    overtimePay,
    bonus,
    commission,
    grossPay,
    employeeSsnit,
    employerSsnit,
    tier1,
    tier2,
    reliefs,
    chargeableIncome,
    paye,
    bonusTax: bonusSplit.flatTax,
    otherDeductions,
    loanDeduction,
    netPay,
    employerCost,
  }
}

export function overtimeAmount(basic: number, hoursPerMonth: number, hours: number, multiplier: number) {
  if (hoursPerMonth <= 0) return 0
  const hourly = basic / hoursPerMonth
  return round2(hours * hourly * multiplier)
}

export function proRateFactor(workedDays: number, monthDays: number) {
  if (monthDays <= 0) return 1
  return Math.min(1, Math.max(0, workedDays / monthDays))
}
