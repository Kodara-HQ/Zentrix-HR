@php
    use App\Support\Format;
    $longDate = fn ($value) => \Carbon\CarbonImmutable::parse($value)->format('j F Y');
    $contact = implode('   ·   ', array_filter([$company->phone, $company->email, implode(', ', array_filter([$company->city, $company->country]))]));
@endphp
<!DOCTYPE html>
<html>
<head>
<meta charset="utf-8">
<style>
    @page { margin: 0; }
    body { margin: 0; font-family: Helvetica, Arial, sans-serif; color: #24302b; font-size: 10.5pt; }
    .band { background: #1b2b57; height: 88pt; position: relative; }
    .rule { background: #8fc3f5; height: 4pt; }
    .mark { position: absolute; left: 52pt; top: 24pt; width: 36pt; height: 36pt; border-radius: 6pt; background: #1570c4; color: #fff; font-weight: bold; font-size: 18pt; text-align: center; line-height: 36pt; }
    .kicker { position: absolute; left: 100pt; top: 24pt; color: #8fc3f5; font-size: 8pt; letter-spacing: 1pt; }
    .company { position: absolute; left: 100pt; top: 36pt; color: #faf8f1; font-weight: bold; font-size: 16pt; }
    .subject { position: absolute; left: 100pt; top: 60pt; color: #faf8f1; font-size: 10pt; }
    .date { position: absolute; right: 52pt; top: 60pt; color: #8fc3f5; font-size: 9pt; }
    .content { padding: 22pt 52pt 60pt; }
    p { margin: 0 0 8pt; text-align: justify; line-height: 1.35; }
    h2 { color: #1b2b57; font-size: 11pt; margin: 10pt 0 6pt; }
    table { width: 100%; border-collapse: collapse; border: 0.6pt solid #e7e2d6; }
    td { padding: 5pt 10pt; }
    tr:nth-child(odd) td { background: #eef4fc; }
    td.label { width: 130pt; color: #6b6b6b; font-size: 8pt; text-transform: uppercase; }
    td.value { color: #1b2b57; font-weight: bold; font-size: 10pt; }
    .sign { margin-top: 18pt; }
    .line { width: 180pt; border-top: 1pt solid #8fc3f5; margin: 28pt 0 6pt; }
    .footer { position: fixed; bottom: 0; left: 0; right: 0; height: 28pt; background: #1b2b57; color: #8fc3f5; font-size: 8pt; text-align: center; line-height: 28pt; }
</style>
</head>
<body>
    <div class="band">
        <div class="mark">{{ mb_substr($company->name, 0, 1) }}</div>
        <div class="kicker">HUMAN RESOURCES</div>
        <div class="company">{{ $company->name }}</div>
        <div class="subject">{{ $subject }}</div>
        <div class="date">{{ $longDate($issuedOn) }}</div>
    </div>
    <div class="rule"></div>
    <div class="content">
        <p>Dear {{ Format::fullName($employee) }},</p>
        @foreach ($paragraphs as $paragraph)
            <p>{{ $paragraph }}</p>
        @endforeach

        @if ($appointment)
            <h2>Particulars of appointment</h2>
            <table>
                @foreach ([
                    'Position' => $employee->jobTitle?->name ?? '—',
                    'Department' => $employee->department?->name ?? '—',
                    'Duty station' => implode(', ', array_filter([$employee->location?->name, $employee->location?->city])) ?: '—',
                    'Employee number' => $employee->employee_no,
                    'Commencement' => $longDate($employee->date_of_employment),
                ] as $label => $value)
                    <tr><td class="label">{{ $label }}</td><td class="value">{{ $value }}</td></tr>
                @endforeach
            </table>
        @endif

        <div class="sign">
            <p>Yours faithfully,</p>
            <div class="line"></div>
            <div style="font-weight: bold; color: #1b2b57;">Human Resources</div>
            <div style="font-size: 9pt; color: #6b6b6b;">{{ $company->name }}</div>
        </div>
    </div>
    <div class="footer">{{ $contact ?: $company->name }}</div>
</body>
</html>
