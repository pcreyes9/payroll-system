<?php

namespace App\Services\Attendance;

use App\Models\AttendanceRecord;
use App\Models\Setting;
use Carbon\Carbon;

class AttendanceCalculator
{
    /**
     * Calculate attendance information using
     * the current attendance settings.
     */
    public function calculate(AttendanceRecord $attendance): AttendanceRecord
    {
        if (! $attendance->time_in || ! $attendance->time_out) {
            return $attendance;
        }

        $date = $attendance->attendance_date->format('Y-m-d');

        /*
         * -------------------------------------------------
         * ACTUAL TIME IN / OUT
         * -------------------------------------------------
         */

        $timeIn = Carbon::parse(
            $date . ' ' . $this->normalizeTime($attendance->time_in)
        );

        $timeOut = Carbon::parse(
            $date . ' ' . $this->normalizeTime($attendance->time_out)
        );

        /*
         * -------------------------------------------------
         * OVERNIGHT ATTENDANCE
         * -------------------------------------------------
         *
         * If time out is earlier than or equal to
         * time in, assume the employee worked overnight.
         */

        if ($timeOut->lessThanOrEqualTo($timeIn)) {
            $timeOut->addDay();
        }

        /*
         * -------------------------------------------------
         * LOAD SETTINGS
         * -------------------------------------------------
         */

        $officialTimeIn = Setting::getValue(
            'attendance',
            'official_time_in',
            '09:00'
        );

        $officialTimeOut = Setting::getValue(
            'attendance',
            'official_time_out',
            '17:00'
        );

        $gracePeriod = (int) Setting::getValue(
            'attendance',
            'grace_period_minutes',
            15
        );

        $workdays = Setting::getValue(
            'attendance',
            'workdays',
            [1, 2, 3, 4, 5]
        );

        $overtimeEnabled = (bool) Setting::getValue(
            'attendance',
            'overtime_enabled',
            true
        );

        $minimumOvertimeMinutes = (int) Setting::getValue(
            'attendance',
            'minimum_overtime_minutes',
            0
        );

        /*
         * -------------------------------------------------
         * DETERMINE WORKING DAY
         * -------------------------------------------------
         *
         * Carbon dayOfWeekIso:
         *
         * 1 = Monday
         * 2 = Tuesday
         * 3 = Wednesday
         * 4 = Thursday
         * 5 = Friday
         * 6 = Saturday
         * 7 = Sunday
         */

        $dayOfWeek = $attendance
            ->attendance_date
            ->dayOfWeekIso;

        $isWorkingDay = in_array(
            $dayOfWeek,
            $workdays,
            true
        );

        /*
         * -------------------------------------------------
         * OFFICIAL SCHEDULE
         * -------------------------------------------------
         */

        $officialTimeInCarbon = Carbon::parse(
            $date . ' ' . $officialTimeIn
        );

        $officialTimeOutCarbon = Carbon::parse(
            $date . ' ' . $officialTimeOut
        );

        /*
         * -------------------------------------------------
         * LATE
         * -------------------------------------------------
         *
         * IMPORTANT:
         *
         * The grace period ONLY determines when
         * lateness begins.
         *
         * Once the grace period is exceeded,
         * late minutes are counted from the
         * OFFICIAL TIME IN.
         *
         * Example:
         *
         * Official time = 09:00
         * Grace period   = 15 minutes
         *
         * 09:00 = 0 late
         * 09:10 = 0 late
         * 09:15 = 0 late
         * 09:16 = 16 late
         * 09:20 = 20 late
         * 09:30 = 30 late
         */

        $lateMinutes = 0;

        if ($isWorkingDay) {

            $lateThreshold = $officialTimeInCarbon
                ->copy()
                ->addMinutes($gracePeriod);

            if ($timeIn->greaterThan($lateThreshold)) {

                $lateMinutes = $officialTimeInCarbon
                    ->diffInMinutes($timeIn);
            }
        }

        /*
         * -------------------------------------------------
         * WORKED MINUTES
         * -------------------------------------------------
         *
         * Lunch is INCLUDED in the regular work period.
         *
         * Example:
         *
         * 09:00 -> 17:00
         *
         * = 480 minutes
         */

        $workedMinutes = $timeIn->diffInMinutes(
            $timeOut
        );

        /*
         * -------------------------------------------------
         * UNDERTIME
         * -------------------------------------------------
         *
         * Only applies to regular working days.
         *
         * Example:
         *
         * Official time out = 17:00
         * Actual time out   = 16:30
         *
         * Undertime = 30 minutes
         */

        $undertimeMinutes = 0;

        if (
            $isWorkingDay
            && $timeOut->lessThan($officialTimeOutCarbon)
        ) {
            $undertimeMinutes = $timeOut->diffInMinutes(
                $officialTimeOutCarbon
            );
        }

        /*
         * -------------------------------------------------
         * OVERTIME
         * -------------------------------------------------
         *
         * Overtime starts after official time out.
         */

        $overtimeMinutes = 0;

        if (
            $overtimeEnabled
            && $timeOut->greaterThan($officialTimeOutCarbon)
        ) {
            $overtimeMinutes = $officialTimeOutCarbon
                ->diffInMinutes($timeOut);

            /*
             * Minimum overtime threshold.
             *
             * Example:
             *
             * Minimum OT = 30 minutes
             *
             * 17:15 = 0 OT
             * 17:30 = 30 OT
             * 18:00 = 60 OT
             */

            if (
                $overtimeMinutes < $minimumOvertimeMinutes
            ) {
                $overtimeMinutes = 0;
            }
        }

        /*
         * -------------------------------------------------
         * STATUS
         * -------------------------------------------------
         */

        if ($workedMinutes <= 0) {

            $status = 'absent';

        } elseif (! $isWorkingDay) {

            $status = 'rest_day';

        } else {

            $status = 'present';
        }

        /*
         * -------------------------------------------------
         * SAVE CALCULATED VALUES
         * -------------------------------------------------
         */

        $attendance->update([
            'worked_minutes' => max(
                0,
                $workedMinutes
            ),

            'late_minutes' => max(
                0,
                $lateMinutes
            ),

            'undertime_minutes' => max(
                0,
                $undertimeMinutes
            ),

            'overtime_minutes' => max(
                0,
                $overtimeMinutes
            ),

            'status' => $status,
        ]);

        return $attendance->fresh();
    }

    /**
     * Normalize a database TIME value.
     */
    private function normalizeTime(mixed $time): string
    {
        if ($time instanceof Carbon) {
            return $time->format('H:i:s');
        }

        return Carbon::parse($time)->format('H:i:s');
    }
}
