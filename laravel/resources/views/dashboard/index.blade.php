@php
    use App\Support\Format;
    $t = $totals;
    $pct = fn ($v) => $v === null ? '—' : $v.'%';
@endphp
<x-layouts.app title="Dashboard">
    <x-page-header title="Dashboard" subtitle="Workforce position, attendance, and the approvals waiting on you." />

    <form method="get" class="mb-5 flex flex-wrap items-end gap-2">
        <x-field label="From"><x-input type="date" name="from" :value="$filters['from']" class="!w-40" /></x-field>
        <x-field label="To"><x-input type="date" name="to" :value="$filters['to']" class="!w-40" /></x-field>
        <x-field label="Department"><x-select name="department_id" :options="$departments" :selected="$filters['department_id']" placeholder="All departments" class="!w-52" /></x-field>
        <x-button type="submit" tone="ghost">Apply</x-button>
        @if (request()->hasAny(['from', 'to', 'department_id']))<a href="{{ route('dashboard') }}" class="px-2 py-2 text-sm font-semibold text-accent-strong">Reset</a>@endif
    </form>

    <div class="grid gap-3 sm:grid-cols-2 xl:grid-cols-5">
        <x-stat label="Total employees" :value="$t['employees']" />
        <x-stat label="Active" :value="$t['active']" />
        <x-stat label="On probation" :value="$t['probation']" />
        <x-stat label="On leave today" :value="$t['on_leave']" />
        <x-stat label="Inactive or exited" :value="$t['inactive']" />
        <x-stat label="New in period" :value="$t['new_hires']" :hint="Format::date($filters['from']).' – '.Format::date($filters['to'])" />
        <x-stat label="Turnover" :value="$t['turnover'].'%'" hint="Exits against average headcount" />
        <x-stat label="Contracts expiring" :value="$t['contracts_expiring']" hint="Within 60 days" />
        <x-stat label="Attendance" :value="$pct($t['attendance_rate'])" hint="Last 30 days" />
        <x-stat label="Absenteeism" :value="$pct($t['absenteeism_rate'])" hint="Last 30 days" />
    </div>

    <div class="mt-5 grid gap-4 xl:grid-cols-3">
        <x-panel class="xl:col-span-2">
            <h2 class="mb-4 font-semibold text-ink">Active headcount by department</h2>
            <x-bar-chart :data="$byDepartment" empty="No active employees." />
        </x-panel>
        <x-panel>
            <h2 class="mb-3 font-semibold text-ink">Waiting on you</h2>
            <ul class="divide-y divide-line text-sm">
                @foreach ($pending as [$label, $count, $link])
                    <li><a href="{{ $link }}" class="flex items-center justify-between py-2 hover:text-accent-strong">
                        <span>{{ $label }}</span>
                        <span class="{{ $count ? 'rounded-full bg-accent-strong px-2 text-xs font-semibold text-white' : 'text-muted' }}">{{ $count }}</span>
                    </a></li>
                @endforeach
            </ul>
        </x-panel>
    </div>

    <div class="mt-4 grid gap-4 xl:grid-cols-3">
        <x-panel class="xl:col-span-2">
            <h2 class="mb-3 font-semibold text-ink">Daily attendance rate</h2>
            <x-line-chart :points="$trend" empty="No attendance has been recorded in the last 30 days." />
        </x-panel>
        <x-panel>
            <h2 class="mb-4 font-semibold text-ink">Active employees by gender</h2>
            <x-bar-chart :data="$byGender" empty="No active employees." />
        </x-panel>
    </div>

    <div class="mt-4 grid gap-4 md:grid-cols-2">
        <x-panel>
            <h2 class="mb-3 font-semibold text-ink">Birthdays in the next 30 days</h2>
            <ul class="space-y-2 text-sm">
                @forelse ($birthdays as $b)
                    <li class="flex justify-between gap-3">
                        <a href="{{ route('employees.show', $b['employee']) }}" class="text-ink hover:text-accent-strong">{{ Format::fullName($b['employee']) }}</a>
                        <span class="text-muted">{{ $b['days'] === 0 ? 'Today' : $b['date']->format('j M') }}</span>
                    </li>
                @empty
                    <li class="text-muted">No birthdays in the next 30 days.</li>
                @endforelse
            </ul>
        </x-panel>
        <x-panel>
            <h2 class="mb-3 font-semibold text-ink">Contracts ending in the next 60 days</h2>
            <ul class="space-y-2 text-sm">
                @forelse ($contracts as $c)
                    <li class="flex justify-between gap-3">
                        <a href="{{ route('employees.show', $c->employee) }}" class="text-ink hover:text-accent-strong">{{ Format::fullName($c->employee) }}</a>
                        <span class="text-muted">{{ Format::date($c->end_date) }}</span>
                    </li>
                @empty
                    <li class="text-muted">No contracts end in the next 60 days.</li>
                @endforelse
            </ul>
        </x-panel>
    </div>
</x-layouts.app>
