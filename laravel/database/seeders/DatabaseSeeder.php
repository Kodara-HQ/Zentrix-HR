<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * `php artisan db:seed` loads the settings every installation needs.
     * `php artisan db:seed --class=DemoSeeder` adds a sample company with 18 staff for trying the system out.
     * Create the first real sign-in with `php artisan hr:admin`.
     */
    public function run(): void
    {
        $this->call(ReferenceDataSeeder::class);
    }
}
