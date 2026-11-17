@php
    use App\Support\Format;
    $user = auth()->user();
    $form = old('_form');
@endphp
<x-layouts.app :title="$own ? 'My leave' : 'Leave'">
    <div x-data="{ applying: {{ $form === 'apply' ? 'true' : 'false' }}, rejecting: {{ str_starts_with((string) $form, 'reject:') ? "'".substr($form, 7)."'" : 'null' }} }">
        <x-page-header :title="$own ? 'My leave' : 'Leave'"
                       :subtitle="$own ? 'Your balances and the leave you have applied for. Print a form to sign.' : 'Balances update when a request is approved, rejected, or cancelled.'">
            @if ($me)<x-button tone="ghost" :href="route('leave.form')" target="_blank">Print leave form</x-button>@endif
            @if ($me || $applicants->isNotEmpty())<x-button @click="applying = true">Apply for leave</x-button>@endif
        </x-page-header>

        @if ($balances->isNotEmpty())
            <div class="mb-5 grid gap-3 sm:grid-cols-2 lg:grid-cols-4">
                @foreach ($balances as $balance)
                    <x-panel>
                        <div class="text-sm font-semibold text-ink">{{ $balance->leaveType->name }}</div>
                        <div class="mt-1 text-3xl font-semibold tracking-tight text-navy">{{ Format::wholeDays($balance->entitled - $balance->used - $balance->pending) }}</div>
                        <div class="text-xs text-muted">days left · {{ Format::wholeDays($balance->used) }} used · {{ Format::wholeDays($balance->pending) }} pending</div>
                    </x-panel>
                @endforeach
            </div>
        @endif

        <div class="mb-3 flex flex-wrap items-center gap-2 text-sm">
            @foreach (['' => 'All', 'PENDING' => 'Pending', 'APPROVED' => 'Approved', 'REJECTED' => 'Rejected', 'CANCELLED' => 'Cancelled'] as $value => $label)
                <a href="{{ route('leave.index', array_filter(['status' => $value])) }}"
                   class="rounded-full px-3 py-1 font-semibold {{ request('status', '') === $value ? 'bg-navy text-white' : 'bg-white text-body ring-1 ring-line hover:bg-sky' }}">
                    {{ $label }}@if ($value === 'PENDING' && $pendingCount) <span class="ml-1 rounded-full bg-accent px-1.5 text-xs text-white">{{ $pendingCount }}</span>@endif
                </a>
            @endforeach
        </div>

        <div class="overflow-auto rounded-2xl border border-line bg-white">
            <table class="w-full min-w-[820px] text-left text-sm">
                <thead class="text-xs uppercase tracking-wide text-muted">
                    <tr>@foreach (['Emp ID', 'Emp name', 'Type', 'Dates', 'Days', 'Status', ''] as $heading)<th class="px-3 py-2">{{ $heading }}</th>@endforeach</tr>
                </thead>
                <tbody>
                    @forelse ($requests as $row)
                        <tr class="border-t border-line align-top">
                            <td class="whitespace-nowrap px-3 py-2.5">{{ $row->employee?->employee_no }}</td>
                            <td class="px-3 py-2.5 font-semibold text-ink">{{ Format::fullName($row->employee) }}</td>
                            <td class="px-3 py-2.5">{{ $row->leaveType?->name }}
                                @if ($row->reason)<div class="max-w-[16rem] text-xs text-muted">{{ $row->reason }}</div>@endif
                            </td>
                            <td class="whitespace-nowrap px-3 py-2.5">{{ Format::date($row->start_date) }} – {{ Format::date($row->end_date) }}</td>
                            <td class="px-3 py-2.5">{{ Format::wholeDays($row->days) }}</td>
                            <td class="px-3 py-2.5"><x-badge :status="$row->status" />
                                @if ($row->decision_note)<div class="mt-1 max-w-[14rem] text-xs text-muted">{{ $row->decision_note }}</div>@endif
                            </td>
                            <td class="px-3 py-2.5">
                                <div class="flex flex-wrap items-center justify-end gap-2">
                                    <a class="text-sm font-semibold text-accent-strong" href="{{ route('leave.form', ['request' => $row->id]) }}" target="_blank">Print</a>
                                    @if ($service->canDecide($user, $row))
                                        <form method="post" action="{{ route('leave.decide', $row) }}">
                                            @csrf
                                            <input type="hidden" name="decision" value="APPROVED">
                                            <x-button type="submit" small>Approve</x-button>
                                        </form>
                                        <x-button small tone="danger" @click="rejecting = '{{ $row->id }}'">Reject</x-button>
                                    @endif
                                    @if ($service->canCancel($user, $row))
                                        <form method="post" action="{{ route('leave.cancel', $row) }}" onsubmit="return confirm('Cancel this leave request? The days go back to the balance.')">
                                            @csrf
                                            <x-button type="submit" small tone="ghost">Cancel</x-button>
                                        </form>
                                    @endif
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="7" class="px-3 py-6 text-center text-muted">No leave requests.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        @if ($requests->hasPages())
            <div class="mt-3 flex items-center justify-between text-sm text-muted">
                <span>{{ $requests->total() }} requests</span>
                <div class="flex gap-2">
                    <x-button small tone="ghost" :href="$requests->previousPageUrl()" @class(['pointer-events-none opacity-50' => $requests->onFirstPage()])>Previous</x-button>
                    <x-button small tone="ghost" :href="$requests->nextPageUrl()" @class(['pointer-events-none opacity-50' => ! $requests->hasMorePages()])>Next</x-button>
                </div>
            </div>
        @endif

        @unless ($own)
            <x-panel class="mt-5">
                <h2 class="mb-2 font-semibold text-ink">Who is away in the next 31 days</h2>
                @forelse ($calendar as $row)
                    <p class="text-sm"><span class="font-semibold text-ink">{{ Format::fullName($row->employee) }}</span> · {{ $row->leaveType?->name }} · {{ Format::date($row->start_date) }} to {{ Format::date($row->end_date) }}</p>
                @empty
                    <p class="text-sm text-muted">No approved leave in this window.</p>
                @endforelse
            </x-panel>
        @endunless

        <x-modal show="applying" title="Leave application">
            <form method="post" action="{{ route('leave.store') }}" class="space-y-3">
                @csrf
                <input type="hidden" name="_form" value="apply">
                @unless ($own)
                    <x-field label="Employee">
                        <x-select name="employee_id" :options="$applicants->mapWithKeys(fn ($e) => [$e->id => $e->employee_no.' · '.Format::fullName($e)])->all()" :selected="$me?->id" placeholder="Choose" required />
                    </x-field>
                @endunless
                <x-field label="Leave type"><x-select name="leave_type_id" :options="$types->pluck('name', 'id')->all()" placeholder="Choose" required /></x-field>
                <div class="grid gap-3 sm:grid-cols-2">
                    <x-field label="Start"><x-input type="date" name="start_date" required /></x-field>
                    <x-field label="End"><x-input type="date" name="end_date" required /></x-field>
                </div>
                <x-field label="Reason"><textarea name="reason" rows="3" class="{{ \App\Support\Ui::CONTROL }}">{{ $form === 'apply' ? old('reason') : '' }}</textarea></x-field>
                <p class="text-xs text-muted">Weekends and public holidays are not counted, except for leave counted in calendar days such as maternity leave.</p>
                @if ($form === 'apply')<x-errors />@endif
                <x-button type="submit">Submit application</x-button>
            </form>
        </x-modal>

        <x-modal show="rejecting !== null" close="rejecting = null" title="Reject leave">
            <form method="post" :action="'{{ url('/leave') }}/' + rejecting + '/decision'" class="space-y-3">
                @csrf
                <input type="hidden" name="_form" :value="'reject:' + rejecting">
                <input type="hidden" name="decision" value="REJECTED">
                <x-field label="Reason for the employee"><textarea name="note" rows="3" required class="{{ \App\Support\Ui::CONTROL }}" placeholder="For example: the team is short-staffed that week.">{{ str_starts_with((string) $form, 'reject:') ? old('note') : '' }}</textarea></x-field>
                @if (str_starts_with((string) $form, 'reject:'))<x-errors />@endif
                <x-button type="submit" tone="danger">Reject request</x-button>
            </form>
        </x-modal>
    </div>
</x-layouts.app>
