<?php

use Illuminate\Support\Facades\Schedule;

// Run `php artisan schedule:work` (or a cron entry for `php artisan schedule:run`) so these happen.
Schedule::command('hr:payroll-month')->dailyAt('06:00')->withoutOverlapping();
Schedule::command('hr:reminders')->hourly()->withoutOverlapping();
