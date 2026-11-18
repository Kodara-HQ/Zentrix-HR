<?php

namespace Tests\Feature;

use App\Enums\Role;
use App\Models\DisciplinaryCase;
use App\Models\FileAsset;
use App\Models\Separation;
use App\Models\StaffFeedback;
use App\Models\Survey;
use App\Models\WelfareRequest;
use Database\Seeders\ReferenceDataSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class WorkplaceTest extends TestCase
{
    use RefreshDatabase;

    private $staff;

    private $staffUser;

    private $hr;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake();
        $this->seed(ReferenceDataSeeder::class);
        $this->staff = $this->makeEmployee(['first_name' => 'Kwame', 'last_name' => 'Boateng']);
        $this->staffUser = $this->makeUser(Role::EMPLOYEE, ['employee_id' => $this->staff->id]);
        $this->hr = $this->makeUser(Role::HR_MANAGER);
    }

    public function test_relations_are_hr_only_and_warnings_are_filed_confidentially(): void
    {
        $this->actingAs($this->staffUser)->get('/relations')->assertRedirect('/');
        $this->actingAs($this->makeUser(Role::SUPERVISOR))->get('/relations')->assertForbidden();

        $this->actingAs($this->hr)->post('/relations/cases', ['employee_id' => $this->staff->id, 'incident_date' => '2026-11-10', 'description' => 'Left the depot unlocked.']);
        $this->actingAs($this->hr)->post('/relations/cases', ['employee_id' => $this->staff->id, 'incident_date' => '2026-11-11', 'description' => 'Second incident.']);
        $this->assertSame(['DC-0001', 'DC-0002'], DisciplinaryCase::orderBy('case_no')->pluck('case_no')->all());

        $case = DisciplinaryCase::first();
        $this->actingAs($this->hr)->patch("/relations/cases/{$case->id}", ['status' => 'HEARING', 'suspend' => '1']);
        $this->assertSame('SUSPENDED', $this->staff->fresh()->status->value);

        $this->actingAs($this->hr)->post('/relations/warnings', ['employee_id' => $this->staff->id, 'case_id' => $case->id, 'type' => 'WRITTEN', 'issued_on' => '2026-11-12', 'content' => 'Keep the depot locked at all times.'])->assertSessionHasNoErrors();
        $file = FileAsset::where('owner_id', $this->staff->id)->latest()->first();
        $this->assertTrue($file->confidential);
        $this->actingAs($this->staffUser)->get("/files/{$file->id}")->assertOk();
        $this->actingAs($this->makeUser(Role::PAYROLL_OFFICER))->get("/files/{$file->id}")->assertForbidden();
    }

    public function test_welfare_requests_feedback_and_surveys(): void
    {
        $this->actingAs($this->staffUser)->get('/welfare')->assertOk()->assertSee('Ask for support');
        $this->actingAs($this->staffUser)->post('/welfare/requests', ['employee_id' => $this->makeEmployee()->id, 'type' => 'Bereavement', 'description' => 'Funeral of my father.', 'amount' => 1500]);
        $request = WelfareRequest::first();
        $this->assertSame($this->staff->id, $request->employee_id);
        $this->assertSame(1, $this->hr->notifications()->where('type', 'WELFARE')->count());
        $this->actingAs($this->staffUser)->patch("/welfare/requests/{$request->id}", ['status' => 'APPROVED'])->assertForbidden();
        $this->actingAs($this->hr)->patch("/welfare/requests/{$request->id}", ['status' => 'APPROVED', 'resolution' => 'GHS 1,500 paid with November salary.']);
        $this->assertSame('APPROVED', $request->fresh()->status->value);

        $this->actingAs($this->staffUser)->post('/welfare/feedback', ['category' => 'Canteen', 'message' => 'Lunch runs out by 1pm.']);
        $this->assertNull(StaffFeedback::first()->employee_id);

        $this->actingAs($this->hr)->post('/welfare/surveys', ['title' => 'Pulse', 'questions' => "I have the tools I need.\nMy manager listens."]);
        $survey = Survey::first();
        $this->assertCount(2, $survey->questions);
        [$q1, $q2] = array_column($survey->questions, 'id');
        $this->actingAs($this->staffUser)->post("/welfare/surveys/{$survey->id}/respond", ['answers' => [$q1 => 4]])->assertSessionHasErrors();
        $this->actingAs($this->staffUser)->post("/welfare/surveys/{$survey->id}/respond", ['answers' => [$q1 => 4, $q2 => 5], 'comment' => 'Good team.']);
        $this->actingAs($this->staffUser)->post("/welfare/surveys/{$survey->id}/respond", ['answers' => [$q1 => 3, $q2 => 5]]);
        $this->assertSame(1, $survey->responses()->count(), 'Answering again replaces the earlier response');
        $this->actingAs($this->hr)->get('/welfare?tab=surveys')->assertSee('I have the tools I need.')->assertSee('5');
    }

    public function test_resignation_notice_and_completion(): void
    {
        $this->actingAs($this->staffUser)->post('/exit', ['type' => 'TERMINATION', 'notice_date' => '2026-11-18', 'last_working_day' => '2026-12-18', 'reason' => 'Trying to terminate myself'])->assertSessionHasErrors('type');
        $this->actingAs($this->staffUser)->post('/exit', ['type' => 'RESIGNATION', 'notice_date' => '2026-11-18', 'last_working_day' => '2026-11-10', 'reason' => 'New job abroad'])->assertSessionHasErrors('last_working_day');
        $this->actingAs($this->staffUser)->post('/exit', ['type' => 'RESIGNATION', 'notice_date' => today()->toDateString(), 'last_working_day' => today()->addMonth()->toDateString(), 'reason' => 'New job abroad'])->assertSessionHasNoErrors();
        $this->actingAs($this->staffUser)->post('/exit', ['type' => 'RESIGNATION', 'notice_date' => today()->toDateString(), 'last_working_day' => today()->addMonth()->toDateString(), 'reason' => 'Again'])->assertSessionHasErrors('employee_id');

        $exit = Separation::first();
        $this->assertSame(5, $exit->clearance()->count());
        $this->actingAs($this->hr)->post("/exit/{$exit->id}/decision", ['decision' => 'APPROVED', 'final_pay_notes' => 'Pay 6 leave days.']);
        $this->assertSame('APPROVED', $exit->fresh()->status->value);
        $this->assertSame('NOTICE', $this->staff->fresh()->status->value);
        $this->assertTrue($this->staffUser->fresh()->is_active);
        $this->actingAs($this->hr)->post("/exit/{$exit->id}/decision", ['decision' => 'REJECTED'])->assertStatus(422);

        $item = $exit->clearance()->first();
        $this->actingAs($this->hr)->patch("/exit/clearance/{$item->id}", ['returned' => '1']);
        $this->assertTrue($item->fresh()->returned);

        $leaver = $this->makeEmployee();
        $account = $this->makeUser(Role::EMPLOYEE, ['employee_id' => $leaver->id]);
        $this->actingAs($this->hr)->post('/exit', ['employee_id' => $leaver->id, 'type' => 'TERMINATION', 'notice_date' => '2026-11-01', 'last_working_day' => '2026-11-15', 'reason' => 'Gross misconduct after hearing DC-0001.']);
        $this->actingAs($this->hr)->post('/exit/'.Separation::where('employee_id', $leaver->id)->value('id').'/decision', ['decision' => 'APPROVED']);
        $this->assertSame('TERMINATED', $leaver->fresh()->status->value);
        $this->assertFalse($account->fresh()->is_active);
        $this->assertTrue($leaver->letters()->where('type', 'TERMINATION')->exists());
    }
}
