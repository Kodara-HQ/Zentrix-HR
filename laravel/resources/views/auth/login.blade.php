<x-layouts.plain title="Sign in">
    <div class="grid min-h-screen place-items-center bg-[#f6f1e8] px-4 py-10">
        <div class="w-full max-w-md">
            <div class="mb-6 flex items-center justify-center gap-3">
                <span class="grid h-10 w-10 place-items-center rounded-md bg-[#1a3c34] text-lg font-bold text-[#f6f1e8]">{{ mb_substr($company, 0, 1) }}</span>
                <div>
                    <div class="text-sm font-semibold text-[#1a3c34]">{{ $company }}</div>
                    <div class="text-[11px] font-semibold tracking-[0.18em] text-[#c4a574]">HUMAN RESOURCES</div>
                </div>
            </div>
            <div class="rounded-2xl border border-[#e6dfd4] bg-[#fffcf8] p-7 shadow-sm">
                <h1 class="text-2xl font-semibold text-[#1a3c34]">Sign in</h1>
                <p class="mt-1 text-sm text-[#6e675e]">Use your username or work email.</p>
                <form method="post" action="{{ url('/login') }}" class="mt-6 space-y-4">
                    @csrf
                    <x-field label="Username or email">
                        <input name="email" value="{{ old('email') }}" class="{{ \App\Support\Ui::CONTROL }}" autocomplete="username" required autofocus>
                    </x-field>
                    <x-field label="Password">
                        <input name="password" type="password" class="{{ \App\Support\Ui::CONTROL }}" autocomplete="current-password" required>
                    </x-field>
                    <x-error-note :text="$errors->first('email') ?: $errors->first('password')" />
                    <x-button type="submit" class="w-full">Sign in</x-button>
                </form>
            </div>
        </div>
    </div>
</x-layouts.plain>
