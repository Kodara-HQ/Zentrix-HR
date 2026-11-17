@php
    use App\Support\Format;
    use App\Support\Lookups;
@endphp
<x-layouts.app title="Employees">
    <div x-data="{ adding: {{ old('_form') === 'create' ? 'true' : 'false' }} }">
        <x-page-header title="Employees" subtitle="One record for identity, job, pay, qualifications, and the history of every change.">
            @if ($writer)<x-button @click="adding = true">Add employee</x-button>@endif
        </x-page-header>

        <form method="get" class="mb-3 flex flex-wrap gap-2">
            <input name="search" value="{{ request('search') }}" placeholder="Name, number, or email" class="{{ \App\Support\Ui::CONTROL }} max-w-xs">
            <x-select name="department_id" :options="$departments" :selected="request('department_id')" placeholder="All departments" class="!w-[220px]" onchange="this.form.submit()" />
            <x-select name="status" :options="Lookups::STATUSES" :labels="true" :selected="request('status')" placeholder="All statuses" class="!w-[180px]" onchange="this.form.submit()" />
            <x-button type="submit" tone="ghost">Search</x-button>
        </form>

        <div class="overflow-auto rounded-2xl border border-line bg-white">
            <table class="w-full min-w-[760px] text-left text-sm">
                <thead class="text-xs uppercase tracking-wide text-muted">
                    <tr>@foreach (['Emp ID', 'Emp name', 'Job', 'Location', 'Hired', 'Status', 'Salary'] as $heading)<th class="px-3 py-2">{{ $heading }}</th>@endforeach</tr>
                </thead>
                <tbody>
                    @forelse ($employees as $row)
                        <tr class="cursor-pointer border-t border-line hover:bg-sky" onclick="window.location='{{ route('employees.show', $row) }}'">
                            <td class="whitespace-nowrap px-3 py-2.5"><a href="{{ route('employees.show', $row) }}">{{ $row->employee_no }}</a></td>
                            <td class="px-3 py-2.5">
                                <div class="font-semibold">{{ Format::fullName($row) }}</div>
                                <div class="text-xs text-muted">{{ $row->email }}</div>
                            </td>
                            <td class="px-3 py-2.5">{{ $row->jobTitle?->name }}<div class="text-xs text-muted">{{ $row->department?->name }}</div></td>
                            <td class="px-3 py-2.5">{{ $row->location?->city }}</td>
                            <td class="whitespace-nowrap px-3 py-2.5">{{ Format::date($row->date_of_employment) }}</td>
                            <td class="px-3 py-2.5"><x-badge :status="$row->status" /></td>
                            <td class="px-3 py-2.5">{{ \App\Support\Access::canSeeCompensation(auth()->user(), $row->id) ? Format::money($row->basic_salary) : 'Restricted' }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="7" class="px-3 py-6 text-center text-muted">No employees match.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        <div class="mt-3 flex items-center justify-between text-sm text-muted">
            <span>{{ $employees->total() }} {{ Str::plural('employee', $employees->total()) }}</span>
            <div class="flex gap-2">
                <x-button small tone="ghost" :href="$employees->previousPageUrl()" @class(['pointer-events-none opacity-50' => $employees->onFirstPage()])>Previous</x-button>
                <x-button small tone="ghost" :href="$employees->nextPageUrl()" @class(['pointer-events-none opacity-50' => ! $employees->hasMorePages()])>Next</x-button>
            </div>
        </div>

        @if ($writer)
            <x-modal show="adding" title="New employee" wide>
                <form method="post" action="{{ route('employees.store') }}" class="space-y-4">
                    @csrf
                    <input type="hidden" name="_form" value="create">
                    @include('employees._form', ['employee' => null, 'full' => true])
                    @if (old('_form') === 'create')<x-errors />@endif
                    <x-button type="submit">Save employee</x-button>
                </form>
            </x-modal>
        @endif
    </div>
</x-layouts.app>
