<?php

namespace App\Support;

/**
 * Ghana PAYE and SSNIT, ported line for line from server/src/lib/payroll.ts so totals match the Node version.
 * Pure functions: no database access, covered by tests/Unit/PayrollMathTest.php.
 */
class PayrollMath
{
    /** Monthly resident bands effective 1 September 2026. A null width is the top band. */
    public const CURRENT_PAYE_BANDS = [
        ['width' => 588, 'rate' => 0],
        ['width' => 80, 'rate' => 0.05],
        ['width' => 100, 'rate' => 0.1],
        ['width' => 2900, 'rate' => 0.175],
        ['width' => 16000, 'rate' => 0.25],
        ['width' => 30332, 'rate' => 0.3],
        ['width' => null, 'rate' => 0.35],
    ];

    /** Monthly resident bands in force before September 2026. */
    public const PREVIOUS_PAYE_BANDS = [
        ['width' => 490, 'rate' => 0],
        ['width' => 110, 'rate' => 0.05],
        ['width' => 130, 'rate' => 0.1],
        ['width' => 3000, 'rate' => 0.175],
        ['width' => 16395, 'rate' => 0.25],
        ['width' => 29875, 'rate' => 0.3],
        ['width' => null, 'rate' => 0.35],
    ];

    public static function round2(float $value): float
    {
        return round($value + PHP_FLOAT_EPSILON, 2);
    }

    private static function cents(float $value): int
    {
        return (int) round(($value + PHP_FLOAT_EPSILON) * 100);
    }

    /** Graduated PAYE on monthly chargeable income, worked in cents so bands never drift. */
    public static function paye(float $chargeableIncome, array $bands): float
    {
        $remaining = max(0, self::cents($chargeableIncome));
        $tax = 0;
        foreach ($bands as $band) {
            if ($remaining <= 0) {
                break;
            }
            $width = $band['width'] === null ? $remaining : self::cents((float) $band['width']);
            $slice = min($remaining, $width);
            $tax += (int) round($slice * $band['rate']);
            $remaining -= $slice;
        }

        return $tax / 100;
    }

    /** Bonus up to 15% of annual basic is taxed at a flat 5%; any excess joins chargeable income. */
    public static function bonusTaxSplit(float $bonus, float $monthlyBasic): array
    {
        $threshold = self::round2($monthlyBasic * 12 * 0.15);
        if ($bonus <= 0) {
            return ['flat_tax' => 0.0, 'taxable_excess' => 0.0, 'threshold' => $threshold];
        }
        if ($bonus <= $threshold) {
            return ['flat_tax' => self::round2($bonus * 0.05), 'taxable_excess' => 0.0, 'threshold' => $threshold];
        }

        return ['flat_tax' => self::round2($threshold * 0.05), 'taxable_excess' => self::round2($bonus - $threshold), 'threshold' => $threshold];
    }

    /**
     * @param  array{basic: float, taxable_allowances: float, non_taxable_allowances: float, overtime: float, bonus: float, commission: float, relief: float, post_tax_deductions: float, loan_deduction: float}  $in
     * @param  array{employee_rate: float, employer_rate: float, tier1_employer_rate: float, tier2_rate: float, bands: array}  $rates
     */
    public static function calculate(array $in, array $rates): array
    {
        $r = fn ($v) => self::round2((float) $v);
        $basic = $r($in['basic']);
        $allowances = $r($in['taxable_allowances'] + $in['non_taxable_allowances']);
        $overtime = $r($in['overtime']);
        $bonus = $r($in['bonus']);
        $commission = $r($in['commission']);
        $gross = $r($basic + $allowances + $overtime + $bonus + $commission);

        $employeeSsnit = $r($basic * $rates['employee_rate']);
        $employerSsnit = $r($basic * $rates['employer_rate']);
        $tier2 = $r($basic * $rates['tier2_rate']);
        $tier1 = $r($employeeSsnit + $r($basic * $rates['tier1_employer_rate']));

        $reliefs = $r(min(max(0, $in['relief']), $r($basic * 0.165)));
        $split = self::bonusTaxSplit($bonus, $basic);
        $chargeable = $r(max(0, $basic + $in['taxable_allowances'] + $overtime + $commission + $split['taxable_excess'] - $employeeSsnit - $reliefs));
        $paye = $r(self::paye($chargeable, $rates['bands']) + $split['flat_tax']);

        $other = $r($in['post_tax_deductions']);
        $loan = $r(max(0, $in['loan_deduction']));
        $net = $r($gross - $employeeSsnit - $paye - $other - $loan);

        return [
            'basic_salary' => $basic,
            'allowances' => $allowances,
            'overtime_pay' => $overtime,
            'bonus' => $bonus,
            'commission' => $commission,
            'gross_pay' => $gross,
            'employee_ssnit' => $employeeSsnit,
            'employer_ssnit' => $employerSsnit,
            'tier1' => $tier1,
            'tier2' => $tier2,
            'reliefs' => $reliefs,
            'chargeable_income' => $chargeable,
            'paye' => $paye,
            'bonus_tax' => $split['flat_tax'],
            'other_deductions' => $other,
            'loan_deduction' => $loan,
            'net_pay' => $net,
            'employer_cost' => $r($gross + $employerSsnit),
        ];
    }

    public static function overtime(float $basic, float $hoursPerMonth, float $hours, float $multiplier): float
    {
        return $hoursPerMonth <= 0 ? 0.0 : self::round2($hours * ($basic / $hoursPerMonth) * $multiplier);
    }

    public static function proRate(int $workedDays, int $monthDays): float
    {
        return $monthDays <= 0 ? 1.0 : min(1, max(0, $workedDays / $monthDays));
    }
}
