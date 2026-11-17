@php
    use App\Support\Format;
    use App\Support\SettingsLists;
    $form = old('_form');
@endphp
<x-layouts.app title="System settings">
    <x-page-header title="System settings" subtitle="Company profile, organisation structure, leave and pay rules, workflows, and notifications." />

    <div class="mb-4 flex gap-1 overflow-x-auto overflow-y-hidden border-b border-line">
        @foreach ($tabs as $key => $label)
            <a href="{{ route('settings.index', ['tab' => $key]) }}"
               class="-mb-px whitespace-nowrap border-b-2 px-3 py-2 text-sm font-semibold {{ $tab === $key ? 'border-accent-strong text-ink' : 'border-transparent text-muted hover:text-ink' }}">{{ $label }}</a>
        @endforeach
    </div>

    @if ($errors->has('delete'))<x-errors class="mb-3" />@endif

    {{-- Company --}}
    @if ($tab === 'company')
        <x-panel>
            <form method="post" action="{{ route('settings.company') }}" class="grid gap-3 sm:grid-cols-2">
                @csrf
                @method('put')
                @foreach (['name' => 'Name shown in the app', 'legal_name' => 'Legal name', 'tin' => 'TIN', 'ssnit_employer_no' => 'SSNIT employer number', 'email' => 'Email', 'phone' => 'Phone', 'address' => 'Address', 'city' => 'City', 'country' => 'Country'] as $key => $label)
                    <x-field :label="$label"><x-input :name="$key" :value="$company?->{$key}" :required="in_array($key, ['name', 'legal_name', 'country'], true)" /></x-field>
                @endforeach
                <div class="sm:col-span-2 space-y-3"><x-errors /><x-button type="submit">Save company</x-button></div>
            </form>
        </x-panel>
    @endif

    {{-- Working hours --}}
    @if ($tab === 'hours')
        <x-panel>
            <p class="mb-4 max-w-2xl text-sm text-muted">Reporting time is when staff are expected to scan in, and closing time is when the day ends. A scan after the grace period is late; leaving before closing time is an early departure. Staff on a shift follow their shift instead.</p>
            <form method="post" action="{{ route('settings.hours') }}" class="grid gap-3 sm:grid-cols-3">
                @csrf
                @method('put')
                <x-field label="Reporting time"><x-input type="time" name="start_time" :value="$calendar?->start_time ?? '08:00'" required /></x-field>
                <x-field label="Closing time"><x-input type="time" name="end_time" :value="$calendar?->end_time ?? '17:00'" required /></x-field>
                <x-field label="Grace minutes"><x-input type="number" min="0" name="grace_minutes" :value="$calendar?->grace_minutes ?? 15" required /></x-field>
                <x-field label="Overtime rate, weekdays"><x-input type="number" step="0.05" min="1" name="overtime_weekday" :value="$calendar?->overtime_weekday ?? 1.5" required /></x-field>
                <x-field label="Overtime rate, weekends"><x-input type="number" step="0.05" min="1" name="overtime_weekend" :value="$calendar?->overtime_weekend ?? 2" required /></x-field>
                <div></div>
                @include('settings._field', ['name' => 'work_days', 'field' => ['Working days', 'days', 'required'], 'value' => $calendar?->work_days, 'own' => true])
                <div class="sm:col-span-3 space-y-3">
                    @if ($calendar)<p class="text-xs text-muted">That is {{ rtrim(rtrim((string) $calendar->hours_per_day, '0'), '.') }} hours a day and {{ rtrim(rtrim((string) $calendar->hours_per_month, '0'), '.') }} a month, used for overtime pay.</p>@endif
                    <x-errors />
                    <x-button type="submit">Save working hours</x-button>
                </div>
            </form>
        </x-panel>
    @endif

    {{-- Simple lists --}}
    @isset($list)
        <div x-data="{ open: {{ $form ? "'".$form."'" : 'null' }} }">
            <div class="mb-3 flex justify-end"><x-button @click="open = 'add'">Add</x-button></div>
            <div class="overflow-auto rounded-2xl border border-line bg-white">
                <table class="w-full min-w-[640px] text-left text-sm">
                    <thead class="text-xs uppercase tracking-wide text-muted">
                        <tr>@foreach ($list['fields'] as $field)<th class="px-3 py-2">{{ $field[0] }}</th>@endforeach<th></th></tr>
                    </thead>
                    <tbody>
                        @forelse ($rows as $row)
                            <tr class="border-t border-line">
                                @foreach ($list['fields'] as $name => $field)
                                    <td class="px-3 py-2.5 {{ $loop->first ? 'font-semibold text-ink' : '' }}">{{ SettingsLists::display($field, $row->{$name}) }}</td>
                                @endforeach
                                <td class="px-3 py-2.5">
                                    <div class="flex justify-end gap-3 text-sm font-semibold">
                                        <button type="button" class="text-accent-strong" @click="open = 'edit:{{ $row->id }}'">Edit</button>
                                        <form method="post" action="{{ route('settings.items.destroy', [$tab, $row->id]) }}" onsubmit="return confirm('Remove this item?')">
                                            @csrf
                                            @method('delete')
                                            <button class="text-[#a12d2d]">Remove</button>
                                        </form>
                                    </div>
                                </td>
                            </tr>
                        @empty
                            <tr><td colspan="{{ count($list['fields']) + 1 }}" class="px-3 py-6 text-center text-muted">Nothing here yet.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            <x-modal show="open === 'add'" close="open = null" :title="'Add to '.strtolower($list['label'])" wide>
                <form method="post" action="{{ route('settings.items.store', $tab) }}" class="space-y-4">
                    @csrf
                    <input type="hidden" name="_form" value="add">
                    <div class="grid gap-3 sm:grid-cols-2">
                        @foreach ($list['fields'] as $name => $field)
                            @include('settings._field', ['value' => null, 'own' => $form === 'add'])
                        @endforeach
                    </div>
                    @if ($form === 'add')<x-errors />@endif
                    <x-button type="submit">Add</x-button>
                </form>
            </x-modal>
            @foreach ($rows as $row)
                <x-modal show="open === 'edit:{{ $row->id }}'" close="open = null" :title="'Edit '.($row->name ?? '')" wide>
                    <form method="post" action="{{ route('settings.items.update', [$tab, $row->id]) }}" class="space-y-4">
                        @csrf
                        @method('patch')
                        <input type="hidden" name="_form" value="edit:{{ $row->id }}">
                        <div class="grid gap-3 sm:grid-cols-2">
                            @foreach ($list['fields'] as $name => $field)
                                @include('settings._field', ['value' => $row->{$name}, 'own' => $form === "edit:{$row->id}"])
                            @endforeach
                        </div>
                        @if ($form === "edit:{$row->id}")<x-errors />@endif
                        <x-button type="submit">Save</x-button>
                    </form>
                </x-modal>
            @endforeach
        </div>
    @endisset

    {{-- Workflows --}}
    @if ($tab === 'workflows')
        <x-panel>
            <p class="mb-3 text-sm text-muted">Who approves each kind of request. Leave follows this setting today; the other workflows are recorded for the modules still being moved over.</p>
            @foreach ($workflows as $workflow)
                <form method="post" action="{{ route('settings.workflows', $workflow) }}" class="flex flex-wrap items-center gap-3 border-t border-line py-2.5 first:border-0">
                    @csrf
                    @method('put')
                    <span class="min-w-48 text-sm font-semibold text-ink">{{ $workflow->label }}</span>
                    @php($current = implode(',', $workflow->steps ?? []))
                    <x-select name="steps" :options="\App\Http\Controllers\SettingsController::WORKFLOW_OPTIONS + (array_key_exists($current, \App\Http\Controllers\SettingsController::WORKFLOW_OPTIONS) ? [] : [$current => Format::title(str_replace(',', ', then ', $current))])" :selected="$current" class="!w-56" />
                    <x-button type="submit" small tone="ghost">Save</x-button>
                </form>
            @endforeach
        </x-panel>
    @endif

    {{-- Notifications --}}
    @if ($tab === 'notifications')
        <x-panel>
            <form method="post" action="{{ route('settings.notifications') }}">
                @csrf
                @method('put')
                @unless ($mailReady)<p class="mb-3 rounded-lg bg-sky px-3 py-2 text-sm text-navy">Email is not set up yet (MAIL_MAILER in .env), so only in-app notifications are delivered.</p>@endunless
                <table class="w-full text-sm">
                    <thead class="text-xs uppercase tracking-wide text-muted"><tr><th class="py-2 text-left">Event</th><th class="w-24 py-2">In app</th><th class="w-24 py-2">Email</th></tr></thead>
                    <tbody>
                        @foreach ($notices as $notice)
                            <tr class="border-t border-line">
                                <td class="py-2">{{ $notice->label }}</td>
                                <td class="py-2 text-center"><input type="checkbox" name="in_app[{{ $notice->id }}]" value="1" @checked($notice->in_app) aria-label="{{ $notice->label }} in app"></td>
                                <td class="py-2 text-center"><input type="checkbox" name="email[{{ $notice->id }}]" value="1" @checked($notice->email) aria-label="{{ $notice->label }} by email"></td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
                <div class="mt-4"><x-button type="submit">Save notification settings</x-button></div>
            </form>
        </x-panel>
    @endif

    {{-- Tax rates --}}
    @if ($tab === 'tax')
        <div class="space-y-4">
            @foreach ($configs as $config)
                <x-panel>
                    <div class="flex flex-wrap items-baseline justify-between gap-2">
                        <h2 class="font-semibold text-ink">{{ $config->name }}</h2>
                        <span class="text-sm text-muted">{{ Format::date($config->effective_from) }} – {{ $config->effective_to ? Format::date($config->effective_to) : 'now' }}</span>
                    </div>
                    <p class="mt-1 text-sm text-muted">{{ $config->notes }}</p>
                    <p class="mt-2 text-sm">SSNIT: employee {{ (float) $config->employee_rate * 100 }}%, employer {{ (float) $config->employer_rate * 100 }}% (Tier 2 {{ (float) $config->tier2_rate * 100 }}%)</p>
                    <table class="mt-3 w-full max-w-md text-sm">
                        <thead class="text-xs uppercase tracking-wide text-muted"><tr><th class="py-1 text-left">Monthly chargeable income</th><th class="py-1 text-right">Rate</th></tr></thead>
                        <tbody>
                            @php($floor = 0)
                            @foreach ($config->bands ?? [] as $band)
                                <tr class="border-t border-line">
                                    <td class="py-1 tabular-nums">{{ $band['width'] === null ? 'Above '.number_format($floor, 2) : number_format($floor, 2).' – '.number_format($floor + $band['width'], 2) }}</td>
                                    <td class="py-1 text-right tabular-nums">{{ $band['rate'] * 100 }}%</td>
                                </tr>
                                @php($floor += $band['width'] ?? 0)
                            @endforeach
                        </tbody>
                    </table>
                </x-panel>
            @endforeach
        </div>
    @endif

    {{-- Audit log --}}
    @if ($tab === 'audit')
        <div class="overflow-auto rounded-2xl border border-line bg-white">
            <table class="w-full min-w-[720px] text-left text-sm">
                <thead class="text-xs uppercase tracking-wide text-muted"><tr>@foreach (['When', 'Who', 'Action', 'What', 'IP'] as $h)<th class="px-3 py-2">{{ $h }}</th>@endforeach</tr></thead>
                <tbody>
                    @foreach ($logs as $log)
                        <tr class="border-t border-line">
                            <td class="whitespace-nowrap px-3 py-2">{{ $log->created_at->format('j M Y H:i') }}</td>
                            <td class="px-3 py-2">{{ $actors[$log->user_id] ?? '—' }}</td>
                            <td class="px-3 py-2"><x-badge>{{ Format::title($log->action) }}</x-badge></td>
                            <td class="px-3 py-2">{{ $log->summary }}</td>
                            <td class="px-3 py-2 text-xs text-muted">{{ $log->ip ?: '—' }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
        @if ($logs->hasPages())
            <div class="mt-3 flex justify-end gap-2">
                <x-button small tone="ghost" :href="$logs->previousPageUrl()" @class(['pointer-events-none opacity-50' => $logs->onFirstPage()])>Previous</x-button>
                <x-button small tone="ghost" :href="$logs->nextPageUrl()" @class(['pointer-events-none opacity-50' => ! $logs->hasMorePages()])>Next</x-button>
            </div>
        @endif
    @endif

    {{-- Backup --}}
    @if ($tab === 'backup')
        <x-panel>
            <p class="mb-3 max-w-2xl text-sm text-muted">Saves a JSON snapshot of the company, employees, sign-ins (without passwords), contracts, leave and payroll. For a full copy of the database, use <code>mysqldump zentrix_hr</code> instead.</p>
            <form method="post" action="{{ route('settings.backup') }}">@csrf<x-button type="submit">Create backup</x-button></form>
            @if ($backups->isNotEmpty())
                <h3 class="mt-5 font-semibold text-ink">Recent backups</h3>
                <ul class="mt-2 space-y-1 text-sm">
                    @foreach ($backups as $file)
                        <li><a class="text-accent-strong" href="{{ route('settings.backup.download', basename($file)) }}">{{ basename($file) }}</a> <span class="text-muted">· {{ number_format(\Illuminate\Support\Facades\Storage::size($file) / 1024, 0) }} KB</span></li>
                    @endforeach
                </ul>
            @endif
        </x-panel>
    @endif
</x-layouts.app>
