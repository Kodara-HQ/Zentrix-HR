@props(['title', 'subtitle' => null])
<div class="mb-5 flex flex-wrap items-end justify-between gap-3">
    <div>
        <h1 class="text-2xl font-semibold tracking-tight text-ink">{{ $title }}</h1>
        @if ($subtitle)<p class="mt-1 text-sm text-muted">{{ $subtitle }}</p>@endif
    </div>
    @if (! $slot->isEmpty())<div class="flex flex-wrap gap-2">{{ $slot }}</div>@endif
</div>
