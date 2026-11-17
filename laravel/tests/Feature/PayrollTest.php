<?php

namespace Tests\Feature;

use App\Enums\Role;
use App\Models\Loan;
use App\Models\PayAdjustment;
use App\Models\PayrollRun;
use App\Models\Payslip;
use Database\Seeders\ReferenceDataSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PayrollTest extends TestCase
{
    use RefreshDatabase;

    private $staff;

    private $staffUser;

    private $officer;

    private $hr;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(ReferenceDataSeeder::class);
        $this->staff = $this->makeEmployee(['basic_salary' => 5000, 'date_of_employment' => '2024-01-02']);
        $this->staffUser = $this->makeUser(Role::EMPLOYEE, ['employee_id' => $this->staff->id]);
        $this->officer = $this->makeUser(Role::PAYROLL_OFFICER);
        $this->hr = $this->makeUser(Role::HR_MANAGER);
    }

    private function process(int $month = 11, $as = null)
    {
        return $this->actingAs($as ?? $this->officer)->post('/payroll/process', ['year' => 2026, 'month' => $month]);
    }

    private function slip(int $month = 11): Payslip
    {
        return Payslip::where('employee_id', $this->staff->id)->whereHas('payrollRun', fn ($q) => $q->where(['year' => 2026, 'month' => $month]))->first();
    }

    public function test_processing_a_month_matches_the_node_figures_and_waits_for_approval(): void
    {
        $this->process()->assertRedirect();

        $run = PayrollRun::first();
        $this->assertSame('PENDING_APPROVAL', $run->status->value);
        $slip = $this->slip();
        $this->assertEquals(275, $slip->employee_ssnit);
        $this->assertEquals(785.75, $slip->paye);
        $this->assertEquals(3939.25, $slip->net_pay);
        $this->assertEquals(3939.25, $run->total_net);
        $this->assertEquals(925, $run->total_ssnit, 'Employee 275 plus employer 650');
        $this->assertSame(1, $this->hr->notifications()->where('title', 'Payroll ready for approval')->count());
    }

    public function test_older_months_use_the_earlier_tax_bands(): void
    {
        $this->process(8);
        // Before September 2026: 490 at 0%, 110 at 5%, 130 at 10%, the rest of 4725 at 17.5%.
        $this->assertEquals(round(110 * 0.05 + 130 * 0.1 + 3000 * 0.175 + (4725 - 3730) * 0.25, 2), $this->slip(8)->paye);
    }

    public function test_pay_items_adjustments_and_starters_are_counted(): void
    {
        $component = \App\Models\PayrollComponent::where('code', 'TRANSPORT')->first();
        $this->staff->payItems()->create(['component_id' => $component->id, 'amount' => 400]);
        $this->actingAs($this->officer)->post('/payroll/adjustments', ['employee_id' => $this->staff->id, 'year' => 2026, 'month' => 11, 'kind' => 'BONUS', 'label' => 'Target bonus', 'amount' => 1000]);

        // Hired on Monday 16 November 2026: 11 of the month's 21 working days.
        $starter = $this->makeEmployee(['basic_salary' => 4200, 'date_of_employment' => '2026-11-16']);
        $this->process();

        $slip = $this->slip();
        $this->assertEquals(6400, $slip->gross_pay);
        $this->assertEquals(400, $slip->allowances);
        $this->assertEquals(1000, $slip->bonus);
        $this->assertCount(2, $slip->lines);
        $this->assertEquals(round(4200 * 11 / 21, 2), Payslip::where('employee_id', $starter->id)->value('basic_salary'));
    }

    public function test_an_adjustment_recalculates_a_pending_month(): void
    {
        $this->process();
        $this->assertEquals(0, $this->slip()->bonus);

        $this->actingAs($this->officer)->post('/payroll/adjustments', ['employee_id' => $this->staff->id, 'year' => 2026, 'month' => 11, 'kind' => 'BONUS', 'label' => 'Spot bonus', 'amount' => 500]);
        $this->assertEquals(500, $this->slip()->bonus);

        $this->actingAs($this->officer)->delete('/payroll/adjustments/'.PayAdjustment::first()->id);
        $this->assertEquals(0, $this->slip()->bonus);
    }

    public function test_approval_reduces_loans_and_releases_payslips(): void
    {
        $this->actingAs($this->officer)->post('/payroll/loans', ['employee_id' => $this->staff->id, 'type' => 'Salary advance', 'principal' => 500, 'monthly_deduction' => 300, 'started_on' => '2026-11-01']);
        $this->process();
        $slip = $this->slip();
        $this->assertEquals(300, $slip->loan_deduction);

        $this->actingAs($this->staffUser)->get("/payroll/payslips/{$slip->id}")->assertForbidden();
        $this->actingAs($this->staffUser)->get('/payroll')->assertSee('No approved payslips yet.');

        $run = PayrollRun::first();
        $this->actingAs($this->officer)->post("/payroll/runs/{$run->id}/decision", ['decision' => 'APPROVED'])->assertForbidden();
        $this->actingAs($this->hr)->post("/payroll/runs/{$run->id}/decision", ['decision' => 'APPROVED'])->assertRedirect();

        $this->assertSame('APPROVED', $run->fresh()->status->value);
        $this->assertEquals(200, Loan::first()->balance);
        $this->assertSame(1, $this->staffUser->notifications()->where('title', 'Payslip available')->count());

        $this->actingAs($this->staffUser)->get('/payroll')->assertSee('November 2026')->assertSee('3,639.25');
        $response = $this->actingAs($this->staffUser)->get("/payroll/payslips/{$slip->id}");
        $response->assertOk()->assertHeader('content-type', 'application/pdf');
        $this->assertStringStartsWith('%PDF', $response->getContent());

        $other = $this->makeUser(Role::EMPLOYEE, ['employee_id' => $this->makeEmployee()->id]);
        $this->actingAs($other)->get("/payroll/payslips/{$slip->id}")->assertForbidden();

        // December takes the last 200, then the loan closes.
        $this->process(12);
        $this->actingAs($this->hr)->post('/payroll/runs/'.PayrollRun::where('month', 12)->value('id').'/decision', ['decision' => 'APPROVED']);
        $this->assertEquals(200, $this->slip(12)->loan_deduction);
        $this->assertSame('CLOSED', Loan::first()->status->value);
    }

    public function test_an_approved_month_is_final(): void
    {
        $this->process();
        $run = PayrollRun::first();
        $this->actingAs($this->hr)->post("/payroll/runs/{$run->id}/decision", ['decision' => 'APPROVED']);

        $this->process()->assertSessionHasErrors('month');
        $this->actingAs($this->officer)->post('/payroll/adjustments', ['employee_id' => $this->staff->id, 'year' => 2026, 'month' => 11, 'kind' => 'BONUS', 'label' => 'Late bonus', 'amount' => 100])
            ->assertSessionHasErrors(['month' => 'November 2026 is already approved, so its payslips are final. Add this to the next month instead.']);
        $this->assertSame(0, PayAdjustment::count());
        $this->assertEquals(3939.25, $this->slip()->net_pay);
    }

    public function test_a_cancelled_run_can_be_processed_again(): void
    {
        $this->process();
        $run = PayrollRun::first();
        $this->actingAs($this->hr)->post("/payroll/runs/{$run->id}/decision", ['decision' => 'CANCELLED', 'note' => 'Wrong rates']);
        $this->assertSame('CANCELLED', $run->fresh()->status->value);

        $this->process()->assertSessionHasNoErrors();
        $this->assertSame('PENDING_APPROVAL', $run->fresh()->status->value);
    }

    public function test_access(): void
    {
        $supervisor = $this->makeUser(Role::SUPERVISOR);
        $this->actingAs($supervisor)->post('/payroll/process', ['year' => 2026, 'month' => 11])->assertForbidden();
        $this->actingAs($supervisor)->get('/payroll')->assertOk()->assertDontSee('Process month');
        $this->process();
        $this->actingAs($supervisor)->get('/payroll/runs/'.PayrollRun::first()->id)->assertForbidden();
        $this->actingAs($this->officer)->get('/payroll/runs/'.PayrollRun::first()->id)->assertOk()->assertSee('3,939.25')->assertDontSee('Approve payroll');
        $this->actingAs($this->hr)->get('/payroll/runs/'.PayrollRun::first()->id)->assertSee('Approve payroll');
    }

    public function test_the_monthly_command_creates_a_pending_run_once(): void
    {
        $this->artisan('hr:payroll-month', ['--year' => 2026, '--month' => 11])->assertSuccessful();
        $this->artisan('hr:payroll-month', ['--year' => 2026, '--month' => 11])->expectsOutputToContain('already exists')->assertSuccessful();

        $this->assertSame(1, PayrollRun::count());
        $this->assertSame('PENDING_APPROVAL', PayrollRun::first()->status->value);
    }
}
