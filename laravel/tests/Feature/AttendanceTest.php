<?php

namespace Tests\Feature;

use App\Enums\Role;
use App\Models\AttendanceCorrection;
use App\Models\AttendanceRecord;
use App\Models\BiometricDevice;
use App\Models\OvertimeRequest;
use App\Models\ShiftAssignment;
use App\Models\ShiftPattern;
use Database\Seeders\ReferenceDataSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AttendanceTest extends TestCase
{
    use RefreshDatabase;

    private $staff;

    private $staffUser;

    private $boss;

    private $bossUser;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(ReferenceDataSeeder::class);
        config(['services.biometric.key' => 'clock-secret']);
        $this->boss = $this->makeEmployee(['employee_no' => 'EMP-0009']);
        $this->bossUser = $this->makeUser(Role::SUPERVISOR, ['employee_id' => $this->boss->id]);
        $this->staff = $this->makeEmployee(['employee_no' => 'EMP-0010', 'manager_id' => $this->boss->id]);
        $this->staffUser = $this->makeUser(Role::EMPLOYEE, ['employee_id' => $this->staff->id]);
    }

    private function scan(string $at, string $key = 'clock-secret', string $no = 'EMP-0010')
    {
        return $this->postJson('/api/time/biometric/punch', ['deviceKey' => $key, 'employeeNo' => $no, 'timestamp' => $at]);
    }

    private function record(string $date = '2026-11-16'): ?AttendanceRecord
    {
        return AttendanceRecord::where('employee_id', $this->staff->id)->where('date', $date)->first();
    }

    public function test_first_scan_clocks_in_and_later_scans_clock_out(): void
    {
        $this->scan('2026-11-16 08:10:00')->assertOk()->assertJson(['status' => 'PRESENT', 'late_minutes' => 0]);
        $this->scan('2026-11-16 17:05:00')->assertOk();

        $record = $this->record();
        $this->assertSame('08:10', $record->clock_in->format('H:i'));
        $this->assertSame('17:05', $record->clock_out->format('H:i'));
        $this->assertSame('BIOMETRIC', $record->source->value);
        $this->assertSame(0, OvertimeRequest::count());
    }

    public function test_late_after_the_grace_period_and_shifts_change_the_times(): void
    {
        $this->scan('2026-11-16 08:16:00')->assertJson(['status' => 'LATE', 'late_minutes' => 16]);

        $early = ShiftPattern::create(['name' => 'Warehouse morning', 'start_time' => '07:00', 'end_time' => '15:00', 'grace_minutes' => 10, 'work_days' => [1, 2, 3, 4, 5, 6]]);
        ShiftAssignment::create(['employee_id' => $this->staff->id, 'shift_id' => $early->id, 'effective_from' => '2026-11-01']);
        $this->scan('2026-11-17 07:30:00')->assertJson(['status' => 'LATE', 'late_minutes' => 30]);
    }

    public function test_leaving_late_opens_one_overtime_request_per_day(): void
    {
        $this->scan('2026-11-16 08:00:00');
        $this->scan('2026-11-16 18:00:00');
        $this->scan('2026-11-16 18:30:00'); // a second scan on the way out must not create another request

        $this->assertSame(1, OvertimeRequest::count());
        $overtime = OvertimeRequest::first();
        $this->assertEquals(1.5, $overtime->hours);
        $this->assertEquals(1.5, $overtime->multiplier);
        $this->assertSame('PENDING_MANAGER', $overtime->status->value);

        $this->scan('2026-11-21 08:00:00'); // Saturday
        $this->scan('2026-11-21 18:00:00');
        $this->assertEquals(2, OvertimeRequest::where('date', '2026-11-21')->value('multiplier'), 'Weekend rate');
    }

    public function test_the_clock_needs_a_known_key_and_employee(): void
    {
        $this->scan('2026-11-16 08:00:00', 'wrong-key')->assertStatus(401);
        $this->scan('2026-11-16 08:00:00', no: 'EMP-9999')->assertStatus(404);
        $this->postJson('/api/time/biometric/punch', ['deviceKey' => 'clock-secret'])->assertStatus(422);
        $this->post('/api/time/biometric/punch', ['deviceKey' => 'clock-secret'])->assertStatus(422)->assertJsonStructure(['message']);

        BiometricDevice::create(['name' => 'Tema gate', 'api_key_hash' => hash('sha256', 'tema-key'), 'is_active' => true]);
        $this->scan('2026-11-16 08:00:00', 'tema-key')->assertOk();
        $this->assertSame('Tema gate', $this->record()->notes);
    }

    public function test_corrections_are_decided_by_the_manager_and_recompute_lateness(): void
    {
        $this->actingAs($this->staffUser)->post('/attendance/corrections', ['date' => '2026-11-16', 'clock_in' => '08:40', 'clock_out' => '17:00', 'reason' => 'The clock did not read my finger.'])->assertSessionHasNoErrors();
        $correction = AttendanceCorrection::first();
        $this->assertSame(1, $this->bossUser->notifications()->count());

        $this->actingAs($this->staffUser)->post("/attendance/corrections/{$correction->id}/decision", ['decision' => 'APPROVED'])->assertForbidden();
        $this->actingAs($this->bossUser)->post("/attendance/corrections/{$correction->id}/decision", ['decision' => 'APPROVED']);

        $record = $this->record();
        $this->assertSame('LATE', $record->status->value);
        $this->assertSame(40, $record->late_minutes);
        $this->assertSame('APPROVED', $correction->fresh()->status->value);
        $this->actingAs($this->bossUser)->post("/attendance/corrections/{$correction->id}/decision", ['decision' => 'REJECTED'])->assertStatus(422);

        $this->actingAs($this->staffUser)->post('/attendance/corrections', ['date' => today()->addDay()->toDateString(), 'reason' => 'Future day test'])->assertSessionHasErrors('date');
    }

    public function test_overtime_approval_needs_the_right_manager_and_never_yourself(): void
    {
        $this->actingAs($this->staffUser)->post('/attendance/overtime', ['date' => '2026-11-16', 'hours' => 2, 'reason' => 'Month-end stock count']);
        $overtime = OvertimeRequest::first();

        $otherSupervisor = $this->makeUser(Role::SUPERVISOR, ['employee_id' => $this->makeEmployee()->id]);
        $this->actingAs($otherSupervisor)->post("/attendance/overtime/{$overtime->id}/decision", ['decision' => 'APPROVED'])->assertForbidden();

        $this->actingAs($this->bossUser)->post('/attendance/overtime', ['date' => '2026-11-16', 'hours' => 1, 'reason' => 'Supervisor cover']);
        $own = OvertimeRequest::where('employee_id', $this->boss->id)->first();
        $this->actingAs($this->bossUser)->post("/attendance/overtime/{$own->id}/decision", ['decision' => 'APPROVED'])->assertForbidden();

        $this->actingAs($this->bossUser)->post("/attendance/overtime/{$overtime->id}/decision", ['decision' => 'APPROVED']);
        $this->assertSame('APPROVED', $overtime->fresh()->status->value);

        $this->actingAs($this->staffUser)->post('/attendance/overtime', ['employee_id' => $this->boss->id, 'date' => '2026-11-16', 'hours' => 1, 'reason' => 'Not mine to file']);
        $this->assertSame(0, OvertimeRequest::where('employee_id', $this->boss->id)->where('reason', 'Not mine to file')->count(), 'Employees can only file their own');
    }

    public function test_manual_entry_and_page_access(): void
    {
        $hr = $this->makeUser(Role::HR_ADMIN);
        $this->actingAs($hr)->post('/attendance/manual', ['employee_id' => $this->staff->id, 'date' => '2026-11-16', 'status' => 'REMOTE', 'clock_in' => '09:00', 'clock_out' => '17:00'])->assertSessionHasNoErrors();
        $this->assertSame('REMOTE', $this->record()->status->value);
        $this->actingAs($this->staffUser)->post('/attendance/manual', ['employee_id' => $this->staff->id, 'date' => '2026-11-16', 'status' => 'PRESENT'])->assertForbidden();

        $outsider = $this->makeUser(Role::EMPLOYEE, ['employee_id' => $this->makeEmployee()->id]);
        $this->actingAs($this->staffUser)->get('/attendance')->assertOk()->assertSee('My attendance')->assertSee('16 Nov 2026')->assertDontSee('Manual entry');
        $this->actingAs($outsider)->get('/attendance')->assertOk()->assertDontSee('16 Nov 2026');
        $this->actingAs($this->bossUser)->get('/attendance')->assertOk()->assertSee('16 Nov 2026')->assertSee('Manual entry');
        $this->actingAs($this->makeUser(Role::SUPER_ADMIN))->get('/attendance')->assertOk()->assertSee('Fingerprint clock address')->assertSee('/api/time/biometric/punch');
    }
}
