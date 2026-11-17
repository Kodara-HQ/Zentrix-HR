<?php

namespace Tests\Feature;

use App\Enums\Role;
use App\Models\Contract;
use App\Models\Notification;
use App\Models\Separation;
use Database\Seeders\ReferenceDataSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class NotificationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(ReferenceDataSeeder::class);
    }

    private function note($user, array $attributes = []): Notification
    {
        return Notification::create($attributes + ['user_id' => $user->id, 'title' => 'Leave approved', 'body' => 'Your leave is approved.', 'type' => 'LEAVE', 'link' => '/leave']);
    }

    public function test_people_see_and_clear_only_their_own(): void
    {
        $me = $this->makeUser(Role::HR_ADMIN);
        $other = $this->makeUser(Role::HR_ADMIN);
        $mine = $this->note($me);
        $this->note($me, ['title' => 'Payroll ready', 'read_at' => now()]);
        $theirs = $this->note($other, ['title' => 'Someone else’s alert']);

        $this->actingAs($me)->get('/notifications')->assertOk()->assertSee('Leave approved')->assertSee('Payroll ready')->assertDontSee('Someone else’s alert');
        $this->actingAs($me)->get('/notifications?unread=1')->assertSee('Leave approved')->assertDontSee('Payroll ready');

        $this->actingAs($me)->post("/notifications/{$theirs->id}/read")->assertNotFound();
        $this->actingAs($me)->post("/notifications/{$mine->id}/read", ['open' => 1])->assertRedirect('/leave');
        $this->assertNotNull($mine->fresh()->read_at);
        $this->assertNull($theirs->fresh()->read_at);

        $this->note($me, ['title' => 'Another']);
        $this->actingAs($me)->post('/notifications/read-all');
        $this->assertSame(0, Notification::where('user_id', $me->id)->whereNull('read_at')->count());
        $this->assertNull($theirs->fresh()->read_at);
    }

    public function test_reminders_are_sent_once_to_hr(): void
    {
        $hr = $this->makeUser(Role::HR_MANAGER);
        $staff = $this->makeUser(Role::EMPLOYEE);
        $employee = $this->makeEmployee(['date_of_birth' => today()->subYears(30)->addDays(3)->toDateString()]);
        Contract::create(['employee_id' => $employee->id, 'type' => 'Fixed-term', 'title' => 'Fixed-term contract', 'start_date' => '2025-01-01', 'end_date' => today()->addDays(10)->toDateString(), 'status' => 'ACTIVE']);

        $this->artisan('hr:reminders')->assertSuccessful();
        $this->artisan('hr:reminders')->assertSuccessful();

        $this->assertSame(1, $hr->notifications()->where('type', 'BIRTHDAY')->count());
        $this->assertSame(1, $hr->notifications()->where('type', 'CONTRACT_EXPIRY')->count());
        $this->assertSame(0, $staff->notifications()->count());
    }

    public function test_probation_is_confirmed_and_due_exits_are_completed(): void
    {
        $probation = $this->makeEmployee(['status' => 'PROBATION', 'probation_end_date' => today()->subDay()->toDateString()]);
        $leaver = $this->makeEmployee();
        $account = $this->makeUser(Role::EMPLOYEE, ['employee_id' => $leaver->id]);
        $exit = Separation::create(['employee_id' => $leaver->id, 'type' => 'RESIGNATION', 'reason' => 'Relocating', 'notice_date' => today()->subMonth()->toDateString(), 'last_working_day' => today()->toDateString(), 'status' => 'APPROVED']);

        $this->artisan('hr:reminders')->expectsOutputToContain('completed 1 exits, confirmed 1 probations')->assertSuccessful();

        $this->assertSame('ACTIVE', $probation->fresh()->status->value);
        $this->assertSame('CONFIRMATION', $probation->events()->first()->type->value);
        $this->assertSame('RESIGNED', $leaver->fresh()->status->value);
        $this->assertFalse($account->fresh()->is_active);
        $this->assertSame('COMPLETED', $exit->fresh()->status->value);
    }
}
