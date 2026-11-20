<?php

namespace Tests\Feature;

use App\Enums\Role;
use App\Models\Company;
use App\Models\Department;
use App\Models\Employee;
use App\Models\LeaveType;
use App\Models\User;
use App\Services\CompanySetup;
use App\Support\Tenant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class CompaniesTest extends TestCase
{
    use RefreshDatabase;

    private function systemAdmin(): User
    {
        return Tenant::run(null, fn () => User::create([
            'username' => 'root', 'password_hash' => Hash::make('Secret123'), 'role' => Role::SYSTEM_ADMIN,
            'first_name' => 'System', 'last_name' => 'Admin', 'is_active' => true, 'must_change_password' => false,
        ]));
    }

    /** A second company with one employee, and the first company's employee and HR user. */
    private function twoCompanies(): array
    {
        $ownEmployee = $this->makeEmployee(['first_name' => 'Abena']);
        $hr = $this->makeUser(Role::HR_ADMIN);
        $other = app(CompanySetup::class)->create(['name' => 'Other Ltd', 'legal_name' => 'Other Limited']);
        $otherEmployee = Tenant::run($other->id, fn () => $this->makeEmployee(['first_name' => 'Yaa']));
        Tenant::forget(); // from here on the signed-in user decides the company

        return [$hr, $ownEmployee, $other, $otherEmployee];
    }

    public function test_the_system_admin_sets_up_a_company_with_its_first_administrator(): void
    {
        $root = $this->systemAdmin();
        Tenant::forget();

        $this->actingAs($root)->get('/')->assertRedirect('/system/companies');
        $this->actingAs($root)->get('/system/companies')->assertOk()->assertSee('Test Company')->assertSee('New company');

        $this->actingAs($root)->post('/system/companies', [
            'name' => 'Akwaaba Foods', 'legal_name' => 'Akwaaba Foods Ltd', 'country' => 'Ghana', 'currency' => 'GHS',
            'admin_first_name' => 'Esi', 'admin_last_name' => 'Owusu', 'admin_username' => 'esi.owusu', 'admin_password' => 'Initial-pass-2026',
        ])->assertRedirect('/system/companies')->assertSessionHasNoErrors();

        $company = Company::firstWhere('name', 'Akwaaba Foods');
        $admin = User::firstWhere('username', 'esi.owusu');
        $this->assertSame([$company->id, Role::SUPER_ADMIN, true], [$admin->company_id, $admin->role, $admin->must_change_password]);
        // Its own settings, separate from the first company's.
        $this->assertSame(5, LeaveType::withoutGlobalScope('company')->where('company_id', $company->id)->count());

        $this->post('/logout');
        $this->post('/login', ['email' => 'esi.owusu', 'password' => 'Initial-pass-2026'])->assertRedirect('/');
        $this->get('/')->assertRedirect('/password');
    }

    public function test_only_the_system_admin_manages_companies(): void
    {
        $admin = $this->makeUser(Role::SUPER_ADMIN);
        Tenant::forget();

        $this->actingAs($admin)->get('/system/companies')->assertForbidden();
        $this->actingAs($admin)->post('/system/companies', ['name' => 'Sneaky'])->assertForbidden();
        $this->assertFalse(Company::where('name', 'Sneaky')->exists());
        // Nor can a company administrator hand out the system role.
        $this->actingAs($admin)->post('/users', ['full_name' => 'X Y', 'username' => 'xy', 'role' => 'SYSTEM_ADMIN', 'password' => 'Long-password-1'])
            ->assertSessionHasErrors('role');
    }

    public function test_each_company_sees_only_its_own_records(): void
    {
        [$hr, $ownEmployee, , $otherEmployee] = $this->twoCompanies();

        $this->actingAs($hr)->get('/employees')->assertOk()->assertSee('Abena')->assertDontSee('Yaa');
        $this->actingAs($hr)->get("/employees/{$ownEmployee->id}")->assertOk();
        $this->actingAs($hr)->get("/employees/{$otherEmployee->id}")->assertNotFound();
        $this->actingAs($hr)->get('/employees?search=Yaa')->assertDontSee($otherEmployee->employee_no);

        // Forms cannot point at another company's records.
        $this->actingAs($hr)->post('/leave', [
            'employee_id' => $otherEmployee->id, 'leave_type_id' => 'x', 'start_date' => now()->toDateString(), 'end_date' => now()->toDateString(),
        ])->assertSessionHasErrors('employee_id');
    }

    public function test_names_and_numbers_only_need_to_be_unique_within_a_company(): void
    {
        [, , $other] = $this->twoCompanies();

        // makeEmployee created an OPS department in both companies.
        $this->assertSame(2, Department::withoutGlobalScope('company')->where('code', 'OPS')->count());
        $this->assertSame(['EMP-0001', 'EMP-0001'], [
            Tenant::run($this->company->id, fn () => app(\App\Services\EmployeeService::class)->nextEmployeeNo()),
            Tenant::run($other->id, fn () => app(\App\Services\EmployeeService::class)->nextEmployeeNo()),
        ]);
    }

    public function test_the_system_admin_can_open_a_company_and_leave_it(): void
    {
        [, , $other] = $this->twoCompanies();
        $root = $this->systemAdmin();
        Tenant::forget();

        $this->actingAs($root)->post("/system/companies/{$other->id}/open")->assertRedirect('/');
        $this->actingAs($root)->get('/employees')->assertOk()->assertSee('Yaa')->assertDontSee('Abena')
            ->assertSee('as the system administrator');
        $this->actingAs($root)->get('/users')->assertOk();

        $this->actingAs($root)->post('/system/leave')->assertRedirect('/system/companies');
        $this->actingAs($root)->get('/employees')->assertRedirect('/system/companies');
        $this->assertSame(Role::SYSTEM_ADMIN, $root->fresh()->role);
        $this->assertNull($root->fresh()->company_id);
    }

    public function test_each_company_gets_its_own_colours(): void
    {
        [$hr, , $other] = $this->twoCompanies();
        $root = $this->systemAdmin();
        Tenant::forget();
        $fields = ['name' => 'Other Ltd', 'legal_name' => 'Other Limited', 'country' => 'Ghana', 'currency' => 'GHS'];

        $this->actingAs($root)->patch("/system/companies/{$other->id}", $fields + ['theme_primary' => '#ffffcc', 'theme_accent' => '#0f7c83', 'is_active' => '1'])
            ->assertSessionHasErrors('theme_primary');
        $this->actingAs($root)->patch("/system/companies/{$other->id}", $fields + ['theme_primary' => '#4a1426', 'theme_accent' => '#a3264b', 'is_active' => '1'])
            ->assertSessionHasNoErrors();

        // The first company keeps the standard colours; the other company's pages use its own.
        $this->actingAs($hr)->get('/employees')->assertOk()->assertDontSee('--color-navy:#4a1426', false);
        $otherHr = Tenant::run($other->id, fn () => $this->makeUser(Role::HR_ADMIN));
        Tenant::forget();
        $this->actingAs($otherHr)->get('/employees')->assertOk()->assertSee('--color-navy:#4a1426;', false)->assertSee('--color-accent-strong:#a3264b;', false);
    }

    public function test_demo_staff_can_be_added_to_any_empty_company(): void
    {
        $this->seed(\Database\Seeders\DemoSeeder::class); // the first company, as on a new installation
        $danlice = app(CompanySetup::class)->create(['name' => 'Danlice HR', 'legal_name' => 'Danlice HR Ltd', 'theme_primary' => '#173d2c']);
        Tenant::forget();

        $this->assertSame('danlice.gh', \App\Console\Commands\AddDemoData::domain('Danlice HR'));
        $this->artisan('hr:demo', ['company' => 'Danlice HR'])->assertSuccessful();
        $this->artisan('hr:demo', ['company' => 'Danlice HR'])->assertFailed(); // only once
        $this->artisan('hr:demo', ['company' => 'Nobody'])->assertFailed();

        $count = fn (string $model) => $model::withoutGlobalScope('company')->where('company_id', $danlice->id)->count();
        $this->assertSame([18, 18], [$count(Employee::class), $count(User::class)]);
        $this->assertGreaterThan(0, $count(\App\Models\AttendanceRecord::class));
        $this->assertSame(['Danlice HR', '#173d2c'], [$danlice->fresh()->name, $danlice->fresh()->theme_primary]);

        // Its staff sign in to their own company only.
        $this->post('/login', ['email' => 'kojo.mensah@danlice.gh', 'password' => \Database\Seeders\DemoSeeder::PASSWORD])->assertRedirect('/');
        $this->get('/employees')->assertOk()->assertSee('danlice.gh')->assertDontSee('zentrix.gh');
    }

    public function test_a_suspended_company_cannot_sign_in(): void
    {
        [$hr, , $other] = $this->twoCompanies();
        $root = $this->systemAdmin();
        Tenant::forget();

        $this->actingAs($root)->patch("/system/companies/{$this->company->id}", [
            'name' => 'Test Company', 'legal_name' => 'Test Company Ltd', 'country' => 'Ghana', 'currency' => 'GHS', 'is_active' => '0',
        ])->assertSessionHasNoErrors();
        $this->assertFalse($this->company->fresh()->is_active);
        $this->assertTrue($other->fresh()->is_active);

        $this->actingAs($hr)->get('/')->assertRedirect('/login');
        $this->post('/login', ['email' => $hr->email, 'password' => 'Secret123'])->assertSessionHasErrors('email');
        $this->assertGuest();
    }

    public function test_reminders_and_payroll_run_for_every_company(): void
    {
        [, , $other] = $this->twoCompanies();
        $this->seed(\Database\Seeders\ReferenceDataSeeder::class);

        $this->artisan('hr:payroll-month')->assertSuccessful();
        $runs = \App\Models\PayrollRun::withoutGlobalScope('company')->pluck('company_id')->sort()->values()->all();
        $this->assertSame(collect([$this->company->id, $other->id])->sort()->values()->all(), $runs);
        $this->artisan('hr:reminders')->assertSuccessful();
    }

    public function test_an_existing_account_can_become_the_system_admin(): void
    {
        $lam = $this->makeUser(Role::SUPER_ADMIN, ['username' => 'Lam']);
        Tenant::forget();

        $this->artisan('hr:system-admin', ['login' => 'Lam'])->assertSuccessful();
        $this->assertSame([Role::SYSTEM_ADMIN, null], [$lam->fresh()->role, $lam->fresh()->company_id]);
        $this->artisan('hr:system-admin', ['login' => 'nobody'])->assertFailed();
    }
}
