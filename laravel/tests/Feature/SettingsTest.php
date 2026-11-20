<?php

namespace Tests\Feature;

use App\Enums\Role;
use App\Models\Company;
use App\Models\Department;
use App\Models\Holiday;
use App\Models\NotificationSetting;
use App\Models\ShiftPattern;
use App\Models\WorkflowConfig;
use App\Models\WorkingCalendar;
use Database\Seeders\ReferenceDataSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class SettingsTest extends TestCase
{
    use RefreshDatabase;

    private $hr;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(ReferenceDataSeeder::class);
        $this->hr = $this->makeUser(Role::HR_ADMIN);
    }

    public function test_everyone_else_gets_account_settings_and_can_change_their_password(): void
    {
        $staff = $this->makeUser(Role::SUPERVISOR);
        $this->actingAs($staff)->get('/settings')->assertOk()->assertSee('Your sign-in details')->assertDontSee('System settings');
        $this->actingAs($staff)->put('/settings/company', ['name' => 'X', 'legal_name' => 'X', 'country' => 'Ghana'])->assertForbidden();

        $this->actingAs($staff)->from('/settings')->put('/password', ['current_password' => 'Secret123', 'new_password' => 'Fresh2027'])->assertRedirect('/settings');
        $this->assertTrue(password_verify('Fresh2027', $staff->fresh()->password_hash));
    }

    public function test_hr_edits_the_company_and_working_hours(): void
    {
        $this->actingAs($this->hr)->get('/settings')->assertOk()->assertSee('System settings')->assertDontSee('Audit log');
        $this->actingAs($this->hr)->put('/settings/company', ['name' => 'Zentrix HR', 'legal_name' => 'Zentrix Ltd', 'country' => 'Ghana', 'tin' => 'C001'])->assertSessionHasNoErrors();
        $this->assertSame('Zentrix Ltd', Company::first()->legal_name);

        $general = ShiftPattern::create(['name' => 'General day', 'start_time' => '08:00', 'end_time' => '17:00', 'grace_minutes' => 15, 'work_days' => [1, 2, 3, 4, 5]]);
        $this->actingAs($this->hr)->put('/settings/hours', ['start_time' => '09:00', 'end_time' => '08:00', 'grace_minutes' => 10, 'work_days' => [1, 2], 'overtime_weekday' => 1.5, 'overtime_weekend' => 2])
            ->assertSessionHasErrors(['end_time' => 'Closing time must be after the reporting time.']);
        $this->actingAs($this->hr)->put('/settings/hours', ['start_time' => '07:30', 'end_time' => '16:30', 'grace_minutes' => 10, 'work_days' => [1, 2, 3, 4, 5, 6], 'overtime_weekday' => 1.5, 'overtime_weekend' => 2]);

        $calendar = WorkingCalendar::first();
        $this->assertSame(['07:30', '16:30', [1, 2, 3, 4, 5, 6]], [$calendar->start_time, $calendar->end_time, $calendar->work_days]);
        $this->assertEquals(9, $calendar->hours_per_day);
        $this->assertEquals(198, $calendar->hours_per_month);
        $this->assertSame('07:30', $general->fresh()->start_time, 'A shift that mirrored office hours follows them');
    }

    public function test_lists_add_edit_and_refuse_to_remove_what_is_in_use(): void
    {
        $this->actingAs($this->hr)->post('/settings/lists/departments', ['name' => 'Logistics', 'code' => 'LOG'])->assertRedirect('/settings?tab=departments');
        $logistics = Department::firstWhere('code', 'LOG');
        $this->actingAs($this->hr)->post('/settings/lists/departments', ['name' => 'Logistics', 'code' => 'LG2'])->assertSessionHasErrors(['name' => 'Another item already uses that Name.']);
        $this->actingAs($this->hr)->patch("/settings/lists/departments/{$logistics->id}", ['name' => 'Logistics & Fleet', 'code' => 'LOG']);
        $this->assertSame('Logistics & Fleet', $logistics->fresh()->name);

        $this->makeEmployee(['department_id' => $logistics->id]);
        $this->actingAs($this->hr)->delete("/settings/lists/departments/{$logistics->id}")->assertSessionHasErrors('delete');
        $this->assertNotNull($logistics->fresh());

        $this->actingAs($this->hr)->post('/settings/lists/holidays', ['name' => "Farmers' Day", 'date' => '2026-12-04', 'recurring' => '0']);
        $this->assertFalse(Holiday::firstWhere('name', "Farmers' Day")->recurring);
        $this->actingAs($this->hr)->get('/settings?tab=holidays')->assertSee("Farmers&#039; Day", false)->assertSee('4 Dec 2026');

        $this->actingAs($this->hr)->post('/settings/lists/shifts', ['name' => 'Night', 'start_time' => '22:00', 'end_time' => '06:00', 'grace_minutes' => 10, 'work_days' => [1, 2]])->assertSessionHasErrors('end_time');
        $this->actingAs($this->hr)->post('/settings/lists/leave-types', ['name' => 'Compassionate leave', 'code' => 'COMP', 'days_per_year' => 3, 'count_mode' => 'WORKING_DAYS', 'affects_balance' => '1', 'paid' => '1', 'requires_reason' => '1'])->assertSessionHasNoErrors();
        $this->actingAs($this->hr)->post('/settings/lists/unknown', ['name' => 'x'])->assertNotFound();
    }

    public function test_workflows_and_notifications(): void
    {
        $leave = WorkflowConfig::firstWhere('key', 'LEAVE');
        $this->actingAs($this->hr)->put("/settings/workflows/{$leave->id}", ['steps' => 'HR,MANAGER'])->assertSessionHasErrors('steps');
        $this->actingAs($this->hr)->put("/settings/workflows/{$leave->id}", ['steps' => 'HR']);
        $this->assertSame(['HR'], $leave->fresh()->steps);

        $birthday = NotificationSetting::firstWhere('event_key', 'BIRTHDAY');
        $payroll = NotificationSetting::firstWhere('event_key', 'PAYROLL');
        $this->actingAs($this->hr)->put('/settings/notifications', ['in_app' => [$payroll->id => 1], 'email' => [$payroll->id => 1]]);
        $this->assertFalse($birthday->fresh()->in_app);
        $this->assertTrue($payroll->fresh()->email);
    }

    public function test_super_administrators_see_the_audit_log_and_make_backups(): void
    {
        Storage::fake();
        $admin = $this->makeUser(Role::SUPER_ADMIN);
        $this->actingAs($admin)->get('/settings?tab=audit')->assertOk()->assertSee('Audit log');
        $this->actingAs($this->hr)->get('/settings?tab=audit')->assertOk()->assertDontSee('IP</th>', false);
        $this->actingAs($this->hr)->post('/settings/backup')->assertForbidden();

        $this->actingAs($admin)->post('/settings/backup')->assertSessionHas('status');
        $file = Storage::files('backups/'.$this->company->id)[0];
        $this->assertArrayHasKey('employees', json_decode(Storage::get($file), true));
        $this->assertStringNotContainsString('password_hash', Storage::get($file));
        $this->actingAs($admin)->get('/settings/backup/'.basename($file))->assertOk();
        $this->actingAs($admin)->get('/settings/backup/..%2F.env')->assertNotFound();
    }

    public function test_tax_rates_are_shown(): void
    {
        $this->actingAs($this->hr)->get('/settings?tab=tax')->assertOk()->assertSee('Ghana payroll from September 2026')->assertSee('35%')->assertSee('5.5%');
    }
}
