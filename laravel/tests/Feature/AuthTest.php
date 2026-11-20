<?php

namespace Tests\Feature;

use App\Enums\Role;
use App\Models\AuditLog;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AuthTest extends TestCase
{
    use RefreshDatabase;

    public function test_guests_are_sent_to_sign_in(): void
    {
        \App\Support\Tenant::forget(); // a guest is in no company
        $this->get('/')->assertRedirect('/login');
        $this->get('/login')->assertOk()->assertSee('Zentrix HR')->assertSee('Sign in to your HR workspace.')->assertSee('Username or work email');
    }

    public function test_signs_in_with_email_in_any_case_or_username(): void
    {
        $user = $this->makeUser(attributes: ['email' => 'ama@zentrix.test', 'username' => 'ama.mensah']);

        $this->post('/login', ['email' => 'AMA@Zentrix.test', 'password' => 'Secret123'])->assertRedirect('/');
        $this->assertAuthenticatedAs($user);
        $this->assertNotNull($user->fresh()->last_login_at);
        $this->assertSame('LOGIN', AuditLog::first()->action);

        $this->post('/logout');
        $this->post('/login', ['email' => 'ama.mensah', 'password' => 'Secret123'])->assertRedirect('/');
        $this->assertAuthenticatedAs($user);
    }

    public function test_accepts_password_hashes_written_by_the_node_version(): void
    {
        // bcryptjs writes $2a$/$2b$ hashes; existing accounts must keep working after the move.
        $nodeHash = '$2b$'.substr(password_hash('Secret123', PASSWORD_BCRYPT, ['cost' => 10]), 4);
        $this->makeUser(attributes: ['email' => 'old@zentrix.test', 'password_hash' => $nodeHash]);

        $this->post('/login', ['email' => 'old@zentrix.test', 'password' => 'Secret123'])->assertRedirect('/');
        $this->assertAuthenticated();
    }

    public function test_rejects_wrong_password_and_inactive_accounts(): void
    {
        $this->makeUser(attributes: ['email' => 'ama@zentrix.test']);
        $this->makeUser(attributes: ['email' => 'gone@zentrix.test', 'is_active' => false]);

        $this->post('/login', ['email' => 'ama@zentrix.test', 'password' => 'wrong'])->assertSessionHasErrors(['email' => 'Email or password is incorrect.']);
        $this->post('/login', ['email' => 'gone@zentrix.test', 'password' => 'Secret123'])->assertSessionHasErrors('email');
        $this->assertGuest();
    }

    public function test_temporary_password_must_be_changed_first(): void
    {
        $user = $this->makeUser(attributes: ['must_change_password' => true]);

        $this->actingAs($user)->get('/')->assertRedirect('/password');
        $this->actingAs($user)->get('/employees')->assertRedirect('/password');

        $this->actingAs($user)->put('/password', ['current_password' => 'Secret123', 'new_password' => 'short'])->assertSessionHasErrors('new_password');
        $this->actingAs($user)->put('/password', ['current_password' => 'nope', 'new_password' => 'Better2026'])->assertSessionHasErrors('current_password');
        $this->actingAs($user)->put('/password', ['current_password' => 'Secret123', 'new_password' => 'Better2026'])->assertRedirect('/');

        $this->assertFalse($user->fresh()->must_change_password);
        $this->actingAs($user->fresh())->get('/')->assertOk();
    }

    public function test_employees_only_reach_their_self_service_pages(): void
    {
        $employee = $this->makeEmployee();
        $other = $this->makeEmployee();
        $user = $this->makeUser(Role::EMPLOYEE, ['employee_id' => $employee->id]);

        foreach (['/', '/attendance', '/leave', '/payroll', '/notifications', '/settings', "/employees/{$employee->id}"] as $path) {
            $this->actingAs($user)->get($path)->assertOk();
        }
        foreach (['/employees', '/recruitment', '/users', "/employees/{$other->id}"] as $path) {
            $this->actingAs($user)->get($path)->assertRedirect('/');
        }
        $this->actingAs($user)->get('/')->assertSee('Employee Self Service Portal')->assertSee('My payslips')->assertDontSee('Recruitment');
    }

    public function test_menu_and_pages_follow_roles(): void
    {
        $payroll = $this->makeUser(Role::PAYROLL_OFFICER);
        $this->actingAs($payroll)->get('/')->assertSee('Payroll')->assertDontSee('Recruitment')->assertDontSee('User management');
        $this->actingAs($payroll)->get('/recruitment')->assertForbidden();
        $this->actingAs($payroll)->get('/payroll')->assertOk();

        $admin = $this->makeUser(Role::SUPER_ADMIN);
        $this->actingAs($admin)->get('/users')->assertOk();
        $this->actingAs($admin)->get('/recruitment')->assertOk();
    }
}
