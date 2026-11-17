<?php

namespace Tests\Unit;

use App\Support\PayrollMath;
use PHPUnit\Framework\TestCase;

/** The same figures server/src/lib/payroll.test.ts checks in the Node version, plus a few more. */
class PayrollMathTest extends TestCase
{
    private const RATES = ['employee_rate' => 0.055, 'employer_rate' => 0.13, 'tier1_employer_rate' => 0.08, 'tier2_rate' => 0.05, 'bands' => PayrollMath::CURRENT_PAYE_BANDS];

    private function slip(array $overrides = []): array
    {
        return PayrollMath::calculate($overrides + [
            'basic' => 5000, 'taxable_allowances' => 0, 'non_taxable_allowances' => 0, 'overtime' => 0, 'bonus' => 0,
            'commission' => 0, 'relief' => 0, 'post_tax_deductions' => 0, 'loan_deduction' => 0,
        ], self::RATES);
    }

    public function test_paye_bands_match_the_node_version(): void
    {
        $this->assertSame(785.75, PayrollMath::paye(4725, PayrollMath::CURRENT_PAYE_BANDS));
        $this->assertEquals(0, PayrollMath::paye(588, PayrollMath::CURRENT_PAYE_BANDS));
        $manual = 80 * 0.05 + 100 * 0.1 + 2900 * 0.175 + 16000 * 0.25 + 30332 * 0.3;
        $this->assertSame(round($manual, 2), PayrollMath::paye(50000, PayrollMath::CURRENT_PAYE_BANDS));
        $this->assertSame(round($manual + 10000 * 0.35, 2), PayrollMath::paye(60000, PayrollMath::CURRENT_PAYE_BANDS));
        $this->assertEquals(0, PayrollMath::paye(-50, PayrollMath::CURRENT_PAYE_BANDS));
    }

    public function test_a_5000_basic_salary_matches_the_node_version(): void
    {
        $slip = $this->slip();
        $this->assertSame(275.0, $slip['employee_ssnit']);
        $this->assertSame(4725.0, $slip['chargeable_income']);
        $this->assertSame(785.75, $slip['paye']);
        $this->assertSame(3939.25, $slip['net_pay']);
        $this->assertSame(650.0, $slip['employer_ssnit']);
        $this->assertSame(250.0, $slip['tier2']);
        $this->assertSame(675.0, $slip['tier1']);
        $this->assertSame(5650.0, $slip['employer_cost']);
    }

    public function test_bonus_is_taxed_flat_up_to_15_percent_of_annual_basic(): void
    {
        $this->assertSame(['flat_tax' => 150.0, 'taxable_excess' => 0.0, 'threshold' => 9000.0], PayrollMath::bonusTaxSplit(3000, 5000));
        $this->assertSame(['flat_tax' => 450.0, 'taxable_excess' => 1000.0, 'threshold' => 9000.0], PayrollMath::bonusTaxSplit(10000, 5000));

        $slip = $this->slip(['bonus' => 3000]);
        $this->assertSame(4725.0, $slip['chargeable_income'], 'A bonus under the threshold does not raise chargeable income');
        $this->assertSame(935.75, $slip['paye']);
    }

    public function test_allowances_reliefs_and_deductions(): void
    {
        $slip = $this->slip(['taxable_allowances' => 400, 'non_taxable_allowances' => 100, 'relief' => 2000, 'post_tax_deductions' => 50, 'loan_deduction' => 300]);
        $this->assertSame(5500.0, $slip['gross_pay']);
        $this->assertSame(825.0, $slip['reliefs'], 'Relief is capped at 16.5% of basic');
        $this->assertSame(4300.0, $slip['chargeable_income']);
        $this->assertSame(PayrollMath::round2(5500 - 275 - $slip['paye'] - 50 - 300), $slip['net_pay']);
    }

    public function test_overtime_and_pro_rating(): void
    {
        $this->assertSame(85.23, PayrollMath::overtime(5000, 176, 2, 1.5));
        $this->assertSame(0.5, PayrollMath::proRate(11, 22));
        $this->assertSame(1.0, PayrollMath::proRate(30, 22));
        $this->assertSame(1.0, PayrollMath::proRate(5, 0));
    }
}
