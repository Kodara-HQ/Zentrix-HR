@php
    $query = array_filter(['report' => $name] + $filters);
@endphp
<x-layouts.app title="Reports">
    <x-page-header title="Reports and analytics" subtitle="Pick a report, filter it, then download the same figures for Excel or as a PDF.">
        <x-button tone="ghost" :href="route('reports.index', $query + ['format' => 'csv'])">Excel (CSV)</x-button>
        <x-button tone="ghost" :href="route('reports.index', $query + ['format' => 'pdf'])" target="_blank">PDF</x-button>
    </x-page-header>

    <form method="get" class="mb-4 flex flex-wrap items-end gap-2">
        <x-field label="Report"><x-select name="report" :options="$available" :selected="$name" class="!w-60" onchange="this.form.submit()" /></x-field>
        <x-field label="Department"><x-select name="department_id" :options="$departments" :selected="$filters['department_id'] ?? null" placeholder="All departments" class="!w-52" /></x-field>
        <x-field label="From"><x-input type="date" name="from" :value="$filters['from']" class="!w-40" /></x-field>
        <x-field label="To"><x-input type="date" name="to" :value="$filters['to']" class="!w-40" /></x-field>
        <x-button type="submit">Apply</x-button>
    </form>
    <x-errors class="mb-3" />

    <div class="mb-2 flex items-baseline justify-between">
        <h2 class="font-semibold text-ink">{{ $report['title'] }}</h2>
        <span class="text-sm text-muted">{{ count($report['rows']) }} {{ str('row')->plural(count($report['rows'])) }}</span>
    </div>
    <div class="overflow-auto rounded-2xl border border-line bg-white">
        <table class="w-full text-left text-sm">
            <thead class="sticky top-0 bg-white text-xs uppercase tracking-wide text-muted">
                <tr>@foreach ($report['columns'] as $label)<th class="whitespace-nowrap px-3 py-2">{{ $label }}</th>@endforeach</tr>
            </thead>
            <tbody>
                @forelse (array_slice($report['rows'], 0, 500) as $row)
                    <tr class="border-t border-line">
                        @foreach (array_keys($report['columns']) as $key)
                            @php($v = $row[$key] ?? '')
                            <td class="px-3 py-2 {{ is_float($v) ? 'text-right tabular-nums' : '' }}">{{ is_float($v) ? number_format($v, 2) : ($v === '' || $v === null ? '—' : $v) }}</td>
                        @endforeach
                    </tr>
                @empty
                    <tr><td colspan="{{ count($report['columns']) }}" class="px-3 py-6 text-center text-muted">Nothing for these filters.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
    @if (count($report['rows']) > 500)<p class="mt-2 text-xs text-muted">Showing the first 500 rows. The downloads include all {{ count($report['rows']) }}.</p>@endif
</x-layouts.app>
