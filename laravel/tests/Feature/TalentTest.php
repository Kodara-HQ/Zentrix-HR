<?php

namespace Tests\Feature;

use App\Enums\Role;
use App\Models\Appraisal;
use App\Models\Certification;
use App\Models\PerformanceCycle;
use App\Models\TrainingNeed;
use App\Models\TrainingProgram;
use Database\Seeders\ReferenceDataSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TalentTest extends TestCase
{
    use RefreshDatabase;

    private $boss;

    private $bossUser;

    private $staff;

    private $staffUser;

    private $hr;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(ReferenceDataSeeder::class);
        $this->boss = $this->makeEmployee();
        $this->bossUser = $this->makeUser(Role::SUPERVISOR, ['employee_id' => $this->boss->id]);
        $this->staff = $this->makeEmployee(['manager_id' => $this->boss->id, 'first_name' => 'Kwame', 'last_name' => 'Boateng']);
        $this->staffUser = $this->makeUser(Role::EMPLOYEE, ['employee_id' => $this->staff->id]);
        $this->hr = $this->makeUser(Role::HR_MANAGER);
    }

    public function test_a_review_from_opening_to_acknowledgement(): void
    {
        $this->actingAs($this->hr)->post('/performance/cycles', ['name' => '2026 annual', 'period_type' => 'ANNUAL', 'start_date' => '2026-01-01', 'end_date' => '2026-12-31', 'status' => 'OPEN']);
        $cycle = PerformanceCycle::first();

        $this->actingAs($this->bossUser)->post('/performance/appraisals', ['cycle_id' => $cycle->id, 'employee_id' => $this->staff->id])->assertSessionHasNoErrors();
        $this->actingAs($this->bossUser)->post('/performance/appraisals', ['cycle_id' => $cycle->id, 'employee_id' => $this->staff->id])
            ->assertSessionHasErrors(['employee_id' => 'This employee already has a review in that cycle.']);
        $appraisal = Appraisal::first();
        $this->assertSame(1, $this->staffUser->notifications()->count());

        $this->actingAs($this->staffUser)->get('/performance')->assertOk()->assertSee('My reviews')->assertSee('Self-assessment');
        $this->actingAs($this->staffUser)->post("/performance/appraisals/{$appraisal->id}/self", ['self_rating' => 4, 'self_comments' => 'Hit every route target.']);
        $this->assertSame('SELF_SUBMITTED', $appraisal->fresh()->status->value);

        $this->actingAs($this->staffUser)->post("/performance/appraisals/{$appraisal->id}/review", ['supervisor_rating' => 5])->assertForbidden();
        $this->actingAs($this->bossUser)->post("/performance/appraisals/{$appraisal->id}/review", ['supervisor_rating' => 4, 'final_rating' => 4, 'supervisor_comments' => 'Reliable and safe.', 'recommendation' => 'Retain']);
        $this->assertSame('REVIEWED', $appraisal->fresh()->status->value);

        $this->actingAs($this->staffUser)->post("/performance/appraisals/{$appraisal->id}/self", ['step' => 'acknowledge']);
        $this->assertSame('ACKNOWLEDGED', $appraisal->fresh()->status->value);
    }

    public function test_reviewers_only_manage_their_own_team(): void
    {
        $cycle = PerformanceCycle::create(['name' => '2026', 'period_type' => 'ANNUAL', 'start_date' => '2026-01-01', 'end_date' => '2026-12-31', 'status' => 'OPEN']);
        $stranger = $this->makeEmployee();
        $this->actingAs($this->bossUser)->post('/performance/appraisals', ['cycle_id' => $cycle->id, 'employee_id' => $stranger->id])->assertForbidden();
        $this->actingAs($this->bossUser)->post('/performance/appraisals', ['cycle_id' => $cycle->id, 'employee_id' => $this->boss->id])->assertForbidden();
        $this->actingAs($this->bossUser)->post('/performance/objectives', ['cycle_id' => $cycle->id, 'level' => 'INDIVIDUAL', 'employee_id' => $stranger->id, 'title' => 'Sales', 'kpi' => 'GHS', 'target' => '10k', 'weight' => 20])->assertForbidden();
        $this->actingAs($this->bossUser)->post('/performance/objectives', ['cycle_id' => $cycle->id, 'level' => 'INDIVIDUAL', 'employee_id' => $this->staff->id, 'title' => 'On-time deliveries', 'kpi' => '% on time', 'target' => '95%', 'weight' => 40])->assertSessionHasNoErrors();
        $this->actingAs($this->staffUser)->get('/performance')->assertSee('On-time deliveries');
        $this->actingAs($this->makeUser(Role::PAYROLL_OFFICER))->get('/performance')->assertForbidden();
    }

    public function test_training_programmes_enrolment_needs_and_certificates(): void
    {
        $this->actingAs($this->hr)->post('/training/programs', ['title' => 'Defensive driving', 'provider_name' => 'Ghana Road Safety', 'start_date' => '2026-12-01', 'end_date' => '2026-12-02', 'cost' => 1200, 'capacity' => 1])->assertSessionHasNoErrors();
        $program = TrainingProgram::first();
        $this->assertSame('Ghana Road Safety', $program->provider->name);

        $manager = $this->makeUser(Role::DEPARTMENT_MANAGER, ['department_id' => $this->staff->department_id]);
        $this->actingAs($manager)->post("/training/programs/{$program->id}/enrol", ['employee_id' => $this->staff->id])->assertSessionHasNoErrors();
        $this->assertSame(1, $this->staffUser->notifications()->where('type', 'TRAINING')->count());
        $this->actingAs($this->hr)->post("/training/programs/{$program->id}/enrol", ['employee_id' => $this->staff->id])->assertSessionHasErrors('employee_id');
        $this->actingAs($this->hr)->post("/training/programs/{$program->id}/enrol", ['employee_id' => $this->boss->id])->assertStatus(422);

        $enrollment = $program->enrollments()->first();
        $this->actingAs($this->hr)->patch("/training/enrollments/{$enrollment->id}", ['status' => 'COMPLETED', 'score' => 88]);
        $this->assertTrue($enrollment->fresh()->attended);

        $this->actingAs($this->staffUser)->post('/training/needs', ['employee_id' => $this->boss->id, 'skill' => 'Forklift', 'priority' => 'High']);
        $this->assertSame($this->staff->id, TrainingNeed::first()->employee_id, 'Employees raise needs for themselves');
        $this->actingAs($this->staffUser)->post('/training/certificates', ['employee_id' => $this->boss->id, 'name' => 'Class C licence', 'issuer' => 'DVLA', 'expires_on' => '2027-03-01']);
        $this->assertSame($this->staff->id, Certification::first()->employee_id);

        $this->actingAs($this->staffUser)->get('/training')->assertOk()->assertSee('Defensive driving')->assertSee('Class C licence')->assertDontSee('Add programme');
        $this->actingAs($this->staffUser)->post('/training/programs', ['title' => 'x'])->assertForbidden();
    }
}
