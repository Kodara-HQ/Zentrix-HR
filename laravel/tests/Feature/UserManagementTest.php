<?php

namespace Tests\Feature;

use App\Enums\Role;
use App\Models\Department;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class UserManagementTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        $this->admin = $this->makeUser(Role::SUPER_ADMIN, ['username' => 'boss']);
    }

    private function form(array $overrides = []): array
    {
        return $overrides + ['full_name' => 'Adwoa Frimpong', 'username' => 'adwoa', 'email' => 'Adwoa@Zentrix.test', 'role' => 'PAYROLL_OFFICER', 'password' => 'a-long-initial-pass'];
    }

    public function test_only_super_administrators_manage_accounts(): void
    {
        $this->actingAs($this->admin)->get('/users')->assertOk()->assertSee('User management')->assertSee('boss');
        $this->actingAs($this->makeUser(Role::HR_MANAGER))->get('/users')->assertForbidden();
        $this->actingAs($this->makeUser(Role::HR_MANAGER))->post('/users', $this->form())->assertForbidden();
    }

    public function test_creating_an_account_requires_a_strong_initial_password_and_a_free_username(): void
    {
        $this->actingAs($this->admin)->post('/users', $this->form(['password' => 'short']))->assertSessionHasErrors(['password' => 'Passwords must be at least 12 characters.']);
        $this->actingAs($this->admin)->post('/users', $this->form(['username' => 'boss']))->assertSessionHasErrors(['username' => 'That username is already in use.']);
        $this->actingAs($this->admin)->post('/users', $this->form(['email' => $this->admin->email]))->assertSessionHasErrors(['email' => 'Another account already uses that email.']);

        $this->actingAs($this->admin)->post('/users', $this->form())->assertSessionHasNoErrors();
        $user = User::firstWhere('username', 'adwoa');
        $this->assertSame(['Adwoa', 'Frimpong', 'adwoa@zentrix.test'], [$user->first_name, $user->last_name, $user->email]);
        $this->assertTrue($user->must_change_password);

        $this->post('/logout');
        $this->post('/login', ['email' => 'adwoa', 'password' => 'a-long-initial-pass'])->assertRedirect('/');
        $this->get('/')->assertRedirect('/password');
    }

    public function test_linking_an_employee_gives_managers_their_department(): void
    {
        $finance = Department::create(['code' => 'FIN', 'name' => 'Finance']);
        $manager = $this->makeEmployee(['department_id' => $finance->id]);
        $report = $this->makeEmployee(['department_id' => $finance->id, 'first_name' => 'Nana', 'last_name' => 'Yeboah']);

        $this->actingAs($this->admin)->post('/users', $this->form(['role' => 'DEPARTMENT_MANAGER', 'employee_id' => $manager->id]));
        $user = User::firstWhere('username', 'adwoa');
        $this->assertSame($finance->id, $user->department_id);
        $this->actingAs($user->refresh()->forceFill(['must_change_password' => false]))->get('/employees')->assertSee('Nana Yeboah');

        $this->actingAs($this->admin)->post('/users', $this->form(['username' => 'second', 'email' => null, 'employee_id' => $manager->id]))
            ->assertSessionHasErrors(['employee_id' => 'That employee already has a sign-in.']);
        $this->assertNotNull($report);
    }

    public function test_edit_reset_deactivate_and_delete(): void
    {
        $user = $this->makeUser(Role::SUPERVISOR, ['username' => 'ama']);

        $this->actingAs($this->admin)->patch("/users/{$user->id}", ['full_name' => 'Ama Adjei Mensah', 'username' => 'ama.adjei', 'email' => '', 'role' => 'HR_ADMIN']);
        $user->refresh();
        $this->assertSame(['Ama', 'Adjei Mensah', 'ama.adjei', null, 'HR_ADMIN'], [$user->first_name, $user->last_name, $user->username, $user->email, $user->role->value]);

        $this->actingAs($this->admin)->post("/users/{$user->id}/password", ['password' => 'another-long-one']);
        $this->assertTrue($user->fresh()->must_change_password);
        $this->assertTrue(password_verify('another-long-one', $user->fresh()->password_hash));

        $this->actingAs($this->admin)->post("/users/{$user->id}/toggle");
        $this->assertFalse($user->fresh()->is_active);
        $this->actingAs($user->fresh())->get('/')->assertRedirect('/login');

        $this->actingAs($this->admin)->post("/users/{$user->id}/toggle");
        $this->assertTrue($user->fresh()->is_active);

        $this->actingAs($this->admin)->delete("/users/{$user->id}");
        $this->assertNull(User::find($user->id));
    }

    public function test_the_last_super_administrator_and_your_own_account_are_protected(): void
    {
        $this->actingAs($this->admin)->post("/users/{$this->admin->id}/toggle")->assertStatus(422);
        $this->actingAs($this->admin)->delete("/users/{$this->admin->id}")->assertStatus(422);
        $this->actingAs($this->admin)->patch("/users/{$this->admin->id}", ['full_name' => 'Boss', 'username' => 'boss', 'role' => 'HR_ADMIN'])
            ->assertSessionHasErrors(['role' => 'Keep at least one active super administrator.']);
        $this->assertSame('SUPER_ADMIN', $this->admin->fresh()->role->value);

        $second = $this->makeUser(Role::SUPER_ADMIN);
        $this->actingAs($second)->post("/users/{$this->admin->id}/toggle");
        $this->assertFalse($this->admin->fresh()->is_active);
        $this->actingAs($second)->delete("/users/{$second->id}")->assertStatus(422);
    }
}
