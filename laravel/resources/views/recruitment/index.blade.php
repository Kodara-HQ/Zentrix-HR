@php
    use App\Support\Format;
    use App\Support\Lookups;
    use App\Http\Controllers\RecruitmentController;
    $form = old('_form');
    $approvedReqs = $requisitions->filter(fn ($r) => $r->status->value === 'APPROVED')
        ->mapWithKeys(fn ($r) => [$r->id => ($names['jobTitles'][$r->job_title_id] ?? 'Role').' × '.$r->headcount.' · '.str($r->justification)->limit(40)])->all();
@endphp
<x-layouts.app title="Recruitment">
    <div x-data="{ open: {{ $form ? "'".$form."'" : 'null' }} }">
        <x-page-header title="Recruitment" subtitle="From the manpower request to the employee record, with cost and time to hire.">
            <x-button @click="open = 'requisition'">New requisition</x-button>
            @if ($recruiter)
                <x-button tone="ghost" @click="open = 'vacancy'">New vacancy</x-button>
                <x-button tone="ghost" @click="open = 'applicant'">Register applicant</x-button>
            @endif
        </x-page-header>

        <div class="mb-4 flex gap-1 overflow-x-auto overflow-y-hidden border-b border-line">
            @foreach ($tabs as $key => $label)
                <a href="{{ route('recruitment.index', ['tab' => $key]) }}" class="-mb-px whitespace-nowrap border-b-2 px-3 py-2 text-sm font-semibold {{ $tab === $key ? 'border-accent-strong text-ink' : 'border-transparent text-muted hover:text-ink' }}">{{ $label }}</a>
            @endforeach
        </div>
        @if ($errors->any() && ! $form)<x-errors class="mb-3" />@endif

        @if ($tab === 'requisitions')
            <div class="overflow-auto rounded-2xl border border-line bg-white">
                <table class="w-full min-w-[760px] text-left text-sm">
                    <thead class="text-xs uppercase tracking-wide text-muted"><tr>@foreach (['Requested', 'Role', 'Department', 'Headcount', 'Justification', 'Status', ''] as $h)<th class="px-3 py-2">{{ $h }}</th>@endforeach</tr></thead>
                    <tbody>
                        @forelse ($requisitions as $row)
                            <tr class="border-t border-line align-top">
                                <td class="whitespace-nowrap px-3 py-2.5">{{ Format::date($row->created_at) }}</td>
                                <td class="px-3 py-2.5 font-semibold text-ink">{{ $names['jobTitles'][$row->job_title_id] ?? '—' }}</td>
                                <td class="px-3 py-2.5">{{ $names['departments'][$row->department_id] ?? '—' }}</td>
                                <td class="px-3 py-2.5">{{ $row->headcount }}</td>
                                <td class="max-w-sm px-3 py-2.5">{{ $row->justification }}@if ($row->decision_note)<div class="mt-1 text-xs text-muted">{{ $row->decision_note }}</div>@endif</td>
                                <td class="px-3 py-2.5"><x-badge :status="$row->status" /></td>
                                <td class="px-3 py-2.5">
                                    @if ($approver && $row->status->value === 'PENDING')
                                        @include('attendance._decide', ['route' => route('recruitment.requisitions.decide', $row)])
                                    @endif
                                </td>
                            </tr>
                        @empty
                            <tr><td colspan="7" class="px-3 py-6 text-center text-muted">No requisitions yet.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        @endif

        @if ($tab === 'vacancies')
            <div class="overflow-auto rounded-2xl border border-line bg-white">
                <table class="w-full min-w-[820px] text-left text-sm">
                    <thead class="text-xs uppercase tracking-wide text-muted"><tr>@foreach (['Vacancy', 'Department', 'Location', 'Filled', 'Applicants', 'Cost', 'Status', ''] as $h)<th class="px-3 py-2">{{ $h }}</th>@endforeach</tr></thead>
                    <tbody>
                        @forelse ($vacancies as $v)
                            <tr class="border-t border-line">
                                <td class="px-3 py-2.5 font-semibold text-ink">{{ $v->title }}</td>
                                <td class="px-3 py-2.5">{{ $v->department?->name }}</td>
                                <td class="px-3 py-2.5">{{ $v->location?->city }}</td>
                                <td class="px-3 py-2.5">{{ $v->filled }} / {{ $v->openings }}</td>
                                <td class="px-3 py-2.5"><a class="text-accent-strong" href="{{ route('recruitment.index', ['tab' => 'applicants', 'vacancy_id' => $v->id]) }}">{{ $v->applicants_count }}</a></td>
                                <td class="px-3 py-2.5">{{ Format::money((float) $v->advertising_cost + (float) $v->agency_cost) }}</td>
                                <td class="px-3 py-2.5"><x-badge :status="$v->status" /></td>
                                <td class="px-3 py-2.5 text-right">@if ($recruiter)<button type="button" class="text-sm font-semibold text-accent-strong" @click="open = 'vacancy:{{ $v->id }}'">Update</button>@endif</td>
                            </tr>
                        @empty
                            <tr><td colspan="8" class="px-3 py-6 text-center text-muted">No vacancies yet.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
            @if ($recruiter)
                @foreach ($vacancies as $v)
                    <x-modal show="open === 'vacancy:{{ $v->id }}'" close="open = null" :title="$v->title">
                        <form method="post" action="{{ route('recruitment.vacancies.update', $v) }}" class="space-y-3">
                            @csrf @method('patch')
                            <input type="hidden" name="_form" value="vacancy:{{ $v->id }}">
                            <div class="grid gap-3 sm:grid-cols-2">
                                <x-field label="Status"><x-select name="status" :options="['DRAFT', 'OPEN', 'ON_HOLD', 'CLOSED', 'FILLED']" :labels="true" :selected="$v->status->value" /></x-field>
                                <x-field label="Openings"><x-input type="number" min="1" name="openings" :value="$v->openings" required /></x-field>
                                <x-field label="Advertising cost"><x-input type="number" step="0.01" min="0" name="advertising_cost" :value="$v->advertising_cost" /></x-field>
                                <x-field label="Agency cost"><x-input type="number" step="0.01" min="0" name="agency_cost" :value="$v->agency_cost" /></x-field>
                            </div>
                            @if ($form === "vacancy:{$v->id}")<x-errors />@endif
                            <x-button type="submit">Save</x-button>
                        </form>
                    </x-modal>
                @endforeach
            @endif
        @endif

        @if ($tab === 'applicants')
            <form method="get" class="mb-3 flex flex-wrap gap-2">
                <input type="hidden" name="tab" value="applicants">
                <x-select name="vacancy_id" :options="$vacancies->pluck('title', 'id')->all()" :selected="request('vacancy_id')" placeholder="All vacancies" class="!w-60" onchange="this.form.submit()" />
                <x-select name="status" :options="RecruitmentController::APPLICANT_STATUSES" :labels="true" :selected="request('status')" placeholder="Any status" class="!w-44" onchange="this.form.submit()" />
            </form>
            <div class="space-y-3">
                @forelse ($applicants as $a)
                    @php($sent = $a->offers->first(fn ($o) => $o->status->value === 'SENT'))
                    <x-panel>
                        <div class="flex flex-wrap items-start justify-between gap-3">
                            <div>
                                <div class="font-semibold text-ink">{{ Format::fullName($a) }} <x-badge :status="$a->status" class="ml-1" /></div>
                                <div class="text-sm text-muted">{{ $a->vacancy?->title }} · {{ $a->email }} · {{ $a->phone }}@if ($a->source) · via {{ $a->source }}@endif</div>
                                @if ($a->screening_score !== null)<div class="text-sm">Screening score {{ rtrim(rtrim((string) $a->screening_score, '0'), '.') }}</div>@endif
                                @foreach ($a->interviews as $i)
                                    <div class="mt-1 text-sm">Interview {{ \Carbon\CarbonImmutable::parse($i->scheduled_at)->format('j M Y H:i') }} · {{ $i->mode }}{{ $i->location ? " · {$i->location}" : '' }}
                                        @if ($i->score !== null) — <span class="font-semibold">{{ rtrim(rtrim((string) $i->score, '0'), '.') }}/100, {{ $i->recommendation }}</span>@elseif ($recruiter) — <button type="button" class="font-semibold text-accent-strong" @click="open = 'score:{{ $i->id }}'">record result</button>@endif
                                    </div>
                                @endforeach
                                @foreach ($a->offers as $o)<div class="mt-1 text-sm">Offer {{ Format::money($o->salary) }} from {{ Format::date($o->start_date) }} · <x-badge :status="$o->status" /></div>@endforeach
                                @if ($a->employee_id)<a href="{{ route('employees.show', $a->employee_id) }}" class="mt-1 inline-block text-sm font-semibold text-accent-strong">Open employee record</a>@endif
                            </div>
                            @if ($recruiter && $a->status->value !== 'HIRED')
                                <div class="flex flex-wrap gap-2">
                                    <x-button small tone="ghost" @click="open = 'status:{{ $a->id }}'">Update</x-button>
                                    <x-button small tone="ghost" @click="open = 'interview:{{ $a->id }}'">Interview</x-button>
                                    <x-button small tone="ghost" @click="open = 'offer:{{ $a->id }}'">Offer</x-button>
                                    @if ($sent)<x-button small @click="open = 'hire:{{ $sent->id }}'">Convert to employee</x-button>@endif
                                </div>
                            @endif
                        </div>
                    </x-panel>

                    @if ($recruiter && $a->status->value !== 'HIRED')
                        <x-modal show="open === 'status:{{ $a->id }}'" close="open = null" :title="'Update '.Format::fullName($a)">
                            <form method="post" action="{{ route('recruitment.applicants.update', $a) }}" class="space-y-3">
                                @csrf @method('patch')
                                <input type="hidden" name="_form" value="status:{{ $a->id }}">
                                <x-field label="Status"><x-select name="status" :options="array_diff(RecruitmentController::APPLICANT_STATUSES, ['HIRED'])" :labels="true" :selected="$a->status->value" /></x-field>
                                <x-field label="Screening score (0–100)"><x-input type="number" min="0" max="100" step="0.5" name="screening_score" :value="$a->screening_score" /></x-field>
                                <x-field label="Notes"><textarea name="notes" rows="2" class="{{ \App\Support\Ui::CONTROL }}">{{ $a->notes }}</textarea></x-field>
                                @if ($form === "status:{$a->id}")<x-errors />@endif
                                <x-button type="submit">Save</x-button>
                            </form>
                        </x-modal>
                        <x-modal show="open === 'interview:{{ $a->id }}'" close="open = null" :title="'Interview '.Format::fullName($a)">
                            <form method="post" action="{{ route('recruitment.interviews.store', $a) }}" class="space-y-3">
                                @csrf
                                <input type="hidden" name="_form" value="interview:{{ $a->id }}">
                                <div class="grid gap-3 sm:grid-cols-2">
                                    <x-field label="When"><x-input type="datetime-local" name="scheduled_at" required /></x-field>
                                    <x-field label="How"><x-select name="mode" :options="['In person' => 'In person', 'Phone' => 'Phone', 'Video' => 'Video']" /></x-field>
                                    <x-field label="Interviewer"><x-select name="interviewer_id" :options="$interviewers" placeholder="—" /></x-field>
                                    <x-field label="Where"><x-input name="location" /></x-field>
                                </div>
                                @if ($form === "interview:{$a->id}")<x-errors />@endif
                                <x-button type="submit">Schedule</x-button>
                            </form>
                        </x-modal>
                        <x-modal show="open === 'offer:{{ $a->id }}'" close="open = null" :title="'Offer to '.Format::fullName($a)">
                            <form method="post" action="{{ route('recruitment.offers.store', $a) }}" class="space-y-3">
                                @csrf
                                <input type="hidden" name="_form" value="offer:{{ $a->id }}">
                                <div class="grid gap-3 sm:grid-cols-2">
                                    <x-field label="Monthly basic salary (GHS)"><x-input type="number" step="0.01" min="1" name="salary" :value="$a->vacancy?->salary_min" required /></x-field>
                                    <x-field label="Start date"><x-input type="date" name="start_date" required /></x-field>
                                    <x-field label="Offer expires"><x-input type="date" name="expires_on" /></x-field>
                                </div>
                                <x-field label="Notes"><textarea name="notes" rows="2" class="{{ \App\Support\Ui::CONTROL }}"></textarea></x-field>
                                @if ($form === "offer:{$a->id}")<x-errors />@endif
                                <x-button type="submit">Record offer</x-button>
                            </form>
                        </x-modal>
                        @if ($sent)
                            <x-modal show="open === 'hire:{{ $sent->id }}'" close="open = null" :title="'Hire '.Format::fullName($a)">
                                <form method="post" action="{{ route('recruitment.offers.accept', $sent) }}" class="space-y-3">
                                    @csrf
                                    <input type="hidden" name="_form" value="hire:{{ $sent->id }}">
                                    <p class="text-sm text-muted">Creates the employee record with a 6-month probation, their leave balances, contract, appointment letter and a sign-in. Two details the application didn’t include:</p>
                                    <div class="grid gap-3 sm:grid-cols-2">
                                        <x-field label="Gender"><x-select name="gender" :options="Lookups::GENDERS" :labels="true" placeholder="Choose" required /></x-field>
                                        <x-field label="Date of birth"><x-input type="date" name="date_of_birth" required /></x-field>
                                    </div>
                                    @if ($form === "hire:{$sent->id}")<x-errors />@endif
                                    <x-button type="submit">Hire</x-button>
                                </form>
                            </x-modal>
                        @endif
                    @endif
                    @foreach ($a->interviews->whereNull('score') as $i)
                        <x-modal show="open === 'score:{{ $i->id }}'" close="open = null" title="Interview result">
                            <form method="post" action="{{ route('recruitment.interviews.score', $i) }}" class="space-y-3">
                                @csrf @method('patch')
                                <input type="hidden" name="_form" value="score:{{ $i->id }}">
                                <div class="grid gap-3 sm:grid-cols-2">
                                    <x-field label="Score (0–100)"><x-input type="number" min="0" max="100" step="0.5" name="score" required /></x-field>
                                    <x-field label="Recommendation"><x-select name="recommendation" :options="['Hire' => 'Hire', 'Consider' => 'Consider', 'Do not hire' => 'Do not hire']" /></x-field>
                                </div>
                                <x-field label="Comments"><textarea name="comments" rows="2" class="{{ \App\Support\Ui::CONTROL }}"></textarea></x-field>
                                @if ($form === "score:{$i->id}")<x-errors />@endif
                                <x-button type="submit">Save result</x-button>
                            </form>
                        </x-modal>
                    @endforeach
                @empty
                    <p class="text-sm text-muted">No applicants{{ request()->hasAny(['vacancy_id', 'status']) ? ' match' : ' yet' }}.</p>
                @endforelse
            </div>
        @endif

        @if ($tab === 'metrics')
            <div class="overflow-auto rounded-2xl border border-line bg-white">
                <table class="w-full min-w-[600px] text-left text-sm">
                    <thead class="text-xs uppercase tracking-wide text-muted"><tr>@foreach (['Vacancy', 'Applicants', 'Cost', 'Cost per hire', 'Days to hire', 'Status'] as $h)<th class="px-3 py-2">{{ $h }}</th>@endforeach</tr></thead>
                    <tbody>
                        @forelse ($metrics as $m)
                            <tr class="border-t border-line">
                                <td class="px-3 py-2.5 font-semibold text-ink">{{ $m['vacancy']->title }}</td>
                                <td class="px-3 py-2.5">{{ $m['vacancy']->applicants_count }}</td>
                                <td class="px-3 py-2.5">{{ Format::money($m['cost']) }}</td>
                                <td class="px-3 py-2.5">{{ $m['vacancy']->filled ? Format::money($m['cost'] / $m['vacancy']->filled) : '—' }}</td>
                                <td class="px-3 py-2.5">{{ $m['days'] ?? '—' }}</td>
                                <td class="px-3 py-2.5"><x-badge :status="$m['vacancy']->status" /></td>
                            </tr>
                        @empty
                            <tr><td colspan="6" class="px-3 py-6 text-center text-muted">No vacancies yet.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        @endif

        {{-- Create dialogs --}}
        <x-modal show="open === 'requisition'" close="open = null" title="Manpower requisition">
            <form method="post" action="{{ route('recruitment.requisitions.store') }}" class="space-y-3">
                @csrf
                <input type="hidden" name="_form" value="requisition">
                <div class="grid gap-3 sm:grid-cols-2">
                    <x-field label="Department"><x-select name="department_id" :options="$names['departments']" placeholder="Choose" required /></x-field>
                    <x-field label="Job title"><x-select name="job_title_id" :options="$names['jobTitles']" placeholder="Choose" required /></x-field>
                    <x-field label="Location"><x-select name="location_id" :options="$names['locations']" placeholder="Any" /></x-field>
                    <x-field label="Headcount"><x-input type="number" min="1" name="headcount" value="1" required /></x-field>
                </div>
                <x-field label="Why is this role needed?"><textarea name="justification" rows="3" required class="{{ \App\Support\Ui::CONTROL }}">{{ $form === 'requisition' ? old('justification') : '' }}</textarea></x-field>
                @if ($form === 'requisition')<x-errors />@endif
                <x-button type="submit">Send for approval</x-button>
            </form>
        </x-modal>
        @if ($recruiter)
            <x-modal show="open === 'vacancy'" close="open = null" title="New vacancy" wide>
                <form method="post" action="{{ route('recruitment.vacancies.store') }}" class="space-y-3">
                    @csrf
                    <input type="hidden" name="_form" value="vacancy">
                    <div class="grid gap-3 sm:grid-cols-2">
                        <x-field label="From approved requisition"><x-select name="requisition_id" :options="$approvedReqs" placeholder="None" /></x-field>
                        <x-field label="Title"><x-input name="title" required /></x-field>
                        <x-field label="Department"><x-select name="department_id" :options="$names['departments']" placeholder="Choose" required /></x-field>
                        <x-field label="Job title"><x-select name="job_title_id" :options="$names['jobTitles']" placeholder="Choose" required /></x-field>
                        <x-field label="Location"><x-select name="location_id" :options="$names['locations']" placeholder="Choose" required /></x-field>
                        <x-field label="Openings"><x-input type="number" min="1" name="openings" value="1" required /></x-field>
                        <x-field label="Salary from (GHS)"><x-input type="number" step="0.01" min="0" name="salary_min" /></x-field>
                        <x-field label="Salary to (GHS)"><x-input type="number" step="0.01" min="0" name="salary_max" /></x-field>
                        <x-field label="Advertising cost"><x-input type="number" step="0.01" min="0" name="advertising_cost" /></x-field>
                        <x-field label="Agency cost"><x-input type="number" step="0.01" min="0" name="agency_cost" /></x-field>
                        <x-field label="Status"><x-select name="status" :options="['OPEN' => 'Open now', 'DRAFT' => 'Draft']" /></x-field>
                    </div>
                    <x-field label="Job description"><textarea name="description" rows="3" required class="{{ \App\Support\Ui::CONTROL }}">{{ $form === 'vacancy' ? old('description') : '' }}</textarea></x-field>
                    <x-field label="Requirements"><textarea name="requirements" rows="3" required class="{{ \App\Support\Ui::CONTROL }}">{{ $form === 'vacancy' ? old('requirements') : '' }}</textarea></x-field>
                    @if ($form === 'vacancy')<x-errors />@endif
                    <x-button type="submit">Save vacancy</x-button>
                </form>
            </x-modal>
            <x-modal show="open === 'applicant'" close="open = null" title="Register applicant">
                <form method="post" action="{{ route('recruitment.applicants.store') }}" class="space-y-3">
                    @csrf
                    <input type="hidden" name="_form" value="applicant">
                    <x-field label="Vacancy"><x-select name="vacancy_id" :options="$vacancies->filter(fn ($v) => in_array($v->status->value, ['OPEN', 'ON_HOLD'], true))->pluck('title', 'id')->all()" placeholder="Choose" required /></x-field>
                    <div class="grid gap-3 sm:grid-cols-2">
                        <x-field label="First name"><x-input name="first_name" required /></x-field>
                        <x-field label="Last name"><x-input name="last_name" required /></x-field>
                        <x-field label="Email"><x-input type="email" name="email" required /></x-field>
                        <x-field label="Phone"><x-input name="phone" required /></x-field>
                        <x-field label="Source"><x-input name="source" placeholder="Referral, job board…" /></x-field>
                        <x-field label="Screening score (0–100)"><x-input type="number" min="0" max="100" step="0.5" name="screening_score" /></x-field>
                    </div>
                    @if ($form === 'applicant')<x-errors />@endif
                    <x-button type="submit">Register</x-button>
                </form>
            </x-modal>
        @endif
    </div>
</x-layouts.app>
