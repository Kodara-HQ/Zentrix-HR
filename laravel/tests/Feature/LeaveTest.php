<?php

namespace Tests\Feature;

use App\Enums\Role;
use App\Models\Holiday;
use App\Models\LeaveBalance;
use App\Models\LeaveRequest;
use App\Models\LeaveType;
use App\Models\WorkflowConfig;
use Database\Seeders\ReferenceDataSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LeaveTest extends TestCase
{
    use RefreshDatabase;

    private $staff;

    private $staffUser;

    private $boss;

    private $bossUser;

    private $hr;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(ReferenceDataSeeder::class);
        $this->boss = $this->makeEmployee(['first_name' => 'Ama', 'last_name' => 'Adjei', 'gender' => 'FEMALE']);
        $this->bossUser = $this->makeUser(Role::SUPERVISOR, ['employee_id' => $this->boss->id, 'department_id' => $this->boss->department_id]);
        $this->staff = $this->makeEmployee(['first_name' => 'Kwame', 'last_name' => 'Boateng', 'manager_id' => $this->boss->id]);
        $this->staffUser = $this->makeUser(Role::EMPLOYEE, ['employee_id' => $this->staff->id]);
        $this->hr = $this->makeUser(Role::HR_MANAGER);
    }

    private function type(string $code): LeaveType
    {
        return LeaveType::where('code', $code)->first();
    }

    private function balance(string $code, ?int $year = 2026): LeaveBalance
    {
        return LeaveBalance::where(['employee_id' => $this->staff->id, 'leave_type_id' => $this->type($code)->id, 'year' => $year])->first();
    }

    private function apply(array $data, $as = null)
    {
        return $this->actingAs($as ?? $this->staffUser)->post('/leave', $data + ['leave_type_id' => $this->type('ANNUAL')->id, '_form' => 'apply']);
    }

    public function test_working_days_skip_weekends_and_holidays_but_calendar_days_do_not(): void
    {
        Holiday::create(['name' => 'Founders Day', 'date' => '2020-08-04', 'recurring' => true]);

        // Mon 3 Aug 2026 – Fri 14 Aug 2026: 10 weekdays, minus the recurring holiday on Tue 4 Aug.
        $this->apply(['start_date' => '2026-08-03', 'end_date' => '2026-08-14'])->assertSessionHasNoErrors();
        $request = LeaveRequest::first();
        $this->assertEquals(9, $request->days);
        $this->assertSame('PENDING_MANAGER', $request->status->value);
        $this->assertEquals(9, $this->balance('ANNUAL')->pending);

        $this->assertEquals(14, \App\Support\WorkCalendar::countDays(\Carbon\CarbonImmutable::parse('2026-08-03'), \Carbon\CarbonImmutable::parse('2026-08-16'), 'CALENDAR_DAYS'));
    }

    public function test_rejects_bad_applications(): void
    {
        $this->apply(['start_date' => '2026-08-10', 'end_date' => '2026-08-07'])->assertSessionHasErrors(['end_date' => 'The end date is before the start date.']);
        $this->apply(['start_date' => '2026-08-08', 'end_date' => '2026-08-09'])->assertSessionHasErrors(['end_date' => 'The selected dates contain no leave days.']);
        $this->apply(['start_date' => '2026-08-03', 'end_date' => '2026-09-30'])->assertSessionHasErrors(['end_date' => 'Only 25 days remain for Annual leave.']);
        $this->apply(['leave_type_id' => $this->type('MATERNITY')->id, 'start_date' => '2026-08-03', 'end_date' => '2026-08-04', 'reason' => 'x'])
            ->assertSessionHasErrors(['leave_type_id' => 'Maternity leave does not apply to this employee.']);
        $this->apply(['leave_type_id' => $this->type('SICK')->id, 'start_date' => '2026-08-03', 'end_date' => '2026-08-03'])
            ->assertSessionHasErrors(['reason' => 'Give a reason for Sick leave.']);

        $this->apply(['start_date' => '2026-08-03', 'end_date' => '2026-08-05']);
        $this->apply(['start_date' => '2026-08-05', 'end_date' => '2026-08-06'])->assertSessionHasErrors(['start_date' => 'These dates overlap an existing leave request.']);
        $this->assertSame(1, LeaveRequest::count());
    }

    public function test_manager_then_hr_approval_moves_days_from_pending_to_used(): void
    {
        $this->apply(['start_date' => '2026-08-03', 'end_date' => '2026-08-05']);
        $request = LeaveRequest::first();

        $this->actingAs($this->hr)->post("/leave/{$request->id}/decision", ['decision' => 'APPROVED']);
        $this->assertSame('APPROVED', $request->fresh()->status->value, 'HR may approve at the manager step and finish it');
        $this->assertEquals(0, $this->balance('ANNUAL')->pending);
        $this->assertEquals(3, $this->balance('ANNUAL')->used);
        $this->assertSame(1, $this->staffUser->notifications()->where('title', 'Leave approved')->count());

        $this->apply(['start_date' => '2026-09-07', 'end_date' => '2026-09-07']);
        $second = LeaveRequest::where('start_date', '2026-09-07')->first();
        $this->actingAs($this->bossUser)->post("/leave/{$second->id}/decision", ['decision' => 'APPROVED']);
        $this->assertSame('PENDING_HR', $second->fresh()->status->value);
        $this->actingAs($this->bossUser)->post("/leave/{$second->id}/decision", ['decision' => 'APPROVED'])->assertForbidden();
        $this->actingAs($this->hr)->post("/leave/{$second->id}/decision", ['decision' => 'APPROVED']);
        $this->assertSame('APPROVED', $second->fresh()->status->value);
        $this->assertEquals(4, $this->balance('ANNUAL')->used);
    }

    public function test_rejection_needs_a_reason_and_returns_the_days(): void
    {
        $this->apply(['start_date' => '2026-08-03', 'end_date' => '2026-08-05']);
        $request = LeaveRequest::first();

        $this->actingAs($this->bossUser)->post("/leave/{$request->id}/decision", ['decision' => 'REJECTED'])->assertSessionHasErrors('note');
        $this->actingAs($this->bossUser)->post("/leave/{$request->id}/decision", ['decision' => 'REJECTED', 'note' => 'Stock count that week.']);

        $this->assertSame('REJECTED', $request->fresh()->status->value);
        $this->assertSame('Stock count that week.', $request->fresh()->decision_note);
        $this->assertEquals(0, $this->balance('ANNUAL')->pending);
        $this->actingAs($this->hr)->post("/leave/{$request->id}/decision", ['decision' => 'APPROVED'])->assertStatus(422);
    }

    public function test_nobody_decides_their_own_leave(): void
    {
        $hrEmployee = $this->makeEmployee();
        $hrUser = $this->makeUser(Role::HR_ADMIN, ['employee_id' => $hrEmployee->id]);
        $this->apply(['employee_id' => $hrEmployee->id, 'start_date' => '2026-08-03', 'end_date' => '2026-08-03'], $hrUser);
        $request = LeaveRequest::first();
        $this->assertSame('PENDING_HR', $request->status->value, 'No manager on file, so it goes straight to HR');

        $this->actingAs($hrUser)->post("/leave/{$request->id}/decision", ['decision' => 'APPROVED'])->assertForbidden();
        $this->actingAs($this->hr)->post("/leave/{$request->id}/decision", ['decision' => 'APPROVED']);
        $this->assertSame('APPROVED', $request->fresh()->status->value);
    }

    public function test_cancelling_returns_pending_or_used_days(): void
    {
        $this->apply(['start_date' => '2026-08-03', 'end_date' => '2026-08-05']);
        $pending = LeaveRequest::first();
        $this->actingAs($this->staffUser)->post("/leave/{$pending->id}/cancel")->assertRedirect();
        $this->assertSame('CANCELLED', $pending->fresh()->status->value);
        $this->assertEquals(0, $this->balance('ANNUAL')->pending);

        $this->apply(['start_date' => '2026-09-07', 'end_date' => '2026-09-08']);
        $approved = LeaveRequest::where('start_date', '2026-09-07')->first();
        $this->actingAs($this->hr)->post("/leave/{$approved->id}/decision", ['decision' => 'APPROVED']);
        $this->assertEquals(2, $this->balance('ANNUAL')->used);
        $this->actingAs($this->hr)->post("/leave/{$approved->id}/cancel");
        $this->assertEquals(0, $this->balance('ANNUAL')->used);

        $this->actingAs($this->bossUser)->post("/leave/{$approved->id}/cancel")->assertForbidden();
    }

    public function test_hr_only_workflow_and_next_year_balances(): void
    {
        WorkflowConfig::where('key', 'LEAVE')->update(['steps' => json_encode(['HR'])]);
        $this->apply(['start_date' => '2027-02-01', 'end_date' => '2027-02-02'])->assertSessionHasNoErrors();
        $request = LeaveRequest::first();
        $this->assertSame('PENDING_HR', $request->status->value);
        $this->assertEquals(2, $this->balance('ANNUAL', 2027)->pending);
    }

    public function test_pages_and_forms_respect_access(): void
    {
        $this->apply(['start_date' => '2026-08-03', 'end_date' => '2026-08-05']);
        $request = LeaveRequest::first();
        $outsider = $this->makeUser(Role::EMPLOYEE, ['employee_id' => $this->makeEmployee()->id]);

        $this->actingAs($this->staffUser)->get('/leave')->assertOk()->assertSee('My leave')->assertSee('Annual leave')->assertDontSee('>Approve<', false);
        $this->actingAs($this->bossUser)->get('/leave')->assertOk()->assertSee('Kwame Boateng')->assertSee('>Approve<', false);
        $this->actingAs($outsider)->get('/leave')->assertOk()->assertDontSee('Kwame Boateng');

        $this->actingAs($this->staffUser)->get('/leave/form')->assertOk()->assertHeader('content-type', 'application/pdf');
        $this->actingAs($this->staffUser)->get("/leave/form?request={$request->id}")->assertOk();
        $this->actingAs($outsider)->get("/leave/form?request={$request->id}")->assertForbidden();
        $this->actingAs($this->staffUser)->post("/leave/{$request->id}/decision", ['decision' => 'APPROVED'])->assertForbidden();
    }
}
