@php
    use App\Enums\Role;
    use App\Support\Format;
    $me = auth()->user();
    $form = old('_form');
    $roles = collect(Role::cases())->mapWithKeys(fn ($r) => [$r->value => Format::role($r)])->all();
    $name = fn ($u) => $u->first_name === $u->last_name ? $u->first_name : "{$u->first_name} {$u->last_name}";
@endphp
<x-layouts.app title="User management">
    <div x-data="{ open: {{ $form ? "'".$form."'" : 'null' }} }">
        <x-page-header title="User management" subtitle="Create sign-ins, assign roles, and manage access.">
            <x-button @click="open = 'add'"><x-lucide-user-plus class="h-4 w-4" /> Add user</x-button>
        </x-page-header>

        <form method="get" class="mb-3 flex flex-wrap gap-2">
            <input name="search" value="{{ request('search') }}" placeholder="Name, username or email" class="{{ \App\Support\Ui::CONTROL }} max-w-xs">
            <x-select name="role" :options="$roles" :selected="request('role')" placeholder="All roles" class="!w-56" onchange="this.form.submit()" />
            <x-button type="submit" tone="ghost">Search</x-button>
        </form>

        @if ($errors->has('role') && ! $form)<x-errors class="mb-3" />@endif

        <div class="overflow-auto rounded-2xl border border-line bg-white">
            <table class="w-full min-w-[900px] text-left text-sm">
                <thead class="text-xs uppercase tracking-wide text-muted">
                    <tr>@foreach (['Name', 'Username', 'Email', 'Role', 'Employee', 'Status', 'Last sign-in', ''] as $h)<th class="px-3 py-2">{{ $h }}</th>@endforeach</tr>
                </thead>
                <tbody>
                    @forelse ($users as $account)
                        <tr class="border-t border-line align-middle {{ $account->is_active ? '' : 'text-muted' }}">
                            <td class="px-3 py-2.5 font-semibold text-ink">{{ $name($account) }}@if ($account->id === $me->id) <span class="ml-1 rounded-full bg-sky px-1.5 text-xs font-semibold text-navy">You</span>@endif</td>
                            <td class="px-3 py-2.5">{{ $account->username ?: '—' }}</td>
                            <td class="px-3 py-2.5">{{ $account->email ?: '—' }}</td>
                            <td class="px-3 py-2.5">{{ Format::role($account->role) }}</td>
                            <td class="px-3 py-2.5">
                                @if ($account->employee)<a href="{{ route('employees.show', $account->employee) }}" class="text-accent-strong">{{ $account->employee->employee_no }}</a>@else — @endif
                            </td>
                            <td class="px-3 py-2.5"><x-badge :tone="$account->is_active ? 'green' : 'red'">{{ $account->is_active ? 'Active' : 'Inactive' }}</x-badge>
                                @if ($account->must_change_password)<div class="mt-0.5 text-xs text-muted">Must change password</div>@endif</td>
                            <td class="whitespace-nowrap px-3 py-2.5">{{ $account->last_login_at ? Format::date($account->last_login_at) : 'Never' }}</td>
                            <td class="px-3 py-2.5">
                                <div class="flex flex-wrap justify-end gap-3 text-sm font-semibold text-accent-strong">
                                    <button type="button" @click="open = 'edit:{{ $account->id }}'">Edit</button>
                                    <button type="button" @click="open = 'password:{{ $account->id }}'">Reset password</button>
                                    @if ($account->id !== $me->id)
                                        <form method="post" action="{{ route('users.toggle', $account) }}" @if ($account->is_active) onsubmit="return confirm('{{ addslashes($name($account)) }} will no longer be able to sign in. You can activate the account again later.')" @endif>
                                            @csrf
                                            <button>{{ $account->is_active ? 'Deactivate' : 'Activate' }}</button>
                                        </form>
                                        <form method="post" action="{{ route('users.destroy', $account) }}" onsubmit="return confirm('Delete {{ addslashes($name($account)) }}\'s sign-in? Their employee record, if any, stays on file. This cannot be undone.')">
                                            @csrf
                                            @method('delete')
                                            <button class="text-[#a12d2d]">Delete</button>
                                        </form>
                                    @endif
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="8" class="px-3 py-6 text-center text-muted">No accounts match.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        @if ($users->hasPages())
            <div class="mt-3 flex justify-end gap-2">
                <x-button small tone="ghost" :href="$users->previousPageUrl()" @class(['pointer-events-none opacity-50' => $users->onFirstPage()])>Previous</x-button>
                <x-button small tone="ghost" :href="$users->nextPageUrl()" @class(['pointer-events-none opacity-50' => ! $users->hasMorePages()])>Next</x-button>
            </div>
        @endif

        {{-- Add --}}
        <x-modal show="open === 'add'" close="open = null" title="Add user" wide>
            <form method="post" action="{{ route('users.store') }}" class="space-y-4">
                @csrf
                <input type="hidden" name="_form" value="add">
                @include('users._fields', ['account' => null])
                <x-field label="Initial password"><input type="password" name="password" minlength="12" required autocomplete="new-password" class="{{ \App\Support\Ui::CONTROL }}"></x-field>
                <p class="text-xs text-muted">At least 12 characters. Give it to the person directly; they must change it at first sign-in.</p>
                @if ($form === 'add')<x-errors />@endif
                <x-button type="submit">Create account</x-button>
            </form>
        </x-modal>

        {{-- Edit and reset password, one dialog per account on this page --}}
        @foreach ($users as $account)
            <x-modal show="open === 'edit:{{ $account->id }}'" close="open = null" :title="'Edit '.$name($account)" wide>
                <form method="post" action="{{ route('users.update', $account) }}" class="space-y-4">
                    @csrf
                    @method('patch')
                    <input type="hidden" name="_form" value="edit:{{ $account->id }}">
                    @include('users._fields', ['account' => $account])
                    @if ($form === "edit:{$account->id}")<x-errors />@endif
                    <x-button type="submit">Save changes</x-button>
                </form>
            </x-modal>
            <x-modal show="open === 'password:{{ $account->id }}'" close="open = null" :title="'Reset password for '.$name($account)">
                <form method="post" action="{{ route('users.password', $account) }}" class="space-y-3">
                    @csrf
                    <input type="hidden" name="_form" value="password:{{ $account->id }}">
                    <x-field label="New initial password"><input type="password" name="password" minlength="12" required autocomplete="new-password" class="{{ \App\Support\Ui::CONTROL }}"></x-field>
                    <p class="text-xs text-muted">At least 12 characters. They must choose their own at the next sign-in.</p>
                    @if ($form === "password:{$account->id}")<x-errors />@endif
                    <x-button type="submit">Reset password</x-button>
                </form>
            </x-modal>
        @endforeach
    </div>
</x-layouts.app>
