<!DOCTYPE html>
<html>
<head>
<meta charset="utf-8">
<style>
    @page { margin: 26pt 28pt 34pt; }
    body { font-family: DejaVu Sans, Helvetica, sans-serif; color: #24302b; font-size: 8pt; }
    .band { background: #1b2b57; color: #faf8f1; padding: 12pt 14pt; margin: -26pt -28pt 12pt; }
    .kicker { color: #8fc3f5; font-size: 7pt; letter-spacing: 1pt; }
    .company { font-size: 14pt; font-weight: bold; }
    .meta { color: #c9dcf5; font-size: 8pt; margin-top: 2pt; }
    table { width: 100%; border-collapse: collapse; }
    th { background: #1b2b57; color: #faf8f1; text-align: left; padding: 4pt 5pt; font-size: 7pt; }
    td { padding: 3.5pt 5pt; border-bottom: 0.5pt solid #e7e2d6; }
    tr:nth-child(even) td { background: #eef4fc; }
    .num { text-align: right; }
    .footer { position: fixed; bottom: -20pt; left: 0; right: 0; text-align: center; color: #6b6b6b; font-size: 7pt; }
</style>
</head>
<body>
    <div class="band">
        <div class="kicker">HUMAN RESOURCES</div>
        <div class="company">{{ $company }}</div>
        <div class="meta">{{ $report['title'] }} · {{ \App\Support\Format::date($filters['from']) }} – {{ \App\Support\Format::date($filters['to']) }} · {{ count($report['rows']) }} rows · generated {{ \App\Support\Format::date(now()) }}</div>
    </div>
    <table>
        <thead><tr>@foreach ($report['columns'] as $label)<th>{{ $label }}</th>@endforeach</tr></thead>
        <tbody>
            @foreach (array_slice($report['rows'], 0, 2000) as $row)
                <tr>@foreach (array_keys($report['columns']) as $key)@php($v = $row[$key] ?? '')<td class="{{ is_float($v) ? 'num' : '' }}">{{ is_float($v) ? number_format($v, 2) : $v }}</td>@endforeach</tr>
            @endforeach
        </tbody>
    </table>
    <div class="footer">Confidential · {{ $company }}</div>
</body>
</html>
