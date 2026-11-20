<?php

namespace App\Console\Commands;

use App\Enums\Role;
use App\Models\User;
use Illuminate\Console\Command;

/**
 * Turns an existing account into the system administrator, who sets up companies and can open any of
 * them. The account leaves its company; its password is not touched.
 */
class MakeSystemAdmin extends Command
{
    protected $signature = 'hr:system-admin {login : Username or email of an existing account}';

    protected $description = 'Make an existing account the system administrator';

    public function handle(): int
    {
        $login = trim($this->argument('login'));
        $user = User::withoutGlobalScope('company')->where('username', $login)->orWhere('email', $login)->first();
        if (! $user) {
            $this->error("No account {$login}.");

            return self::FAILURE;
        }

        $user->forceFill(['role' => Role::SYSTEM_ADMIN, 'company_id' => null, 'employee_id' => null, 'department_id' => null, 'location_id' => null])->save();
        $this->info("{$login} is now the system administrator.");

        return self::SUCCESS;
    }
}
