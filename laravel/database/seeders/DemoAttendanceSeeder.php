<?php

namespace Database\Seeders;

use App\Models\AttendanceRecord;
use App\Models\Company;
use App\Models\Employee;
use App\Models\ShiftAssignment;
use App\Models\ShiftPattern;
use App\Support\Tenant;
use App\Support\WorkCalendar;
use Carbon\CarbonImmutable;
use Illuminate\Database\Seeder;

/**
 * Shift patterns, shift assignments and four weeks of fingerprint punches for the demo staff.
 * Ported from server/src/seed.ts. Runs on its own too: php artisan db:seed --class=DemoAttendanceSeeder
 */
class DemoAttendanceSeeder extends Seeder
{
    public function run(): void
    {
        if (! Tenant::id()) {
            Tenant::run(Company::orderBy('id')->value('id'), fn () => $this->run());

            return;
        }

        $day = ShiftPattern::firstOrCreate(['name' => 'General day'], ['start_time' => '08:00', 'end_time' => '17:00', 'grace_minutes' => 15, 'work_days' => [1, 2, 3, 4, 5]]);
        $warehouse = ShiftPattern::firstOrCreate(['name' => 'Warehouse morning'], ['start_time' => '07:00', 'end_time' => '15:00', 'grace_minutes' => 10, 'work_days' => [1, 2, 3, 4, 5, 6]]);

        $staff = Employee::with('department')->whereIn('status', ['ACTIVE', 'PROBATION'])->get();
        foreach ($staff as $employee) {
            ShiftAssignment::firstOrCreate(['employee_id' => $employee->id], [
                'shift_id' => ($employee->department?->code === 'WH' ? $warehouse : $day)->id,
                'effective_from' => $employee->date_of_employment,
            ]);
        }

        if (AttendanceRecord::exists()) {
            $this->command?->warn('Attendance already exists; no sample punches were added.');

            return;
        }

        $today = CarbonImmutable::today();
        $holidays = WorkCalendar::holidays($today->subDays(28), $today);
        foreach ($staff as $employee) {
            $warehouseShift = $employee->department?->code === 'WH';
            for ($offset = 28; $offset >= 1; $offset--) {
                $date = $today->subDays($offset);
                if ($date->isWeekend() || isset($holidays[$date->toDateString()]) || $date->lt($employee->date_of_employment)) {
                    continue;
                }
                // Deterministic mix: about 1 in 20 absent, 1 in 10 late, the rest on time.
                $roll = array_sum(array_map('ord', str_split($employee->employee_no.$offset))) % 20;
                $start = $date->setTime($warehouseShift ? 7 : 8, 0);
                if ($roll === 0) {
                    AttendanceRecord::create(['employee_id' => $employee->id, 'date' => $date->toDateString(), 'status' => 'ABSENT', 'source' => 'BIOMETRIC']);

                    continue;
                }
                $late = $roll < 3 ? 20 + $roll * 7 : 0;
                AttendanceRecord::create([
                    'employee_id' => $employee->id,
                    'date' => $date->toDateString(),
                    'clock_in' => $start->addMinutes($late ?: $roll % 10),
                    'clock_out' => $start->addHours($warehouseShift ? 8 : 9)->addMinutes($roll % 15),
                    'status' => $late ? 'LATE' : 'PRESENT',
                    'late_minutes' => $late,
                    'source' => 'BIOMETRIC',
                    'notes' => 'Airport City lobby',
                ]);
            }
        }
        $this->command?->info('Added four weeks of sample attendance.');
    }
}
