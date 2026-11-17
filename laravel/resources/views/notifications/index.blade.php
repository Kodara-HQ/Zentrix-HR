@php
    use App\Support\Format;
@endphp
<x-layouts.app title="Notifications">
    <x-page-header title="Notifications" subtitle="Alerts for approvals, payslips, birthdays, contracts, probation, training and hearings. Email is sent too when it is switched on for that event.">
        @if ($unreadCount)
            <form method="post" action="{{ route('notifications.read-all') }}">
                @csrf
                <x-button type="submit" tone="ghost">Mark all read</x-button>
            </form>
        @endif
    </x-page-header>

    <div class="mb-3 flex gap-2 text-sm">
        <a href="{{ route('notifications.index') }}" class="rounded-full px-3 py-1 font-semibold {{ ! $unreadOnly ? 'bg-navy text-white' : 'bg-white text-body ring-1 ring-line hover:bg-sky' }}">All</a>
        <a href="{{ route('notifications.index', ['unread' => 1]) }}" class="rounded-full px-3 py-1 font-semibold {{ $unreadOnly ? 'bg-navy text-white' : 'bg-white text-body ring-1 ring-line hover:bg-sky' }}">
            Unread @if ($unreadCount)<span class="ml-1 rounded-full bg-accent-strong px-1.5 text-xs text-white">{{ $unreadCount }}</span>@endif
        </a>
    </div>

    <div class="space-y-2">
        @forelse ($items as $item)
            <div class="flex items-start justify-between gap-3 rounded-2xl border p-4 {{ $item->read_at ? 'border-line bg-paper' : 'border-sky-text bg-sky/50' }}">
                <div class="flex gap-3">
                    <span class="mt-1.5 h-2 w-2 shrink-0 rounded-full {{ $item->read_at ? 'bg-transparent' : 'bg-accent-strong' }}" aria-hidden="true"></span>
                    <div>
                        <h2 class="font-semibold text-ink">{{ $item->title }}@unless ($item->read_at)<span class="sr-only"> (unread)</span>@endunless</h2>
                        <p class="text-sm text-body">{{ $item->body }}</p>
                        <p class="mt-1 text-xs text-muted">{{ $item->created_at->diffForHumans() }} · {{ Format::date($item->created_at) }}</p>
                    </div>
                </div>
                <div class="flex shrink-0 items-center gap-2">
                    @if ($item->link)
                        <form method="post" action="{{ route('notifications.read', ['notification' => $item, 'open' => 1]) }}">
                            @csrf
                            <button class="text-sm font-semibold text-accent-strong">Open</button>
                        </form>
                    @endif
                    @unless ($item->read_at)
                        <form method="post" action="{{ route('notifications.read', $item) }}">
                            @csrf
                            <x-button type="submit" small tone="ghost">Mark read</x-button>
                        </form>
                    @endunless
                </div>
            </div>
        @empty
            <p class="text-sm text-muted">{{ $unreadOnly ? 'Nothing unread.' : 'No notifications yet.' }}</p>
        @endforelse
    </div>
    @if ($items->hasPages())
        <div class="mt-3 flex justify-end gap-2">
            <x-button small tone="ghost" :href="$items->previousPageUrl()" @class(['pointer-events-none opacity-50' => $items->onFirstPage()])>Previous</x-button>
            <x-button small tone="ghost" :href="$items->nextPageUrl()" @class(['pointer-events-none opacity-50' => ! $items->hasMorePages()])>Next</x-button>
        </div>
    @endif
</x-layouts.app>
