@php
    use App\Support\Format;
    $user = auth()->user();
    $form = old('_form');
    $subtitle = $own
        ? 'Clock-in and clock-out come from the fingerprint clock. Ask for a correction if a scan was missed.'
        : ($calendar
            ? "Punches come from the fingerprint clock. Reporting time {$calendar->start_time}, closing time {$calendar->end_time}. A scan more than {$calendar->grace_minutes} minutes after reporting time is late."
            : 'Attendance is recorded by the fingerprint clock.');
    $statuses = ['PRESENT', 'LATE', 'ABSENT', 'REMOTE', 'LEAVE', 'HOLIDAY', 'OFF'];
    $patternOptions = $patterns->mapWithKeys(fn ($s) => [$s->id => "{$s->name} {$s->start_time}–{$s->end_time}"])->all();
@endphp
<x-layouts.app :title="$own ? 'My attendance' : 'Attendance'">
    <div x-data="{ open: {{ $form ? "'".$form."'" : 'null' }} }">
        <x-page-header :title="$own ? 'My attendance' : 'Attendance'" :subtitle="$subtitle">
            @if ($desk)<x-button tone="ghost" @click="open = 'manual'">Manual entry</x-button>@endif
            @if ($own || $desk)
                <x-button tone="ghost" @click="open = 'correction'">Request correction</x-button>
                <x-button tone="ghost" @click="open = 'overtime'">Request overtime</x-button>
            @endif
        </x-page-header>

        @if ($admin)
            <x-panel class="mb-4">
                <h2 class="font-semibold text-ink">Fingerprint clock address</h2>
                <p class="mt-1 text-sm text-muted">Point the clock at one of these, and set the same device key as <code>BIOMETRIC_DEVICE_KEY</code> in <code>.env</code>.</p>
                <ul class="mt-2 space-y-1 font-mono text-sm">
                    @forelse ($clockUrls as $url)<li>{{ $url }}</li>@empty<li class="text-muted">No office network address is available on this computer.</li>@endforelse
                </ul>
            </x-panel>
        @endif

        <form method="get" class="mb-3 flex flex-wrap items-end gap-2">
            @unless ($own)
                <x-field label="Employee"><x-select name="employee_id" :options="$peopleOptions" :selected="request('employee_id')" placeholder="Everyone" class="!w-60" /></x-field>
            @endunless
            <x-field label="From"><x-input type="date" name="from" :value="request('from')" class="!w-40" /></x-field>
            <x-field label="To"><x-input type="date" name="to" :value="request('to')" class="!w-40" /></x-field>
            <x-field label="Status"><x-select name="status" :options="$statuses" :labels="true" :selected="request('status')" placeholder="Any" class="!w-36" /></x-field>
            <x-button type="submit" tone="ghost">Filter</x-button>
            @if (request()->hasAny(['employee_id', 'from', 'to', 'status']))<a href="{{ route('attendance.index') }}" class="px-2 py-2 text-sm font-semibold text-accent-strong">Reset</a>@endif
        </form>

        <div class="overflow-auto rounded-2xl border border-line bg-white">
            <table class="w-full min-w-[760px] text-left text-sm">
                <thead class="text-xs uppercase tracking-wide text-muted">
                    <tr>@foreach (array_merge(['Date', 'Emp ID', 'Emp name', 'Clock in', 'Clock out'], $admin ? ['In IP', 'Out IP'] : [], ['Status', 'Late (min)', 'Source']) as $h)<th class="px-3 py-2">{{ $h }}</th>@endforeach</tr>
                </thead>
                <tbody>
                    @forelse ($records as $row)
                        <tr class="border-t border-line">
                            <td class="whitespace-nowrap px-3 py-2">{{ Format::date($row->date) }}</td>
                            <td class="whitespace-nowrap px-3 py-2">{{ $row->employee?->employee_no }}</td>
                            <td class="px-3 py-2 font-semibold text-ink">{{ Format::fullName($row->employee) }}</td>
                            <td class="px-3 py-2 tabular-nums">{{ Format::time($row->clock_in) }}</td>
                            <td class="px-3 py-2 tabular-nums">{{ Format::time($row->clock_out) }}</td>
                            @if ($admin)<td class="px-3 py-2 text-xs">{{ $row->clock_in_ip ?: '—' }}</td><td class="px-3 py-2 text-xs">{{ $row->clock_out_ip ?: '—' }}</td>@endif
                            <td class="px-3 py-2"><x-badge :status="$row->status" /></td>
                            <td class="px-3 py-2 tabular-nums">{{ $row->late_minutes ?: '—' }}</td>
                            <td class="px-3 py-2 text-muted">{{ Format::title($row->source) }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="{{ $admin ? 10 : 8 }}" class="px-3 py-6 text-center text-muted">No attendance recorded{{ request()->hasAny(['employee_id', 'from', 'to', 'status']) ? ' for this filter' : ' yet' }}.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        @if ($records->hasPages())
            <div class="mt-3 flex items-center justify-between text-sm text-muted">
                <span>{{ $records->total() }} days</span>
                <div class="flex gap-2">
                    <x-button small tone="ghost" :href="$records->previousPageUrl()" @class(['pointer-events-none opacity-50' => $records->onFirstPage()])>Previous</x-button>
                    <x-button small tone="ghost" :href="$records->nextPageUrl()" @class(['pointer-events-none opacity-50' => ! $records->hasMorePages()])>Next</x-button>
                </div>
            </div>
        @endif

        <div class="mt-5 grid gap-4 lg:grid-cols-2">
            <x-panel>
                <h2 class="mb-2 font-semibold text-ink">Correction requests</h2>
                @forelse ($corrections as $item)
                    <div class="flex flex-wrap items-center justify-between gap-2 border-t border-line py-2 text-sm first:border-0">
                        <div>
                            <span class="font-semibold text-ink">{{ Format::fullName($item->employee) }}</span> · {{ Format::date($item->date) }}
                            · {{ Format::time($item->requested_clock_in) }}–{{ Format::time($item->requested_clock_out) }}
                            <div class="text-xs text-muted">{{ $item->reason }}</div>
                        </div>
                        @if (in_array($item->status->value, ['PENDING_MANAGER', 'PENDING_HR'], true) && $desk && $service->canDecide($user, $item->employee))
                            @include('attendance._decide', ['route' => route('attendance.corrections.decide', $item)])
                        @else
                            <x-badge :status="$item->status" />
                        @endif
                    </div>
                @empty
                    <p class="text-sm text-muted">No correction requests.</p>
                @endforelse
            </x-panel>
            <x-panel>
                <h2 class="mb-2 font-semibold text-ink">Overtime</h2>
                @forelse ($overtime as $item)
                    <div class="flex flex-wrap items-center justify-between gap-2 border-t border-line py-2 text-sm first:border-0">
                        <div>
                            <span class="font-semibold text-ink">{{ Format::fullName($item->employee) }}</span> · {{ Format::date($item->date) }}
                            · {{ rtrim(rtrim((string) $item->hours, '0'), '.') }} h at ×{{ rtrim(rtrim((string) $item->multiplier, '0'), '.') }}
                            <div class="text-xs text-muted">{{ $item->reason }}</div>
                        </div>
                        @if (in_array($item->status->value, ['PENDING_MANAGER', 'PENDING_HR'], true) && $desk && $service->canDecide($user, $item->employee))
                            @include('attendance._decide', ['route' => route('attendance.overtime.decide', $item)])
                        @else
                            <x-badge :status="$item->status" />
                        @endif
                    </div>
                @empty
                    <p class="text-sm text-muted">No overtime requests.</p>
                @endforelse
            </x-panel>
        </div>

        @unless ($own)
            <x-panel class="mt-4">
                <div class="mb-2 flex items-center justify-between">
                    <h2 class="font-semibold text-ink">Shift roster</h2>
                    @if (\App\Support\Access::hasRole($user, ['HR_ADMIN', 'HR_MANAGER', 'DEPARTMENT_MANAGER']) && $patterns->isNotEmpty())
                        <x-button small tone="ghost" @click="open = 'shift'">Assign shift</x-button>
                    @endif
                </div>
                @forelse ($shifts as $item)
                    <p class="text-sm"><span class="font-semibold text-ink">{{ Format::fullName($item->employee) }}</span> · {{ $item->shift->name }} ({{ $item->shift->start_time }}–{{ $item->shift->end_time }}) from {{ Format::date($item->effective_from) }}</p>
                @empty
                    <p class="text-sm text-muted">Nobody has a shift; everyone follows the working calendar.</p>
                @endforelse
            </x-panel>
        @endunless

        {{-- Dialogs --}}
        @if ($desk)
            <x-modal show="open === 'manual'" close="open = null" title="Manual attendance">
                <form method="post" action="{{ route('attendance.manual') }}" class="space-y-3">
                    @csrf
                    <input type="hidden" name="_form" value="manual">
                    <x-field label="Employee"><x-select name="employee_id" :options="$peopleOptions" placeholder="Choose" required /></x-field>
                    <div class="grid gap-3 sm:grid-cols-2">
                        <x-field label="Date"><x-input type="date" name="date" :value="today()->toDateString()" required /></x-field>
                        <x-field label="Status"><x-select name="status" :options="$statuses" :labels="true" /></x-field>
                        <x-field label="Clock in"><x-input type="time" name="clock_in" /></x-field>
                        <x-field label="Clock out"><x-input type="time" name="clock_out" /></x-field>
                    </div>
                    <x-field label="Notes"><x-input name="notes" /></x-field>
                    @if ($form === 'manual')<x-errors />@endif
                    <x-button type="submit">Save</x-button>
                </form>
            </x-modal>
        @endif

        @if ($own || $desk)
            <x-modal show="open === 'correction'" close="open = null" title="Attendance correction">
                <form method="post" action="{{ route('attendance.corrections.store') }}" class="space-y-3">
                    @csrf
                    <input type="hidden" name="_form" value="correction">
                    @unless ($own)<x-field label="Employee"><x-select name="employee_id" :options="$peopleOptions" placeholder="Choose" required /></x-field>@endunless
                    <x-field label="Date"><x-input type="date" name="date" required /></x-field>
                    <div class="grid gap-3 sm:grid-cols-2">
                        <x-field label="Actual clock in"><x-input type="time" name="clock_in" /></x-field>
                        <x-field label="Actual clock out"><x-input type="time" name="clock_out" /></x-field>
                    </div>
                    <x-field label="Reason"><textarea name="reason" rows="3" required class="{{ \App\Support\Ui::CONTROL }}" placeholder="For example: the clock did not read my finger at 08:02.">{{ $form === 'correction' ? old('reason') : '' }}</textarea></x-field>
                    @if ($form === 'correction')<x-errors />@endif
                    <x-button type="submit">Send request</x-button>
                </form>
            </x-modal>

            <x-modal show="open === 'overtime'" close="open = null" title="Overtime request">
                <form method="post" action="{{ route('attendance.overtime.store') }}" class="space-y-3">
                    @csrf
                    <input type="hidden" name="_form" value="overtime">
                    @unless ($own)<x-field label="Employee"><x-select name="employee_id" :options="$peopleOptions" placeholder="Choose" required /></x-field>@endunless
                    <div class="grid gap-3 sm:grid-cols-2">
                        <x-field label="Date"><x-input type="date" name="date" required /></x-field>
                        <x-field label="Hours"><x-input type="number" step="0.25" min="0.25" max="24" name="hours" required /></x-field>
                    </div>
                    <x-field label="Reason"><textarea name="reason" rows="3" required class="{{ \App\Support\Ui::CONTROL }}">{{ $form === 'overtime' ? old('reason') : '' }}</textarea></x-field>
                    <p class="text-xs text-muted">Weekend overtime is paid at the weekend rate. Approved overtime is added to that month’s payroll.</p>
                    @if ($form === 'overtime')<x-errors />@endif
                    <x-button type="submit">Send request</x-button>
                </form>
            </x-modal>
        @endif

        @unless ($own)
            <x-modal show="open === 'shift'" close="open = null" title="Assign shift">
                <form method="post" action="{{ route('attendance.shifts.store') }}" class="space-y-3">
                    @csrf
                    <input type="hidden" name="_form" value="shift">
                    <x-field label="Employee"><x-select name="employee_id" :options="$peopleOptions" placeholder="Choose" required /></x-field>
                    <x-field label="Shift"><x-select name="shift_id" :options="$patternOptions" required /></x-field>
                    <div class="grid gap-3 sm:grid-cols-2">
                        <x-field label="From"><x-input type="date" name="effective_from" :value="today()->toDateString()" required /></x-field>
                        <x-field label="Until (optional)"><x-input type="date" name="effective_to" /></x-field>
                    </div>
                    @if ($form === 'shift')<x-errors />@endif
                    <x-button type="submit">Assign</x-button>
                </form>
            </x-modal>
        @endunless
    </div>
</x-layouts.app>
