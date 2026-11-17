<?php

namespace Tests\Feature;

use App\Enums\Role;
use App\Models\Department;
use App\Models\Employee;
use App\Models\EmploymentType;
use App\Models\FileAsset;
use App\Models\HrLetter;
use App\Models\JobTitle;
use App\Models\User;
use App\Models\WorkLocation;
use Database\Seeders\ReferenceDataSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class EmployeeTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake();
        $this->seed(ReferenceDataSeeder::class);
    }

    private function hireForm(array $overrides = []): array
    {
        $department = Department::firstOrCreate(['code' => 'OPS'], ['name' => 'Operations']);

        return $overrides + [
            'first_name' => 'Esi', 'last_name' => 'Quaye', 'gender' => 'FEMALE', 'date_of_birth' => '1997-05-02',
            'email' => 'Esi.Quaye@zentrix.test', 'phone' => '0244000011',
            'department_id' => $department->id,
            'job_title_id' => JobTitle::firstOrCreate(['name' => 'Officer', 'department_id' => $department->id])->id,
            'employment_type_id' => EmploymentType::where('name', 'Full-time')->value('id'),
            'location_id' => WorkLocation::firstOrCreate(['name' => 'Head office', 'branch' => 'Accra'], ['city' => 'Accra', 'region' => 'Greater Accra'])->id,
            'date_of_employment' => '2026-11-02', 'basic_salary' => 5000, 'create_login' => '1',
        ];
    }

    public function test_hr_hires_an_employee_with_number_balances_contract_letter_and_login(): void
    {
        $hr = $this->makeUser(Role::HR_ADMIN);

        $response = $this->actingAs($hr)->post('/employees', $this->hireForm());

        $employee = Employee::firstWhere('email', 'esi.quaye@zentrix.test');
        $response->assertRedirect("/employees/{$employee->id}")->assertSessionHas('status', fn ($s) => str_contains($s, 'Welcome@0001'));
        $this->assertSame('EMP-0001', $employee->employee_no);
        $this->assertSame('ACTIVE', $employee->status->value);
        $this->assertSame(4, $employee->leaveBalances()->count()); // annual, sick, study, maternity
        $this->assertSame(1, $employee->contracts()->count());
        $this->assertSame('HIRE', $employee->events()->first()->type->value);

        $letter = HrLetter::firstWhere('employee_id', $employee->id);
        $this->assertSame('APPOINTMENT', $letter->type->value);
        Storage::assertExists(FileAsset::find($letter->file_asset_id)->storage_key);
        $this->assertStringStartsWith('%PDF', Storage::get(FileAsset::find($letter->file_asset_id)->storage_key));

        $login = User::firstWhere('employee_id', $employee->id);
        $this->assertSame(Role::EMPLOYEE, $login->role);
        $this->assertTrue($login->must_change_password);
        $this->assertTrue(password_verify('Welcome@0001', $login->password_hash));

        $this->assertSame(2, \App\Models\Company::first()->next_employee_no);
        $this->actingAs($hr)->post('/employees', $this->hireForm(['email' => 'second@zentrix.test', 'create_login' => '0']));
        $this->assertSame('EMP-0002', Employee::firstWhere('email', 'second@zentrix.test')->employee_no);
        $this->assertSame(1, User::where('role', 'EMPLOYEE')->count());
    }

    public function test_hire_is_validated_and_limited_to_hr(): void
    {
        $this->actingAs($this->makeUser(Role::HR_ADMIN))
            ->post('/employees', $this->hireForm(['email' => 'not-an-email', 'location_id' => 'gone']))
            ->assertSessionHasErrors(['email', 'location_id' => 'That work location is no longer in System settings. Choose it again, then save.']);
        $this->actingAs($this->makeUser(Role::PAYROLL_OFFICER))->post('/employees', $this->hireForm())->assertForbidden();
        $this->assertSame(0, Employee::count());
    }

    public function test_list_is_scoped_to_what_each_role_may_see(): void
    {
        $manager = $this->makeEmployee(['first_name' => 'Kofi', 'last_name' => 'Asante']);
        $report = $this->makeEmployee(['first_name' => 'Ama', 'last_name' => 'Adjei', 'manager_id' => $manager->id]);
        $other = $this->makeEmployee(['first_name' => 'Yaw', 'last_name' => 'Darko', 'department_id' => Department::create(['code' => 'FIN', 'name' => 'Finance'])->id]);

        $this->actingAs($this->makeUser(Role::HR_MANAGER))->get('/employees')->assertOk()->assertSee('Ama Adjei')->assertSee('Yaw Darko')->assertSee('GH₵');

        $supervisor = $this->makeUser(Role::SUPERVISOR, ['employee_id' => $manager->id, 'department_id' => $manager->department_id]);
        $this->actingAs($supervisor)->get('/employees')->assertSee('Ama Adjei')->assertDontSee('Yaw Darko')->assertSee('Restricted');
        $this->actingAs($supervisor)->get("/employees/{$other->id}")->assertForbidden();
        $this->actingAs($supervisor)->get("/employees/{$report->id}")->assertOk()->assertSee('Compensation is restricted for your role.');

        // The add-employee dialog lists every manager, so check the table rows rather than the whole page.
        $this->actingAs($this->makeUser(Role::HR_ADMIN))->get('/employees?search=darko')
            ->assertViewHas('employees', fn ($page) => $page->total() === 1 && $page->first()->id === $other->id);
    }

    public function test_employees_edit_only_their_own_contact_details(): void
    {
        $employee = $this->makeEmployee(['phone' => '0200000000']);
        $user = $this->makeUser(Role::EMPLOYEE, ['employee_id' => $employee->id]);

        $this->actingAs($user)->get("/employees/{$employee->id}")->assertOk()->assertSee('Basic salary');
        $this->actingAs($user)->patch("/employees/{$employee->id}", ['phone' => '0555555555', 'basic_salary' => 99999, '_form' => 'edit'])->assertRedirect();

        $employee->refresh();
        $this->assertSame('0555555555', $employee->phone);
        $this->assertEquals(4500, (float) $employee->basic_salary);

        $other = $this->makeEmployee();
        $this->actingAs($user)->patch("/employees/{$other->id}", ['phone' => '0555555555'])->assertRedirect('/');
    }

    public function test_promote_transfer_deactivate_and_reinstate(): void
    {
        $hr = $this->makeUser(Role::HR_ADMIN);
        $employee = $this->makeEmployee();
        $login = $this->makeUser(Role::EMPLOYEE, ['employee_id' => $employee->id]);
        $senior = JobTitle::create(['name' => 'Senior Officer', 'department_id' => $employee->department_id]);

        $this->actingAs($hr)->post("/employees/{$employee->id}/actions", ['type' => 'PROMOTE'])->assertSessionHasErrors('job_title_id');
        $this->actingAs($hr)->post("/employees/{$employee->id}/actions", ['type' => 'PROMOTE', 'job_title_id' => $senior->id, 'basic_salary' => 6200, 'effective_date' => '2026-12-01']);
        $employee->refresh();
        $this->assertSame($senior->id, $employee->job_title_id);
        $this->assertEquals(6200, (float) $employee->basic_salary);
        $this->assertSame('Promoted to Senior Officer', $employee->events()->where('type', 'PROMOTION')->value('title'));
        $this->assertTrue(HrLetter::where('employee_id', $employee->id)->where('type', 'PROMOTION')->exists());

        $finance = Department::create(['code' => 'FIN', 'name' => 'Finance']);
        $this->actingAs($hr)->post("/employees/{$employee->id}/actions", ['type' => 'TRANSFER', 'department_id' => $finance->id]);
        $this->assertSame($finance->id, $employee->fresh()->department_id);
        $this->assertSame($finance->id, $login->fresh()->department_id);

        $this->actingAs($hr)->post("/employees/{$employee->id}/actions", ['type' => 'DEACTIVATE']);
        $this->assertSame('INACTIVE', $employee->fresh()->status->value);
        $this->assertFalse($login->fresh()->is_active);
        $this->assertSame(1, $login->notifications()->count());

        $this->actingAs($hr)->post("/employees/{$employee->id}/actions", ['type' => 'REINSTATE']);
        $this->assertSame('ACTIVE', $employee->fresh()->status->value);
        $this->assertTrue($login->fresh()->is_active);

        $this->actingAs($this->makeUser(Role::SUPERVISOR))->post("/employees/{$employee->id}/actions", ['type' => 'SUSPEND'])->assertForbidden();
    }

    public function test_documents_photo_and_confidential_files(): void
    {
        $employee = $this->makeEmployee();
        $own = $this->makeUser(Role::EMPLOYEE, ['employee_id' => $employee->id]);
        $outsider = $this->makeUser(Role::EMPLOYEE, ['employee_id' => $this->makeEmployee()->id]);

        $this->actingAs($own)->post('/files', ['owner_type' => 'EMPLOYEE', 'owner_id' => $employee->id, 'photo' => '1', 'file' => UploadedFile::fake()->image('me.png')])->assertRedirect();
        $photo = FileAsset::find($employee->fresh()->photo_file_id);
        $this->assertNotNull($photo);
        $this->actingAs($own)->get("/files/{$photo->id}")->assertOk();
        $this->actingAs($outsider)->get("/files/{$photo->id}")->assertForbidden();

        $this->actingAs($own)->post('/files', ['owner_type' => 'EMPLOYEE', 'owner_id' => $employee->id, 'photo' => '1', 'file' => UploadedFile::fake()->create('cv.pdf', 10, 'application/pdf')])
            ->assertSessionHasErrors(['file' => 'Use a JPEG, PNG, or WebP image.']);
        $this->actingAs($outsider)->post('/files', ['owner_type' => 'EMPLOYEE', 'owner_id' => $employee->id, 'file' => UploadedFile::fake()->create('x.pdf', 10, 'application/pdf')])->assertForbidden();

        $hr = $this->makeUser(Role::HR_ADMIN);
        $this->actingAs($hr)->post('/files', ['owner_type' => 'EMPLOYEE', 'owner_id' => $employee->id, 'confidential' => '1', 'file' => UploadedFile::fake()->create('warning.pdf', 10, 'application/pdf')]);
        $secret = FileAsset::where('file_name', 'warning.pdf')->first();
        $this->actingAs($this->makeUser(Role::PAYROLL_OFFICER))->get("/files/{$secret->id}")->assertForbidden();
        $this->actingAs($own)->get("/files/{$secret->id}")->assertOk();
        $this->actingAs($hr)->get("/employees/{$employee->id}")->assertSee('warning.pdf');
    }

    public function test_payroll_staff_manage_pay_items(): void
    {
        $employee = $this->makeEmployee();
        $component = \App\Models\PayrollComponent::firstOrCreate(['code' => 'TRANSPORT'], ['name' => 'Transport allowance', 'kind' => 'ALLOWANCE']);
        $payroll = $this->makeUser(Role::PAYROLL_OFFICER);

        $this->actingAs($payroll)->post("/employees/{$employee->id}/pay-items", ['component_id' => $component->id, 'amount' => 400]);
        $this->actingAs($payroll)->post("/employees/{$employee->id}/pay-items", ['component_id' => $component->id, 'amount' => 450]);
        $this->assertSame(1, $employee->payItems()->count());
        $this->assertEquals(450, (float) $employee->payItems()->value('amount'));

        $this->actingAs($this->makeUser(Role::SUPERVISOR))->post("/employees/{$employee->id}/pay-items", ['component_id' => $component->id, 'amount' => 1])->assertForbidden();
    }
}
