@php
    use App\Enums\PayrollStatus;
    use App\Services\PayrollService;
    use App\Support\Format;
    $period = PayrollService::period($run->year, $run->month);
    $pending = $run->status === PayrollStatus::PENDING_APPROVAL;
@endphp
<x-layouts.app :title="'Payroll '.$period">
    <div x-data="{ cancelling: false }">
        <a href="{{ route('payroll.index') }}" class="text-sm text-accent-strong">Back to payroll</a>
        <div class="mt-3"></div>
        <x-page-header :title="'Payroll · '.$period" :subtitle="$run->notes">
            <x-badge :status="$run->status" class="self-center" />
            @if ($pending)
                <form method="post" action="{{ route('payroll.process') }}">
                    @csrf
                    <input type="hidden" name="year" value="{{ $run->year }}"><input type="hidden" name="month" value="{{ $run->month }}">
                    <x-button type="submit" tone="ghost">Recalculate</x-button>
                </form>
                @if ($approver)
                    <x-button tone="danger" @click="cancelling = true">Cancel run</x-button>
                    <form method="post" action="{{ route('payroll.decide', $run) }}" onsubmit="return confirm('Approve {{ $period }}? Payslips become final and loan repayments are taken off balances.')">
                        @csrf
                        <input type="hidden" name="decision" value="APPROVED">
                        <x-button type="submit">Approve payroll</x-button>
                    </form>
                @endif
            @endif
        </x-page-header>

        <div class="mb-5 grid gap-3 sm:grid-cols-2 lg:grid-cols-4">
            @foreach (['Gross pay' => $run->total_gross, 'PAYE' => $run->total_paye, 'SSNIT (employee + employer)' => $run->total_ssnit, 'Net pay' => $run->total_net] as $label => $value)
                <x-panel>
                    <div class="text-sm text-muted">{{ $label }}</div>
                    <div class="mt-1 text-2xl font-semibold tracking-tight text-navy">{{ Format::money($value) }}</div>
                </x-panel>
            @endforeach
        </div>

        <div class="overflow-auto rounded-2xl border border-line bg-white">
            <table class="w-full min-w-[900px] text-left text-sm">
                <thead class="text-xs uppercase tracking-wide text-muted">
                    <tr>@foreach (['Emp ID', 'Emp name', 'Basic', 'Gross', 'SSNIT', 'PAYE', 'Other', 'Loan', 'Net', ''] as $h)<th class="px-3 py-2">{{ $h }}</th>@endforeach</tr>
                </thead>
                <tbody>
                    @forelse ($slips as $slip)
                        <tr class="border-t border-line">
                            <td class="whitespace-nowrap px-3 py-2.5">{{ $slip->employee?->employee_no }}</td>
                            <td class="px-3 py-2.5"><div class="font-semibold text-ink">{{ Format::fullName($slip->employee) }}</div><div class="text-xs text-muted">{{ $slip->employee?->department?->name }}</div></td>
                            <td class="px-3 py-2.5">{{ Format::money($slip->basic_salary) }}</td>
                            <td class="px-3 py-2.5">{{ Format::money($slip->gross_pay) }}</td>
                            <td class="px-3 py-2.5">{{ Format::money($slip->employee_ssnit) }}</td>
                            <td class="px-3 py-2.5">{{ Format::money($slip->paye) }}</td>
                            <td class="px-3 py-2.5">{{ Format::money($slip->other_deductions) }}</td>
                            <td class="px-3 py-2.5">{{ Format::money($slip->loan_deduction) }}</td>
                            <td class="px-3 py-2.5 font-semibold text-ink">{{ Format::money($slip->net_pay) }}</td>
                            <td class="px-3 py-2.5 text-right"><a class="font-semibold text-accent-strong" href="{{ route('payroll.payslip', $slip) }}">PDF</a></td>
                        </tr>
                    @empty
                        <tr><td colspan="10" class="px-3 py-6 text-center text-muted">No payslips in this run.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        @if ($pending && $approver)
            <x-modal show="cancelling" title="Cancel this run">
                <form method="post" action="{{ route('payroll.decide', $run) }}" class="space-y-3">
                    @csrf
                    <input type="hidden" name="decision" value="CANCELLED">
                    <x-field label="Reason"><textarea name="note" rows="3" class="{{ \App\Support\Ui::CONTROL }}" required></textarea></x-field>
                    <p class="text-xs text-muted">You can process the month again later.</p>
                    <x-button type="submit" tone="danger">Cancel run</x-button>
                </form>
            </x-modal>
        @endif
    </div>
</x-layouts.app>
