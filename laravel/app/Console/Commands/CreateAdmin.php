<?php

namespace App\Console\Commands;

use App\Enums\Role;
use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * Creates the first super administrator (the database starts without users) or resets an existing
 * one's password. Replaces RESET_USER_EMAIL / RESET_USER_PASSWORD from the Node version.
 */
class CreateAdmin extends Command
{
    protected $signature = 'hr:admin {email} {--first=System} {--last=Administrator} {--password= : Leave empty to generate one}';

    protected $description = 'Create a super administrator, or reset the password of an existing account';

    public function handle(): int
    {
        $email = strtolower(trim($this->argument('email')));
        $password = $this->option('password') ?: Str::password(14, symbols: false);

        $user = User::where('email', $email)->first();
        if ($user) {
            $user->forceFill(['password_hash' => Hash::make($password), 'is_active' => true, 'must_change_password' => true])->save();
            $this->info("Password reset for {$email}.");
        } else {
            User::create([
                'email' => $email,
                'password_hash' => Hash::make($password),
                'role' => Role::SUPER_ADMIN,
                'first_name' => $this->option('first'),
                'last_name' => $this->option('last'),
                'is_active' => true,
                'must_change_password' => true,
            ]);
            $this->info("Super administrator {$email} created.");
        }

        if (! $this->option('password')) {
            $this->line("Temporary password: {$password}");
        }
        $this->line('It must be changed at the first sign-in.');

        return self::SUCCESS;
    }
}
