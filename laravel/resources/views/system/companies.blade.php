@php
    $form = old('_form');
    $control = \App\Support\Ui::CONTROL;
@endphp
<x-layouts.app title="Companies">
    <div x-data="{ open: {{ $form ? "'".$form."'" : 'null' }} }">
        <x-page-header title="Companies" subtitle="Set up the organisations that use Zentrix HR. Each company sees only its own staff and records.">
            <x-button @click="open = 'add'"><x-lucide-building-2 class="h-4 w-4" /> New company</x-button>
        </x-page-header>

        <div class="overflow-auto rounded-2xl border border-line bg-white">
            <table class="w-full min-w-[760px] text-left text-sm">
                <thead class="text-xs uppercase tracking-wide text-muted">
                    <tr>@foreach (['Company', 'Location', 'Employees', 'Sign-ins', 'Status', ''] as $h)<th class="px-3 py-2">{{ $h }}</th>@endforeach</tr>
                </thead>
                <tbody>
                    @forelse ($companies as $company)
                        <tr class="border-t border-line align-middle {{ $company->is_active ? '' : 'text-muted' }}">
                            <td class="px-3 py-2.5">
                                <div class="font-semibold text-ink">{{ $company->name }}@if ($acting === $company->id) <span class="ml-1 rounded-full bg-sky px-1.5 text-xs font-semibold text-navy">Open</span>@endif</div>
                                @if ($company->legal_name !== $company->name)<div class="text-xs text-muted">{{ $company->legal_name }}</div>@endif
                            </td>
                            <td class="px-3 py-2.5">{{ collect([$company->city, $company->country])->filter()->join(', ') ?: '—' }}</td>
                            <td class="px-3 py-2.5">{{ $company->employees_count }}</td>
                            <td class="px-3 py-2.5">{{ $company->users_count }}</td>
                            <td class="px-3 py-2.5"><x-badge :tone="$company->is_active ? 'green' : 'red'">{{ $company->is_active ? 'Active' : 'Suspended' }}</x-badge></td>
                            <td class="px-3 py-2.5">
                                <div class="flex flex-wrap items-center justify-end gap-3 text-sm font-semibold text-accent-strong">
                                    <button type="button" @click="open = 'edit:{{ $company->id }}'">Edit</button>
                                    <form method="post" action="{{ route('system.companies.open', $company) }}">
                                        @csrf
                                        <button class="inline-flex items-center gap-1"><x-lucide-log-in class="h-4 w-4" /> Open</button>
                                    </form>
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="6" class="px-3 py-6 text-center text-muted">No companies yet. Set up the first one with New company.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        {{-- New company and its first administrator --}}
        <x-modal show="open === 'add'" close="open = null" title="New company" wide>
            <form method="post" action="{{ route('system.companies.store') }}" class="space-y-4">
                @csrf
                <input type="hidden" name="_form" value="add">
                @include('system._company_fields', ['company' => null])
                <div class="border-t border-line pt-4">
                    <h3 class="font-semibold text-ink">First administrator</h3>
                    <p class="mb-3 text-xs text-muted">This person becomes the company's super administrator and can add everyone else.</p>
                    <div class="grid gap-3 sm:grid-cols-2">
                        <x-field label="First name"><x-input name="admin_first_name" required /></x-field>
                        <x-field label="Last name"><x-input name="admin_last_name" required /></x-field>
                        <x-field label="Username"><x-input name="admin_username" required autocomplete="off" /></x-field>
                        <x-field label="Email (optional)"><x-input name="admin_email" type="email" /></x-field>
                        <x-field label="Initial password" class="sm:col-span-2"><input type="password" name="admin_password" minlength="12" required autocomplete="new-password" class="{{ $control }}"></x-field>
                    </div>
                    <p class="mt-1 text-xs text-muted">At least 12 characters. Give it to the person directly; they must change it at first sign-in.</p>
                </div>
                @if ($form === 'add')<x-errors />@endif
                <x-button type="submit">Set up company</x-button>
            </form>
        </x-modal>

        @foreach ($companies as $company)
            <x-modal show="open === 'edit:{{ $company->id }}'" close="open = null" :title="'Edit '.$company->name" wide>
                <form method="post" action="{{ route('system.companies.update', $company) }}" class="space-y-4">
                    @csrf
                    @method('patch')
                    <input type="hidden" name="_form" value="edit:{{ $company->id }}">
                    @include('system._company_fields', ['company' => $company])
                    <label class="flex items-center gap-2 text-sm">
                        <input type="hidden" name="is_active" value="0">
                        <input type="checkbox" name="is_active" value="1" @checked(old('_form') === "edit:{$company->id}" ? old('is_active') : $company->is_active)>
                        <span><span class="font-semibold text-body">Active.</span> When off, the company's staff cannot sign in. Nothing is deleted.</span>
                    </label>
                    @if ($form === "edit:{$company->id}")<x-errors />@endif
                    <x-button type="submit">Save changes</x-button>
                </form>
            </x-modal>
        @endforeach
    </div>
</x-layouts.app>
