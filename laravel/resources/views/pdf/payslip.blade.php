@php
    use App\Services\PayrollService;
    use App\Support\Format;
    $money = fn ($v) => 'GHS '.number_format((float) $v, 2);
    $earnings = ['Basic salary' => $slip->basic_salary, 'Allowances' => $slip->allowances, 'Overtime' => $slip->overtime_pay, 'Bonus' => $slip->bonus, 'Commission' => $slip->commission];
    $deductions = ['Employee SSNIT' => $slip->employee_ssnit, 'PAYE' => $slip->paye, 'Other deductions' => $slip->other_deductions, 'Loan / advance' => $slip->loan_deduction];
    $totalDeductions = array_sum(array_map('floatval', $deductions));
    $name = $company?->name ?: 'Zentrix HR';
    $contact = implode('   ·   ', array_filter([$company?->phone, $company?->email, $company?->country]));
@endphp
<!DOCTYPE html>
<html>
<head>
<meta charset="utf-8">
<style>
    @page { margin: 0; }
    body { margin: 0; font-family: Helvetica, Arial, sans-serif; color: #24302b; font-size: 9.5pt; }
    .band { background: #1b2b57; height: 86pt; position: relative; }
    .rule { background: #8fc3f5; height: 4pt; }
    .mark { position: absolute; left: 48pt; top: 22pt; width: 36pt; height: 36pt; border-radius: 6pt; background: #1570c4; color: #fff; font-weight: bold; font-size: 18pt; text-align: center; line-height: 36pt; }
    .kicker { position: absolute; left: 96pt; top: 22pt; color: #8fc3f5; font-size: 8pt; letter-spacing: 1pt; }
    .company { position: absolute; left: 96pt; top: 34pt; color: #faf8f1; font-weight: bold; font-size: 16pt; }
    .period { position: absolute; right: 48pt; top: 56pt; color: #faf8f1; font-size: 10pt; }
    .content { padding: 18pt 48pt 50pt; }
    .who { background: #eef4fc; padding: 10pt 14pt; }
    .who .name { color: #1b2b57; font-weight: bold; font-size: 14pt; }
    .who .meta { color: #6b6b6b; margin-top: 3pt; }
    .cols { width: 100%; border-collapse: separate; border-spacing: 0; margin-top: 16pt; }
    .cols > tbody > tr > td { width: 50%; vertical-align: top; }
    h2 { color: #1b2b57; font-size: 11pt; margin: 0 0 6pt; }
    table.list { width: 100%; border-collapse: collapse; border: 0.6pt solid #e7e2d6; }
    table.list th { background: #1b2b57; color: #faf8f1; font-size: 7.5pt; text-align: left; padding: 5pt 8pt; font-weight: normal; }
    table.list td { padding: 5pt 8pt; }
    table.list tr:nth-child(even) td { background: #eef4fc; }
    table.list .num { text-align: right; }
    table.list tr.total td { font-weight: bold; color: #1b2b57; }
    .net { background: #1b2b57; color: #faf8f1; padding: 10pt 16pt; margin-top: 16pt; }
    .net .label { color: #8fc3f5; font-size: 8pt; letter-spacing: 1pt; }
    .net .value { font-size: 18pt; font-weight: bold; text-align: right; margin-top: -10pt; }
    .note { color: #6b6b6b; margin-top: 12pt; line-height: 1.5; }
    .footer { position: fixed; bottom: 0; left: 0; right: 0; height: 28pt; background: #1b2b57; color: #8fc3f5; font-size: 8pt; text-align: center; line-height: 28pt; }
</style>
</head>
<body>
    <div class="band">
        <div class="mark">{{ mb_substr($name, 0, 1) }}</div>
        <div class="kicker">PAYSLIP</div>
        <div class="company">{{ $name }}</div>
        <div class="period">{{ PayrollService::period($slip->payrollRun->year, $slip->payrollRun->month) }}</div>
    </div>
    <div class="rule"></div>
    <div class="content">
        <div class="who">
            <div class="name">{{ Format::fullName($slip->employee) }}</div>
            <div class="meta">{{ $slip->employee->employee_no }} · {{ $slip->employee->jobTitle?->name }} · {{ $slip->employee->department?->name }}</div>
        </div>

        <table class="cols"><tr>
            <td style="padding-right: 8pt;">
                <h2>Earnings</h2>
                <table class="list">
                    <tr><th>ITEM</th><th class="num">AMOUNT</th></tr>
                    @foreach ($earnings as $label => $value)<tr><td>{{ $label }}</td><td class="num">{{ $money($value) }}</td></tr>@endforeach
                    <tr class="total"><td>Gross pay</td><td class="num">{{ $money($slip->gross_pay) }}</td></tr>
                </table>
            </td>
            <td style="padding-left: 8pt;">
                <h2>Deductions</h2>
                <table class="list">
                    <tr><th>ITEM</th><th class="num">AMOUNT</th></tr>
                    @foreach ($deductions as $label => $value)<tr><td>{{ $label }}</td><td class="num">{{ $money($value) }}</td></tr>@endforeach
                    <tr class="total"><td>Total deductions</td><td class="num">{{ $money($totalDeductions) }}</td></tr>
                </table>
            </td>
        </tr></table>

        <div class="net"><div class="label">NET PAY</div><div class="value">{{ $money($slip->net_pay) }}</div></div>

        <div class="note">
            Employer SSNIT {{ $money($slip->employer_ssnit) }} · Tier 1 {{ $money($slip->tier1) }} · Tier 2 {{ $money($slip->tier2) }}<br>
            Chargeable income {{ $money($slip->chargeable_income) }}
        </div>

        @if (! empty($slip->lines))
            <h2 style="margin-top: 14pt;">Included in this pay</h2>
            <table class="list">
                @foreach ($slip->lines as $line)<tr><td>{{ $line['label'] }}</td><td>{{ Format::title($line['kind'] ?? '') }}</td><td class="num">{{ $money($line['amount']) }}</td></tr>@endforeach
            </table>
        @endif
    </div>
    <div class="footer">{{ $contact ?: $name }}</div>
</body>
</html>
