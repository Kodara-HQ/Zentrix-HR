@php
    use App\Support\Format;
    use App\Http\Controllers\ContractController;
    $form = old('_form');
@endphp
<x-layouts.app title="Contracts & documents">
    <div x-data="{ open: {{ $form ? "'".$form."'" : 'null' }} }">
        <x-page-header title="Contracts and documents" subtitle="Every contract with its end date, and the letters issued to staff. HR is reminded 60 days before a contract ends.">
            @if ($hr)<x-button @click="open = 'contract'">Add contract</x-button>@endif
        </x-page-header>

        <div class="mb-3 flex gap-2 text-sm">
            @foreach (['all' => 'All', 'active' => 'Active', 'expiring' => 'Ending in 60 days'] as $key => $label)
                <a href="{{ route('contracts.index', ['show' => $key]) }}" class="rounded-full px-3 py-1 font-semibold {{ $filter === $key ? 'bg-navy text-white' : 'bg-white text-body ring-1 ring-line hover:bg-sky' }}">{{ $label }}</a>
            @endforeach
        </div>

        <div class="overflow-auto rounded-2xl border border-line bg-white">
            <table class="w-full min-w-[760px] text-left text-sm">
                <thead class="text-xs uppercase tracking-wide text-muted"><tr>@foreach (['Employee', 'Department', 'Contract', 'Start', 'End', 'Status', ''] as $h)<th class="px-3 py-2">{{ $h }}</th>@endforeach</tr></thead>
                <tbody>
                    @forelse ($contracts as $c)
                        @php($soon = $c->status === 'ACTIVE' && $c->end_date && $c->end_date->lte(today()->addDays(60)))
                        <tr class="border-t border-line">
                            <td class="px-3 py-2.5 font-semibold text-ink"><a href="{{ route('employees.show', ['employee' => $c->employee_id, 'tab' => 'documents']) }}">{{ Format::fullName($c->employee) }}</a></td>
                            <td class="px-3 py-2.5">{{ $c->employee?->department?->name }}</td>
                            <td class="px-3 py-2.5">{{ $c->title }}@if ($c->notes)<div class="text-xs text-muted">{{ $c->notes }}</div>@endif</td>
                            <td class="whitespace-nowrap px-3 py-2.5">{{ Format::date($c->start_date) }}</td>
                            <td class="whitespace-nowrap px-3 py-2.5 {{ $soon ? 'font-semibold text-[#a12d2d]' : '' }}">{{ $c->end_date ? Format::date($c->end_date) : 'Open-ended' }}</td>
                            <td class="px-3 py-2.5"><x-badge :status="$c->status" /></td>
                            <td class="px-3 py-2.5 text-right">@if ($hr)<button type="button" class="font-semibold text-accent-strong" @click="open = 'c:{{ $c->id }}'">Update</button>@endif</td>
                        </tr>
                    @empty
                        <tr><td colspan="7" class="px-3 py-6 text-center text-muted">No contracts{{ $filter === 'all' ? '' : ' match' }}.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        <x-panel class="mt-4">
            <h2 class="mb-2 font-semibold text-ink">Letters issued</h2>
            @forelse ($letters as $l)
                <div class="flex flex-wrap justify-between gap-2 border-t border-line py-2 text-sm first:border-0">
                    <span>@if ($l->file_asset_id)<a class="font-semibold text-accent-strong" href="{{ route('files.show', $l->file_asset_id) }}" target="_blank">{{ $l->subject }}</a>@else{{ $l->subject }}@endif · {{ Format::fullName($l->employee) }}</span>
                    <span class="text-muted">{{ Format::date($l->issued_on) }}</span>
                </div>
            @empty
                <p class="text-sm text-muted">No letters issued yet.</p>
            @endforelse
        </x-panel>

        @if ($hr)
            @foreach ($contracts as $c)
                <x-modal show="open === 'c:{{ $c->id }}'" close="open = null" :title="$c->title">
                    <form method="post" action="{{ route('contracts.update', $c) }}" class="space-y-3">@csrf @method('patch')
                        <input type="hidden" name="_form" value="c:{{ $c->id }}">
                        <div class="grid gap-3 sm:grid-cols-2">
                            <x-field label="Status"><x-select name="status" :options="ContractController::STATUSES" :labels="true" :selected="$c->status" /></x-field>
                            <x-field label="End date"><x-input type="date" name="end_date" :value="$c->end_date" /></x-field>
                        </div>
                        <x-field label="Notes"><textarea name="notes" rows="2" class="{{ \App\Support\Ui::CONTROL }}">{{ $c->notes }}</textarea></x-field>
                        @if ($form === "c:{$c->id}")<x-errors />@endif
                        <x-button type="submit">Save</x-button>
                    </form>
                </x-modal>
            @endforeach
            <x-modal show="open === 'contract'" close="open = null" title="Add contract">
                <form method="post" action="{{ route('contracts.store') }}" class="space-y-3">@csrf
                    <input type="hidden" name="_form" value="contract">
                    <x-field label="Employee"><x-select name="employee_id" :options="$people" placeholder="Choose" required /></x-field>
                    <x-field label="Type"><x-select name="type" :options="array_combine($t = ['Employment contract', 'Fixed-term contract', 'Casual engagement', 'Consultancy', 'Internship'], $t)" /></x-field>
                    <x-field label="Title (optional)"><x-input name="title" /></x-field>
                    <div class="grid gap-3 sm:grid-cols-2">
                        <x-field label="Starts"><x-input type="date" name="start_date" required /></x-field>
                        <x-field label="Ends"><x-input type="date" name="end_date" /></x-field>
                    </div>
                    <x-field label="Notes"><textarea name="notes" rows="2" class="{{ \App\Support\Ui::CONTROL }}"></textarea></x-field>
                    @if ($form === 'contract')<x-errors />@endif
                    <x-button type="submit">Add</x-button>
                </form>
            </x-modal>
        @endif
    </div>
</x-layouts.app>
