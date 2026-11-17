@props(['title' => null])
@php
    use App\Enums\Role;
    use App\Support\Format;
    use App\Support\Navigation;

    $user = auth()->user();
    $staff = $user->role === Role::EMPLOYEE;
    $items = Navigation::for($user);
@endphp
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>{{ $staff ? 'ESS — Employee Self Service Portal' : (isset($title) ? "$title · $company" : $company) }}</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body x-data="{ collapsed: false, mobile: false }">
<div class="flex h-screen overflow-hidden">
    <button x-show="mobile" x-cloak class="fixed inset-0 z-30 bg-[#1a3c34]/40 md:hidden" @click="mobile = false" aria-label="Close menu"></button>

    <aside class="fixed z-40 flex h-screen shrink-0 flex-col bg-[#1a3c34] text-[#f6f1e8] transition-all md:static"
           :class="[collapsed ? 'w-[84px]' : 'w-[268px]', mobile ? 'translate-x-0' : '-translate-x-full md:translate-x-0']">
        <div class="flex items-center gap-3 px-4 py-5">
            <div class="grid h-9 w-9 shrink-0 place-items-center rounded-md bg-[#c4622d] font-bold text-white">{{ mb_substr($company, 0, 1) }}</div>
            <div x-show="!collapsed">
                <div class="text-sm font-semibold leading-tight">{{ $company }}</div>
                @if ($staff)
                    <div class="text-xs font-semibold tracking-[0.18em] text-[#c4a574]">ESS</div>
                    <div class="text-[11px] leading-snug text-[#e7dfd2]">Employee Self Service Portal</div>
                @else
                    <div class="text-xs tracking-[0.14em] text-[#c4a574]">HUMAN RESOURCES</div>
                @endif
            </div>
        </div>
        <nav class="flex min-h-0 flex-1 flex-col px-2 pb-4">
            <div class="space-y-0.5 overflow-auto">
                @foreach ($items as $item)
                    <a href="{{ url($item['path']) }}"
                       class="flex items-center gap-3 rounded-lg px-3 py-2 text-sm {{ Navigation::isActive($item['path']) ? 'bg-[#c4622d] text-white' : 'text-[#e7dfd2] hover:bg-white/8' }}">
                        <x-dynamic-component :component="'lucide-'.$item['icon']" class="h-[18px] w-[18px] shrink-0" />
                        <span x-show="!collapsed">{{ $item['label'] }}</span>
                        @if ($item['path'] === '/notifications' && $unread > 0)
                            <span x-show="!collapsed" class="ml-auto rounded-full bg-[#f6f1e8] px-1.5 text-xs text-[#1a3c34]">{{ $unread }}</span>
                        @endif
                    </a>
                @endforeach
            </div>
            @if ($staff)
                <div class="mt-auto border-t border-white/10 pt-3">
                    <a href="{{ url('/settings') }}" class="flex items-center gap-3 rounded-lg px-3 py-2 text-sm {{ Navigation::isActive('/settings') ? 'bg-[#c4622d] text-white' : 'text-[#e7dfd2] hover:bg-white/8' }}">
                        <x-lucide-settings class="h-[18px] w-[18px]" />
                        <span x-show="!collapsed">Settings</span>
                    </a>
                </div>
            @endif
        </nav>
    </aside>

    <div class="flex h-screen min-w-0 flex-1 flex-col">
        <header class="no-print z-20 flex h-16 shrink-0 items-center gap-3 border-b border-[#e6dfd4] bg-[#fffcf8]/95 px-4 backdrop-blur">
            <button class="rounded-lg p-2 text-[#1a3c34] hover:bg-[#f6f1e8] md:hidden" @click="mobile = true" aria-label="Open menu"><x-lucide-menu class="h-[18px] w-[18px]" /></button>
            <button class="hidden rounded-lg p-2 text-[#1a3c34] hover:bg-[#f6f1e8] md:block" @click="collapsed = !collapsed" aria-label="Collapse menu">
                <x-lucide-menu x-show="collapsed" class="h-[18px] w-[18px]" />
                <x-lucide-x x-show="!collapsed" class="h-[18px] w-[18px]" />
            </button>
            @if ($staff)
                <div class="min-w-0 leading-tight">
                    <div class="text-xs font-semibold tracking-[0.18em] text-[#c4622d]">ESS</div>
                    <div class="truncate text-sm font-semibold text-[#1a3c34]">Employee Self Service Portal</div>
                </div>
            @else
                <form action="{{ url('/employees') }}" method="get" class="hidden min-w-0 flex-1 md:block">
                    <input name="search" value="{{ request('search') }}" placeholder="Search employees by name or number"
                           class="w-full max-w-md rounded-lg border border-[#e4ddd2] bg-[#f6f1e8] px-3 py-2 text-sm outline-none focus:border-[#c4622d]">
                </form>
            @endif
            <div class="ml-auto flex items-center gap-1 sm:gap-2">
                <a href="{{ url('/notifications') }}" class="relative rounded-lg p-2 text-[#1a3c34] hover:bg-[#f6f1e8]" title="Notifications">
                    <x-lucide-bell class="h-[18px] w-[18px]" />
                    @if ($unread > 0)<span class="absolute right-1 top-1 h-2 w-2 rounded-full bg-[#c4622d]"></span>@endif
                </a>
                <div class="min-w-0 px-1 text-right leading-tight">
                    <div class="truncate text-sm font-semibold text-[#1a3c34]">{{ $user->first_name }} {{ $user->last_name }}</div>
                    <div class="text-xs text-[#6e675e]">{{ Format::role($user->role) }}</div>
                </div>
                <form method="post" action="{{ route('logout') }}">
                    @csrf
                    <button class="rounded-lg p-2 text-[#1a3c34] hover:bg-[#f6f1e8]" title="Sign out"><x-lucide-log-out class="h-[18px] w-[18px]" /></button>
                </form>
            </div>
        </header>
        <main class="min-h-0 min-w-0 flex-1 overflow-y-auto px-4 py-5 md:px-6">
            @if (session('status'))
                <div class="mb-4 rounded-lg border border-[#cfe0d5] bg-[#e7f0ea] px-3 py-2 text-sm text-[#1a3c34]">{{ session('status') }}</div>
            @endif
            {{ $slot }}
        </main>
    </div>
</div>
</body>
</html>
