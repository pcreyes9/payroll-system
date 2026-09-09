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
     *
     * Important rules:
     *
     * - Official Time In / Out come from Attendance Settings.
     * - Official working duration is calculated from those settings.
     * - On a rest day, regular premium hours are based on
     *   the actual overlap with the official schedule.
     * - On a rest day, work outside the official schedule
     *   is Rest Day OT.
     * - Lunch / break is INCLUDED in worked minutes.
     * - NSD is overlapping time and is NOT deducted from OT.
     * - Only APPROVED OT becomes payroll-usable OT.
     *
     * Example:
     *
     * Official schedule:
     * 09:00 - 17:00
     *
     * Rest day attendance:
     * 05:28 - 21:00
     *
     * Rest Day:
     * 09:00 - 17:00 = 8 hours
     *
     * Rest Day OT:
     * 05:28 - 09:00 = 3h32
     * 17:00 - 21:00 = 4h
     *
     * Total Rest Day OT = 7h32
     *
     * NSD:
     * 05:28 - 06:00 = 32 minutes
     *
     * Therefore:
     *
     * Rest Day OT + NSD = 32 minutes
     */
    public function calculate(
        AttendanceRecord $attendance
    ): AttendanceRecord {

        /*
        |--------------------------------------------------------------------------
        | REQUIRE TIME IN / TIME OUT
        |--------------------------------------------------------------------------
        */

        if (
            ! $attendance->time_in
            || ! $attendance->time_out
        ) {
            return $attendance;
        }

        $date =
            $attendance
                ->attendance_date
                ->format('Y-m-d');

        /*
        |--------------------------------------------------------------------------
        | PRESERVE MANUALLY SELECTED STATUS
        |--------------------------------------------------------------------------
        */

        $originalStatus =
            $attendance->status;

        $protectedStatuses = [
            'half_day',
            'half_day_vl',
            'half_day_sl',
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
        |--------------------------------------------------------------------------
        | ACTUAL TIME IN / OUT
        |--------------------------------------------------------------------------
        */

        $timeIn = Carbon::parse(
            $date . ' ' .
            $this->normalizeTime(
                $attendance->time_in
            )
        );

        $timeOut = Carbon::parse(
            $date . ' ' .
            $this->normalizeTime(
                $attendance->time_out
            )
        );

        /*
        |--------------------------------------------------------------------------
        | OVERNIGHT ATTENDANCE
        |--------------------------------------------------------------------------
        */

        if (
            $timeOut->lessThanOrEqualTo(
                $timeIn
            )
        ) {
            $timeOut->addDay();
        }

        /*
        |--------------------------------------------------------------------------
        | LOAD ATTENDANCE SETTINGS
        |--------------------------------------------------------------------------
        */

        $officialTimeIn =
            Setting::getValue(
                'attendance',
                'official_time_in',
                '09:00'
            );

        $officialTimeOut =
            Setting::getValue(
                'attendance',
                'official_time_out',
                '17:00'
            );

        $gracePeriod =
            (int) Setting::getValue(
                'attendance',
                'grace_period_minutes',
                15
            );

        $workdays =
            Setting::getValue(
                'attendance',
                'workdays',
                [1, 2, 3, 4, 5]
            );

        if (! is_array($workdays)) {
            $workdays = [
                1,
                2,
                3,
                4,
                5,
            ];
        }

        /*
        |--------------------------------------------------------------------------
        | OVERTIME SETTINGS
        |--------------------------------------------------------------------------
        */

        $overtimeEnabled =
            (bool) Setting::getValue(
                'attendance',
                'overtime_enabled',
                true
            );

        $minimumOvertimeMinutes =
            (int) Setting::getValue(
                'attendance',
                'minimum_overtime_minutes',
                0
            );

        /*
        |--------------------------------------------------------------------------
        | NSD SETTINGS
        |--------------------------------------------------------------------------
        */

        $nightShiftEnabled =
            (bool) Setting::getValue(
                'attendance',
                'night_shift_differential_enabled',
                false
            );

        $nightShiftStart =
            Setting::getValue(
                'attendance',
                'night_shift_differential_start',
                '22:00'
            );

        $nightShiftEnd =
            Setting::getValue(
                'attendance',
                'night_shift_differential_end',
                '06:00'
            );

        /*
        |--------------------------------------------------------------------------
        | DETERMINE WORKING DAY
        |--------------------------------------------------------------------------
        */

        $dayOfWeek =
            $attendance
                ->attendance_date
                ->dayOfWeekIso;

        $isWorkingDay =
            in_array(
                $dayOfWeek,
                $workdays,
                true
            );

        /*
        |--------------------------------------------------------------------------
        | OFFICIAL SCHEDULE
        |--------------------------------------------------------------------------
        |
        | The official schedule is completely controlled
        | by Attendance Settings.
        |
        | Example:
        |
        | 09:00 - 17:00 = 480 minutes
        |
        | This schedule is used for:
        |
        | - Regular working-day hours
        | - Rest-day regular hours
        | - Rest-day OT boundaries
        |
        */

        $officialTimeInCarbon =
            Carbon::parse(
                $date . ' ' . $officialTimeIn
            );

        $officialTimeOutCarbon =
            Carbon::parse(
                $date . ' ' . $officialTimeOut
            );

        /*
        |--------------------------------------------------------------------------
        | HANDLE OVERNIGHT OFFICIAL SCHEDULE
        |--------------------------------------------------------------------------
        */

        if (
            $officialTimeOutCarbon
                ->lessThanOrEqualTo(
                    $officialTimeInCarbon
                )
        ) {
            $officialTimeOutCarbon->addDay();
        }

        /*
        |--------------------------------------------------------------------------
        | OFFICIAL WORKING MINUTES
        |--------------------------------------------------------------------------
        */

        $officialWorkingMinutes =
            max(
                0,
                $officialTimeInCarbon->diffInMinutes(
                    $officialTimeOutCarbon
                )
            );

        /*
        |--------------------------------------------------------------------------
        | LATE
        |--------------------------------------------------------------------------
        |
        | Grace period only determines whether the
        | employee is considered late.
        |
        | Official = 09:00
        | Grace    = 15
        |
        | 09:15 = 0 late
        | 09:16 = 16 late
        | 09:20 = 20 late
        | 09:30 = 30 late
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
            $lateThreshold =
                $officialTimeInCarbon
                    ->copy()
                    ->addMinutes(
                        $gracePeriod
                    );

            if (
                $timeIn->greaterThan(
                    $lateThreshold
                )
            ) {
                $lateMinutes =
                    $officialTimeInCarbon->diffInMinutes(
                        $timeIn
                    );
            }
        }

        /*
        |--------------------------------------------------------------------------
        | WORKED MINUTES
        |--------------------------------------------------------------------------
        |
        | Break / lunch is INCLUDED.
        */

        $workedMinutes =
            max(
                0,
                $timeIn->diffInMinutes(
                    $timeOut
                )
            );

        /*
        |--------------------------------------------------------------------------
        | REGULAR MINUTES
        |--------------------------------------------------------------------------
        |
        | Working-day regular hours are the overlap between
        | actual attendance and the official schedule.
        */

        $regularMinutes = 0;

        if ($isWorkingDay) {

            $regularStart =
                $timeIn->copy();

            if (
                $regularStart->lessThan(
                    $officialTimeInCarbon
                )
            ) {
                $regularStart =
                    $officialTimeInCarbon->copy();
            }

            $regularEnd =
                $timeOut->copy();

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
        |--------------------------------------------------------------------------
        | UNDERTIME
        |--------------------------------------------------------------------------
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
        |--------------------------------------------------------------------------
        | REST DAY MINUTES
        |--------------------------------------------------------------------------
        |
        | IMPORTANT:
        |
        | Rest Day regular hours are based on the OFFICIAL
        | schedule, not the employee's actual Time In.
        |
        | Example:
        |
        | Official = 09:00 - 17:00
        |
        | Actual = 05:28 - 21:00
        |
        | Rest Day regular:
        | 09:00 - 17:00 = 480 minutes
        |
        | Rest Day OT:
        | 05:28 - 09:00
        | +
        | 17:00 - 21:00
        |
        | = 452 minutes
        | = 7 hours 32 minutes
        */

        $restDayMinutes = 0;

        $restDayOvertimeMinutes = 0;

        /*
        |--------------------------------------------------------------------------
        | DETECTED OVERTIME
        |--------------------------------------------------------------------------
        */

        $detectedOvertimeMinutes = 0;

        /*
        |--------------------------------------------------------------------------
        | WORKING DAY OT
        |--------------------------------------------------------------------------
        |
        | Working-day OT begins after the configured
        | official Time Out.
        */

        if (
            $isWorkingDay
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

            if (
                $overtimeEnabled
                && $timeOut->greaterThan(
                    $officialTimeOutCarbon
                )
            ) {

                $potentialOvertimeMinutes =
                    $officialTimeOutCarbon->diffInMinutes(
                        $timeOut
                    );

                if (
                    $potentialOvertimeMinutes >=
                    $minimumOvertimeMinutes
                ) {
                    $detectedOvertimeMinutes =
                        $potentialOvertimeMinutes;
                }
            }
        }

        /*
        |--------------------------------------------------------------------------
        | REST DAY OT
        |--------------------------------------------------------------------------
        |
        | IMPORTANT:
        |
        | Rest Day regular hours are the overlap with
        | the OFFICIAL schedule.
        |
        | Any actual work OUTSIDE the official schedule
        | is Rest Day OT.
        |
        | Example:
        |
        | Official:
        | 09:00 - 17:00
        |
        | Actual:
        | 05:28 - 21:00
        |
        | Official overlap:
        | 09:00 - 17:00 = 8 hours
        |
        | Outside official schedule:
        |
        | 05:28 - 09:00 = 3h32
        | 17:00 - 21:00 = 4h
        |
        | Rest Day OT:
        | 7h32
        */

        elseif (
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
            |--------------------------------------------------------------------------
            | REST DAY REGULAR HOURS
            |--------------------------------------------------------------------------
            |
            | Calculate only the actual overlap with the
            | official schedule.
            */

            $restDayOverlapStart =
                $timeIn->greaterThan(
                    $officialTimeInCarbon
                )
                    ? $timeIn
                    : $officialTimeInCarbon;

            $restDayOverlapEnd =
                $timeOut->lessThan(
                    $officialTimeOutCarbon
                )
                    ? $timeOut
                    : $officialTimeOutCarbon;

            if (
                $restDayOverlapEnd->greaterThan(
                    $restDayOverlapStart
                )
            ) {
                $restDayMinutes =
                    $restDayOverlapStart->diffInMinutes(
                        $restDayOverlapEnd
                    );
            }

            /*
            |--------------------------------------------------------------------------
            | REST DAY OVERTIME
            |--------------------------------------------------------------------------
            |
            | Everything worked outside the official schedule
            | is Rest Day OT.
            */

            $potentialRestDayOvertime =
                max(
                    0,
                    $workedMinutes
                    - $restDayMinutes
                );

            if (
                $potentialRestDayOvertime > 0
            ) {

                if (
                    $overtimeEnabled
                    && $potentialRestDayOvertime >=
                    $minimumOvertimeMinutes
                ) {

                    $restDayOvertimeMinutes =
                        $potentialRestDayOvertime;

                    $detectedOvertimeMinutes =
                        $potentialRestDayOvertime;

                } else {

                    /*
                    |--------------------------------------------------------------------------
                    | BELOW MINIMUM OT THRESHOLD
                    |--------------------------------------------------------------------------
                    |
                    | Both Rest Day OT and detected OT
                    | must remain zero.
                    */

                    $restDayOvertimeMinutes = 0;

                    $detectedOvertimeMinutes = 0;
                }
            }
        }

        /*
        |--------------------------------------------------------------------------
        | NSD
        |--------------------------------------------------------------------------
        |
        | NSD is an overlapping classification.
        |
        | It does NOT reduce:
        |
        | - Regular minutes
        | - Rest Day minutes
        | - Regular OT
        | - Rest Day OT
        |
        | Example:
        |
        | 05:28 - 06:00
        |
        | This can simultaneously be:
        |
        | Rest Day OT
        | +
        | NSD
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
        |--------------------------------------------------------------------------
        | STATUS
        |--------------------------------------------------------------------------
        */

        if (
            in_array(
                $originalStatus,
                $protectedStatuses,
                true
            )
        ) {

            $status =
                $originalStatus;

        } elseif (
            $workedMinutes <= 0
        ) {

            $status =
                'absent';

        } elseif (
            ! $isWorkingDay
        ) {

            $status =
                'rest_day';

        } else {

            $status =
                'present';
        }

        /*
        |--------------------------------------------------------------------------
        | OT APPROVAL STATE
        |--------------------------------------------------------------------------
        */

        $previousDetectedOt =
            (int) (
                $attendance
                    ->detected_overtime_minutes
                    ?? 0
            );

        $previousApprovedOt =
            (int) (
                $attendance
                    ->approved_overtime_minutes
                    ?? 0
            );

        $previousStatus =
            $attendance
                ->overtime_status
                ?? 'none';

        $detectedOtChanged =
            $previousDetectedOt !==
            $detectedOvertimeMinutes;

        $approvedOvertimeMinutes =
            $previousApprovedOt;

        $overtimeStatus =
            $previousStatus;

        /*
        |--------------------------------------------------------------------------
        | NO DETECTED OT
        |--------------------------------------------------------------------------
        */

        if (
            $detectedOvertimeMinutes <= 0
        ) {

            $approvedOvertimeMinutes = 0;

            $overtimeStatus =
                'none';
        }

        /*
        |--------------------------------------------------------------------------
        | NEW / CHANGED OT
        |--------------------------------------------------------------------------
        */

        elseif (
            $detectedOtChanged
        ) {

            $approvedOvertimeMinutes = 0;

            $overtimeStatus =
                'pending';
        }

        /*
        |--------------------------------------------------------------------------
        | EXISTING OT
        |--------------------------------------------------------------------------
        */

        elseif (
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

            $overtimeStatus =
                'pending';
        }

        /*
        |--------------------------------------------------------------------------
        | NEVER APPROVE MORE THAN DETECTED
        |--------------------------------------------------------------------------
        */

        $approvedOvertimeMinutes =
            min(
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
        |--------------------------------------------------------------------------
        | APPROVED STATUS WITH ZERO MINUTES
        |--------------------------------------------------------------------------
        */

        if (
            $overtimeStatus === 'approved'
            && $approvedOvertimeMinutes <= 0
        ) {

            $overtimeStatus =
                'rejected';
        }

        /*
        |--------------------------------------------------------------------------
        | PAYROLL-COMPATIBLE OT
        |--------------------------------------------------------------------------
        |
        | Payroll must only use approved OT.
        */

        $usableOvertimeMinutes =
            $overtimeStatus === 'approved'
                ? $approvedOvertimeMinutes
                : 0;

        /*
        |--------------------------------------------------------------------------
        | SAVE CALCULATED VALUES
        |--------------------------------------------------------------------------
        */

        $attendance->update([

            'worked_minutes' =>
                max(
                    0,
                    $workedMinutes
                ),

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
     *
     * NSD is calculated independently from
     * regular/rest-day/OT classifications.
     */
    private function calculateNightShiftMinutes(
        Carbon $timeIn,
        Carbon $timeOut,
        string $date,
        string $nightShiftStart,
        string $nightShiftEnd
    ): int {

        $nightStart =
            Carbon::parse(
                $date . ' ' . $nightShiftStart
            );

        $nightEnd =
            Carbon::parse(
                $date . ' ' . $nightShiftEnd
            );

        /*
        |--------------------------------------------------------------------------
        | NSD CROSSES MIDNIGHT
        |--------------------------------------------------------------------------
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
        |--------------------------------------------------------------------------
        | CHECK PREVIOUS, CURRENT, AND NEXT NSD WINDOWS
        |--------------------------------------------------------------------------
        */

        for (
            $dayOffset = -1;
            $dayOffset <= 1;
            $dayOffset++
        ) {

            $windowStart =
                $nightStart
                    ->copy()
                    ->addDays(
                        $dayOffset
                    );

            $windowEnd =
                $nightEnd
                    ->copy()
                    ->addDays(
                        $dayOffset
                    );

            $overlapStart =
                $timeIn->greaterThan(
                    $windowStart
                )
                    ? $timeIn
                    : $windowStart;

            $overlapEnd =
                $timeOut->lessThan(
                    $windowEnd
                )
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
    private function normalizeTime(
        mixed $time
    ): string {

        if ($time instanceof Carbon) {
            return $time->format(
                'H:i:s'
            );
        }

        if ($time instanceof \DateTimeInterface) {
            return $time->format(
                'H:i:s'
            );
        }

        $value =
            trim((string) $time);

        /*
         * Handle values that may already contain
         * a date and time.
         */

        if (
            preg_match(
                '/(?:^|\s|T)(\d{2}:\d{2}(?::\d{2})?)/',
                $value,
                $matches
            )
        ) {

            $normalized =
                $matches[1];

            if (
                strlen($normalized) === 5
            ) {
                $normalized .= ':00';
            }

            return $normalized;
        }

        return Carbon::parse(
            $value
        )->format('H:i:s');
    }
}