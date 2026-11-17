<?php

namespace Tests\Feature;

use App\Enums\Role;
use App\Models\AttendanceRecord;
use App\Models\Contract;
use App\Models\Department;
use App\Models\LeaveRequest;
use App\Models\LeaveType;
use App\Models\PayrollRun;
use Database\Seeders\ReferenceDataSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DashboardTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(ReferenceDataSeeder::class);
    }

    public function test_hr_sees_company_figures(): void
    {
        $boss = $this->makeEmployee(['gender' => 'FEMALE', 'date_of_birth' => today()->subYears(30)->addDays(5)->toDateString()]);
        $staff = $this->makeEmployee(['manager_id' => $boss->id, 'status' => 'PROBATION', 'date_of_employment' => today()->subMonth()->toDateString()]);
        $finance = Department::create(['code' => 'FIN', 'name' => 'Finance']);
        $this->makeEmployee(['department_id' => $finance->id, 'status' => 'RESIGNED']);
        Contract::create(['employee_id' => $staff->id, 'type' => 'Fixed-term', 'title' => 'Fixed-term', 'start_date' => '2026-01-01', 'end_date' => today()->addDays(20)->toDateString(), 'status' => 'ACTIVE']);
        LeaveRequest::create(['employee_id' => $staff->id, 'leave_type_id' => LeaveType::first()->id, 'start_date' => today()->toDateString(), 'end_date' => today()->addDay()->toDateString(), 'days' => 2, 'status' => 'APPROVED']);
        LeaveRequest::create(['employee_id' => $boss->id, 'leave_type_id' => LeaveType::first()->id, 'start_date' => today()->addMonth()->toDateString(), 'end_date' => today()->addMonth()->toDateString(), 'days' => 1, 'status' => 'PENDING_HR']);
        PayrollRun::create(['year' => 2026, 'month' => 11, 'status' => 'PENDING_APPROVAL']);
        AttendanceRecord::create(['employee_id' => $staff->id, 'date' => today()->subDays(2)->toDateString(), 'status' => 'PRESENT']);

        $response = $this->actingAs($this->makeUser(Role::HR_MANAGER))->get('/');

        $response->assertOk()->assertSee('Dashboard')->assertSee('Active headcount by department');
        $response->assertViewHas('totals', fn ($t) => $t['employees'] === 3 && $t['active'] === 1 && $t['probation'] === 1 && $t['inactive'] === 1
            && $t['on_leave'] === 1 && $t['contracts_expiring'] === 1 && $t['new_hires'] === 1 && $t['attendance_rate'] !== null);
        $response->assertViewHas('byDepartment', ['Operations' => 2]);
        $response->assertViewHas('birthdays', fn ($b) => $b->count() === 1 && $b[0]['days'] === 5);
        $response->assertViewHas('pending', fn ($p) => collect($p)->firstWhere(0, 'Leave requests')[1] === 1 && collect($p)->firstWhere(0, 'Payroll runs')[1] === 1);
    }

    public function test_managers_only_see_their_people_and_filters_apply(): void
    {
        $boss = $this->makeEmployee();
        $this->makeEmployee(['manager_id' => $boss->id]);
        $finance = Department::create(['code' => 'FIN', 'name' => 'Finance']);
        $this->makeEmployee(['department_id' => $finance->id]);
        $supervisor = $this->makeUser(Role::SUPERVISOR, ['employee_id' => $boss->id]);

        $this->actingAs($supervisor)->get('/')->assertOk()
            ->assertViewHas('totals', fn ($t) => $t['employees'] === 2)
            ->assertViewHas('pending', fn ($p) => ! collect($p)->firstWhere(0, 'Payroll runs') && ! collect($p)->firstWhere(0, 'Recruitment requests'));

        $this->actingAs($this->makeUser(Role::HR_ADMIN))->get("/?department_id={$finance->id}")
            ->assertViewHas('totals', fn ($t) => $t['employees'] === 1)
            ->assertViewHas('byDepartment', ['Finance' => 1]);
    }

    public function test_attendance_shows_a_dash_until_any_is_recorded(): void
    {
        $this->makeEmployee();
        $this->actingAs($this->makeUser(Role::HR_ADMIN))->get('/')
            ->assertViewHas('totals', fn ($t) => $t['attendance_rate'] === null)
            ->assertSee('No attendance has been recorded in the last 30 days.');
    }

    public function test_employees_get_their_self_service_home(): void
    {
        $employee = $this->makeEmployee(['first_name' => 'Kwame']);
        $user = $this->makeUser(Role::EMPLOYEE, ['employee_id' => $employee->id, 'first_name' => 'Kwame']);

        $this->actingAs($user)->get('/')->assertOk()->assertSee('Hello, Kwame')->assertSee($employee->employee_no)
            ->assertSee('My payslips')->assertDontSee('Active headcount by department');

        $unlinked = $this->makeUser(Role::EMPLOYEE);
        $this->actingAs($unlinked)->get('/')->assertOk()->assertSee('not linked to an employee record');
    }
}
