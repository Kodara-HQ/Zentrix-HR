<?php

namespace App\Console\Commands;

use App\Enums\Role;
use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * Creates a super administrator (the database starts without users) or resets an existing one's password.
 * Find the account by email (argument) or --username. With --if-missing an existing account is left
 * untouched, so the container can run this on every start without undoing a changed password.
 * Replaces RESET_USER_EMAIL / RESET_USER_PASSWORD from the Node version.
 */
class CreateAdmin extends Command
{
    protected $signature = 'hr:admin
        {email? : Work email}
        {--username= : Sign-in username}
        {--first=System} {--last=Administrator}
        {--password= : Leave empty to generate one}
        {--if-missing : Do nothing when the account already exists}';

    protected $description = 'Create a super administrator, or reset the password of an existing account';

    public function handle(): int
    {
        $email = $this->argument('email') ? strtolower(trim($this->argument('email'))) : null;
        $username = $this->option('username') ? trim($this->option('username')) : null;
        if (! $email && ! $username) {
            $this->error('Give an email or --username.');

            return self::FAILURE;
        }
        $label = $username ?? $email;

        $user = User::query()
            ->when($email, fn ($q) => $q->orWhere('email', $email))
            ->when($username, fn ($q) => $q->orWhere('username', $username))
            ->first();

        if ($user && $this->option('if-missing')) {
            $this->line("{$label} already exists; left unchanged.");

            return self::SUCCESS;
        }

        $password = $this->option('password') ?: Str::password(14, symbols: false);

        if ($user) {
            $user->forceFill(['password_hash' => Hash::make($password), 'is_active' => true, 'must_change_password' => true])->save();
            $this->info("Password reset for {$label}.");
        } else {
            User::create([
                'email' => $email,
                'username' => $username,
                'password_hash' => Hash::make($password),
                'role' => Role::SUPER_ADMIN,
                'first_name' => $this->option('first'),
                'last_name' => $this->option('last'),
                'is_active' => true,
                'must_change_password' => true,
            ]);
            $this->info("Super administrator {$label} created.");
        }

        if (! $this->option('password')) {
            $this->line("Temporary password: {$password}");
        }
        $this->line('It must be changed at the first sign-in.');

        return self::SUCCESS;
    }
}
