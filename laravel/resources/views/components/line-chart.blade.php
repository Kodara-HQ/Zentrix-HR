@props(['points' => [], 'unit' => '%', 'empty' => 'No data yet.'])
{{-- One series over time: [['date' => 'Y-m-d', 'rate' => int], ...] on a 0–100 scale, with a crosshair and tooltip on hover. --}}
@php
    $points = collect($points)->values();
    $w = 600; $h = 180; $padL = 34; $padB = 22; $padT = 8;
    $n = $points->count();
    $x = fn ($i) => $padL + ($n > 1 ? $i / ($n - 1) : 0.5) * ($w - $padL - 8);
    $y = fn ($v) => $padT + (1 - $v / 100) * ($h - $padT - $padB);
    $path = $points->map(fn ($p, $i) => ($i ? 'L' : 'M').round($x($i), 1).' '.round($y($p['rate']), 1))->implode(' ');
    $json = $points->map(fn ($p, $i) => ['x' => round($x($i), 1), 'y' => round($y($p['rate']), 1), 'label' => \App\Support\Format::date($p['date']), 'value' => $p['rate']])->all();
@endphp
@if ($n === 0)
    <div class="grid h-44 place-items-center rounded-xl border border-dashed border-line text-sm text-muted">{{ $empty }}</div>
@else
    <div class="relative" x-data="{ pts: @js($json), i: null,
            pick(e) { const r = this.$refs.svg.getBoundingClientRect(); const px = (e.clientX - r.left) / r.width * {{ $w }};
                      this.i = this.pts.reduce((best, p, k) => Math.abs(p.x - px) < Math.abs(this.pts[best].x - px) ? k : best, 0); } }">
        <svg x-ref="svg" viewBox="0 0 {{ $w }} {{ $h }}" class="h-44 w-full" role="img" aria-label="Line chart" @mousemove="pick($event)" @mouseleave="i = null">
            @foreach ([0, 50, 100] as $tick)
                <line x1="{{ $padL }}" x2="{{ $w - 8 }}" y1="{{ $y($tick) }}" y2="{{ $y($tick) }}" stroke="var(--color-line)" stroke-width="1" />
                <text x="{{ $padL - 6 }}" y="{{ $y($tick) + 4 }}" text-anchor="end" font-size="11" fill="var(--color-muted)">{{ $tick }}{{ $unit }}</text>
            @endforeach
            <text x="{{ $padL }}" y="{{ $h - 4 }}" font-size="11" fill="var(--color-muted)">{{ \App\Support\Format::date($points->first()['date']) }}</text>
            <text x="{{ $w - 8 }}" y="{{ $h - 4 }}" text-anchor="end" font-size="11" fill="var(--color-muted)">{{ \App\Support\Format::date($points->last()['date']) }}</text>
            <path d="{{ $path }}" fill="none" stroke="var(--color-accent-strong)" stroke-width="2" stroke-linejoin="round" stroke-linecap="round" />
            {{-- x-if does not work inside SVG, so the marker is shown/hidden and every binding guards against no point. --}}
            <g x-show="i !== null">
                <line :x1="i === null ? 0 : pts[i].x" :x2="i === null ? 0 : pts[i].x" y1="{{ $padT }}" y2="{{ $h - $padB }}" stroke="var(--color-muted)" stroke-width="1" stroke-dasharray="3 3" />
                <circle :cx="i === null ? 0 : pts[i].x" :cy="i === null ? 0 : pts[i].y" r="5" fill="var(--color-accent-strong)" stroke="white" stroke-width="2" />
            </g>
        </svg>
        <div x-show="i !== null" x-cloak class="pointer-events-none absolute top-0 rounded-lg bg-navy px-2.5 py-1.5 text-xs text-white shadow-lg"
             :style="i !== null && `left: calc(${pts[i].x / {{ $w }} * 100}% - 3rem)`">
            <div x-text="i !== null && pts[i].label"></div>
            <div class="font-semibold" x-text="i !== null && pts[i].value + '{{ $unit }}'"></div>
        </div>
    </div>
@endif
