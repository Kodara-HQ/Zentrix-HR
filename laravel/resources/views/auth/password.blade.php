<x-layouts.plain title="Choose a new password">
    <div class="px-4">
        <div class="mx-auto mt-16 max-w-md rounded-2xl border border-line bg-paper p-6 shadow-sm">
            <h1 class="text-xl font-semibold text-ink">Choose a new password</h1>
            <p class="mt-1 text-sm text-muted">Use at least 8 characters, including a letter and a number.</p>
            <form method="post" action="{{ route('password.update') }}" class="mt-4 space-y-3">
                @csrf
                @method('put')
                <input name="current_password" type="password" class="{{ \App\Support\Ui::CONTROL }}" placeholder="Current password" autocomplete="current-password" required>
                <input name="new_password" type="password" class="{{ \App\Support\Ui::CONTROL }}" placeholder="New password" autocomplete="new-password" required>
                <x-error-note :text="$errors->first()" />
                <div class="flex gap-2">
                    <x-button type="submit">Update password</x-button>
                    <x-button type="submit" tone="ghost" form="logout-form">Sign out</x-button>
                </div>
            </form>
            <form id="logout-form" method="post" action="{{ route('logout') }}">@csrf</form>
        </div>
    </div>
</x-layouts.plain>
