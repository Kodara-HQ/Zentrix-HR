<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CreateAdminTest extends TestCase
{
    use RefreshDatabase;

    public function test_creates_an_admin_by_username_once_and_never_resets_a_changed_password(): void
    {
        \App\Support\Tenant::forget(); // the system administrator works outside any company
        $this->artisan('hr:admin', ['--username' => 'Lam', '--first' => 'Emmanuel', '--last' => 'Lamadeku', '--password' => 'First-pass-2026', '--if-missing' => true])->assertSuccessful();
        $user = User::firstWhere('username', 'Lam');
        $this->assertSame(['Emmanuel', 'Lamadeku', 'SYSTEM_ADMIN', null, null], [$user->first_name, $user->last_name, $user->role->value, $user->email, $user->company_id]);
        $this->assertTrue($user->must_change_password);

        $this->post('/login', ['email' => 'lam', 'password' => 'First-pass-2026'])->assertRedirect('/');
        $this->get('/')->assertRedirect('/password');
        $this->put('/password', ['current_password' => 'First-pass-2026', 'new_password' => 'Own-choice-77']);
        $this->get('/')->assertRedirect('/system/companies');

        // The next container start runs the same command again: the changed password must survive.
        $this->artisan('hr:admin', ['--username' => 'Lam', '--password' => 'First-pass-2026', '--if-missing' => true])->expectsOutputToContain('left unchanged')->assertSuccessful();
        $this->assertTrue(password_verify('Own-choice-77', $user->fresh()->password_hash));
        $this->assertFalse($user->fresh()->must_change_password);
        $this->assertSame(1, User::where('username', 'Lam')->count());
    }

    public function test_needs_an_email_or_username(): void
    {
        $this->artisan('hr:admin')->assertFailed();
    }
}
