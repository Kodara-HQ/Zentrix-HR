@php
    use App\Support\Format;
    use App\Support\Lookups;
    $user = auth()->user();
    $tabs = ['overview' => 'Overview', 'qualifications' => 'Qualifications', 'compensation' => 'Compensation', 'documents' => 'Documents', 'history' => 'History'];
    $tab = array_key_exists($tab, $tabs) ? $tab : 'overview';
    $form = old('_form');
    $canPhoto = $writer || $own;
    $initials = mb_substr($employee->first_name, 0, 1).mb_substr($employee->last_name, 0, 1);
@endphp
<x-layouts.app :title="Format::fullName($employee)">
    <div x-data="{ tab: '{{ $tab }}', action: {{ str_starts_with((string) $form, 'action:') ? "'".substr($form, 7)."'" : 'null' }}, editing: {{ $form === 'edit' ? 'true' : 'false' }} }">
        <a href="{{ $user->role->value === 'EMPLOYEE' ? route('dashboard') : route('employees.index') }}" class="text-sm text-accent-strong">
            {{ $user->role->value === 'EMPLOYEE' ? 'Back to home' : 'Back to employees' }}
        </a>

        <div class="mt-3 flex flex-wrap items-start justify-between gap-4">
            <div class="flex items-center gap-4">
                @if ($canPhoto)
                    <form method="post" action="{{ route('files.store') }}" enctype="multipart/form-data" class="group relative h-20 w-20 shrink-0">
                        @csrf
                        <input type="hidden" name="owner_type" value="EMPLOYEE">
                        <input type="hidden" name="owner_id" value="{{ $employee->id }}">
                        <input type="hidden" name="photo" value="1">
                        <label class="block h-20 w-20 cursor-pointer" title="{{ $employee->photo_file_id ? 'Click to update photo' : 'Click to upload photo' }}">
                            <input class="sr-only" type="file" name="file" accept="image/jpeg,image/png,image/webp" onchange="this.form.submit()">
                            @include('employees._avatar')
                            <span class="absolute inset-0 grid place-items-center rounded-full bg-navy/75 px-2 text-center text-[11px] font-semibold leading-tight text-white opacity-0 transition group-hover:opacity-100">
                                {{ $employee->photo_file_id ? 'Update photo' : 'Upload photo' }}
                            </span>
                            <span class="absolute -bottom-1 -right-1 grid h-7 w-7 place-items-center rounded-full border-2 border-cream bg-accent-strong text-white"><x-lucide-camera class="h-3.5 w-3.5" /></span>
                        </label>
                    </form>
                @else
                    <div class="h-20 w-20 shrink-0">@include('employees._avatar')</div>
                @endif
                <div>
                    <h1 class="text-2xl font-semibold text-ink">{{ Format::fullName($employee) }}</h1>
                    <p class="text-sm text-muted">{{ $employee->employee_no }} · {{ $employee->jobTitle?->name }} · {{ $employee->department?->name }}</p>
                    <x-badge :status="$employee->status" />
                    @error('file')<p class="mt-1 text-xs text-[#9f2d2d]">{{ $message }}</p>@enderror
                </div>
            </div>
            <div class="flex flex-wrap gap-2">
                @if ($writer || $own)<x-button small tone="ghost" @click="editing = true">Edit details</x-button>@endif
                @if ($writer)
                    @foreach (['PROMOTE', 'TRANSFER', 'SUSPEND', 'REINSTATE', 'DEACTIVATE'] as $item)
                        <x-button small :tone="$item === 'DEACTIVATE' ? 'danger' : 'ghost'" @click="action = '{{ $item }}'">{{ Format::title($item) }}</x-button>
                    @endforeach
                @endif
            </div>
        </div>

        <div class="mt-4 mb-4 flex gap-1 overflow-x-auto overflow-y-hidden border-b border-line">
            @foreach ($tabs as $key => $label)
                <button type="button" @click="tab = '{{ $key }}'; history.replaceState(null, '', '?tab={{ $key }}')"
                        class="-mb-px whitespace-nowrap border-b-2 px-3 py-2 text-sm font-semibold"
                        :class="tab === '{{ $key }}' ? 'border-accent-strong text-ink' : 'border-transparent text-muted hover:text-ink'">{{ $label }}</button>
            @endforeach
        </div>

        {{-- Overview --}}
        <div x-show="tab === 'overview'" class="grid gap-4 md:grid-cols-2">
            <x-panel>
                <h2 class="mb-2 font-semibold text-ink">Personal</h2>
                <dl class="grid grid-cols-2 gap-2 text-sm text-muted">
                    <div>Gender<div class="font-semibold text-ink">{{ Format::title($employee->gender) }}</div></div>
                    <div>Date of birth<div class="font-semibold text-ink">{{ Format::date($employee->date_of_birth) }}</div></div>
                    <div>Phone<div class="font-semibold text-ink">{{ $employee->phone }}</div></div>
                    <div>Email<div class="break-all font-semibold text-ink">{{ $employee->email }}</div></div>
                    <div class="col-span-2">Address<div class="font-semibold text-ink">{{ implode(', ', array_filter([$employee->address, $employee->city])) ?: '—' }}</div></div>
                    <div>Emergency<div class="font-semibold text-ink">{{ $employee->emergency_name ?: '—' }} {{ $employee->emergency_phone }}</div></div>
                    <div>Next of kin<div class="font-semibold text-ink">{{ $employee->next_of_kin_name ?: '—' }}</div></div>
                </dl>
            </x-panel>
            <x-panel>
                <h2 class="mb-2 font-semibold text-ink">Job</h2>
                <dl class="grid grid-cols-2 gap-2 text-sm text-muted">
                    <div>Title<div class="font-semibold text-ink">{{ $employee->jobTitle?->name }}</div></div>
                    <div>Type<div class="font-semibold text-ink">{{ $employee->employmentType?->name }}</div></div>
                    <div>Location<div class="font-semibold text-ink">{{ $employee->location?->name }}, {{ $employee->location?->city }}</div></div>
                    <div>Manager<div class="font-semibold text-ink">{{ $employee->manager ? Format::fullName($employee->manager) : '—' }}</div></div>
                    <div>Hired<div class="font-semibold text-ink">{{ Format::date($employee->date_of_employment) }}</div></div>
                    <div>Probation<div class="font-semibold text-ink">{{ Format::date($employee->probation_end_date) }}</div></div>
                </dl>
            </x-panel>
            <x-panel class="md:col-span-2">
                <h2 class="mb-2 font-semibold text-ink">Leave balances this year</h2>
                <div class="grid gap-2 sm:grid-cols-2 lg:grid-cols-4">
                    @forelse ($employee->leaveBalances as $balance)
                        <div class="rounded-xl bg-sky px-3 py-2 text-sm">
                            <div class="font-semibold text-ink">{{ $balance->leaveType->name }}</div>
                            <div class="text-muted">{{ Format::wholeDays($balance->entitled - $balance->used - $balance->pending) }} days left · {{ Format::wholeDays($balance->used) }} used</div>
                        </div>
                    @empty
                        <p class="text-sm text-muted">No leave balances for this year.</p>
                    @endforelse
                </div>
            </x-panel>
        </div>

        {{-- Qualifications --}}
        <div x-show="tab === 'qualifications'" x-cloak class="grid gap-4 lg:grid-cols-2">
            <x-panel>
                <h2 class="mb-2 font-semibold text-ink">Education</h2>
                @forelse ($employee->education as $item)
                    <div class="flex items-center justify-between gap-2 text-sm">
                        <span>{{ $item->qualification }}{{ $item->field ? " in {$item->field}" : '' }}, {{ $item->institution }}{{ $item->end_year ? " ({$item->end_year})" : '' }}</span>
                        @if ($writer)@include('employees._remove', ['route' => route('employees.education.destroy', [$employee, $item])])@endif
                    </div>
                @empty
                    <p class="text-sm text-muted">None recorded.</p>
                @endforelse
                @if ($writer || $own)
                    <form method="post" action="{{ route('employees.education.store', $employee) }}" class="mt-3 grid gap-2">
                        @csrf
                        <input type="hidden" name="_form" value="education">
                        <input name="institution" class="{{ \App\Support\Ui::CONTROL }}" placeholder="Institution" required value="{{ $form === 'education' ? old('institution') : '' }}">
                        <input name="qualification" class="{{ \App\Support\Ui::CONTROL }}" placeholder="Qualification" required value="{{ $form === 'education' ? old('qualification') : '' }}">
                        @if ($form === 'education')<x-errors />@endif
                        <x-button type="submit" small>Add education</x-button>
                    </form>
                @endif
            </x-panel>
            <x-panel>
                <h2 class="mb-2 font-semibold text-ink">Skills</h2>
                @forelse ($employee->skills as $item)
                    <div class="flex items-center justify-between gap-2 text-sm">
                        <span>{{ $item->name }} · {{ $item->level }}</span>
                        @if ($writer)@include('employees._remove', ['route' => route('employees.skills.destroy', [$employee, $item])])@endif
                    </div>
                @empty
                    <p class="text-sm text-muted">None recorded.</p>
                @endforelse
                @if ($writer || $own)
                    <form method="post" action="{{ route('employees.skills.store', $employee) }}" class="mt-3 grid gap-2">
                        @csrf
                        <input type="hidden" name="_form" value="skill">
                        <input name="name" class="{{ \App\Support\Ui::CONTROL }}" placeholder="Skill" required value="{{ $form === 'skill' ? old('name') : '' }}">
                        <x-select name="level" :options="['Beginner', 'Proficient', 'Advanced', 'Expert']" :labels="false" :selected="'Proficient'" />
                        @if ($form === 'skill')<x-errors />@endif
                        <x-button type="submit" small>Add skill</x-button>
                    </form>
                @endif
                <h2 class="mb-2 mt-4 font-semibold text-ink">Experience</h2>
                @forelse ($employee->experience as $item)<p class="text-sm">{{ $item->role }}, {{ $item->employer }}</p>@empty<p class="text-sm text-muted">None recorded.</p>@endforelse
                <h2 class="mb-2 mt-4 font-semibold text-ink">Qualifications</h2>
                @forelse ($employee->qualifications as $item)<p class="text-sm">{{ $item->name }}</p>@empty<p class="text-sm text-muted">None recorded.</p>@endforelse
            </x-panel>
        </div>

        {{-- Compensation --}}
        <div x-show="tab === 'compensation'" x-cloak>
            <x-panel>
                @if (! $compensation)
                    <p class="text-sm">Compensation is restricted for your role.</p>
                @else
                    <p class="text-sm">Basic salary <strong>{{ Format::money($employee->basic_salary) }}</strong></p>
                    <p class="mt-1 text-sm">SSNIT {{ $employee->ssnit_number ?: '—' }} · TIN {{ $employee->tin_number ?: '—' }}</p>
                    <h3 class="mt-4 font-semibold text-ink">Allowances and deductions</h3>
                    <ul class="mt-1 text-sm">
                        @forelse ($employee->payItems as $item)
                            <li class="flex items-center justify-between gap-2"><span>{{ $item->component->name }}: {{ Format::money($item->amount) }}</span>
                                @if ($payroll)@include('employees._remove', ['route' => route('employees.pay-items.destroy', [$employee, $item])])@endif</li>
                        @empty
                            <li class="text-muted">None.</li>
                        @endforelse
                    </ul>
                    @if ($payroll)
                        <form method="post" action="{{ route('employees.pay-items.store', $employee) }}" class="mt-3 flex flex-wrap gap-2">
                            @csrf
                            <input type="hidden" name="_form" value="pay-item">
                            <x-select name="component_id" :options="Lookups::payComponents()" placeholder="Component" class="!w-56" required />
                            <input name="amount" type="number" step="0.01" class="{{ \App\Support\Ui::CONTROL }} !w-36" placeholder="Amount" required>
                            <x-button type="submit" small>Save</x-button>
                        </form>
                        @if ($form === 'pay-item')<x-errors class="mt-2" />@endif
                    @endif
                    <h3 class="mt-4 font-semibold text-ink">Bank accounts</h3>
                    <ul class="mt-1 text-sm">
                        @forelse ($employee->bankAccounts as $item)<li>{{ $item->bank_name }} · {{ $item->account_number }}</li>@empty<li class="text-muted">None.</li>@endforelse
                    </ul>
                @endif
            </x-panel>
        </div>

        {{-- Documents --}}
        <div x-show="tab === 'documents'" x-cloak>
            <x-panel>
                <ul class="space-y-2 text-sm">
                    @foreach ($employee->contracts as $item)
                        <li>{{ $item->title }} · {{ Format::date($item->start_date) }} to {{ Format::date($item->end_date) }} · {{ Format::title($item->status) }}</li>
                    @endforeach
                    @foreach ($employee->letters as $item)
                        <li><a class="text-accent-strong" href="{{ $item->file_asset_id ? route('files.show', $item->file_asset_id) : '#' }}" target="_blank">{{ $item->subject }}</a> · {{ Format::date($item->issued_on) }}</li>
                    @endforeach
                </ul>
                <h3 class="mt-4 font-semibold text-ink">Uploaded documents</h3>
                <ul class="mt-2 text-sm">
                    @forelse ($files as $file)
                        <li class="flex items-center justify-between gap-2"><a class="text-accent-strong" href="{{ route('files.show', $file) }}" target="_blank">{{ $file->file_name }}</a>
                            @if ($writer)@include('employees._remove', ['route' => route('files.destroy', $file)])@endif</li>
                    @empty
                        <li class="text-muted">None yet.</li>
                    @endforelse
                </ul>
                @if ($writer || $own)
                    <form method="post" action="{{ route('files.store') }}" enctype="multipart/form-data" class="mt-3 flex flex-wrap items-center gap-2">
                        @csrf
                        <input type="hidden" name="owner_type" value="EMPLOYEE">
                        <input type="hidden" name="owner_id" value="{{ $employee->id }}">
                        <x-select name="category_id" :options="Lookups::categories()" placeholder="Category" class="!w-56" />
                        <input type="file" name="file" class="text-sm" required>
                        <x-button type="submit" small>Upload</x-button>
                    </form>
                @endif
            </x-panel>
        </div>

        {{-- History --}}
        <div x-show="tab === 'history'" x-cloak>
            <x-panel>
                <ul class="space-y-3 text-sm">
                    @forelse ($employee->events as $item)
                        <li><span class="font-semibold text-ink">{{ $item->title }}</span> · {{ Format::date($item->effective_date) }}<div class="text-muted">{{ $item->description }}</div></li>
                    @empty
                        <li class="text-muted">No history yet.</li>
                    @endforelse
                </ul>
            </x-panel>
        </div>

        @if ($writer || $own)
            <x-modal show="editing" title="Edit details" wide>
                <form method="post" action="{{ route('employees.update', $employee) }}" class="space-y-4">
                    @csrf
                    @method('patch')
                    <input type="hidden" name="_form" value="edit">
                    @include('employees._form', ['employee' => $employee, 'full' => $writer])
                    @if ($form === 'edit')<x-errors />@endif
                    <x-button type="submit">Save details</x-button>
                </form>
            </x-modal>
        @endif

        @if ($writer)
            @foreach (['PROMOTE', 'TRANSFER', 'SUSPEND', 'REINSTATE', 'DEACTIVATE'] as $item)
                <x-modal show="action === '{{ $item }}'" close="action = null" :title="Format::title($item)" wide>
                    <form method="post" action="{{ route('employees.action', $employee) }}">
                        @csrf
                        <input type="hidden" name="_form" value="action:{{ $item }}">
                        <input type="hidden" name="type" value="{{ $item }}">
                        <div class="grid gap-x-4 gap-y-3 sm:grid-cols-2">
                            @if ($item === 'PROMOTE')
                                <x-field label="New job title"><x-select name="job_title_id" :options="Lookups::jobTitles()" :selected="$employee->job_title_id" required /></x-field>
                                <x-field label="New basic salary"><x-input type="number" step="0.01" min="0" name="basic_salary" required /></x-field>
                            @elseif ($item === 'TRANSFER')
                                <x-field label="Department"><x-select name="department_id" :options="Lookups::departments()" :selected="$employee->department_id" /></x-field>
                                <x-field label="Location"><x-select name="location_id" :options="Lookups::locations()" :selected="$employee->location_id" /></x-field>
                                <x-field label="Reporting manager"><x-select name="manager_id" :options="array_diff_key(Lookups::managers(), [$employee->id => true])" :selected="$employee->manager_id" placeholder="None" /></x-field>
                            @endif
                            <x-field label="Effective date"><x-input type="date" name="effective_date" :value="today()->toDateString()" /></x-field>
                            <div class="sm:col-span-2"><x-field label="Notes"><textarea name="notes" rows="3" class="{{ \App\Support\Ui::CONTROL }}">{{ $form === "action:{$item}" ? old('notes') : '' }}</textarea></x-field></div>
                        </div>
                        @if ($form === "action:{$item}")<x-errors class="mt-3" />@endif
                        <div class="mt-4"><x-button type="submit" :tone="$item === 'DEACTIVATE' ? 'danger' : 'primary'">Confirm</x-button></div>
                    </form>
                </x-modal>
            @endforeach
        @endif
    </div>
</x-layouts.app>
