<?php

namespace App\Support;

use App\Models\Holiday;
use App\Models\WorkingCalendar;
use Carbon\CarbonImmutable;

/** Working days and public holidays, ported from server/src/lib/calendar.ts. Days of week: 0 = Sunday … 6 = Saturday. */
class WorkCalendar
{
    /** @return int[] */
    public static function workDays(): array
    {
        $days = WorkingCalendar::where('is_active', true)->value('work_days');
        $days = is_string($days) ? json_decode($days, true) : $days;

        return $days ?: [1, 2, 3, 4, 5];
    }

    /**
     * Holiday dates (Y-m-d) between two dates. Recurring holidays repeat on the same day and month every year.
     *
     * @return array<string, true>
     */
    public static function holidays(CarbonImmutable $from, CarbonImmutable $to): array
    {
        $set = [];
        foreach (Holiday::all() as $holiday) {
            $date = CarbonImmutable::parse($holiday->date);
            if ($holiday->recurring) {
                for ($year = $from->year; $year <= $to->year; $year++) {
                    $set[$date->setYear($year)->toDateString()] = true;
                }
            } else {
                $set[$date->toDateString()] = true;
            }
        }

        return array_filter($set, fn ($_, $day) => $day >= $from->toDateString() && $day <= $to->toDateString(), ARRAY_FILTER_USE_BOTH);
    }

    /** Leave days between two dates inclusive: every day for CALENDAR_DAYS, otherwise working days that are not holidays. */
    public static function countDays(CarbonImmutable $start, CarbonImmutable $end, string $mode, ?array $workDays = null, ?array $holidays = null): int
    {
        $workDays ??= self::workDays();
        $holidays ??= self::holidays($start, $end);
        $days = 0;
        for ($day = $start; $day->lte($end); $day = $day->addDay()) {
            if ($mode === 'CALENDAR_DAYS' || (in_array($day->dayOfWeek, $workDays, true) && ! isset($holidays[$day->toDateString()]))) {
                $days++;
            }
        }

        return $days;
    }
}
