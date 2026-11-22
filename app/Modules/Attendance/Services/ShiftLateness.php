<?php

declare(strict_types=1);

namespace App\Modules\Attendance\Services;

use App\Modules\Attendance\Models\AttendancePolicy;
use App\Modules\Attendance\Models\AttendanceShift;
use App\Modules\Employees\Models\Employee;
use App\Modules\Settings\Models\CompanySetting;
use Illuminate\Support\Carbon;

/**
 * Minutes late for a first check-in, from the employee's shift. No shift means 0,
 * so companies that never create shifts behave exactly as before.
 * The shift start is read in the company timezone. For overnight shifts the start
 * closest to the check-in (yesterday, today or tomorrow) is used, so 01:00 on a
 * 22:00 to 06:00 shift is measured from 22:00 the evening before.
 */
class ShiftLateness
{
    public function minutesLate(Employee $employee, Carbon $checkIn): int
    {
        if (! $employee->shift_id) {
            return 0;
        }

        $shift = AttendanceShift::query()
            ->where('tenant_id', $employee->tenant_id)
            ->find($employee->shift_id);
        if (! $shift) {
            return 0;
        }

        $tz = CompanySetting::query()->where('tenant_id', $employee->tenant_id)->value('timezone') ?: 'Africa/Cairo';
        $local = $checkIn->copy()->setTimezone($tz);
        [$h, $m] = array_map('intval', explode(':', substr((string) $shift->start_time, 0, 5)));

        $best = null;
        foreach ([-1, 0, 1] as $offset) {
            $start = $local->copy()->startOfDay()->addDays($offset)->setTime($h, $m);
            $diff = $local->diffInMinutes($start, false); // negative when check-in is after start
            if ($best === null || abs($diff) < abs($best)) {
                $best = $diff;
            }
        }

        $grace = $shift->grace_minutes
            ?? (int) AttendancePolicy::query()->where('tenant_id', $employee->tenant_id)->value('late_grace_minutes');

        return max(0, (int) (-$best) - $grace);
    }
}
