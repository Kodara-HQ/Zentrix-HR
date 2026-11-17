@php
    // Drop the photo at public/images/login.jpg (or .webp / .png); a navy panel shows until then.
    $photo = collect(['login.jpg', 'login.webp', 'login.png'])->first(fn ($file) => file_exists(public_path("images/{$file}")));
@endphp
<x-layouts.plain title="Sign in">
    <div class="grid min-h-screen bg-cream lg:grid-cols-[minmax(0,5fr)_minmax(0,7fr)]">
        {{-- Left: brand and sign-in --}}
        <div class="flex flex-col px-6 py-8 sm:px-12 lg:px-16">
            <div class="flex items-center gap-3">
                <span class="grid h-10 w-10 place-items-center rounded-lg bg-navy text-lg font-bold text-white">{{ mb_substr($company, 0, 1) }}</span>
                <span class="text-base font-semibold text-ink">{{ $company }}</span>
            </div>

            <div class="my-auto w-full max-w-md py-12">
                <h1 class="text-4xl font-semibold leading-[1.1] tracking-tight text-ink sm:text-5xl">Sign in to your HR workspace.</h1>
                <p class="mt-4 text-base leading-relaxed text-muted">People, leave, attendance and payroll in one place, for HR and every employee.</p>

                <form method="post" action="{{ url('/login') }}" class="mt-10 space-y-4">
                    @csrf
                    <x-field label="Username or work email">
                        <input name="email" value="{{ old('email') }}" class="{{ \App\Support\Ui::CONTROL }} !py-2.5" autocomplete="username" required autofocus>
                    </x-field>
                    <x-field label="Password">
                        <input name="password" type="password" class="{{ \App\Support\Ui::CONTROL }} !py-2.5" autocomplete="current-password" required>
                    </x-field>
                    <x-error-note :text="$errors->first('email') ?: $errors->first('password')" />
                    <button type="submit" class="flex w-full items-center justify-center gap-2 rounded-lg bg-navy px-4 py-3 text-sm font-semibold text-white transition hover:bg-navy-deep">
                        Sign in <x-lucide-arrow-right class="h-4 w-4" />
                    </button>
                </form>
                <p class="mt-6 text-sm text-muted">Forgot your password? Ask Human Resources to reset it.</p>
            </div>

            <p class="text-xs text-muted">&copy; {{ now()->year }} {{ $company }}</p>
        </div>

        {{-- Right: photo with one card in the corner --}}
        <div class="relative hidden overflow-hidden bg-navy lg:block">
            @if ($photo)
                <img src="{{ asset("images/{$photo}") }}" alt="" class="absolute inset-0 h-full w-full object-cover">
                <div class="absolute inset-y-0 left-0 w-2/5 bg-gradient-to-r from-cream via-cream/60 to-transparent"></div>
            @else
                <div class="absolute inset-0 bg-[radial-gradient(circle_at_70%_30%,#2a3f7a_0%,#1b2b57_45%,#13204a_100%)]"></div>
            @endif

            <div class="absolute bottom-8 left-8 w-72 bg-navy/95 p-5 text-white shadow-2xl ring-1 ring-white/10 backdrop-blur">
                <h2 class="text-base font-semibold leading-snug">Everything HR runs on, in one record</h2>
                <ul class="mt-3 space-y-2 text-sm text-sky-text">
                    @foreach (['Employee records and history', 'Leave and attendance', 'Payroll with PAYE and SSNIT', 'Recruitment to exit'] as $line)
                        <li class="flex items-center gap-3"><span class="h-2 w-2 rounded-full bg-accent ring-4 ring-accent/20"></span>{{ $line }}</li>
                    @endforeach
                </ul>
            </div>
        </div>
    </div>
</x-layouts.plain>
