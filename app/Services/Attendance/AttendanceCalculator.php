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
        /*
         * -------------------------------------------------
         * REQUIRE TIME IN / TIME OUT
         * -------------------------------------------------
         */

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
         * Example:
         *
         * 14:00 -> 00:00
         *
         * becomes:
         *
         * Sep 06 14:00 -> Sep 07 00:00
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

        /*
         * Make sure workdays is always an array.
         */
        if (! is_array($workdays)) {
            $workdays = [1, 2, 3, 4, 5];
        }

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
         * NSD SETTINGS
         * -------------------------------------------------
         */

        $nightShiftEnabled = (bool) Setting::getValue(
            'attendance',
            'night_shift_differential_enabled',
            false
        );

        $nightShiftStart = Setting::getValue(
            'attendance',
            'night_shift_differential_start',
            '22:00'
        );

        $nightShiftEnd = Setting::getValue(
            'attendance',
            'night_shift_differential_end',
            '06:00'
        );

        /*
         * -------------------------------------------------
         * DETERMINE WORKING DAY
         * -------------------------------------------------
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
         * Grace period only determines when lateness starts.
         *
         * Example:
         *
         * Official = 09:00
         * Grace    = 15 minutes
         *
         * 09:15 = 0
         * 09:16 = 16
         * 09:20 = 20
         * 09:30 = 30
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
         * Break/lunch is INCLUDED.
         *
         * Example:
         *
         * 09:00 -> 17:00
         * = 480 minutes
         */

        $workedMinutes = max(
            0,
            $timeIn->diffInMinutes($timeOut)
        );

        /*
         * -------------------------------------------------
         * REGULAR MINUTES
         * -------------------------------------------------
         *
         * For a normal working day:
         *
         * Regular work is from actual Time In
         * up to official Time Out.
         *
         * Early arrival does NOT create regular
         * worked minutes before official Time In.
         *
         * Example:
         *
         * 08:30 -> 17:00
         *
         * Regular = 480 minutes
         */

        $regularMinutes = 0;

        if ($isWorkingDay) {

            $regularStart = $timeIn->copy();

            if ($regularStart->lessThan($officialTimeInCarbon)) {
                $regularStart = $officialTimeInCarbon->copy();
            }

            $regularEnd = $timeOut->copy();

            if ($regularEnd->greaterThan($officialTimeOutCarbon)) {
                $regularEnd = $officialTimeOutCarbon->copy();
            }

            if ($regularEnd->greaterThan($regularStart)) {

                $regularMinutes = $regularStart->diffInMinutes(
                    $regularEnd
                );
            }
        }

        /*
         * -------------------------------------------------
         * UNDERTIME
         * -------------------------------------------------
         *
         * Only regular working days.
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
         * REST DAY MINUTES
         * -------------------------------------------------
         *
         * First 8 hours are rest-day work.
         *
         * Anything beyond 8 hours may become
         * rest-day OT.
         */

        $restDayMinutes = 0;
        $restDayOvertimeMinutes = 0;

        /*
         * -------------------------------------------------
         * DETECTED OVERTIME
         * -------------------------------------------------
         *
         * This is NOT automatically approved.
         *
         * Normal working day:
         *
         * OT = time after official Time Out.
         *
         * Rest day:
         *
         * OT = work beyond 8 hours.
         */

        $detectedOvertimeMinutes = 0;

        if ($isWorkingDay) {

            if (
                $overtimeEnabled
                && $timeOut->greaterThan($officialTimeOutCarbon)
            ) {

                $detectedOvertimeMinutes =
                    $officialTimeOutCarbon->diffInMinutes($timeOut);
            }

        } else {

            /*
             * Rest day.
             */

            $restDayMinutes = min(
                $workedMinutes,
                8 * 60
            );

            if ($workedMinutes > (8 * 60)) {

                $potentialRestDayOvertime =
                    $workedMinutes - (8 * 60);

                if (
                    $overtimeEnabled
                    && $potentialRestDayOvertime >= $minimumOvertimeMinutes
                ) {

                    $restDayOvertimeMinutes =
                        $potentialRestDayOvertime;

                    $detectedOvertimeMinutes =
                        $potentialRestDayOvertime;
                }
            }
        }

        /*
         * -------------------------------------------------
         * MINIMUM OT THRESHOLD
         * -------------------------------------------------
         *
         * IMPORTANT:
         *
         * This is a threshold, not rounding.
         *
         * Minimum OT = 30
         *
         * 17:15 = 0
         * 17:29 = 0
         * 17:30 = 30
         * 17:45 = 45
         * 18:00 = 60
         */

        if (
            $isWorkingDay
            && $detectedOvertimeMinutes > 0
            && $detectedOvertimeMinutes < $minimumOvertimeMinutes
        ) {

            $detectedOvertimeMinutes = 0;
        }

        /*
         * -------------------------------------------------
         * NSD
         * -------------------------------------------------
         *
         * NSD is overlapping time.
         *
         * It does NOT increase worked minutes.
         *
         * Example:
         *
         * 14:00 -> 00:00
         *
         * Worked = 600 minutes
         * NSD    = 120 minutes
         *
         * NSD overlaps with OT when applicable.
         */

        $nightShiftMinutes = 0;

        if ($nightShiftEnabled) {

            $nightShiftMinutes = $this->calculateNightShiftMinutes(
                $timeIn,
                $timeOut,
                $date,
                $nightShiftStart,
                $nightShiftEnd
            );
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
         * OT APPROVAL STATE
         * -------------------------------------------------
         *
         * We preserve an existing approval ONLY when
         * the detected OT amount has not changed.
         *
         * If the calculated OT changes:
         *
         * approved -> pending
         *
         * This prevents an old approval from silently
         * approving a new OT amount.
         */

        $previousDetectedOt = (int) (
            $attendance->detected_overtime_minutes ?? 0
        );

        $previousApprovedOt = (int) (
            $attendance->approved_overtime_minutes ?? 0
        );

        $previousStatus =
            $attendance->overtime_status ?? 'none';

        $detectedOtChanged =
            $previousDetectedOt !== $detectedOvertimeMinutes;

        $approvedOvertimeMinutes = $previousApprovedOt;
        $overtimeStatus = $previousStatus;

        /*
         * No detected OT.
         */

        if ($detectedOvertimeMinutes <= 0) {

            $approvedOvertimeMinutes = 0;
            $overtimeStatus = 'none';

        /*
         * New or changed OT.
         */

        } elseif ($detectedOtChanged) {

            $approvedOvertimeMinutes = 0;
            $overtimeStatus = 'pending';

        /*
         * Existing OT with no decision yet.
         */

        } elseif (
            ! in_array(
                $overtimeStatus,
                ['approved', 'rejected', 'pending'],
                true
            )
        ) {

            $approvedOvertimeMinutes = 0;
            $overtimeStatus = 'pending';
        }

        /*
         * Never allow approved OT to exceed detected OT.
         */

        $approvedOvertimeMinutes = min(
            max(0, $approvedOvertimeMinutes),
            max(0, $detectedOvertimeMinutes)
        );

        /*
         * If status is approved but approved amount
         * somehow became zero, treat it as rejected.
         */

        if (
            $overtimeStatus === 'approved'
            && $approvedOvertimeMinutes <= 0
        ) {

            $overtimeStatus = 'rejected';
        }

        /*
         * -------------------------------------------------
         * LEGACY / COMPATIBILITY OT FIELD
         * -------------------------------------------------
         *
         * overtime_minutes represents the OT that is
         * actually usable for payroll.
         *
         * Therefore it follows APPROVED OT,
         * not detected OT.
         */

        $usableOvertimeMinutes =
            $overtimeStatus === 'approved'
                ? $approvedOvertimeMinutes
                : 0;

        /*
         * -------------------------------------------------
         * SAVE
         * -------------------------------------------------
         */

        $attendance->update([

            'worked_minutes' => $workedMinutes,

            'regular_minutes' => max(
                0,
                $regularMinutes
            ),

            'rest_day_minutes' => max(
                0,
                $restDayMinutes
            ),

            'rest_day_overtime_minutes' => max(
                0,
                $restDayOvertimeMinutes
            ),

            'night_shift_minutes' => max(
                0,
                $nightShiftMinutes
            ),

            'late_minutes' => max(
                0,
                $lateMinutes
            ),

            'undertime_minutes' => max(
                0,
                $undertimeMinutes
            ),

            /*
             * Detected OT is separate from approved OT.
             */
            'detected_overtime_minutes' => max(
                0,
                $detectedOvertimeMinutes
            ),

            'approved_overtime_minutes' => max(
                0,
                $approvedOvertimeMinutes
            ),

            'overtime_status' => $overtimeStatus,

            /*
             * Payroll-compatible OT.
             */
            'overtime_minutes' => max(
                0,
                $usableOvertimeMinutes
            ),

            'status' => $status,
        ]);

        return $attendance->fresh();
    }

    /**
     * Calculate Night Shift Differential minutes.
     *
     * The default NSD period is:
     *
     * 10:00 PM -> 6:00 AM
     *
     * The period crosses midnight.
     */
    private function calculateNightShiftMinutes(
        Carbon $timeIn,
        Carbon $timeOut,
        string $date,
        string $nightShiftStart,
        string $nightShiftEnd
    ): int {

        $nightStart = Carbon::parse(
            $date . ' ' . $nightShiftStart
        );

        $nightEnd = Carbon::parse(
            $date . ' ' . $nightShiftEnd
        );

        /*
         * NSD crosses midnight.
         *
         * Example:
         *
         * 22:00 -> 06:00
         */

        if ($nightEnd->lessThanOrEqualTo($nightStart)) {
            $nightEnd->addDay();
        }

        /*
         * We need to check the NSD window on the
         * attendance date and the following date.
         *
         * This handles overnight attendance correctly.
         */

        $totalNightMinutes = 0;

        for ($dayOffset = -1; $dayOffset <= 1; $dayOffset++) {

            $windowStart = $nightStart
                ->copy()
                ->addDays($dayOffset);

            $windowEnd = $nightEnd
                ->copy()
                ->addDays($dayOffset);

            /*
             * Find overlap between:
             *
             * Attendance:
             * timeIn -> timeOut
             *
             * NSD:
             * windowStart -> windowEnd
             */

            $overlapStart = $timeIn->greaterThan($windowStart)
                ? $timeIn
                : $windowStart;

            $overlapEnd = $timeOut->lessThan($windowEnd)
                ? $timeOut
                : $windowEnd;

            if ($overlapEnd->greaterThan($overlapStart)) {

                $totalNightMinutes +=
                    $overlapStart->diffInMinutes(
                        $overlapEnd
                    );
            }
        }

        return max(
            0,
            $totalNightMinutes
        );
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
