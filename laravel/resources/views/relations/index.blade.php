@php
    use App\Support\Format;
    use App\Http\Controllers\RelationsController;
    $form = old('_form');
@endphp
<x-layouts.app title="Employee relations">
    <div x-data="{ open: {{ $form ? "'".$form."'" : 'null' }} }">
        <x-page-header title="Employee relations" subtitle="Grievances, disciplinary cases and warnings. Only HR sees this page; people see their own warning letters on their profile.">
            <x-button @click="open = 'grievance'">Record grievance</x-button>
            <x-button tone="ghost" @click="open = 'case'">Open case</x-button>
            <x-button tone="ghost" @click="open = 'warning'">Issue warning</x-button>
        </x-page-header>

        <div class="grid gap-4 xl:grid-cols-2">
            <x-panel>
                <h2 class="mb-2 font-semibold text-ink">Grievances</h2>
                @forelse ($grievances as $g)
                    <div class="flex flex-wrap items-start justify-between gap-2 border-t border-line py-2 text-sm first:border-0">
                        <div><span class="font-semibold text-ink">{{ Format::fullName($g->employee) }}</span> · {{ $g->category }} · {{ Format::date($g->created_at) }}
                            @if ($g->against_employee_id)<span class="text-muted"> · against {{ $names[$g->against_employee_id] ?? '—' }}</span>@endif
                            <div class="text-xs text-muted">{{ $g->description }}</div>
                            @if ($g->resolution)<div class="text-xs">Resolution: {{ $g->resolution }}</div>@endif
                        </div>
                        <span class="flex items-center gap-2"><x-badge :status="$g->status" /><button type="button" class="font-semibold text-accent-strong" @click="open = 'g:{{ $g->id }}'">Update</button></span>
                    </div>
                    <x-modal show="open === 'g:{{ $g->id }}'" close="open = null" title="Update grievance">
                        <form method="post" action="{{ route('relations.grievances.update', $g) }}" class="space-y-3">@csrf @method('patch')
                            <input type="hidden" name="_form" value="g:{{ $g->id }}">
                            <x-field label="Status"><x-select name="status" :options="RelationsController::STATUSES" :labels="true" :selected="$g->status->value" /></x-field>
                            <x-field label="Resolution"><textarea name="resolution" rows="3" class="{{ \App\Support\Ui::CONTROL }}">{{ $g->resolution }}</textarea></x-field>
                            <x-button type="submit">Save</x-button>
                        </form>
                    </x-modal>
                @empty
                    <p class="text-sm text-muted">No grievances.</p>
                @endforelse
            </x-panel>

            <x-panel>
                <h2 class="mb-2 font-semibold text-ink">Disciplinary cases</h2>
                @forelse ($cases as $c)
                    <div class="flex flex-wrap items-start justify-between gap-2 border-t border-line py-2 text-sm first:border-0">
                        <div><span class="font-semibold text-ink">{{ $c->case_no }}</span> · {{ Format::fullName($c->employee) }} · incident {{ Format::date($c->incident_date) }}
                            @if ($c->hearing_at) · hearing {{ \Carbon\CarbonImmutable::parse($c->hearing_at)->format('j M Y H:i') }}@endif
                            <div class="text-xs text-muted">{{ $c->description }}</div>
                            @if ($c->outcome)<div class="text-xs">Outcome: {{ $c->outcome }}</div>@endif
                            @if ($c->warnings->isNotEmpty())<div class="text-xs">{{ $c->warnings->count() }} {{ str('warning')->plural($c->warnings->count()) }}</div>@endif
                        </div>
                        <span class="flex items-center gap-2"><x-badge :status="$c->status" /><button type="button" class="font-semibold text-accent-strong" @click="open = 'c:{{ $c->id }}'">Update</button></span>
                    </div>
                    <x-modal show="open === 'c:{{ $c->id }}'" close="open = null" :title="'Case '.$c->case_no">
                        <form method="post" action="{{ route('relations.cases.update', $c) }}" class="space-y-3">@csrf @method('patch')
                            <input type="hidden" name="_form" value="c:{{ $c->id }}">
                            <div class="grid gap-3 sm:grid-cols-2">
                                <x-field label="Status"><x-select name="status" :options="RelationsController::STATUSES" :labels="true" :selected="$c->status->value" /></x-field>
                                <x-field label="Hearing"><x-input type="datetime-local" name="hearing_at" :value="$c->hearing_at ? \Carbon\CarbonImmutable::parse($c->hearing_at)->format('Y-m-d\TH:i') : null" /></x-field>
                            </div>
                            <x-field label="Outcome"><textarea name="outcome" rows="2" class="{{ \App\Support\Ui::CONTROL }}">{{ $c->outcome }}</textarea></x-field>
                            <x-field label="Confidential notes"><textarea name="confidential_notes" rows="2" class="{{ \App\Support\Ui::CONTROL }}">{{ $c->confidential_notes }}</textarea></x-field>
                            @if ($c->employee->status->value !== 'SUSPENDED')
                                <label class="flex items-center gap-2 text-sm"><input type="checkbox" name="suspend" value="1"> Suspend {{ $c->employee->first_name }} pending the outcome</label>
                            @endif
                            @if ($form === "c:{$c->id}")<x-errors />@endif
                            <x-button type="submit">Save</x-button>
                        </form>
                    </x-modal>
                @empty
                    <p class="text-sm text-muted">No disciplinary cases.</p>
                @endforelse
            </x-panel>
        </div>

        <x-panel class="mt-4">
            <h2 class="mb-2 font-semibold text-ink">Warnings</h2>
            @forelse ($warnings as $w)
                <div class="border-t border-line py-2 text-sm first:border-0">
                    <span class="font-semibold text-ink">{{ Format::fullName($w->employee) }}</span> · {{ Format::title($w->type) }} warning · {{ Format::date($w->issued_on) }}@if ($w->case) · {{ $w->case->case_no }}@endif
                    <div class="text-xs text-muted">{{ $w->content }}</div>
                </div>
            @empty
                <p class="text-sm text-muted">No warnings issued.</p>
            @endforelse
        </x-panel>

        <x-modal show="open === 'grievance'" close="open = null" title="Record grievance">
            <form method="post" action="{{ route('relations.grievances.store') }}" class="space-y-3">@csrf
                <input type="hidden" name="_form" value="grievance">
                <x-field label="Raised by"><x-select name="employee_id" :options="$people" placeholder="Choose" required /></x-field>
                <x-field label="About (optional)"><x-select name="against_employee_id" :options="$people" placeholder="Nobody in particular" /></x-field>
                <x-field label="Category"><x-input name="category" placeholder="Workload, conduct, pay…" required /></x-field>
                <x-field label="What happened"><textarea name="description" rows="3" required class="{{ \App\Support\Ui::CONTROL }}">{{ $form === 'grievance' ? old('description') : '' }}</textarea></x-field>
                @if ($form === 'grievance')<x-errors />@endif
                <x-button type="submit">Record</x-button>
            </form>
        </x-modal>
        <x-modal show="open === 'case'" close="open = null" title="Open disciplinary case">
            <form method="post" action="{{ route('relations.cases.store') }}" class="space-y-3">@csrf
                <input type="hidden" name="_form" value="case">
                <x-field label="Employee"><x-select name="employee_id" :options="$people" placeholder="Choose" required /></x-field>
                <div class="grid gap-3 sm:grid-cols-2">
                    <x-field label="Incident date"><x-input type="date" name="incident_date" required /></x-field>
                    <x-field label="Hearing (optional)"><x-input type="datetime-local" name="hearing_at" /></x-field>
                </div>
                <x-field label="Description"><textarea name="description" rows="3" required class="{{ \App\Support\Ui::CONTROL }}">{{ $form === 'case' ? old('description') : '' }}</textarea></x-field>
                <x-field label="Confidential notes"><textarea name="confidential_notes" rows="2" class="{{ \App\Support\Ui::CONTROL }}"></textarea></x-field>
                @if ($form === 'case')<x-errors />@endif
                <x-button type="submit">Open case</x-button>
            </form>
        </x-modal>
        <x-modal show="open === 'warning'" close="open = null" title="Issue warning">
            <form method="post" action="{{ route('relations.warnings.store') }}" class="space-y-3">@csrf
                <input type="hidden" name="_form" value="warning">
                <x-field label="Employee"><x-select name="employee_id" :options="$people" placeholder="Choose" required /></x-field>
                <div class="grid gap-3 sm:grid-cols-2">
                    <x-field label="Type"><x-select name="type" :options="['VERBAL', 'WRITTEN', 'FINAL']" :labels="true" /></x-field>
                    <x-field label="Date"><x-input type="date" name="issued_on" :value="today()->toDateString()" required /></x-field>
                    <x-field label="Case (optional)" class="sm:col-span-2"><x-select name="case_id" :options="$cases->mapWithKeys(fn ($c) => [$c->id => $c->case_no.' · '.Format::fullName($c->employee)])->all()" placeholder="None" /></x-field>
                </div>
                <x-field label="Letter text"><textarea name="content" rows="4" required class="{{ \App\Support\Ui::CONTROL }}">{{ $form === 'warning' ? old('content') : '' }}</textarea></x-field>
                <p class="text-xs text-muted">A warning letter PDF is filed on the employee’s record as a confidential document, and they are notified.</p>
                @if ($form === 'warning')<x-errors />@endif
                <x-button type="submit">Issue warning</x-button>
            </form>
        </x-modal>
    </div>
</x-layouts.app>
