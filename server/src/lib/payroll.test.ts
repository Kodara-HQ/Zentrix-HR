import assert from 'node:assert/strict'
import { bonusTaxSplit, calculatePaye, calculatePayroll, CURRENT_PAYE_BANDS } from './payroll.js'

const paye = calculatePaye(4725, CURRENT_PAYE_BANDS)
assert.equal(paye, 785.75)

const zero = calculatePaye(588, CURRENT_PAYE_BANDS)
assert.equal(zero, 0)

const top = calculatePaye(50000, CURRENT_PAYE_BANDS)
const manual =
  80 * 0.05 + 100 * 0.1 + 2900 * 0.175 + 16000 * 0.25 + 30332 * 0.3
assert.equal(top, Math.round(manual * 100) / 100)

const result = calculatePayroll({
  basic: 5000,
  taxableAllowances: 0,
  nonTaxableAllowances: 0,
  overtime: 0,
  bonus: 0,
  commission: 0,
  relief: 0,
  postTaxDeductions: 0,
  loanDeduction: 0,
  rates: {
    employeeRate: 0.055,
    employerRate: 0.13,
    tier1EmployerRate: 0.08,
    tier2Rate: 0.05,
    bands: CURRENT_PAYE_BANDS,
  },
})

assert.equal(result.employeeSsnit, 275)
assert.equal(result.chargeableIncome, 4725)
assert.equal(result.paye, 785.75)
assert.equal(result.netPay, 3939.25)
assert.equal(result.employerSsnit, 650)
assert.equal(result.tier2, 250)
assert.equal(result.tier1, 675)

const bonus = bonusTaxSplit(3000, 5000)
assert.equal(bonus.threshold, 9000)
assert.equal(bonus.flatTax, 150)
assert.equal(bonus.taxableExcess, 0)

const excess = bonusTaxSplit(10000, 5000)
assert.equal(excess.flatTax, 450)
assert.equal(excess.taxableExcess, 1000)

console.log('Payroll calculations match the September 2026 Ghana PAYE bands and SSNIT split.')
