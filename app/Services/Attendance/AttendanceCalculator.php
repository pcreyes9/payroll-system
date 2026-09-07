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
         * PRESERVE MANUALLY SELECTED STATUS
         * -------------------------------------------------
         *
         * These statuses must not be changed to Present
         * or Rest Day simply because Time In / Time Out
         * exist.
         */

        $originalStatus = $attendance->status;

        $protectedStatuses = [
            'half_day',
            'vl',
            'sl',
            'sil',
            'regular_holiday',
            'special_non_working_holiday',
            'emergency_leave',
            'lwop',
            'unpaid_leave',
            'holiday',
        ];

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
         * Half Day does NOT receive late minutes.
         *
         * VL / SL / SIL / Holiday / LWOP also do not
         * receive late minutes.
         *
         * For normal Present attendance:
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

        if (
            $isWorkingDay
            && ! in_array(
                $originalStatus,
                $protectedStatuses,
                true
            )
        ) {
            $lateThreshold = $officialTimeInCarbon
                ->copy()
                ->addMinutes($gracePeriod);

            if ($timeIn->greaterThan($lateThreshold)) {

                $lateMinutes =
                    $officialTimeInCarbon->diffInMinutes(
                        $timeIn
                    );
            }
        }

        /*
         * -------------------------------------------------
         * WORKED MINUTES
         * -------------------------------------------------
         *
         * Break/lunch is INCLUDED.
         */

        $workedMinutes = max(
            0,
            $timeIn->diffInMinutes($timeOut)
        );

        /*
         * -------------------------------------------------
         * REGULAR MINUTES
         * -------------------------------------------------
         */

        $regularMinutes = 0;

        if ($isWorkingDay) {

            $regularStart = $timeIn->copy();

            if (
                $regularStart->lessThan(
                    $officialTimeInCarbon
                )
            ) {
                $regularStart =
                    $officialTimeInCarbon->copy();
            }

            $regularEnd = $timeOut->copy();

            if (
                $regularEnd->greaterThan(
                    $officialTimeOutCarbon
                )
            ) {
                $regularEnd =
                    $officialTimeOutCarbon->copy();
            }

            if (
                $regularEnd->greaterThan(
                    $regularStart
                )
            ) {

                $regularMinutes =
                    $regularStart->diffInMinutes(
                        $regularEnd
                    );
            }
        }

        /*
         * -------------------------------------------------
         * UNDERTIME
         * -------------------------------------------------
         *
         * Half Day / Leave records should not be treated
         * as ordinary undertime.
         */

        $undertimeMinutes = 0;

        if (
            $isWorkingDay
            && ! in_array(
                $originalStatus,
                $protectedStatuses,
                true
            )
            && $timeOut->lessThan(
                $officialTimeOutCarbon
            )
        ) {

            $undertimeMinutes =
                $timeOut->diffInMinutes(
                    $officialTimeOutCarbon
                );
        }

        /*
         * -------------------------------------------------
         * REST DAY MINUTES
         * -------------------------------------------------
         */

        $restDayMinutes = 0;
        $restDayOvertimeMinutes = 0;

        /*
         * -------------------------------------------------
         * DETECTED OVERTIME
         * -------------------------------------------------
         *
         * Leave / Half Day / Holiday records are not
         * automatically treated as ordinary working-day OT.
         */

        $detectedOvertimeMinutes = 0;

        if (
            $isWorkingDay
            && ! in_array(
                $originalStatus,
                [
                    'half_day',
                    'vl',
                    'sl',
                    'sil',
                    'regular_holiday',
                    'special_non_working_holiday',
                    'emergency_leave',
                    'lwop',
                    'unpaid_leave',
                    'holiday',
                ],
                true
            )
        ) {

            if (
                $overtimeEnabled
                && $timeOut->greaterThan(
                    $officialTimeOutCarbon
                )
            ) {

                $detectedOvertimeMinutes =
                    $officialTimeOutCarbon->diffInMinutes(
                        $timeOut
                    );
            }

        } elseif (
            ! $isWorkingDay
            && ! in_array(
                $originalStatus,
                [
                    'vl',
                    'sl',
                    'sil',
                    'regular_holiday',
                    'special_non_working_holiday',
                    'emergency_leave',
                    'lwop',
                    'unpaid_leave',
                    'holiday',
                ],
                true
            )
        ) {

            /*
             * -------------------------------------------------
             * REST DAY
             * -------------------------------------------------
             *
             * First 8 hours = rest-day work.
             * Beyond 8 hours = rest-day OT.
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
                    && $potentialRestDayOvertime >=
                        $minimumOvertimeMinutes
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
         */

        if (
            $isWorkingDay
            && $detectedOvertimeMinutes > 0
            && $detectedOvertimeMinutes <
                $minimumOvertimeMinutes
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
         * It does not increase worked minutes.
         */

        $nightShiftMinutes = 0;

        if ($nightShiftEnabled) {

            $nightShiftMinutes =
                $this->calculateNightShiftMinutes(
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
         *
         * Preserve manually selected statuses.
         *
         * Only automatically determine Present,
         * Absent, or Rest Day when the original status
         * is not a protected/manual status.
         */

        if (
            in_array(
                $originalStatus,
                $protectedStatuses,
                true
            )
        ) {

            $status = $originalStatus;

        } elseif ($workedMinutes <= 0) {

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
            $previousDetectedOt !==
            $detectedOvertimeMinutes;

        $approvedOvertimeMinutes =
            $previousApprovedOt;

        $overtimeStatus =
            $previousStatus;

        /*
         * -------------------------------------------------
         * NO DETECTED OT
         * -------------------------------------------------
         */

        if ($detectedOvertimeMinutes <= 0) {

            $approvedOvertimeMinutes = 0;
            $overtimeStatus = 'none';

        /*
         * -------------------------------------------------
         * NEW / CHANGED OT
         * -------------------------------------------------
         */

        } elseif ($detectedOtChanged) {

            $approvedOvertimeMinutes = 0;
            $overtimeStatus = 'pending';

        /*
         * -------------------------------------------------
         * EXISTING OT
         * -------------------------------------------------
         */

        } elseif (
            ! in_array(
                $overtimeStatus,
                [
                    'approved',
                    'rejected',
                    'pending',
                ],
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
            max(
                0,
                $approvedOvertimeMinutes
            ),
            max(
                0,
                $detectedOvertimeMinutes
            )
        );

        /*
         * Approved status with zero approved minutes
         * is invalid.
         */

        if (
            $overtimeStatus === 'approved'
            && $approvedOvertimeMinutes <= 0
        ) {

            $overtimeStatus = 'rejected';
        }

        /*
         * -------------------------------------------------
         * PAYROLL-COMPATIBLE OT
         * -------------------------------------------------
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

            'worked_minutes' =>
                $workedMinutes,

            'regular_minutes' =>
                max(
                    0,
                    $regularMinutes
                ),

            'rest_day_minutes' =>
                max(
                    0,
                    $restDayMinutes
                ),

            'rest_day_overtime_minutes' =>
                max(
                    0,
                    $restDayOvertimeMinutes
                ),

            'night_shift_minutes' =>
                max(
                    0,
                    $nightShiftMinutes
                ),

            /*
             * Half Day / VL / SL will always have
             * zero late minutes.
             */

            'late_minutes' =>
                max(
                    0,
                    $lateMinutes
                ),

            'undertime_minutes' =>
                max(
                    0,
                    $undertimeMinutes
                ),

            'detected_overtime_minutes' =>
                max(
                    0,
                    $detectedOvertimeMinutes
                ),

            'approved_overtime_minutes' =>
                max(
                    0,
                    $approvedOvertimeMinutes
                ),

            'overtime_status' =>
                $overtimeStatus,

            'overtime_minutes' =>
                max(
                    0,
                    $usableOvertimeMinutes
                ),

            'status' =>
                $status,
        ]);

        return $attendance->fresh();
    }

    /**
     * Calculate Night Shift Differential minutes.
     *
     * Default:
     *
     * 22:00 -> 06:00
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
         */

        if (
            $nightEnd->lessThanOrEqualTo(
                $nightStart
            )
        ) {
            $nightEnd->addDay();
        }

        $totalNightMinutes = 0;

        /*
         * Check previous, current, and next NSD windows.
         */

        for (
            $dayOffset = -1;
            $dayOffset <= 1;
            $dayOffset++
        ) {

            $windowStart =
                $nightStart
                    ->copy()
                    ->addDays($dayOffset);

            $windowEnd =
                $nightEnd
                    ->copy()
                    ->addDays($dayOffset);

            $overlapStart =
                $timeIn->greaterThan($windowStart)
                    ? $timeIn
                    : $windowStart;

            $overlapEnd =
                $timeOut->lessThan($windowEnd)
                    ? $timeOut
                    : $windowEnd;

            if (
                $overlapEnd->greaterThan(
                    $overlapStart
                )
            ) {

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