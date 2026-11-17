@props(['title', 'show', 'close' => null, 'wide' => false])
{{-- Opened by an Alpine expression in `show`, e.g. <x-modal show="adding" title="New employee">.
     When `show` is not a plain flag, pass `close` too, e.g. show="action === 'PROMOTE'" close="action = null". --}}
@php($close ??= "{$show} = false")
<div x-show="{{ $show }}" x-cloak class="fixed inset-0 z-50 grid place-items-center bg-navy/40 p-4" @keydown.escape.window="{{ $close }}">
    <div class="max-h-[90vh] w-full {{ $wide ? 'max-w-3xl' : 'max-w-lg' }} overflow-y-auto rounded-2xl bg-white p-5 shadow-xl" @click.outside="{{ $close }}">
        <div class="mb-4 flex items-center justify-between gap-4">
            <h2 class="text-lg font-semibold text-ink">{{ $title }}</h2>
            <button type="button" class="rounded-lg p-1 text-muted hover:bg-sky" @click="{{ $close }}" aria-label="Close"><x-lucide-x class="h-[18px] w-[18px]" /></button>
        </div>
        {{ $slot }}
    </div>
</div>
