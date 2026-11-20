<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CronTest extends TestCase
{
    use RefreshDatabase;

    public function test_daily_cron_needs_the_secret(): void
    {
        config(['services.cron.secret' => null]);
        $this->get('/cron/daily', ['Authorization' => 'Bearer '])->assertNotFound();

        config(['services.cron.secret' => 'cron-test-secret']);
        $this->get('/cron/daily')->assertNotFound();
        $this->get('/cron/daily', ['Authorization' => 'Bearer wrong'])->assertNotFound();
    }

    public function test_daily_cron_runs_reminders_and_payroll(): void
    {
        $this->seed(\Database\Seeders\ReferenceDataSeeder::class);
        config(['services.cron.secret' => 'cron-test-secret']);
        $this->get('/cron/daily', ['Authorization' => 'Bearer cron-test-secret'])
            ->assertOk()
            ->assertExactJson(['hr:reminders' => 'ok', 'hr:payroll-month' => 'ok']);
    }
}
