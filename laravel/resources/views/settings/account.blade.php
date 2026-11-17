@php
    use App\Support\Format;
@endphp
<x-layouts.app title="Settings">
    <x-page-header title="Settings" subtitle="Your sign-in details. HR keeps the company settings." />
    <div class="grid gap-4 lg:grid-cols-2">
        <x-panel>
            <h2 class="mb-3 font-semibold text-ink">Account</h2>
            <dl class="space-y-2 text-sm">
                <div class="flex justify-between gap-3"><dt class="text-muted">Name</dt><dd class="font-semibold text-ink">{{ $user->first_name }} {{ $user->last_name }}</dd></div>
                <div class="flex justify-between gap-3"><dt class="text-muted">Username</dt><dd class="font-semibold text-ink">{{ $user->username ?: '—' }}</dd></div>
                <div class="flex justify-between gap-3"><dt class="text-muted">Email</dt><dd class="font-semibold text-ink">{{ $user->email ?: '—' }}</dd></div>
                <div class="flex justify-between gap-3"><dt class="text-muted">Role</dt><dd class="font-semibold text-ink">{{ Format::role($user->role) }}</dd></div>
                <div class="flex justify-between gap-3"><dt class="text-muted">Last sign-in</dt><dd class="font-semibold text-ink">{{ $user->last_login_at ? Format::date($user->last_login_at) : '—' }}</dd></div>
            </dl>
            <p class="mt-3 text-xs text-muted">Ask HR to change your name, email or role.@if ($user->employee_id) Update your phone and emergency contacts on <a class="text-accent-strong" href="{{ route('employees.show', $user->employee_id) }}">your profile</a>.@endif</p>
        </x-panel>
        <x-panel>
            <h2 class="mb-1 font-semibold text-ink">Password</h2>
            <p class="mb-3 text-sm text-muted">Use at least 8 characters, including a letter and a number.</p>
            <form method="post" action="{{ route('password.update') }}" class="space-y-3">
                @csrf
                @method('put')
                <x-field label="Current password"><input type="password" name="current_password" autocomplete="current-password" required class="{{ \App\Support\Ui::CONTROL }}"></x-field>
                <x-field label="New password"><input type="password" name="new_password" autocomplete="new-password" required class="{{ \App\Support\Ui::CONTROL }}"></x-field>
                <x-errors />
                <x-button type="submit">Update password</x-button>
            </form>
        </x-panel>
    </div>
</x-layouts.app>
