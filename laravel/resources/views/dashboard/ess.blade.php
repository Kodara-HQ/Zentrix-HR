@php
    use App\Support\Format;
    $initials = mb_substr($user->first_name, 0, 1).mb_substr($user->last_name, 0, 1);
    $links = array_filter([
        [url('/attendance'), 'My attendance', 'Punches recorded by the fingerprint clock.'],
        [route('leave.index'), 'My leave', 'Your balances and your own leave requests.'],
        [route('payroll.index'), 'My payslips', 'Payslips that have already been approved.'],
        $employee ? [route('employees.show', $employee), 'My profile', 'Your job, contact details, and documents.'] : null,
        [url('/notifications'), 'Notifications', 'Messages sent to you.'],
    ]);
@endphp
<x-layouts.app title="Home">
    <div class="mb-5 flex items-center gap-4">
        <a href="{{ $employee ? route('employees.show', $employee) : '#' }}" title="Open your profile to change your photo" class="shrink-0">
            @if ($employee?->photo_file_id)
                <img src="{{ route('files.show', $employee->photo_file_id) }}" alt="" class="h-16 w-16 rounded-full border border-line object-cover">
            @else
                <div class="grid h-16 w-16 place-items-center rounded-full bg-navy text-lg font-semibold text-white">{{ $initials }}</div>
            @endif
        </a>
        <div>
            <h1 class="text-2xl font-semibold tracking-tight text-ink">Hello, {{ $user->first_name }}</h1>
            <p class="mt-1 text-sm text-muted">Request leave, download payslips and keep your details up to date.</p>
        </div>
    </div>

    @if (! $employee)
        <x-error-note text="This sign-in is not linked to an employee record. Ask HR to connect it before you can apply for leave." />
    @else
        <div class="grid gap-3 sm:grid-cols-2 xl:grid-cols-4">
            <x-stat label="Employee number" :value="$employee->employee_no" />
            <x-stat label="Status" :value="Format::title($employee->status)" />
            <x-stat label="Job" :value="$employee->jobTitle?->name ?? '—'" :hint="$employee->department?->name" />
            <x-stat label="Location" :value="$employee->location?->city ?? '—'" :hint="$employee->location?->name" />
        </div>
    @endif

    <div class="mt-4 grid gap-3 sm:grid-cols-2 lg:grid-cols-3">
        @foreach ($links as [$href, $title, $text])
            <a href="{{ $href }}" class="rounded-2xl border border-line bg-paper p-4 transition hover:border-accent hover:shadow-sm">
                <div class="font-semibold text-ink">{{ $title }}</div>
                <p class="mt-1 text-sm text-muted">{{ $text }}</p>
            </a>
        @endforeach
    </div>
</x-layouts.app>
