@php
    use App\Support\Format;
@endphp
<!DOCTYPE html>
<html>
<head>
<meta charset="utf-8">
<style>
    @page { margin: 0; }
    body { margin: 0; font-family: Helvetica, Arial, sans-serif; color: #24302b; font-size: 10pt; }
    .band { background: #1b2b57; height: 78pt; position: relative; }
    .kicker { position: absolute; left: 48pt; top: 18pt; color: #8fc3f5; font-size: 8pt; letter-spacing: 1pt; }
    .company { position: absolute; left: 48pt; top: 30pt; color: #faf8f1; font-weight: bold; font-size: 16pt; }
    .title { position: absolute; left: 48pt; top: 52pt; color: #faf8f1; font-size: 11pt; }
    .date { position: absolute; right: 48pt; top: 22pt; color: #8fc3f5; font-size: 8pt; }
    .content { padding: 18pt 48pt; }
    .grid { width: 100%; border-collapse: collapse; margin-bottom: 6pt; }
    .grid td { width: 50%; padding: 0 0 12pt; vertical-align: top; }
    .label { color: #6b6b6b; font-size: 7.5pt; text-transform: uppercase; letter-spacing: 0.5pt; }
    .value { color: #1b2b57; font-weight: bold; font-size: 11pt; margin-top: 2pt; }
    h2 { color: #1b2b57; font-size: 12pt; margin: 10pt 0 6pt; }
    table.list { width: 100%; border-collapse: collapse; }
    table.list th { background: #1b2b57; color: #faf8f1; font-size: 7.5pt; text-align: left; padding: 5pt 8pt; }
    table.list td { padding: 4pt 8pt; font-size: 9pt; }
    table.list tr:nth-child(even) td { background: #eef4fc; }
    .box { display: inline-block; width: 9pt; height: 9pt; border: 1pt solid #1b2b57; margin-right: 6pt; vertical-align: middle; }
    .types td { width: 50%; padding: 3pt 0; }
    .line { border-bottom: 1pt solid #8fc3f5; height: 18pt; }
    .signs { width: 100%; border-collapse: separate; border-spacing: 8pt 0; margin-top: 24pt; }
    .signs td { width: 33%; border: 1pt solid #e7e2d6; padding: 8pt; height: 76pt; vertical-align: top; }
    .signs .who { color: #1b2b57; font-weight: bold; font-size: 9pt; }
    .signs .small { color: #6b6b6b; font-size: 8pt; margin-top: 18pt; }
    .signs .rule { border-bottom: 1pt solid #8fc3f5; height: 10pt; }
</style>
</head>
<body>
    <div class="band">
        <div class="kicker">EMPLOYEE SELF SERVICE</div>
        <div class="company">{{ $company }}</div>
        <div class="title">Leave application form</div>
        <div class="date">{{ Format::date(now()) }}</div>
    </div>
    <div class="content">
        <table class="grid">
            <tr><td><div class="label">Employee</div><div class="value">{{ Format::fullName($employee) }}</div></td>
                <td><div class="label">Employee number</div><div class="value">{{ $employee->employee_no }}</div></td></tr>
            <tr><td><div class="label">Department</div><div class="value">{{ $employee->department?->name }}</div></td>
                <td><div class="label">Job title</div><div class="value">{{ $employee->jobTitle?->name }}</div></td></tr>
            <tr><td><div class="label">Location</div><div class="value">{{ $employee->location?->name }}, {{ $employee->location?->city }}</div></td>
                <td><div class="label">Phone</div><div class="value">{{ $employee->phone }}</div></td></tr>
        </table>

        <h2>Leave balance this year</h2>
        <table class="list">
            <tr><th>LEAVE</th><th>DAYS LEFT</th><th>USED</th></tr>
            @foreach ($balances as $balance)
                <tr><td>{{ $balance->leaveType->name }}</td>
                    <td>{{ Format::wholeDays($balance->entitled - $balance->used - $balance->pending) }}</td>
                    <td>{{ Format::wholeDays($balance->used) }}</td></tr>
            @endforeach
        </table>

        <h2>Application</h2>
        @if ($leave)
            <table class="grid">
                <tr><td><div class="label">Leave type</div><div class="value">{{ $leave->leaveType->name }}</div></td>
                    <td><div class="label">Status</div><div class="value">{{ Format::title($leave->status) }}</div></td></tr>
                <tr><td><div class="label">From – to</div><div class="value">{{ Format::date($leave->start_date) }} – {{ Format::date($leave->end_date) }}</div></td>
                    <td><div class="label">Days</div><div class="value">{{ Format::wholeDays($leave->days) }}</div></td></tr>
            </table>
            <div class="label">Reason</div>
            <div style="margin-top: 3pt; font-size: 11pt;">{{ $leave->reason ?: '—' }}</div>
        @else
            <div class="label">Leave type</div>
            <table class="types" style="width: 100%; margin-top: 4pt;">
                @foreach ($types->values()->chunk(2) as $pair)
                    <tr>@foreach ($pair as $type)<td><span class="box"></span>{{ $type->name }}</td>@endforeach</tr>
                @endforeach
            </table>
            <table class="grid" style="margin-top: 10pt;">
                <tr><td><div class="label">From</div><div class="line"></div></td><td><div class="label">To</div><div class="line"></div></td></tr>
                <tr><td colspan="2"><div class="label">Days</div><div class="line" style="width: 40%;"></div></td></tr>
            </table>
            <div class="label">Reason</div>
            <div class="line"></div><div class="line"></div>
        @endif

        <div class="label" style="margin-top: 16pt;">Contact while on leave</div>
        <div class="line"></div>

        <table class="signs">
            <tr>
                @foreach (['Employee', 'Supervisor', 'Human Resources'] as $who)
                    <td><div class="who">{{ $who }}</div><div class="small">Signature</div><div class="rule"></div><div class="small" style="margin-top: 8pt;">Date</div><div class="rule"></div></td>
                @endforeach
            </tr>
        </table>
    </div>
</body>
</html>
