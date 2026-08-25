<?php

namespace App\Services;

use Carbon\Carbon;

class BusinessHours
{
    /**
     * Calculate difference in business minutes between two dates.
     * Default hours: Monday-Friday 08:00 - 18:00 in Africa/Dakar timezone.
     */
    public static function diffInMinutes(Carbon $startDate, Carbon $endDate, array $workingDays = [1, 2, 3, 4, 5], int $startHour = 8, int $endHour = 18): int
    {
        $timezone = 'Africa/Dakar';
        $start = $startDate->copy()->setTimezone($timezone);
        $end = $endDate->copy()->setTimezone($timezone);

        if ($start->gt($end)) {
            return 0;
        }

        $totalMinutes = 0;
        $current = $start->copy();

        while ($current->lt($end)) {
            $dayOfWeek = $current->dayOfWeek; // 1 (Mon) to 7 (Sun)
            if ($dayOfWeek === 0) $dayOfWeek = 7;

            if (in_array($dayOfWeek, $workingDays)) {
                $dayStart = $current->copy()->setTime($startHour, 0, 0);
                $dayEnd = $current->copy()->setTime($endHour, 0, 0);

                if ($current->between($dayStart, $dayEnd)) {
                    $nextBoundary = $current->copy()->addMinute();
                    if ($nextBoundary->lte($end) && $nextBoundary->lte($dayEnd)) {
                        $totalMinutes++;
                    }
                }
            }
            $current->addMinute();
        }

        return $totalMinutes;
    }
}
