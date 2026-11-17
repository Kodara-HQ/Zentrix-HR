@php
    use App\Services\PayrollService;
    use App\Support\Format;
    $form = old('_form');
    $months = collect(range(1, 12))->mapWithKeys(fn ($m) => [$m => \Carbon\CarbonImmutable::create(2000, $m, 1)->format('F')])->all();
    $employeeOptions = $employees->mapWithKeys(fn ($e) => [$e->id => $e->employee_no.' · '.Format::fullName($e)])->all();
@endphp
<x-layouts.app :title="$officer ? 'Payroll' : 'My payslips'">
    <div x-data="{ open: {{ $form ? "'".$form."'" : 'null' }} }">
        <x-page-header :title="$officer ? 'Payroll' : 'My payslips'"
                       :subtitle="$officer ? 'Calculate a month, check it, then HR approves it. Approved months are final.' : 'Payslips appear here after payroll has been approved.'">
            @if ($officer)
                <x-button @click="open = 'process'">Process month</x-button>
                <x-button tone="ghost" @click="open = 'adjustment'">Bonus or deduction</x-button>
                <x-button tone="ghost" @click="open = 'loan'">Loan or advance</x-button>
            @endif
        </x-page-header>

        @if ($officer)
            <div class="overflow-auto rounded-2xl border border-line bg-white">
                <table class="w-full min-w-[720px] text-left text-sm">
                    <thead class="text-xs uppercase tracking-wide text-muted">
                        <tr>@foreach (['Period', 'Gross', 'PAYE', 'SSNIT', 'Net', 'Status', ''] as $h)<th class="px-3 py-2">{{ $h }}</th>@endforeach</tr>
                    </thead>
                    <tbody>
                        @forelse ($runs as $run)
                            <tr class="border-t border-line">
                                <td class="whitespace-nowrap px-3 py-2.5 font-semibold text-ink">{{ PayrollService::period($run->year, $run->month) }}</td>
                                <td class="px-3 py-2.5">{{ Format::money($run->total_gross) }}</td>
                                <td class="px-3 py-2.5">{{ Format::money($run->total_paye) }}</td>
                                <td class="px-3 py-2.5">{{ Format::money($run->total_ssnit) }}</td>
                                <td class="px-3 py-2.5">{{ Format::money($run->total_net) }}</td>
                                <td class="px-3 py-2.5"><x-badge :status="$run->status" /></td>
                                <td class="px-3 py-2.5 text-right"><x-button small tone="ghost" :href="route('payroll.show', $run)">Open</x-button></td>
                            </tr>
                        @empty
                            <tr><td colspan="7" class="px-3 py-6 text-center text-muted">No payroll has been processed yet. Use “Process month” to calculate the first one.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            <div class="mt-5 grid gap-4 lg:grid-cols-2">
                <x-panel>
                    <div class="mb-2 flex flex-wrap items-center justify-between gap-2">
                        <h2 class="font-semibold text-ink">Bonuses and deductions</h2>
                        <form method="get" class="flex gap-2">
                            <x-select name="month" :options="$months" :selected="$month" class="!w-32 !py-1" onchange="this.form.submit()" />
                            <input name="year" type="number" value="{{ $year }}" class="{{ \App\Support\Ui::CONTROL }} !w-24 !py-1" onchange="this.form.submit()">
                        </form>
                    </div>
                    @forelse ($adjustments as $item)
                        <div class="flex items-center justify-between gap-2 border-t border-line py-2 text-sm first:border-0">
                            <span><span class="font-semibold text-ink">{{ Format::fullName($item->employee) }}</span> · {{ Format::title($item->kind) }} · {{ $item->label }}</span>
                            <span class="flex items-center gap-2">{{ Format::money($item->amount) }}
                                @include('employees._remove', ['route' => route('payroll.adjustments.destroy', $item)])</span>
                        </div>
                    @empty
                        <p class="text-sm text-muted">None for {{ PayrollService::period($year, $month) }}.</p>
                    @endforelse
                </x-panel>
                <x-panel>
                    <h2 class="mb-2 font-semibold text-ink">Loans and advances</h2>
                    @forelse ($loans as $loan)
                        <div class="flex items-center justify-between gap-2 border-t border-line py-2 text-sm first:border-0">
                            <span><span class="font-semibold text-ink">{{ Format::fullName($loan->employee) }}</span> · {{ $loan->type }}
                                <span class="block text-xs text-muted">{{ Format::money($loan->monthly_deduction) }} a month from {{ Format::date($loan->started_on) }}</span></span>
                            <span class="text-right">{{ Format::money($loan->balance) }} left <x-badge :status="$loan->status" class="ml-1" /></span>
                        </div>
                    @empty
                        <p class="text-sm text-muted">No loans or advances.</p>
                    @endforelse
                </x-panel>
            </div>
        @endif

        @if (! $officer || $mine->isNotEmpty())
            <x-panel class="{{ $officer ? 'mt-5' : '' }}">
                <h2 class="mb-3 font-semibold text-ink">{{ $officer ? 'My approved payslips' : 'Approved payslips' }}</h2>
                <div class="grid gap-3">
                    @forelse ($mine as $slip)
                        <div class="flex flex-wrap items-center justify-between gap-3 rounded-xl border border-line bg-cream px-4 py-3">
                            <div>
                                <div class="font-semibold text-ink">{{ PayrollService::period($slip->payrollRun->year, $slip->payrollRun->month) }}</div>
                                <div class="text-sm text-muted">Gross {{ Format::money($slip->gross_pay) }} · PAYE {{ Format::money($slip->paye) }} · Net <span class="font-semibold text-ink">{{ Format::money($slip->net_pay) }}</span></div>
                            </div>
                            <x-button :href="route('payroll.payslip', $slip)">Download</x-button>
                        </div>
                    @empty
                        <p class="text-sm text-muted">No approved payslips yet. A payslip can be downloaded after that month’s payroll is approved.</p>
                    @endforelse
                </div>
            </x-panel>
        @endif

        @if ($officer)
            <x-modal show="open === 'process'" close="open = null" title="Process payroll">
                <form method="post" action="{{ route('payroll.process') }}" class="space-y-3">
                    @csrf
                    <input type="hidden" name="_form" value="process">
                    <div class="grid gap-3 sm:grid-cols-2">
                        <x-field label="Month"><x-select name="month" :options="$months" :selected="now()->month" /></x-field>
                        <x-field label="Year"><x-input type="number" name="year" :value="now()->year" required /></x-field>
                    </div>
                    <x-field label="Notes"><textarea name="notes" rows="2" class="{{ \App\Support\Ui::CONTROL }}">{{ $form === 'process' ? old('notes') : '' }}</textarea></x-field>
                    <p class="text-xs text-muted">Recalculates every active employee’s payslip for the month. Nothing is final until HR approves it.</p>
                    @if ($form === 'process')<x-errors />@endif
                    <x-button type="submit">Calculate</x-button>
                </form>
            </x-modal>

            <x-modal show="open === 'adjustment'" close="open = null" title="Bonus or deduction">
                <form method="post" action="{{ route('payroll.adjustments.store') }}" class="space-y-3">
                    @csrf
                    <input type="hidden" name="_form" value="adjustment">
                    <x-field label="Employee"><x-select name="employee_id" :options="$employeeOptions" placeholder="Choose" required /></x-field>
                    <div class="grid gap-3 sm:grid-cols-2">
                        <x-field label="Month"><x-select name="month" :options="$months" :selected="$month" /></x-field>
                        <x-field label="Year"><x-input type="number" name="year" :value="$year" required /></x-field>
                        <x-field label="Kind"><x-select name="kind" :options="['BONUS', 'COMMISSION', 'ALLOWANCE', 'DEDUCTION']" :labels="true" /></x-field>
                        <x-field label="Amount (GHS)"><x-input type="number" step="0.01" min="0.01" name="amount" required /></x-field>
                    </div>
                    <x-field label="Label"><x-input name="label" placeholder="For example: Year-end bonus" required /></x-field>
                    <label class="flex items-center gap-2 text-sm"><input type="hidden" name="taxable" value="0"><input type="checkbox" name="taxable" value="1" checked> Taxable (allowances only)</label>
                    @if ($form === 'adjustment')<x-errors />@endif
                    <x-button type="submit">Add</x-button>
                </form>
            </x-modal>

            <x-modal show="open === 'loan'" close="open = null" title="Loan or salary advance">
                <form method="post" action="{{ route('payroll.loans.store') }}" class="space-y-3">
                    @csrf
                    <input type="hidden" name="_form" value="loan">
                    <x-field label="Employee"><x-select name="employee_id" :options="$employeeOptions" placeholder="Choose" required /></x-field>
                    <x-field label="Type"><x-input name="type" placeholder="For example: Salary advance" required /></x-field>
                    <div class="grid gap-3 sm:grid-cols-2">
                        <x-field label="Amount lent (GHS)"><x-input type="number" step="0.01" min="0.01" name="principal" required /></x-field>
                        <x-field label="Monthly deduction (GHS)"><x-input type="number" step="0.01" min="0.01" name="monthly_deduction" required /></x-field>
                    </div>
                    <x-field label="Start"><x-input type="date" name="started_on" :value="today()->toDateString()" required /></x-field>
                    <x-field label="Notes"><textarea name="notes" rows="2" class="{{ \App\Support\Ui::CONTROL }}">{{ $form === 'loan' ? old('notes') : '' }}</textarea></x-field>
                    @if ($form === 'loan')<x-errors />@endif
                    <x-button type="submit">Record loan</x-button>
                </form>
            </x-modal>
        @endif
    </div>
</x-layouts.app>
