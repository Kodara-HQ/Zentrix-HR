<?php

namespace Tests\Feature;

use App\Enums\Role;
use App\Models\Contract;
use App\Models\Department;
use App\Models\ManpowerBudget;
use App\Models\SuccessionPlan;
use App\Models\Vacancy;
use Database\Seeders\ReferenceDataSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class InsightsTest extends TestCase
{
    use RefreshDatabase;

    private $hr;

    private $ops;

    private $fin;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(ReferenceDataSeeder::class);
        $this->hr = $this->makeUser(Role::HR_MANAGER);
        $this->ops = $this->makeEmployee(['first_name' => 'Kofi', 'last_name' => 'Asante']);
        $this->fin = $this->makeEmployee(['first_name' => 'Adwoa', 'last_name' => 'Frimpong', 'department_id' => Department::create(['code' => 'FIN', 'name' => 'Finance'])->id]);
    }

    public function test_reports_only_cover_the_people_you_may_see(): void
    {
        $supervisor = $this->makeUser(Role::SUPERVISOR, ['employee_id' => $this->makeEmployee(['first_name' => 'Ama'])->id]);
        $this->ops->update(['manager_id' => $supervisor->employee_id]);

        $this->actingAs($this->hr)->get('/reports?report=employees')->assertOk()->assertSee('Kofi Asante')->assertSee('Adwoa Frimpong');
        $this->actingAs($supervisor)->get('/reports?report=employees')->assertOk()->assertSee('Kofi Asante')->assertDontSee('Adwoa Frimpong');
        $this->actingAs($supervisor)->get('/reports?report=payroll')->assertForbidden();
        $this->actingAs($supervisor)->get('/reports?report=discipline')->assertForbidden();
        $this->actingAs($supervisor)->get('/reports')->assertDontSee('>Payroll<', false);

        $csv = $this->actingAs($this->hr)->get('/reports?report=headcount&format=csv');
        $csv->assertOk()->assertHeader('content-type', 'text/csv; charset=UTF-8');
        $this->assertStringContainsString('Department,Headcount', $csv->streamedContent());
        $this->assertStringContainsString('Finance,1', $csv->streamedContent());

        $pdf = $this->actingAs($this->hr)->get('/reports?report=employees&format=pdf');
        $pdf->assertOk()->assertHeader('content-type', 'application/pdf');
        $this->actingAs($this->makeUser(Role::EMPLOYEE, ['employee_id' => $this->ops->id]))->get('/reports')->assertRedirect('/');
    }

    public function test_every_report_runs(): void
    {
        $admin = $this->makeUser(Role::SUPER_ADMIN);
        foreach (array_keys(\App\Services\Reports::CATALOGUE) as $name) {
            $this->actingAs($admin)->get("/reports?report={$name}")->assertOk();
        }
    }

    public function test_contracts_list_and_update(): void
    {
        $contract = Contract::create(['employee_id' => $this->ops->id, 'type' => 'Fixed-term contract', 'title' => 'Fixed-term — Kofi', 'start_date' => '2026-01-01', 'end_date' => today()->addDays(20)->toDateString(), 'status' => 'ACTIVE']);
        Contract::create(['employee_id' => $this->fin->id, 'type' => 'Employment contract', 'title' => 'Open-ended — Adwoa', 'start_date' => '2020-01-01', 'status' => 'ACTIVE']);

        $this->actingAs($this->hr)->get('/contracts?show=expiring')->assertOk()->assertSee('Fixed-term — Kofi')->assertDontSee('Open-ended — Adwoa');
        $this->actingAs($this->hr)->patch("/contracts/{$contract->id}", ['status' => 'RENEWED', 'end_date' => '2027-12-31']);
        $this->assertSame(['RENEWED', '2027-12-31'], [$contract->fresh()->status, $contract->fresh()->end_date->toDateString()]);
        $this->actingAs($this->hr)->patch("/contracts/{$contract->id}", ['status' => 'ACTIVE', 'end_date' => '2025-01-01'])->assertSessionHasErrors('end_date');

        $manager = $this->makeUser(Role::DEPARTMENT_MANAGER, ['department_id' => $this->fin->department_id]);
        $this->actingAs($manager)->get('/contracts')->assertSee('Open-ended — Adwoa')->assertDontSee('Fixed-term — Kofi');
        $this->actingAs($manager)->patch("/contracts/{$contract->id}", ['status' => 'ENDED'])->assertForbidden();
    }

    public function test_workforce_gap_and_vacancies_are_separate(): void
    {
        $this->actingAs($this->hr)->post('/workforce/budgets', ['year' => now()->year, 'department_id' => $this->fin->department_id, 'approved_headcount' => 3, 'estimated_cost' => 200000]);
        $this->actingAs($this->hr)->post('/workforce/budgets', ['year' => now()->year, 'department_id' => $this->fin->department_id, 'approved_headcount' => 4, 'estimated_cost' => 260000]);
        $this->assertSame(1, ManpowerBudget::count(), 'Saving the same line again updates it');
        Vacancy::create(['title' => 'Accountant', 'department_id' => $this->fin->department_id, 'job_title_id' => $this->fin->job_title_id, 'location_id' => $this->fin->location_id, 'description' => 'x', 'requirements' => 'x', 'openings' => 2, 'filled' => 0, 'status' => 'OPEN']);

        $this->actingAs($this->hr)->get('/workforce')->assertOk()
            ->assertViewHas('rows', fn ($rows) => ($r = $rows->first(fn ($row) => $row['department']->name === 'Finance')) && $r['current'] === 1 && $r['approved'] === 4 && $r['gap'] === 3 && $r['open'] === 2)
            ->assertSee('3 to hire');

        $this->actingAs($this->hr)->post('/workforce/plans', ['position_title' => 'Finance Manager', 'incumbent_id' => $this->fin->id, 'readiness' => 'Ready now', 'critical' => '1']);
        $this->assertTrue(SuccessionPlan::first()->critical);

        $manager = $this->makeUser(Role::DEPARTMENT_MANAGER, ['department_id' => $this->fin->department_id]);
        $this->actingAs($manager)->get('/workforce')->assertViewHas('rows', fn ($rows) => $rows->count() === 1);
        $this->actingAs($manager)->post('/workforce/budgets', [])->assertForbidden();
    }
}
