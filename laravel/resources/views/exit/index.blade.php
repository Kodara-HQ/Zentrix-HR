@php
    use App\Support\Format;
    $form = old('_form');
@endphp
<x-layouts.app title="Exit management">
    <div x-data="{ open: {{ $form ? "'".$form."'" : 'null' }} }">
        <x-page-header title="Exit management" subtitle="A resignation keeps the person on notice until their last working day, then employment ends and their sign-in is switched off.">
            @if ($hr || $user->employee_id)<x-button @click="open = 'exit'">{{ $hr ? 'Record exit' : 'Resign' }}</x-button>@endif
        </x-page-header>
        @if ($errors->any() && ! $form)<x-errors class="mb-3" />@endif

        <div class="space-y-3">
            @forelse ($exits as $s)
                @php($done = $s->clearance->where('returned', true)->count())
                <x-panel>
                    <div class="flex flex-wrap items-start justify-between gap-2">
                        <div>
                            <div class="font-semibold text-ink">{{ Format::fullName($s->employee) }} · {{ Format::title($s->type) }} <x-badge :status="$s->status" class="ml-1" /></div>
                            <div class="text-sm text-muted">Notice {{ Format::date($s->notice_date) }} · last working day {{ Format::date($s->last_working_day) }} · clearance {{ $done }}/{{ $s->clearance->count() }}</div>
                            <div class="mt-1 text-sm">{{ $s->reason }}</div>
                            @if ($s->decision_note)<div class="text-xs text-muted">HR: {{ $s->decision_note }}</div>@endif
                            @if ($hr && $s->final_pay_notes)<div class="text-xs text-muted">Final pay: {{ $s->final_pay_notes }}</div>@endif
                        </div>
                        @if ($hr && $s->status->value === 'PENDING')<x-button small @click="open = 'd:{{ $s->id }}'">Decide</x-button>@endif
                    </div>
                    @if ($hr && in_array($s->status->value, ['APPROVED', 'COMPLETED'], true))
                        <ul class="mt-3 grid gap-1 text-sm sm:grid-cols-2">
                            @foreach ($s->clearance as $item)
                                <li>
                                    <form method="post" action="{{ route('exit.clearance', $item) }}" class="flex items-center gap-2">@csrf @method('patch')
                                        <input type="hidden" name="returned" value="0">
                                        <input type="checkbox" name="returned" value="1" @checked($item->returned) onchange="this.form.submit()" aria-label="{{ $item->item }} returned">
                                        <span class="{{ $item->returned ? 'text-muted line-through' : '' }}">{{ $item->item }}</span>
                                    </form>
                                </li>
                            @endforeach
                        </ul>
                    @endif
                </x-panel>
                @if ($hr && $s->status->value === 'PENDING')
                    <x-modal show="open === 'd:{{ $s->id }}'" close="open = null" :title="Format::title($s->type).' — '.Format::fullName($s->employee)">
                        <form method="post" action="{{ route('exit.decide', $s) }}" class="space-y-3">@csrf
                            <input type="hidden" name="_form" value="d:{{ $s->id }}">
                            <x-field label="Decision"><x-select name="decision" :options="['APPROVED' => 'Approve', 'REJECTED' => 'Reject']" /></x-field>
                            <x-field label="Note"><textarea name="note" rows="2" class="{{ \App\Support\Ui::CONTROL }}"></textarea></x-field>
                            <x-field label="Final pay notes"><textarea name="final_pay_notes" rows="2" class="{{ \App\Support\Ui::CONTROL }}" placeholder="Leave days to pay out, loan balance, gratuity…"></textarea></x-field>
                            @if ($s->type->value === 'TERMINATION')<p class="text-xs text-muted">Approving issues a termination letter to the employee’s file.</p>@endif
                            @if ($form === "d:{$s->id}")<x-errors />@endif
                            <x-button type="submit">Save decision</x-button>
                        </form>
                    </x-modal>
                @endif
            @empty
                <x-panel><p class="text-sm text-muted">No exits recorded.</p></x-panel>
            @endforelse
        </div>

        <x-modal show="open === 'exit'" close="open = null" :title="$hr ? 'Record exit' : 'Resignation'">
            <form method="post" action="{{ route('exit.store') }}" class="space-y-3">@csrf
                <input type="hidden" name="_form" value="exit">
                @if ($hr)
                    <div class="grid gap-3 sm:grid-cols-2">
                        <x-field label="Employee"><x-select name="employee_id" :options="$people" placeholder="Choose" required /></x-field>
                        <x-field label="Type"><x-select name="type" :options="['RESIGNATION', 'RETIREMENT', 'TERMINATION', 'END_OF_CONTRACT']" :labels="true" /></x-field>
                    </div>
                @else
                    <input type="hidden" name="type" value="RESIGNATION">
                @endif
                <div class="grid gap-3 sm:grid-cols-2">
                    <x-field label="Notice date"><x-input type="date" name="notice_date" :value="today()->toDateString()" required /></x-field>
                    <x-field label="Last working day"><x-input type="date" name="last_working_day" required /></x-field>
                </div>
                <x-field label="Reason"><textarea name="reason" rows="3" required class="{{ \App\Support\Ui::CONTROL }}">{{ $form === 'exit' ? old('reason') : '' }}</textarea></x-field>
                @if ($hr)<x-field label="Exit interview notes"><textarea name="interview_notes" rows="2" class="{{ \App\Support\Ui::CONTROL }}"></textarea></x-field>@endif
                @if ($form === 'exit')<x-errors />@endif
                <x-button type="submit">{{ $hr ? 'Record' : 'Send resignation' }}</x-button>
            </form>
        </x-modal>
    </div>
</x-layouts.app>
