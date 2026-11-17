@props(['label', 'value', 'hint' => null])
<div {{ $attributes->merge(['class' => 'rounded-2xl border border-line bg-paper p-4']) }}>
    <div class="text-sm text-muted">{{ $label }}</div>
    <div class="mt-1 text-2xl font-semibold tracking-tight text-ink">{{ $value }}</div>
    @if ($hint)<div class="mt-0.5 text-xs text-muted">{{ $hint }}</div>@endif
</div>
