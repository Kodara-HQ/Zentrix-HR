@props(['title' => null])
@php
    use App\Enums\Role;
    use App\Support\Format;
    use App\Support\Navigation;

    $user = auth()->user();
    $staff = $user->role === Role::EMPLOYEE;
    $items = Navigation::for($user);
    // A system administrator outside any company only has the Companies pages.
    $system = $user->isSystemAdmin() && ! $user->actingInCompany;
@endphp
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>{{ $staff ? 'ESS — Employee Self Service Portal' : (isset($title) ? "$title · $company" : $company) }}</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    @if ($themeCss)<style>{!! $themeCss !!}</style>@endif
</head>
<body x-data="{ collapsed: false, mobile: false }">
<div class="flex h-screen overflow-hidden">
    <button x-show="mobile" x-cloak class="fixed inset-0 z-30 bg-navy/40 md:hidden" @click="mobile = false" aria-label="Close menu"></button>

    <aside class="fixed z-40 flex h-screen shrink-0 flex-col bg-navy text-cream transition-all md:static"
           :class="[collapsed ? 'w-[84px]' : 'w-[268px]', mobile ? 'translate-x-0' : '-translate-x-full md:translate-x-0']">
        <div class="flex items-center gap-3 px-4 py-5">
            <div class="grid h-9 w-9 shrink-0 place-items-center rounded-md bg-accent-strong font-bold text-white">{{ mb_substr($company, 0, 1) }}</div>
            <div x-show="!collapsed">
                <div class="text-sm font-semibold leading-tight">{{ $company }}</div>
                @if ($staff)
                    <div class="text-xs font-semibold tracking-[0.18em] text-accent">ESS</div>
                    <div class="text-[11px] leading-snug text-sky-text">Employee Self Service Portal</div>
                @else
                    <div class="text-xs tracking-[0.14em] text-accent">HUMAN RESOURCES</div>
                @endif
            </div>
        </div>
        <nav class="flex min-h-0 flex-1 flex-col px-2 pb-4">
            <div class="space-y-0.5 overflow-auto">
                @foreach ($items as $item)
                    <a href="{{ url($item['path']) }}"
                       class="flex items-center gap-3 rounded-lg px-3 py-2 text-sm {{ Navigation::isActive($item['path']) ? 'bg-accent-strong text-white' : 'text-sky-text hover:bg-white/8' }}">
                        <x-dynamic-component :component="'lucide-'.$item['icon']" class="h-[18px] w-[18px] shrink-0" />
                        <span x-show="!collapsed">{{ $item['label'] }}</span>
                        @if ($item['path'] === '/notifications' && $unread > 0)
                            <span x-show="!collapsed" class="ml-auto rounded-full bg-cream px-1.5 text-xs text-navy">{{ $unread }}</span>
                        @endif
                    </a>
                @endforeach
            </div>
            @if ($staff)
                <div class="mt-auto border-t border-white/10 pt-3">
                    <a href="{{ url('/settings') }}" class="flex items-center gap-3 rounded-lg px-3 py-2 text-sm {{ Navigation::isActive('/settings') ? 'bg-accent-strong text-white' : 'text-sky-text hover:bg-white/8' }}">
                        <x-lucide-settings class="h-[18px] w-[18px]" />
                        <span x-show="!collapsed">Settings</span>
                    </a>
                </div>
            @endif
        </nav>
    </aside>

    <div class="flex h-screen min-w-0 flex-1 flex-col">
        <header class="no-print z-20 flex h-16 shrink-0 items-center gap-3 border-b border-line bg-paper/95 px-4 backdrop-blur">
            <button class="rounded-lg p-2 text-navy hover:bg-cream md:hidden" @click="mobile = true" aria-label="Open menu"><x-lucide-menu class="h-[18px] w-[18px]" /></button>
            <button class="hidden rounded-lg p-2 text-navy hover:bg-cream md:block" @click="collapsed = !collapsed" aria-label="Collapse menu">
                <x-lucide-menu x-show="collapsed" class="h-[18px] w-[18px]" />
                <x-lucide-x x-show="!collapsed" class="h-[18px] w-[18px]" />
            </button>
            @if ($staff)
                <div class="min-w-0 leading-tight">
                    <div class="text-xs font-semibold tracking-[0.18em] text-accent-strong">ESS</div>
                    <div class="truncate text-sm font-semibold text-navy">Employee Self Service Portal</div>
                </div>
            @elseif ($system)
                <div class="min-w-0 truncate text-sm font-semibold text-navy">System administration</div>
            @else
                <form action="{{ url('/employees') }}" method="get" class="hidden min-w-0 flex-1 md:block">
                    <input name="search" value="{{ request('search') }}" placeholder="Search employees by name or number"
                           class="w-full max-w-md rounded-lg border border-line bg-cream px-3 py-2 text-sm outline-none focus:border-accent-strong">
                </form>
            @endif
            <div class="ml-auto flex items-center gap-1 sm:gap-2">
                @unless ($system)
                <a href="{{ url('/notifications') }}" class="relative rounded-lg p-2 text-navy hover:bg-cream" title="Notifications">
                    <x-lucide-bell class="h-[18px] w-[18px]" />
                    @if ($unread > 0)<span class="absolute right-1 top-1 h-2 w-2 rounded-full bg-accent-strong"></span>@endif
                </a>
                @endunless
                <div class="min-w-0 px-1 text-right leading-tight">
                    <div class="truncate text-sm font-semibold text-navy">{{ $user->first_name }} {{ $user->last_name }}</div>
                    <div class="text-xs text-muted">{{ Format::role($user->isSystemAdmin() ? Role::SYSTEM_ADMIN : $user->role) }}</div>
                </div>
                <form method="post" action="{{ route('logout') }}">
                    @csrf
                    <button class="rounded-lg p-2 text-navy hover:bg-cream" title="Sign out"><x-lucide-log-out class="h-[18px] w-[18px]" /></button>
                </form>
            </div>
        </header>
        @if ($user->actingInCompany)
            <div class="no-print flex flex-wrap items-center gap-x-3 gap-y-1 border-b border-line bg-sky px-4 py-2 text-sm text-navy md:px-6">
                <x-lucide-building-2 class="h-4 w-4 shrink-0" />
                <span>You are working in <strong>{{ $company }}</strong> as the system administrator.</span>
                <form method="post" action="{{ route('system.companies.leave') }}" class="ml-auto">
                    @csrf
                    <button class="font-semibold text-accent-strong">Back to companies</button>
                </form>
            </div>
        @endif
        <main class="min-h-0 min-w-0 flex-1 overflow-y-auto px-4 py-5 md:px-6">
            @if (session('status'))
                <div class="mb-4 rounded-lg border border-sky-text bg-sky px-3 py-2 text-sm text-navy">{{ session('status') }}</div>
            @endif
            {{ $slot }}
        </main>
    </div>
</div>
</body>
</html>
