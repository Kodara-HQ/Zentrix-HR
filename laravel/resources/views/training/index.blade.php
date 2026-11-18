@php
    use App\Support\Format;
    $form = old('_form');
@endphp
<x-layouts.app :title="$user->role->value === 'EMPLOYEE' ? 'My training' : 'Training'">
    <div x-data="{ open: {{ $form ? "'".$form."'" : 'null' }} }">
        <x-page-header title="Training and development" :subtitle="'Recorded programme cost '.Format::money($spend).'. Certificates with an expiry date raise a reminder 30 days before.'">
            @if ($hr)<x-button @click="open = 'program'">Add programme</x-button>@endif
            <x-button tone="ghost" @click="open = 'need'">Training need</x-button>
            <x-button tone="ghost" @click="open = 'certificate'">Certificate</x-button>
        </x-page-header>
        @if ($errors->any() && ! $form)<x-errors class="mb-3" />@endif

        <div class="space-y-3">
            @forelse ($programs as $p)
                <x-panel>
                    <div class="flex flex-wrap items-start justify-between gap-2">
                        <div>
                            <div class="font-semibold text-ink">{{ $p->title }} <x-badge :status="$p->status" class="ml-1" /></div>
                            <div class="text-sm text-muted">{{ Format::date($p->start_date) }} – {{ Format::date($p->end_date) }}{{ $p->location ? " · {$p->location}" : '' }}{{ $p->provider ? " · {$p->provider->name}" : '' }} · {{ Format::money($p->cost) }}{{ $p->capacity ? " · {$p->capacity} places" : '' }}</div>
                        </div>
                        <div class="flex flex-wrap items-center gap-2">
                            @if ($enroller && in_array($p->status->value, ['PLANNED', 'ONGOING'], true))<x-button small tone="ghost" @click="open = 'enrol:{{ $p->id }}'">Enrol</x-button>@endif
                            @if ($hr)
                                <form method="post" action="{{ route('training.programs.update', $p) }}">@csrf @method('patch')
                                    <x-select name="status" :options="['PLANNED', 'ONGOING', 'COMPLETED', 'CANCELLED']" :labels="true" :selected="$p->status->value" class="!w-32 !py-1" onchange="this.form.submit()" />
                                </form>
                            @endif
                        </div>
                    </div>
                    @if ($p->enrollments->isNotEmpty())
                        <ul class="mt-2 space-y-1 text-sm">
                            @foreach ($p->enrollments as $e)
                                <li class="flex flex-wrap items-center gap-2">
                                    <span class="text-ink">{{ Format::fullName($e->employee) }}</span> <x-badge :status="$e->status" />
                                    @if ($e->score !== null)<span class="text-muted">score {{ rtrim(rtrim((string) $e->score, '0'), '.') }}</span>@endif
                                    @if ($hr)
                                        <form method="post" action="{{ route('training.enrollments.update', $e) }}" class="flex items-center gap-1">@csrf @method('patch')
                                            <x-select name="status" :options="['ENROLLED', 'ATTENDED', 'ABSENT', 'COMPLETED']" :labels="true" :selected="$e->status->value" class="!w-28 !py-0.5 !text-xs" />
                                            <input name="score" type="number" min="0" max="100" value="{{ $e->score }}" placeholder="Score" class="{{ \App\Support\Ui::CONTROL }} !w-20 !py-0.5 !text-xs">
                                            <x-button type="submit" small tone="ghost">Save</x-button>
                                        </form>
                                    @endif
                                </li>
                            @endforeach
                        </ul>
                    @endif
                </x-panel>
                @if ($enroller)
                    <x-modal show="open === 'enrol:{{ $p->id }}'" close="open = null" :title="'Enrol on '.$p->title">
                        <form method="post" action="{{ route('training.enrol', $p) }}" class="space-y-3">
                            @csrf <input type="hidden" name="_form" value="enrol:{{ $p->id }}">
                            <x-field label="Employee"><x-select name="employee_id" :options="array_diff_key($people, $p->enrollments->pluck('employee_id')->flip()->all())" placeholder="Choose" required /></x-field>
                            @if ($form === "enrol:{$p->id}")<x-errors />@endif
                            <x-button type="submit">Enrol</x-button>
                        </form>
                    </x-modal>
                @endif
            @empty
                <x-panel><p class="text-sm text-muted">No programmes yet.</p></x-panel>
            @endforelse
        </div>

        <div class="mt-4 grid gap-4 lg:grid-cols-2">
            <x-panel>
                <h2 class="mb-2 font-semibold text-ink">Training needs</h2>
                @forelse ($needs as $n)
                    <div class="flex flex-wrap items-center justify-between gap-2 border-t border-line py-2 text-sm first:border-0">
                        <span><span class="font-semibold text-ink">{{ $n->skill }}</span> · {{ $names[$n->employee_id] ?? '—' }} · {{ $n->priority }} priority
                            @if ($n->justification)<span class="block text-xs text-muted">{{ $n->justification }}</span>@endif</span>
                        @if ($hr)
                            <form method="post" action="{{ route('training.needs.update', $n) }}">@csrf @method('patch')
                                <x-select name="status" :options="['OPEN', 'PLANNED', 'MET', 'DECLINED']" :labels="true" :selected="$n->status" class="!w-28 !py-1" onchange="this.form.submit()" />
                            </form>
                        @else
                            <x-badge :status="$n->status" />
                        @endif
                    </div>
                @empty
                    <p class="text-sm text-muted">No training needs recorded.</p>
                @endforelse
            </x-panel>
            <x-panel>
                <h2 class="mb-2 font-semibold text-ink">Certificates</h2>
                @forelse ($certs as $c)
                    @php($soon = $c->expires_on && $c->expires_on->lte(now()->addDays(30)))
                    <div class="flex flex-wrap items-center justify-between gap-2 border-t border-line py-2 text-sm first:border-0">
                        <span><span class="font-semibold text-ink">{{ $c->name }}</span> · {{ Format::fullName($c->employee) }} · {{ $c->issuer }}</span>
                        <span class="flex items-center gap-2 {{ $soon ? 'font-semibold text-[#a12d2d]' : 'text-muted' }}">{{ $c->expires_on ? 'Expires '.Format::date($c->expires_on) : 'No expiry' }}
                            @if ($hr)@include('employees._remove', ['route' => route('training.certificates.destroy', $c)])@endif</span>
                    </div>
                @empty
                    <p class="text-sm text-muted">No certificates recorded.</p>
                @endforelse
            </x-panel>
        </div>

        @if ($hr)
            <x-modal show="open === 'program'" close="open = null" title="Training programme" wide>
                <form method="post" action="{{ route('training.programs.store') }}" class="space-y-3">
                    @csrf <input type="hidden" name="_form" value="program">
                    <div class="grid gap-3 sm:grid-cols-2">
                        <x-field label="Title"><x-input name="title" required /></x-field>
                        <x-field label="Provider"><x-input name="provider_name" list="providers" /></x-field>
                        <x-field label="Starts"><x-input type="date" name="start_date" required /></x-field>
                        <x-field label="Ends"><x-input type="date" name="end_date" required /></x-field>
                        <x-field label="Cost (GHS)"><x-input type="number" step="0.01" min="0" name="cost" /></x-field>
                        <x-field label="Places"><x-input type="number" min="1" name="capacity" /></x-field>
                        <x-field label="Location" class="sm:col-span-2"><x-input name="location" /></x-field>
                    </div>
                    <datalist id="providers">@foreach ($providers as $name)<option value="{{ $name }}">@endforeach</datalist>
                    <x-field label="Description"><textarea name="description" rows="2" class="{{ \App\Support\Ui::CONTROL }}"></textarea></x-field>
                    @if ($form === 'program')<x-errors />@endif
                    <x-button type="submit">Add programme</x-button>
                </form>
            </x-modal>
        @endif
        <x-modal show="open === 'need'" close="open = null" title="Training need">
            <form method="post" action="{{ route('training.needs.store') }}" class="space-y-3">
                @csrf <input type="hidden" name="_form" value="need">
                @if ($user->role->value !== 'EMPLOYEE' && count($people) > 1)<x-field label="Employee"><x-select name="employee_id" :options="$people" :selected="$user->employee_id" placeholder="Choose" /></x-field>@endif
                <div class="grid gap-3 sm:grid-cols-2">
                    <x-field label="Skill"><x-input name="skill" required /></x-field>
                    <x-field label="Priority"><x-select name="priority" :options="['Low' => 'Low', 'Medium' => 'Medium', 'High' => 'High']" :selected="'Medium'" /></x-field>
                </div>
                <x-field label="Why is it needed?"><textarea name="justification" rows="2" class="{{ \App\Support\Ui::CONTROL }}"></textarea></x-field>
                @if ($form === 'need')<x-errors />@endif
                <x-button type="submit">Record</x-button>
            </form>
        </x-modal>
        <x-modal show="open === 'certificate'" close="open = null" title="Certificate">
            <form method="post" action="{{ route('training.certificates.store') }}" class="space-y-3">
                @csrf <input type="hidden" name="_form" value="certificate">
                @if ($hr)<x-field label="Employee"><x-select name="employee_id" :options="$people" :selected="$user->employee_id" placeholder="Choose" /></x-field>@endif
                <div class="grid gap-3 sm:grid-cols-2">
                    <x-field label="Certificate"><x-input name="name" required /></x-field>
                    <x-field label="Issued by"><x-input name="issuer" required /></x-field>
                    <x-field label="Credential number"><x-input name="credential_no" /></x-field>
                    <x-field label="Issued on"><x-input type="date" name="issued_on" /></x-field>
                    <x-field label="Expires on"><x-input type="date" name="expires_on" /></x-field>
                </div>
                @if ($form === 'certificate')<x-errors />@endif
                <x-button type="submit">Add</x-button>
            </form>
        </x-modal>
    </div>
</x-layouts.app>
