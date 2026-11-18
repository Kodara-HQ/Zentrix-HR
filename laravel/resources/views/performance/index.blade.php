@php
    use App\Support\Format;
    $form = old('_form');
    $openCycles = $cycles->filter(fn ($c) => $c->status->value !== 'CLOSED')->pluck('name', 'id')->all();
    $num = fn ($v) => $v === null ? '—' : rtrim(rtrim((string) $v, '0'), '.');
@endphp
<x-layouts.app :title="$own ? 'My reviews' : 'Performance'">
    <div x-data="{ open: {{ $form ? "'".$form."'" : 'null' }} }">
        <x-page-header :title="$own ? 'My reviews' : 'Performance'" :subtitle="$own ? 'Your objectives and reviews. Complete your self-assessment when a review opens.' : 'Objectives, self-assessment and the manager’s rating sit on the same review.'">
            @if ($hr)<x-button @click="open = 'cycle'">New cycle</x-button>@endif
            @if ($reviewer)
                <x-button tone="ghost" @click="open = 'objective'">Add objective</x-button>
                <x-button tone="ghost" @click="open = 'appraisal'">Open review</x-button>
            @endif
        </x-page-header>
        @if ($errors->any() && ! $form)<x-errors class="mb-3" />@endif

        @unless ($own)
            <x-panel>
                <h2 class="mb-2 font-semibold text-ink">Cycles</h2>
                @forelse ($cycles as $c)
                    <div class="flex flex-wrap items-center justify-between gap-2 border-t border-line py-2 text-sm first:border-0">
                        <span><span class="font-semibold text-ink">{{ $c->name }}</span> · {{ Format::title($c->period_type) }} · {{ Format::date($c->start_date) }} – {{ Format::date($c->end_date) }} · {{ $c->appraisals_count }} reviews, {{ $c->objectives_count }} objectives</span>
                        <span class="flex items-center gap-2">
                            <x-badge :status="$c->status" />
                            @if ($hr)
                                <form method="post" action="{{ route('performance.cycles.update', $c) }}">@csrf @method('patch')
                                    <x-select name="status" :options="['DRAFT', 'OPEN', 'CLOSED']" :labels="true" :selected="$c->status->value" class="!w-28 !py-1" onchange="this.form.submit()" />
                                </form>
                            @endif
                        </span>
                    </div>
                @empty
                    <p class="text-sm text-muted">No cycles yet.</p>
                @endforelse
            </x-panel>
        @endunless

        <x-panel class="mt-4">
            <h2 class="mb-2 font-semibold text-ink">Reviews</h2>
            @forelse ($appraisals as $a)
                @php($mine = $user->employee_id === $a->employee_id)
                <div class="border-t border-line py-3 text-sm first:border-0">
                    <div class="flex flex-wrap items-center justify-between gap-2">
                        <span><span class="font-semibold text-ink">{{ Format::fullName($a->employee) }}</span> · {{ $a->cycle?->name }} <x-badge :status="$a->status" class="ml-1" /></span>
                        <span class="flex flex-wrap gap-2">
                            @if ($mine && $a->status->value === 'DRAFT')<x-button small @click="open = 'self:{{ $a->id }}'">Self-assessment</x-button>@endif
                            @if ($mine && $a->status->value === 'REVIEWED')
                                <form method="post" action="{{ route('performance.appraisals.self', $a) }}">@csrf <input type="hidden" name="step" value="acknowledge"><x-button type="submit" small>Acknowledge</x-button></form>
                            @endif
                            @if ($reviewer && ! $mine && in_array($a->status->value, ['DRAFT', 'SELF_SUBMITTED'], true))<x-button small @click="open = 'review:{{ $a->id }}'">Rate</x-button>@endif
                            @if ($reviewer && ! $mine)<x-button small tone="ghost" @click="open = 'plan:{{ $a->id }}'">Improvement plan</x-button>@endif
                        </span>
                    </div>
                    <div class="mt-1 grid gap-1 text-muted sm:grid-cols-2">
                        <div>Self: <span class="text-ink">{{ $num($a->self_rating) }}/5</span>{{ $a->self_comments ? ' — '.$a->self_comments : '' }}</div>
                        <div>Manager: <span class="text-ink">{{ $num($a->supervisor_rating) }}/5</span>{{ $a->supervisor_comments ? ' — '.$a->supervisor_comments : '' }}</div>
                        @if ($a->final_rating !== null)<div>Final rating <span class="font-semibold text-ink">{{ $num($a->final_rating) }}/5</span> · {{ $a->recommendation }}</div>@endif
                        @foreach ($a->plans as $p)<div class="sm:col-span-2">Improvement plan: {{ $p->goals }}@if ($p->review_date) (review {{ Format::date($p->review_date) }})@endif</div>@endforeach
                    </div>
                </div>

                @if ($mine && $a->status->value === 'DRAFT')
                    <x-modal show="open === 'self:{{ $a->id }}'" close="open = null" title="Self-assessment">
                        <form method="post" action="{{ route('performance.appraisals.self', $a) }}" class="space-y-3">
                            @csrf <input type="hidden" name="_form" value="self:{{ $a->id }}">
                            <x-field label="How would you rate your performance? (1–5)"><x-select name="self_rating" :options="[1 => '1 · Below expectations', 2 => '2', 3 => '3 · Meets expectations', 4 => '4', 5 => '5 · Outstanding']" /></x-field>
                            <x-field label="What went well, and what would you do differently?"><textarea name="self_comments" rows="4" required class="{{ \App\Support\Ui::CONTROL }}">{{ old('self_comments') }}</textarea></x-field>
                            @if ($form === "self:{$a->id}")<x-errors />@endif
                            <x-button type="submit">Submit</x-button>
                        </form>
                    </x-modal>
                @endif
                @if ($reviewer && ! $mine)
                    <x-modal show="open === 'review:{{ $a->id }}'" close="open = null" :title="'Rate '.Format::fullName($a->employee)">
                        <form method="post" action="{{ route('performance.appraisals.review', $a) }}" class="space-y-3">
                            @csrf <input type="hidden" name="_form" value="review:{{ $a->id }}">
                            @if ($a->self_rating !== null)<p class="rounded-lg bg-sky px-3 py-2 text-sm">Self-rating {{ $num($a->self_rating) }}/5 — {{ $a->self_comments }}</p>@endif
                            <div class="grid gap-3 sm:grid-cols-2">
                                <x-field label="Your rating (1–5)"><x-select name="supervisor_rating" :options="[1 => 1, 2 => 2, 3 => 3, 4 => 4, 5 => 5]" :selected="3" /></x-field>
                                <x-field label="Final rating (1–5)"><x-select name="final_rating" :options="[1 => 1, 2 => 2, 3 => 3, 4 => 4, 5 => 5]" :selected="3" /></x-field>
                            </div>
                            <x-field label="Recommendation"><x-select name="recommendation" :options="array_combine($r = ['Retain', 'Promote', 'Improvement plan', 'Confirm appointment', 'Extend probation'], $r)" /></x-field>
                            <x-field label="Comments"><textarea name="supervisor_comments" rows="3" required class="{{ \App\Support\Ui::CONTROL }}"></textarea></x-field>
                            @if ($form === "review:{$a->id}")<x-errors />@endif
                            <x-button type="submit">Save rating</x-button>
                        </form>
                    </x-modal>
                    <x-modal show="open === 'plan:{{ $a->id }}'" close="open = null" title="Improvement plan">
                        <form method="post" action="{{ route('performance.plans.store', $a) }}" class="space-y-3">
                            @csrf <input type="hidden" name="_form" value="plan:{{ $a->id }}">
                            <x-field label="Goals"><textarea name="goals" rows="3" required class="{{ \App\Support\Ui::CONTROL }}"></textarea></x-field>
                            <x-field label="Support offered"><textarea name="support" rows="2" class="{{ \App\Support\Ui::CONTROL }}"></textarea></x-field>
                            <x-field label="Review date"><x-input type="date" name="review_date" /></x-field>
                            @if ($form === "plan:{$a->id}")<x-errors />@endif
                            <x-button type="submit">Add plan</x-button>
                        </form>
                    </x-modal>
                @endif
            @empty
                <p class="text-sm text-muted">No reviews yet.</p>
            @endforelse
        </x-panel>

        <x-panel class="mt-4">
            <h2 class="mb-2 font-semibold text-ink">Objectives and KPIs</h2>
            @forelse ($objectives as $o)
                <div class="flex flex-wrap items-center justify-between gap-2 border-t border-line py-2 text-sm first:border-0">
                    <span><span class="font-semibold text-ink">{{ $o->title }}</span> · {{ $o->employee ? Format::fullName($o->employee) : ($o->department?->name.' (department)') }} · KPI {{ $o->kpi }} · target {{ $o->target }} · weight {{ $num($o->weight) }}%
                        @if ($o->actual || $o->score !== null)<span class="text-muted"> · actual {{ $o->actual ?: '—' }}, score {{ $num($o->score) }}</span>@endif</span>
                    @if ($reviewer && $o->employee_id !== $user->employee_id)<button type="button" class="text-sm font-semibold text-accent-strong" @click="open = 'score:{{ $o->id }}'">Record result</button>@endif
                </div>
                @if ($reviewer && $o->employee_id !== $user->employee_id)
                    <x-modal show="open === 'score:{{ $o->id }}'" close="open = null" :title="$o->title">
                        <form method="post" action="{{ route('performance.objectives.score', $o) }}" class="space-y-3">
                            @csrf @method('patch') <input type="hidden" name="_form" value="score:{{ $o->id }}">
                            <x-field label="Actual result"><x-input name="actual" :value="$o->actual" /></x-field>
                            <x-field label="Score (0–100)"><x-input type="number" min="0" max="100" name="score" :value="$o->score" /></x-field>
                            @if ($form === "score:{$o->id}")<x-errors />@endif
                            <x-button type="submit">Save</x-button>
                        </form>
                    </x-modal>
                @endif
            @empty
                <p class="text-sm text-muted">No objectives yet.</p>
            @endforelse
        </x-panel>

        @if ($hr)
            <x-modal show="open === 'cycle'" close="open = null" title="New cycle">
                <form method="post" action="{{ route('performance.cycles.store') }}" class="space-y-3">
                    @csrf <input type="hidden" name="_form" value="cycle">
                    <x-field label="Name"><x-input name="name" placeholder="2027 annual review" required /></x-field>
                    <div class="grid gap-3 sm:grid-cols-2">
                        <x-field label="Period"><x-select name="period_type" :options="['QUARTERLY', 'HALF_YEARLY', 'ANNUAL']" :labels="true" :selected="'ANNUAL'" /></x-field>
                        <x-field label="Status"><x-select name="status" :options="['OPEN', 'DRAFT']" :labels="true" /></x-field>
                        <x-field label="Starts"><x-input type="date" name="start_date" required /></x-field>
                        <x-field label="Ends"><x-input type="date" name="end_date" required /></x-field>
                    </div>
                    @if ($form === 'cycle')<x-errors />@endif
                    <x-button type="submit">Create</x-button>
                </form>
            </x-modal>
        @endif
        @if ($reviewer)
            <x-modal show="open === 'objective'" close="open = null" title="Add objective" wide>
                <form method="post" action="{{ route('performance.objectives.store') }}" class="space-y-3" x-data="{ level: '{{ old('level', 'INDIVIDUAL') }}' }">
                    @csrf <input type="hidden" name="_form" value="objective">
                    <div class="grid gap-3 sm:grid-cols-2">
                        <x-field label="Cycle"><x-select name="cycle_id" :options="$openCycles" placeholder="Choose" required /></x-field>
                        <x-field label="For"><select name="level" x-model="level" class="{{ \App\Support\Ui::CONTROL }}"><option value="INDIVIDUAL">An employee</option><option value="DEPARTMENT">A department</option></select></x-field>
                        <x-field label="Employee" x-show="level === 'INDIVIDUAL'"><x-select name="employee_id" :options="$people" placeholder="Choose" /></x-field>
                        <x-field label="Department" x-show="level === 'DEPARTMENT'"><x-select name="department_id" :options="$departments" placeholder="Choose" /></x-field>
                        <x-field label="Title"><x-input name="title" required /></x-field>
                        <x-field label="KPI"><x-input name="kpi" placeholder="How it is measured" required /></x-field>
                        <x-field label="Target"><x-input name="target" required /></x-field>
                        <x-field label="Weight (%)"><x-input type="number" min="1" max="100" name="weight" value="20" required /></x-field>
                    </div>
                    @if ($form === 'objective')<x-errors />@endif
                    <x-button type="submit">Add</x-button>
                </form>
            </x-modal>
            <x-modal show="open === 'appraisal'" close="open = null" title="Open a review">
                <form method="post" action="{{ route('performance.appraisals.store') }}" class="space-y-3">
                    @csrf <input type="hidden" name="_form" value="appraisal">
                    <x-field label="Cycle"><x-select name="cycle_id" :options="$openCycles" placeholder="Choose" required /></x-field>
                    <x-field label="Employee"><x-select name="employee_id" :options="$people" placeholder="Choose" required /></x-field>
                    @if ($form === 'appraisal')<x-errors />@endif
                    <x-button type="submit">Open review</x-button>
                </form>
            </x-modal>
        @endif
    </div>
</x-layouts.app>
