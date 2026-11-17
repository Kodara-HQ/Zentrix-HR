@props(['data' => [], 'unit' => '', 'empty' => 'No data yet.'])
{{-- Horizontal bars for one series: [label => value]. Values are printed at the bar end, so the chart doubles as its own table. --}}
@php($max = max(1, ...array_values($data ?: [0])))
@if (! $data)
    <p class="text-sm text-muted">{{ $empty }}</p>
@else
    <ul class="space-y-2.5" role="list">
        @foreach ($data as $label => $value)
            <li class="group grid grid-cols-[minmax(6rem,10rem)_1fr_2.5rem] items-center gap-3 text-sm" title="{{ $label }}: {{ $value }}{{ $unit }}">
                <span class="truncate text-body">{{ $label }}</span>
                <span class="relative h-5 rounded-r bg-sky/60">
                    <span class="absolute inset-y-0 left-0 rounded-r bg-navy transition-colors group-hover:bg-accent-strong" style="width: {{ max(2, $value / $max * 100) }}%"></span>
                </span>
                <span class="text-right font-semibold tabular-nums text-ink">{{ $value }}{{ $unit }}</span>
            </li>
        @endforeach
    </ul>
@endif
