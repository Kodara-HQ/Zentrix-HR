@php
    use App\Support\Format;
    $form = old('_form');
    $staff = $user->employee_id !== null;
@endphp
<x-layouts.app title="Welfare">
    <div x-data="{ open: {{ $form ? "'".$form."'" : 'null' }} }">
        <x-page-header title="Welfare and engagement" subtitle="Ask HR for support, answer pulse surveys, share suggestions, and see who has been recognised.">
            @if ($staff || $hr)<x-button @click="open = 'request'">Ask for support</x-button>@endif
            <x-button tone="ghost" @click="open = 'suggestion'">Suggestion</x-button>
            <x-button tone="ghost" @click="open = 'feedback'">Anonymous feedback</x-button>
        </x-page-header>

        <div class="mb-4 flex gap-1 overflow-x-auto overflow-y-hidden border-b border-line">
            @foreach ($tabs as $key => $label)
                <a href="{{ route('welfare.index', ['tab' => $key]) }}" class="-mb-px whitespace-nowrap border-b-2 px-3 py-2 text-sm font-semibold {{ $tab === $key ? 'border-accent-strong text-ink' : 'border-transparent text-muted hover:text-ink' }}">{{ $label }}</a>
            @endforeach
        </div>
        @if ($errors->any() && ! $form)<x-errors class="mb-3" />@endif

        @if ($tab === 'requests')
            <x-panel>
                @forelse ($requests as $r)
                    <div class="flex flex-wrap items-start justify-between gap-2 border-t border-line py-2 text-sm first:border-0">
                        <div><span class="font-semibold text-ink">{{ Format::fullName($r->employee) }}</span> · {{ $r->type }}@if ($r->amount) · {{ Format::money($r->amount) }}@endif · {{ Format::date($r->created_at) }}
                            <div class="text-xs text-muted">{{ $r->description }}</div>
                            @if ($r->resolution)<div class="text-xs">HR: {{ $r->resolution }}</div>@endif
                        </div>
                        <span class="flex items-center gap-2"><x-badge :status="$r->status" />@if ($hr)<button type="button" class="font-semibold text-accent-strong" @click="open = 'r:{{ $r->id }}'">Decide</button>@endif</span>
                    </div>
                    @if ($hr)
                        <x-modal show="open === 'r:{{ $r->id }}'" close="open = null" title="Welfare request">
                            <form method="post" action="{{ route('welfare.requests.decide', $r) }}" class="space-y-3">@csrf @method('patch')
                                <x-field label="Status"><x-select name="status" :options="['PENDING', 'APPROVED', 'REJECTED', 'FULFILLED']" :labels="true" :selected="$r->status->value" /></x-field>
                                <x-field label="Note to the employee"><textarea name="resolution" rows="2" class="{{ \App\Support\Ui::CONTROL }}">{{ $r->resolution }}</textarea></x-field>
                                <x-button type="submit">Save</x-button>
                            </form>
                        </x-modal>
                    @endif
                @empty
                    <p class="text-sm text-muted">No welfare requests.</p>
                @endforelse
            </x-panel>
        @endif

        @if ($tab === 'surveys')
            @if ($hr)<div class="mb-3 flex justify-end"><x-button @click="open = 'survey'">New survey</x-button></div>@endif
            <div class="space-y-3">
                @forelse ($surveys as $s)
                    @php($live = $s->is_open && (! $s->closes_on || $s->closes_on->gte(today())))
                    <x-panel>
                        <div class="flex flex-wrap items-start justify-between gap-2">
                            <div>
                                <div class="font-semibold text-ink">{{ $s->title }} <x-badge :tone="$live ? 'green' : 'slate'">{{ $live ? 'Open' : 'Closed' }}</x-badge></div>
                                <div class="text-sm text-muted">{{ $s->description }} {{ $s->responses_count }} {{ str('response')->plural($s->responses_count) }}@if ($s->closes_on) · closes {{ Format::date($s->closes_on) }}@endif</div>
                            </div>
                            <div class="flex gap-2">
                                @if ($live && $staff)<x-button small @click="open = 's:{{ $s->id }}'">{{ in_array($s->id, $answered, true) ? 'Change my answers' : 'Respond' }}</x-button>@endif
                                @if ($hr && $live)<form method="post" action="{{ route('welfare.surveys.close', $s) }}">@csrf <x-button type="submit" small tone="ghost">Close</x-button></form>@endif
                            </div>
                        </div>
                        @if ($hr && $s->responses_count)
                            @php($responses = $s->responses()->get())
                            <ul class="mt-3 space-y-1.5 text-sm">
                                @foreach ($s->questions as $q)
                                    @php($avg = round($responses->avg(fn ($r) => $r->answers[$q['id']] ?? null), 1))
                                    <li class="grid grid-cols-[1fr_8rem_2.5rem] items-center gap-3">
                                        <span>{{ $q['prompt'] }}</span>
                                        <span class="relative h-3 rounded-r bg-sky/60"><span class="absolute inset-y-0 left-0 rounded-r bg-navy" style="width: {{ $avg / 5 * 100 }}%"></span></span>
                                        <span class="text-right font-semibold tabular-nums text-ink">{{ $avg }}</span>
                                    </li>
                                @endforeach
                            </ul>
                            @foreach ($responses->pluck('answers.comment')->filter() as $comment)<p class="mt-2 border-l-2 border-line pl-2 text-xs text-muted">“{{ $comment }}”</p>@endforeach
                        @endif
                    </x-panel>
                    @if ($live && $staff)
                        <x-modal show="open === 's:{{ $s->id }}'" close="open = null" :title="$s->title" wide>
                            <form method="post" action="{{ route('welfare.surveys.respond', $s) }}" class="space-y-3">@csrf
                                <input type="hidden" name="_form" value="s:{{ $s->id }}">
                                <p class="text-sm text-muted">1 = strongly disagree, 5 = strongly agree.</p>
                                @foreach ($s->questions as $q)
                                    <fieldset class="flex flex-wrap items-center justify-between gap-2 border-t border-line pt-2 text-sm">
                                        <legend class="sr-only">{{ $q['prompt'] }}</legend>
                                        <span>{{ $q['prompt'] }}</span>
                                        <span class="flex gap-3">@foreach (range(1, 5) as $n)<label class="flex items-center gap-1"><input type="radio" name="answers[{{ $q['id'] }}]" value="{{ $n }}" required> {{ $n }}</label>@endforeach</span>
                                    </fieldset>
                                @endforeach
                                <x-field label="Anything to add?"><textarea name="comment" rows="2" class="{{ \App\Support\Ui::CONTROL }}"></textarea></x-field>
                                @if ($form === "s:{$s->id}")<x-errors />@endif
                                <x-button type="submit">Submit</x-button>
                            </form>
                        </x-modal>
                    @endif
                @empty
                    <x-panel><p class="text-sm text-muted">No surveys yet.</p></x-panel>
                @endforelse
            </div>
        @endif

        @if ($tab === 'recognition')
            @if ($hr)<div class="mb-3 flex justify-end"><x-button @click="open = 'recognition'">Recognise someone</x-button></div>@endif
            <x-panel>
                @forelse ($recognitions as $r)
                    <div class="border-t border-line py-2 text-sm first:border-0"><span class="font-semibold text-ink">{{ Format::fullName($r->employee) }}</span> · {{ $r->title }} · {{ Format::date($r->awarded_on) }}
                        @if ($r->description)<div class="text-xs text-muted">{{ $r->description }}</div>@endif</div>
                @empty
                    <p class="text-sm text-muted">Nobody has been recognised yet.</p>
                @endforelse
            </x-panel>
        @endif

        @if ($tab === 'suggestions')
            <x-panel>
                @forelse ($suggestions as $s)
                    <div class="flex flex-wrap items-start justify-between gap-2 border-t border-line py-2 text-sm first:border-0">
                        <div>{{ $s->message }}<div class="text-xs text-muted">{{ Format::date($s->created_at) }}</div>@if ($s->response)<div class="text-xs">HR: {{ $s->response }}</div>@endif</div>
                        <span class="flex items-center gap-2"><x-badge :status="$s->status" />@if ($hr)<button type="button" class="font-semibold text-accent-strong" @click="open = 'sg:{{ $s->id }}'">Respond</button>@endif</span>
                    </div>
                    @if ($hr)
                        <x-modal show="open === 'sg:{{ $s->id }}'" close="open = null" title="Respond to suggestion">
                            <form method="post" action="{{ route('welfare.suggestions.respond', $s) }}" class="space-y-3">@csrf @method('patch')
                                <x-field label="Status"><x-select name="status" :options="['NEW', 'UNDER_REVIEW', 'ADOPTED', 'DECLINED']" :labels="true" :selected="$s->status" /></x-field>
                                <x-field label="Response"><textarea name="response" rows="2" class="{{ \App\Support\Ui::CONTROL }}">{{ $s->response }}</textarea></x-field>
                                <x-button type="submit">Save</x-button>
                            </form>
                        </x-modal>
                    @endif
                @empty
                    <p class="text-sm text-muted">No suggestions{{ $hr ? '' : ' from you' }} yet.</p>
                @endforelse
            </x-panel>
        @endif

        @if ($tab === 'activities')
            @if ($hr)<div class="mb-3 flex justify-end"><x-button @click="open = 'activity'">Add activity</x-button></div>@endif
            <x-panel>
                @forelse ($activities as $a)
                    <div class="flex items-start justify-between gap-2 border-t border-line py-2 text-sm first:border-0">
                        <div><span class="font-semibold text-ink">{{ $a->title }}</span> · {{ Format::date($a->activity_date) }}{{ $a->location ? " · {$a->location}" : '' }}
                            @if ($a->description)<div class="text-xs text-muted">{{ $a->description }}</div>@endif</div>
                        @if ($hr)@include('employees._remove', ['route' => route('welfare.activities.destroy', $a)])@endif
                    </div>
                @empty
                    <p class="text-sm text-muted">No activities planned.</p>
                @endforelse
            </x-panel>
        @endif

        @if ($tab === 'feedback' && $hr)
            <x-panel>
                <p class="mb-2 text-xs text-muted">Feedback is stored without any name attached.</p>
                @forelse ($feedback as $f)
                    <div class="border-t border-line py-2 text-sm first:border-0"><span class="font-semibold text-ink">{{ $f->category }}</span> · {{ Format::date($f->created_at) }}<div class="text-muted">{{ $f->message }}</div></div>
                @empty
                    <p class="text-sm text-muted">No feedback yet.</p>
                @endforelse
            </x-panel>
        @endif

        {{-- Dialogs --}}
        <x-modal show="open === 'request'" close="open = null" title="Ask HR for support">
            <form method="post" action="{{ route('welfare.requests.store') }}" class="space-y-3">@csrf
                <input type="hidden" name="_form" value="request">
                @if ($hr)<x-field label="For"><x-select name="employee_id" :options="$people" :selected="$user->employee_id" placeholder="Choose" /></x-field>@endif
                <div class="grid gap-3 sm:grid-cols-2">
                    <x-field label="Type"><x-select name="type" :options="array_combine($t = ['Bereavement', 'Medical', 'Wedding', 'Childbirth', 'Emergency loan', 'Other'], $t)" /></x-field>
                    <x-field label="Amount requested (optional)"><x-input type="number" step="0.01" min="0" name="amount" /></x-field>
                </div>
                <x-field label="Details"><textarea name="description" rows="3" required class="{{ \App\Support\Ui::CONTROL }}">{{ $form === 'request' ? old('description') : '' }}</textarea></x-field>
                @if ($form === 'request')<x-errors />@endif
                <x-button type="submit">Send to HR</x-button>
            </form>
        </x-modal>
        <x-modal show="open === 'suggestion'" close="open = null" title="Suggestion">
            <form method="post" action="{{ route('welfare.suggestions.store') }}" class="space-y-3">@csrf
                <input type="hidden" name="_form" value="suggestion">
                <x-field label="Your idea"><textarea name="message" rows="4" required class="{{ \App\Support\Ui::CONTROL }}"></textarea></x-field>
                @if ($form === 'suggestion')<x-errors />@endif
                <x-button type="submit">Send</x-button>
            </form>
        </x-modal>
        <x-modal show="open === 'feedback'" close="open = null" title="Anonymous feedback">
            <form method="post" action="{{ route('welfare.feedback.store') }}" class="space-y-3">@csrf
                <input type="hidden" name="_form" value="feedback">
                <p class="text-sm text-muted">Your name is not stored with this message.</p>
                <x-field label="Topic"><x-input name="category" required /></x-field>
                <x-field label="Message"><textarea name="message" rows="4" required class="{{ \App\Support\Ui::CONTROL }}"></textarea></x-field>
                @if ($form === 'feedback')<x-errors />@endif
                <x-button type="submit">Send anonymously</x-button>
            </form>
        </x-modal>
        @if ($hr)
            <x-modal show="open === 'recognition'" close="open = null" title="Recognise someone">
                <form method="post" action="{{ route('welfare.recognition.store') }}" class="space-y-3">@csrf
                    <input type="hidden" name="_form" value="recognition">
                    <x-field label="Employee"><x-select name="employee_id" :options="$people" placeholder="Choose" required /></x-field>
                    <x-field label="Title"><x-input name="title" placeholder="Employee of the month" required /></x-field>
                    <x-field label="Date"><x-input type="date" name="awarded_on" :value="today()->toDateString()" required /></x-field>
                    <x-field label="Why"><textarea name="description" rows="2" class="{{ \App\Support\Ui::CONTROL }}"></textarea></x-field>
                    @if ($form === 'recognition')<x-errors />@endif
                    <x-button type="submit">Save</x-button>
                </form>
            </x-modal>
            <x-modal show="open === 'activity'" close="open = null" title="Engagement activity">
                <form method="post" action="{{ route('welfare.activities.store') }}" class="space-y-3">@csrf
                    <input type="hidden" name="_form" value="activity">
                    <x-field label="Title"><x-input name="title" required /></x-field>
                    <div class="grid gap-3 sm:grid-cols-2">
                        <x-field label="Date"><x-input type="date" name="activity_date" required /></x-field>
                        <x-field label="Where"><x-input name="location" /></x-field>
                    </div>
                    <x-field label="Details"><textarea name="description" rows="2" class="{{ \App\Support\Ui::CONTROL }}"></textarea></x-field>
                    @if ($form === 'activity')<x-errors />@endif
                    <x-button type="submit">Add</x-button>
                </form>
            </x-modal>
            <x-modal show="open === 'survey'" close="open = null" title="New pulse survey" wide>
                <form method="post" action="{{ route('welfare.surveys.store') }}" class="space-y-3">@csrf
                    <input type="hidden" name="_form" value="survey">
                    <x-field label="Title"><x-input name="title" required /></x-field>
                    <x-field label="Introduction"><x-input name="description" /></x-field>
                    <x-field label="Statements, one per line (each rated 1–5)"><textarea name="questions" rows="5" required class="{{ \App\Support\Ui::CONTROL }}" placeholder="I have the tools I need to do my job well.&#10;My manager gives me useful feedback.">{{ $form === 'survey' ? old('questions') : '' }}</textarea></x-field>
                    <x-field label="Closes on"><x-input type="date" name="closes_on" /></x-field>
                    @if ($form === 'survey')<x-errors />@endif
                    <x-button type="submit">Publish</x-button>
                </form>
            </x-modal>
        @endif
    </div>
</x-layouts.app>
