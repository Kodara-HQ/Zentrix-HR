@props(['title', 'subtitle' => null])
<div class="mb-5 flex flex-wrap items-end justify-between gap-3">
    <div>
        <h1 class="text-2xl font-semibold text-[#1a3c34]">{{ $title }}</h1>
        @if ($subtitle)<p class="mt-1 text-sm text-[#6e675e]">{{ $subtitle }}</p>@endif
    </div>
    @if (! $slot->isEmpty())<div class="flex flex-wrap gap-2">{{ $slot }}</div>@endif
</div>
