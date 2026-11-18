<?php

namespace Tests\Feature;

use App\Enums\Role;
use App\Models\Applicant;
use App\Models\Department;
use App\Models\Employee;
use App\Models\JobOffer;
use App\Models\JobTitle;
use App\Models\ManpowerRequisition;
use App\Models\User;
use App\Models\Vacancy;
use App\Models\WorkLocation;
use Database\Seeders\ReferenceDataSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class RecruitmentTest extends TestCase
{
    use RefreshDatabase;

    private $recruiter;

    private $hrManager;

    private array $ids;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake();
        $this->seed(ReferenceDataSeeder::class);
        $this->recruiter = $this->makeUser(Role::RECRUITMENT_OFFICER);
        $this->hrManager = $this->makeUser(Role::HR_MANAGER);
        $department = Department::create(['code' => 'OPS', 'name' => 'Operations']);
        $this->ids = [
            'department_id' => $department->id,
            'job_title_id' => JobTitle::create(['name' => 'Driver', 'department_id' => $department->id])->id,
            'location_id' => WorkLocation::create(['name' => 'Tema Port', 'branch' => 'Tema', 'city' => 'Tema', 'region' => 'Greater Accra'])->id,
        ];
    }

    private function vacancy(array $overrides = []): Vacancy
    {
        $this->actingAs($this->recruiter)->post('/recruitment/vacancies', $overrides + $this->ids + [
            'title' => 'Driver', 'description' => 'Drive the delivery van.', 'requirements' => 'Valid licence.', 'openings' => 1, 'status' => 'OPEN', 'advertising_cost' => 300,
        ])->assertSessionHasNoErrors();

        return Vacancy::latest()->first();
    }

    public function test_requisitions_are_approved_once_by_the_hr_manager(): void
    {
        $manager = $this->makeUser(Role::DEPARTMENT_MANAGER);
        $this->actingAs($manager)->post('/recruitment/requisitions', $this->ids + ['headcount' => 2, 'justification' => 'Two new delivery routes.'])->assertSessionHasNoErrors();
        $requisition = ManpowerRequisition::first();
        $this->assertSame(1, $this->hrManager->notifications()->count());

        $this->actingAs($this->recruiter)->post("/recruitment/requisitions/{$requisition->id}/decision", ['decision' => 'APPROVED'])->assertForbidden();
        $this->actingAs($this->hrManager)->post("/recruitment/requisitions/{$requisition->id}/decision", ['decision' => 'APPROVED']);
        $this->assertSame('APPROVED', $requisition->fresh()->status->value);
        $this->assertSame(1, $manager->notifications()->count());
        $this->actingAs($this->hrManager)->post("/recruitment/requisitions/{$requisition->id}/decision", ['decision' => 'REJECTED'])->assertStatus(422);

        $pending = ManpowerRequisition::create($this->ids + ['headcount' => 1, 'justification' => 'Not yet approved', 'status' => 'PENDING']);
        $this->actingAs($this->recruiter)->post('/recruitment/vacancies', $this->ids + ['requisition_id' => $pending->id, 'title' => 'Driver', 'description' => 'xxxxx', 'requirements' => 'xxxxx', 'openings' => 1, 'status' => 'OPEN'])
            ->assertSessionHasErrors(['requisition_id' => 'Open a vacancy from an approved requisition.']);
        $this->vacancy(['requisition_id' => $requisition->id]);
    }

    public function test_from_applicant_to_employee(): void
    {
        $vacancy = $this->vacancy();
        $this->actingAs($this->recruiter)->post('/recruitment/applicants', ['vacancy_id' => $vacancy->id, 'first_name' => 'Yaw', 'last_name' => 'Darko', 'email' => 'Yaw.Darko@mail.test', 'phone' => '0244000000', 'screening_score' => 72]);
        $applicant = Applicant::first();
        $this->assertSame('yaw.darko@mail.test', $applicant->email);

        $this->actingAs($this->recruiter)->post("/recruitment/applicants/{$applicant->id}/interviews", ['scheduled_at' => '2026-11-25 10:00', 'mode' => 'In person', 'interviewer_id' => $this->hrManager->id]);
        $this->assertSame('INTERVIEW', $applicant->fresh()->status->value);
        $this->assertSame(1, $this->hrManager->notifications()->where('title', 'Interview scheduled')->count());
        $interview = $applicant->interviews()->first();
        $this->actingAs($this->recruiter)->patch("/recruitment/interviews/{$interview->id}", ['score' => 81, 'recommendation' => 'Hire']);
        $this->assertEquals(81, $interview->fresh()->score);

        $this->actingAs($this->recruiter)->post("/recruitment/applicants/{$applicant->id}/offers", ['salary' => 3400, 'start_date' => '2026-12-01']);
        $offer = JobOffer::first();
        $this->assertSame('OFFERED', $applicant->fresh()->status->value);

        $this->actingAs($this->recruiter)->post("/recruitment/offers/{$offer->id}/accept", ['gender' => 'MALE'])->assertSessionHasErrors('date_of_birth');
        $response = $this->actingAs($this->recruiter)->post("/recruitment/offers/{$offer->id}/accept", ['gender' => 'MALE', 'date_of_birth' => '1987-03-14']);

        $employee = Employee::firstWhere('email', 'yaw.darko@mail.test');
        $response->assertRedirect("/employees/{$employee->id}")->assertSessionHas('status', fn ($s) => str_contains($s, 'Temporary password'));
        $this->assertSame(['MALE', '1987-03-14', 'PROBATION', '2027-06-01'], [$employee->gender->value, $employee->date_of_birth->toDateString(), $employee->status->value, $employee->probation_end_date->toDateString()]);
        $this->assertEquals(3400, $employee->basic_salary);
        $this->assertSame('ACCEPTED', $offer->fresh()->status->value);
        $this->assertSame('HIRED', $applicant->fresh()->status->value);
        $this->assertSame($employee->id, $applicant->fresh()->employee_id);
        $this->assertSame(['FILLED', 1], [$vacancy->fresh()->status->value, $vacancy->fresh()->filled]);
        $this->assertTrue(User::where('employee_id', $employee->id)->exists());
        $this->assertSame(1, $employee->letters()->count());

        $this->actingAs($this->recruiter)->post("/recruitment/offers/{$offer->id}/accept", ['gender' => 'MALE', 'date_of_birth' => '1987-03-14'])->assertStatus(422);
        $this->actingAs($this->recruiter)->get('/recruitment?tab=metrics')->assertOk()->assertSee('GH₵300.00');
    }

    public function test_an_applicant_whose_email_is_taken_cannot_be_hired_until_fixed(): void
    {
        $existing = $this->makeEmployee(['email' => 'taken@mail.test']);
        $vacancy = $this->vacancy();
        $applicant = Applicant::create(['vacancy_id' => $vacancy->id, 'first_name' => 'Esi', 'last_name' => 'Mensah', 'email' => 'taken@mail.test', 'phone' => '0244000001', 'status' => 'OFFERED']);
        $offer = $applicant->offers()->create(['salary' => 3000, 'start_date' => '2026-12-01', 'status' => 'SENT']);

        $this->actingAs($this->recruiter)->post("/recruitment/offers/{$offer->id}/accept", ['gender' => 'FEMALE', 'date_of_birth' => '1995-01-01'])
            ->assertSessionHasErrors('date_of_birth');
        $this->assertSame(1, Employee::count());
        $this->assertNotNull($existing);
    }

    public function test_access(): void
    {
        $this->actingAs($this->makeUser(Role::PAYROLL_OFFICER))->get('/recruitment')->assertForbidden();
        $manager = $this->makeUser(Role::DEPARTMENT_MANAGER);
        $this->actingAs($manager)->get('/recruitment')->assertOk()->assertSee('New requisition')->assertDontSee('Register applicant');
        $this->actingAs($manager)->post('/recruitment/vacancies', [])->assertForbidden();
        $this->actingAs($this->recruiter)->get('/recruitment?tab=applicants')->assertOk()->assertSee('Register applicant');
    }
}
