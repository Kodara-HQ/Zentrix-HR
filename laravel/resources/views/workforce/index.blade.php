@php
    use App\Support\Format;
    $form = old('_form');
@endphp
<x-layouts.app title="Workforce planning">
    <div x-data="{ open: {{ $form ? "'".$form."'" : 'null' }} }">
        <x-page-header title="Workforce planning" :subtitle="'Approved headcount against the people in post for '.$year.', with open vacancies and payroll cost.'">
            @if ($hr)
                <x-button @click="open = 'budget'">Set budget</x-button>
                <x-button tone="ghost" @click="open = 'plan'">Succession plan</x-button>
            @endif
        </x-page-header>

        <form method="get" class="mb-3 flex items-end gap-2">
            <x-field label="Year"><x-input type="number" name="year" :value="$year" min="2020" max="2100" class="!w-28" onchange="this.form.submit()" /></x-field>
        </form>

        <div class="overflow-auto rounded-2xl border border-line bg-white">
            <table class="w-full min-w-[760px] text-left text-sm">
                <thead class="text-xs uppercase tracking-wide text-muted"><tr>@foreach (['Department', 'In post', 'Approved', 'Gap', 'Open vacancies', 'Monthly basic pay', 'Budgeted cost'] as $h)<th class="px-3 py-2">{{ $h }}</th>@endforeach</tr></thead>
                <tbody>
                    @forelse ($rows as $r)
                        <tr class="border-t border-line">
                            <td class="px-3 py-2.5 font-semibold text-ink">{{ $r['department']->name }}</td>
                            <td class="px-3 py-2.5 tabular-nums">{{ $r['current'] }}</td>
                            <td class="px-3 py-2.5 tabular-nums">{{ $r['approved'] ?: '—' }}</td>
                            <td class="px-3 py-2.5 tabular-nums {{ ($r['gap'] ?? 0) > 0 ? 'font-semibold text-accent-strong' : (($r['gap'] ?? 0) < 0 ? 'font-semibold text-[#a12d2d]' : '') }}">
                                {{ $r['gap'] === null ? '—' : ($r['gap'] > 0 ? $r['gap'].' to hire' : ($r['gap'] < 0 ? abs($r['gap']).' over' : 'On plan')) }}</td>
                            <td class="px-3 py-2.5 tabular-nums">{{ $r['open'] ?: '—' }}</td>
                            <td class="px-3 py-2.5 tabular-nums">{{ Format::money($r['monthly']) }}</td>
                            <td class="px-3 py-2.5 tabular-nums">{{ $r['budget'] ? Format::money($r['budget']) : '—' }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="7" class="px-3 py-6 text-center text-muted">No departments to show.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        @if ($budgets->isNotEmpty())
            <x-panel class="mt-4">
                <h2 class="mb-2 font-semibold text-ink">Budget lines for {{ $year }}</h2>
                @foreach ($budgets as $b)
                    <div class="border-t border-line py-2 text-sm first:border-0"><span class="font-semibold text-ink">{{ $b->department?->name }}</span>{{ $b->jobTitle ? ' · '.$b->jobTitle->name : '' }} · {{ $b->approved_headcount }} approved · {{ Format::money($b->estimated_cost) }}@if ($b->notes)<span class="text-muted"> · {{ $b->notes }}</span>@endif</div>
                @endforeach
            </x-panel>
        @endif

        <x-panel class="mt-4">
            <h2 class="mb-2 font-semibold text-ink">Succession plans</h2>
            @forelse ($plans as $p)
                <div class="flex items-start justify-between gap-2 border-t border-line py-2 text-sm first:border-0">
                    <div><span class="font-semibold text-ink">{{ $p->position_title }}</span>@if ($p->critical) <x-badge tone="red">Critical</x-badge>@endif
                        · now {{ $p->incumbent ? Format::fullName($p->incumbent) : 'vacant' }} · next {{ $p->successor ? Format::fullName($p->successor) : 'not named' }} ({{ $p->readiness }})
                        @if ($p->notes)<div class="text-xs text-muted">{{ $p->notes }}</div>@endif</div>
                    @if ($hr)@include('employees._remove', ['route' => route('workforce.plans.destroy', $p)])@endif
                </div>
            @empty
                <p class="text-sm text-muted">No succession plans yet.</p>
            @endforelse
        </x-panel>

        @if ($hr)
            <x-modal show="open === 'budget'" close="open = null" title="Headcount budget">
                <form method="post" action="{{ route('workforce.budgets.save') }}" class="space-y-3">@csrf
                    <input type="hidden" name="_form" value="budget">
                    <div class="grid gap-3 sm:grid-cols-2">
                        <x-field label="Year"><x-input type="number" name="year" :value="$year" required /></x-field>
                        <x-field label="Department"><x-select name="department_id" :options="$departments" placeholder="Choose" required /></x-field>
                        <x-field label="Job title (optional)"><x-select name="job_title_id" :options="$jobTitles" placeholder="Whole department" /></x-field>
                        <x-field label="Approved headcount"><x-input type="number" min="0" name="approved_headcount" required /></x-field>
                        <x-field label="Estimated annual cost (GHS)"><x-input type="number" step="0.01" min="0" name="estimated_cost" required /></x-field>
                    </div>
                    <x-field label="Notes"><x-input name="notes" /></x-field>
                    <p class="text-xs text-muted">Saving the same year, department and job title again updates that line.</p>
                    @if ($form === 'budget')<x-errors />@endif
                    <x-button type="submit">Save</x-button>
                </form>
            </x-modal>
            <x-modal show="open === 'plan'" close="open = null" title="Succession plan">
                <form method="post" action="{{ route('workforce.plans.store') }}" class="space-y-3">@csrf
                    <input type="hidden" name="_form" value="plan">
                    <x-field label="Position"><x-input name="position_title" required /></x-field>
                    <div class="grid gap-3 sm:grid-cols-2">
                        <x-field label="Department"><x-select name="department_id" :options="$departments" placeholder="—" /></x-field>
                        <x-field label="Readiness"><x-select name="readiness" :options="array_combine($r = ['Ready now', '1–2 years', '3+ years'], $r)" /></x-field>
                        <x-field label="Current holder"><x-select name="incumbent_id" :options="$people" placeholder="Vacant" /></x-field>
                        <x-field label="Successor"><x-select name="successor_id" :options="$people" placeholder="Not named" /></x-field>
                    </div>
                    <label class="flex items-center gap-2 text-sm"><input type="checkbox" name="critical" value="1"> Critical role</label>
                    <x-field label="Notes"><x-input name="notes" /></x-field>
                    @if ($form === 'plan')<x-errors />@endif
                    <x-button type="submit">Add</x-button>
                </form>
            </x-modal>
        @endif
    </div>
</x-layouts.app>
