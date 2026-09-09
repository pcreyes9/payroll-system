<?php

namespace App\Services\Payroll;

use App\Models\AttendanceRecord;
use App\Models\Payroll;
use App\Models\Setting;
use Carbon\Carbon;

class PayrollDashboardData
{
    public function getData(Payroll $record): array
    {
        $employee = $record->employee;
        $payrollPeriod = $record->payrollPeriod;

        $attendanceRecords = collect();

        if ($employee && $payrollPeriod) {
            $attendanceRecords = AttendanceRecord::query()
                ->where('employee_id', $employee->id)
                ->whereBetween('attendance_date', [
                    $payrollPeriod->period_start,
                    $payrollPeriod->period_end,
                ])
                ->orderBy('attendance_date')
                ->get();
        }

        /*
         * -------------------------------------------------
         * FORMATTERS
         * -------------------------------------------------
         */

        $formatMinutes = static function ($minutes): string {
            $minutes = (int) $minutes;

            if ($minutes <= 0) {
                return '—';
            }

            return number_format(
                $minutes / 60,
                2
            );
        };

        $money = static function ($amount): string {
            return '₱' . number_format(
                (float) $amount,
                2
            );
        };

        /*
         * -------------------------------------------------
         * ATTENDANCE TOTALS
         * -------------------------------------------------
         */

        $totalRegularMinutes = (int) $attendanceRecords
            ->sum('regular_minutes');

        $totalLateMinutes = (int) $attendanceRecords
            ->sum('late_minutes');

        $totalUndertimeMinutes = (int) $attendanceRecords
            ->sum('undertime_minutes');

        $totalRestDayMinutes = (int) $attendanceRecords
            ->sum('rest_day_minutes');

        $totalDetectedOtMinutes = (int) $attendanceRecords
            ->sum('detected_overtime_minutes');

        $totalApprovedOtMinutes = (int) $attendanceRecords
            ->sum('approved_overtime_minutes');

        $totalNsdMinutes = (int) $attendanceRecords
            ->sum('night_shift_minutes');

        /*
         * -------------------------------------------------
         * REGULAR HOLIDAY ACTUAL HOURS
         * -------------------------------------------------
         *
         * Calculate regular-holiday pay from the actual Time In
         * → Time Out duration, capped at 8 hours. This intentionally
         * does not depend on worked_minutes so manually classified
         * holiday records still display their actual worked hours.
         */

        $totalRegularHolidayMinutes = 0;

        foreach ($attendanceRecords as $attendance) {

            if (
                $attendance->status !== 'regular_holiday'
                || ! $attendance->time_in
                || ! $attendance->time_out
            ) {
                continue;
            }

            $holidayTimeIn =
                $this->buildAttendanceDateTime(
                    $attendance,
                    'time_in'
                );

            $holidayTimeOut =
                $this->buildAttendanceDateTime(
                    $attendance,
                    'time_out'
                );

            if (
                $holidayTimeOut->lessThanOrEqualTo(
                    $holidayTimeIn
                )
            ) {
                $holidayTimeOut->addDay();
            }

            $totalRegularHolidayMinutes += min(
                max(
                    0,
                    $holidayTimeIn->diffInMinutes(
                        $holidayTimeOut
                    )
                ),
                8 * 60
            );
        }

        /*
         * -------------------------------------------------
         * PAYROLL AMOUNTS
         * -------------------------------------------------
         */

        $basicSalary = (float) $record->basic_salary;
        $basicPay = (float) $record->basic_pay;
        $allowances = (float) $record->allowances;
        $overtimePay = (float) $record->overtime_pay;
        $grossPay = (float) $record->gross_pay;

        $totalDeductions =
            (float) $record->total_deductions;

        $netPay =
            (float) $record->net_pay;

        /*
         * -------------------------------------------------
         * PAYROLL RATES
         * -------------------------------------------------
         *
         * Settings are stored as percentages.
         *
         * Example:
         *
         * 125 = 125%
         * 169 = 169%
         * 10  = 10%
         * -------------------------------------------------
         */

        $regularDayRate = (float) Setting::getValue(
            'payroll',
            'regular_day_rate',
            100
        );

        $regularDayOvertimeRate = (float) Setting::getValue(
            'payroll',
            'regular_day_overtime_rate',
            125
        );

        $restDayRate = (float) Setting::getValue(
            'payroll',
            'rest_day_rate',
            130
        );

        $restDayOvertimeRate = (float) Setting::getValue(
            'payroll',
            'rest_day_overtime_rate',
            169
        );

        $specialHolidayRate = (float) Setting::getValue(
            'payroll',
            'special_holiday_rate',
            130
        );

        $specialHolidayOvertimeRate = (float) Setting::getValue(
            'payroll',
            'special_holiday_overtime_rate',
            169
        );

        $specialHolidayRestDayRate =
            (float) Setting::getValue(
                'payroll',
                'special_holiday_rest_day_rate',
                150
            );

        $specialHolidayRestDayOvertimeRate =
            (float) Setting::getValue(
                'payroll',
                'special_holiday_rest_day_overtime_rate',
                195
            );

        $regularHolidayRate = (float) Setting::getValue(
            'payroll',
            'regular_holiday_rate',
            200
        );

        $regularHolidayOvertimeRate = (float) Setting::getValue(
            'payroll',
            'regular_holiday_overtime_rate',
            260
        );

        $regularHolidayRestDayRate =
            (float) Setting::getValue(
                'payroll',
                'regular_holiday_rest_day_rate',
                260
            );

        $regularHolidayRestDayOvertimeRate =
            (float) Setting::getValue(
                'payroll',
                'regular_holiday_rest_day_overtime_rate',
                338
            );

        $nightShiftDifferentialRate = (float) Setting::getValue(
            'payroll',
            'night_shift_differential_rate',
            10
        );

        /*
         * -------------------------------------------------
         * SALARY BASIS
         * -------------------------------------------------
         *
         * Monthly Salary × 12 ÷ 260
         * -------------------------------------------------
         */

        $dailyRate = round(
            ($basicSalary * 12) / 260,
            2
        );

        $hourlyRate =
            $dailyRate / 8;

        /*
         * -------------------------------------------------
         * HOURLY EQUIVALENTS
         * -------------------------------------------------
         */

        $regularHourlyAmount =
            $hourlyRate
            * ($regularDayRate / 100);

        $regularOtHourlyAmount =
            $hourlyRate
            * ($regularDayOvertimeRate / 100);

        $restDayHourlyAmount =
            $hourlyRate
            * ($restDayRate / 100);

        $restDayOtHourlyAmount =
            $hourlyRate
            * ($restDayOvertimeRate / 100);

        $specialHolidayHourlyAmount =
            $hourlyRate
            * ($specialHolidayRate / 100);

        $specialHolidayOtHourlyAmount =
            $hourlyRate
            * ($specialHolidayOvertimeRate / 100);

        $specialHolidayRestDayHourlyAmount =
            $hourlyRate
            * ($specialHolidayRestDayRate / 100);

        $specialHolidayRestDayOtHourlyAmount =
            $hourlyRate
            * ($specialHolidayRestDayOvertimeRate / 100);

        $regularHolidayHourlyAmount =
            $hourlyRate
            * ($regularHolidayRate / 100);

        $regularHolidayAmount =
            ($totalRegularHolidayMinutes / 60)
            * $regularHolidayHourlyAmount;

        $regularHolidayOtHourlyAmount =
            $hourlyRate
            * ($regularHolidayOvertimeRate / 100);

        $regularHolidayRestDayHourlyAmount =
            $hourlyRate
            * ($regularHolidayRestDayRate / 100);

        $regularHolidayRestDayOtHourlyAmount =
            $hourlyRate
            * ($regularHolidayRestDayOvertimeRate / 100);

        /*
         * Basic NSD hourly amount.
         */

        $nsdHourlyAmount =
            $hourlyRate
            * ($nightShiftDifferentialRate / 100);

        /*
         * -------------------------------------------------
         * OT TOTALS
         * -------------------------------------------------
         */

        $weekdayOt = (int) $attendanceRecords
            ->filter(
                fn ($item) =>
                    in_array(
                        $item->status,
                        [
                            'present',
                            'half_day',
                            'half_day_vl',
                            'half_day_sl',
                        ],
                        true
                    )
            )
            ->sum('approved_overtime_minutes');

        $restDayOt = (int) $attendanceRecords
            ->filter(
                fn ($item) =>
                    $item->status === 'rest_day'
            )
            ->sum('approved_overtime_minutes');

        /*
         * -------------------------------------------------
         * OT + NSD OVERLAP
         * -------------------------------------------------
         *
         * These values represent ONLY approved OT
         * minutes that overlap with NSD.
         *
         * NSD outside approved OT is NOT included here.
         * -------------------------------------------------
         */

        $weekdayOtNsdMinutes = 0;
        $restDayOtNsdMinutes = 0;

        $specialHolidayOtNsdMinutes = 0;
        $specialHolidayRestDayOtNsdMinutes = 0;

        $regularHolidayOtNsdMinutes = 0;
        $regularHolidayRestDayOtNsdMinutes = 0;

        foreach ($attendanceRecords as $attendance) {

            $approvedOtMinutes = (int) (
                $attendance->approved_overtime_minutes
            );

            if (
                $attendance->overtime_status !== 'approved'
                || $approvedOtMinutes <= 0
                || ! $attendance->time_in
                || ! $attendance->time_out
            ) {
                continue;
            }

            if (
                ! in_array(
                    $attendance->status,
                    [
                        'present',
                        'half_day',
                        'half_day_vl',
                        'half_day_sl',
                        'rest_day',
                        'special_non_working_holiday',
                        'regular_holiday',
                    ],
                    true
                )
            ) {
                continue;
            }

            /*
             * -------------------------------------------------
             * BUILD ACTUAL ATTENDANCE DATETIMES
             * -------------------------------------------------
             */

            $timeIn = $this->buildAttendanceDateTime(
                $attendance,
                'time_in'
            );

            $timeOut = $this->buildAttendanceDateTime(
                $attendance,
                'time_out'
            );

            /*
             * Overnight attendance.
             */

            if ($timeOut->lessThanOrEqualTo($timeIn)) {
                $timeOut->addDay();
            }

            /*
             * -------------------------------------------------
             * GET OT INTERVALS
             * -------------------------------------------------
             *
             * For a regular working day:
             *
             *     Official Time Out → Time Out
             *
             * For a Rest Day:
             *
             *     Time In → Official Time In
             *     AND
             *     Official Time Out → Time Out
             *
             * This is important because Rest Day OT can exist
             * both BEFORE and AFTER the official schedule.
             * -------------------------------------------------
             */

            $otIntervals =
                $this->getOvertimeIntervals(
                    $attendance,
                    $timeIn,
                    $timeOut
                );

            if (empty($otIntervals)) {
                continue;
            }

            /*
             * Total detected OT represented by the intervals.
             */

            $detectedOtMinutes = 0;

            foreach ($otIntervals as $interval) {
                $detectedOtMinutes +=
                    $interval['start']->diffInMinutes(
                        $interval['end']
                    );
            }

            if ($detectedOtMinutes <= 0) {
                continue;
            }

            $approvedOtMinutes =
                min(
                    $approvedOtMinutes,
                    $detectedOtMinutes
                );

            if ($approvedOtMinutes <= 0) {
                continue;
            }

            /*
             * -------------------------------------------------
             * CALCULATE APPROVED OT + NSD
             * -------------------------------------------------
             *
             * For Rest Day, OT may be split into two intervals.
             *
             * Approved OT is therefore allocated across the
             * actual OT intervals instead of assuming one
             * continuous block before Time Out.
             *
             * This correctly handles:
             *
             * 05:28 → 09:00   Rest Day OT
             * 09:00 → 17:00   Rest Day
             * 17:00 → 21:00   Rest Day OT
             *
             * For 452 approved minutes, BOTH OT intervals are
             * included.
             * -------------------------------------------------
             */

            $overlapMinutes =
                $this->calculateApprovedOtNightMinutesFromIntervals(
                    $otIntervals,
                    $approvedOtMinutes
                );

            /*
             * Never allow OT + NSD to exceed approved OT.
             */

            $overlapMinutes =
                min(
                    $overlapMinutes,
                    $approvedOtMinutes
                );

            if ($overlapMinutes <= 0) {
                continue;
            }

            /*
             * -------------------------------------------------
             * CLASSIFY OT + NSD
             * -------------------------------------------------
             */

            switch ($attendance->status) {

                case 'present':

                    $weekdayOtNsdMinutes +=
                        $overlapMinutes;

                    break;

                case 'rest_day':

                    $restDayOtNsdMinutes +=
                        $overlapMinutes;

                    break;

                case 'special_non_working_holiday':

                    if (
                        $this->isRestDay(
                            $attendance->attendance_date
                        )
                    ) {

                        $specialHolidayRestDayOtNsdMinutes +=
                            $overlapMinutes;

                    } else {

                        $specialHolidayOtNsdMinutes +=
                            $overlapMinutes;
                    }

                    break;

                case 'regular_holiday':

                    if (
                        $this->isRestDay(
                            $attendance->attendance_date
                        )
                    ) {

                        $regularHolidayRestDayOtNsdMinutes +=
                            $overlapMinutes;

                    } else {

                        $regularHolidayOtNsdMinutes +=
                            $overlapMinutes;
                    }

                    break;
            }
        }

        /*
         * -------------------------------------------------
         * OT + NSD TOTAL MINUTES
         * -------------------------------------------------
         */

        $totalOtNsdMinutes =
            $weekdayOtNsdMinutes
            + $restDayOtNsdMinutes
            + $specialHolidayOtNsdMinutes
            + $specialHolidayRestDayOtNsdMinutes
            + $regularHolidayOtNsdMinutes
            + $regularHolidayRestDayOtNsdMinutes;

        /*
         * -------------------------------------------------
         * DISPLAY-ONLY OVERTIME
         * -------------------------------------------------
         *
         * OT + NSD is displayed separately below.
         *
         * Remove the OT + NSD portion from the normal
         * Overtime Summary so the same minutes are not
         * visually shown under both rates.
         *
         * IMPORTANT:
         *
         * $weekdayOt and $restDayOt remain the actual
         * approved OT minutes used by payroll calculations.
         * These values are only for dashboard display.
         * -------------------------------------------------
         */

        $weekdayOtDisplayMinutes = max(
            0,
            $weekdayOt - $weekdayOtNsdMinutes
        );

        $restDayOtDisplayMinutes = max(
            0,
            $restDayOt - $restDayOtNsdMinutes
        );

        /*
         * -------------------------------------------------
         * ACTUAL PREMIUM PAY AMOUNTS
         * -------------------------------------------------
         */

        $regularDayOvertimeAmount =
            ($weekdayOt / 60)
            * $regularOtHourlyAmount;

        /*
         * Rest Day pay is based on the actual overlap
         * with the official 8-hour schedule.
         */

        $restDayAmount =
            ($totalRestDayMinutes / 60)
            * $restDayHourlyAmount;

        $restDayOvertimeAmount =
            ($restDayOt / 60)
            * $restDayOtHourlyAmount;

        /*
         * Display-only OT amounts.
         *
         * These exclude the portion separately shown
         * under OT + NSD.
         */

        $weekdayOtDisplayAmount =
            ($weekdayOtDisplayMinutes / 60)
            * $regularOtHourlyAmount;

        $restDayOtDisplayAmount =
            ($restDayOtDisplayMinutes / 60)
            * $restDayOtHourlyAmount;

        $displayOvertimeAmount =
            $weekdayOtDisplayAmount
            + $restDayOtDisplayAmount;

        /*
         * -------------------------------------------------
         * NSD AMOUNT
         * -------------------------------------------------
         *
         * NSD is an additional premium.
         *
         * It does NOT remove time from:
         *
         * - regular hours
         * - rest-day hours
         * - OT hours
         *
         * It overlays the applicable period.
         * -------------------------------------------------
         */

        $nightShiftDifferentialAmount = 0.00;

        foreach ($attendanceRecords as $attendance) {

            $nightMinutes =
                (int) $attendance->night_shift_minutes;

            if ($nightMinutes <= 0) {
                continue;
            }

            $approvedOtMinutes =
                $attendance->overtime_status === 'approved'
                    ? (int) $attendance->approved_overtime_minutes
                    : 0;

            /*
             * Determine NSD minutes overlapping approved OT.
             */

            $otNightMinutes =
                $this->calculateApprovedOtNightMinutes(
                    $attendance,
                    $approvedOtMinutes
                );

            $otNightMinutes =
                min(
                    $otNightMinutes,
                    $nightMinutes
                );

            /*
             * NSD outside approved OT.
             */

            $nonOtNightMinutes =
                max(
                    0,
                    $nightMinutes
                    - $otNightMinutes
                );

            /*
             * -------------------------------------------------
             * DETERMINE DAY TYPE
             * -------------------------------------------------
             */

            $isRestDay =
                $this->isRestDay(
                    $attendance->attendance_date
                );

            /*
             * -------------------------------------------------
             * DETERMINE APPLICABLE RATES
             * -------------------------------------------------
             */

            $normalRate =
                $regularDayRate;

            $otRate =
                $regularDayOvertimeRate;

            if ($attendance->status === 'rest_day') {

                $normalRate =
                    $restDayRate;

                $otRate =
                    $restDayOvertimeRate;

            } elseif (
                $attendance->status ===
                'special_non_working_holiday'
            ) {

                if ($isRestDay) {

                    $normalRate =
                        $specialHolidayRestDayRate;

                    $otRate =
                        $specialHolidayRestDayOvertimeRate;

                } else {

                    $normalRate =
                        $specialHolidayRate;

                    $otRate =
                        $specialHolidayOvertimeRate;
                }

            } elseif (
                $attendance->status ===
                'regular_holiday'
            ) {

                if ($isRestDay) {

                    $normalRate =
                        $regularHolidayRestDayRate;

                    $otRate =
                        $regularHolidayRestDayOvertimeRate;

                } else {

                    $normalRate =
                        $regularHolidayRate;

                    $otRate =
                        $regularHolidayOvertimeRate;
                }
            }

            /*
             * -------------------------------------------------
             * NSD DURING NON-OT HOURS
             * -------------------------------------------------
             */

            if ($nonOtNightMinutes > 0) {

                $nightShiftDifferentialAmount +=
                    ($nonOtNightMinutes / 60)
                    * $hourlyRate
                    * ($normalRate / 100)
                    * ($nightShiftDifferentialRate / 100);
            }

            /*
             * -------------------------------------------------
             * NSD DURING APPROVED OT
             * -------------------------------------------------
             */

            if ($otNightMinutes > 0) {

                $nightShiftDifferentialAmount +=
                    ($otNightMinutes / 60)
                    * $hourlyRate
                    * ($otRate / 100)
                    * ($nightShiftDifferentialRate / 100);
            }
        }

        /*
         * -------------------------------------------------
         * OT + NSD COMBINED HOURLY RATES
         * -------------------------------------------------
         *
         * Combined rate:
         *
         * OT rate × 110%
         *
         * 125% × 110% = 137.5%
         *
         * 169% × 110% = 185.9%
         * -------------------------------------------------
         */

        $weekdayOtNsdHourlyAmount =
            $hourlyRate
            * ($regularDayOvertimeRate / 100)
            * (
                1
                + ($nightShiftDifferentialRate / 100)
            );

        $restDayOtNsdHourlyAmount =
            $hourlyRate
            * ($restDayOvertimeRate / 100)
            * (
                1
                + ($nightShiftDifferentialRate / 100)
            );

        $specialHolidayOtNsdHourlyAmount =
            $hourlyRate
            * ($specialHolidayOvertimeRate / 100)
            * (
                1
                + ($nightShiftDifferentialRate / 100)
            );

        $specialHolidayRestDayOtNsdHourlyAmount =
            $hourlyRate
            * ($specialHolidayRestDayOvertimeRate / 100)
            * (
                1
                + ($nightShiftDifferentialRate / 100)
            );

        $regularHolidayOtNsdHourlyAmount =
            $hourlyRate
            * ($regularHolidayOvertimeRate / 100)
            * (
                1
                + ($nightShiftDifferentialRate / 100)
            );

        $regularHolidayRestDayOtNsdHourlyAmount =
            $hourlyRate
            * ($regularHolidayRestDayOvertimeRate / 100)
            * (
                1
                + ($nightShiftDifferentialRate / 100)
            );

        /*
         * -------------------------------------------------
         * OT + NSD COMBINED AMOUNTS
         * -------------------------------------------------
         *
         * DISPLAY ONLY.
         *
         * These values must NOT be added again to payroll.
         * -------------------------------------------------
         */

        $weekdayOtNsdAmount =
            ($weekdayOtNsdMinutes / 60)
            * $weekdayOtNsdHourlyAmount;

        $restDayOtNsdAmount =
            ($restDayOtNsdMinutes / 60)
            * $restDayOtNsdHourlyAmount;

        $specialHolidayOtNsdAmount =
            ($specialHolidayOtNsdMinutes / 60)
            * $specialHolidayOtNsdHourlyAmount;

        $specialHolidayRestDayOtNsdAmount =
            ($specialHolidayRestDayOtNsdMinutes / 60)
            * $specialHolidayRestDayOtNsdHourlyAmount;

        $regularHolidayOtNsdAmount =
            ($regularHolidayOtNsdMinutes / 60)
            * $regularHolidayOtNsdHourlyAmount;

        $regularHolidayRestDayOtNsdAmount =
            ($regularHolidayRestDayOtNsdMinutes / 60)
            * $regularHolidayRestDayOtNsdHourlyAmount;

        $totalOtNsdAmount =
            $weekdayOtNsdAmount
            + $restDayOtNsdAmount
            + $specialHolidayOtNsdAmount
            + $specialHolidayRestDayOtNsdAmount
            + $regularHolidayOtNsdAmount
            + $regularHolidayRestDayOtNsdAmount;

        /*
         * -------------------------------------------------
         * COMBINED RATES FOR DISPLAY
         * -------------------------------------------------
         */

        $weekdayOtNsdRate =
            $regularDayOvertimeRate
            * (
                1
                + ($nightShiftDifferentialRate / 100)
            );

        $restDayOtNsdRate =
            $restDayOvertimeRate
            * (
                1
                + ($nightShiftDifferentialRate / 100)
            );

        $specialHolidayOtNsdRate =
            $specialHolidayOvertimeRate
            * (
                1
                + ($nightShiftDifferentialRate / 100)
            );

        $specialHolidayRestDayOtNsdRate =
            $specialHolidayRestDayOvertimeRate
            * (
                1
                + ($nightShiftDifferentialRate / 100)
            );

        $regularHolidayOtNsdRate =
            $regularHolidayOvertimeRate
            * (
                1
                + ($nightShiftDifferentialRate / 100)
            );

        $regularHolidayRestDayOtNsdRate =
            $regularHolidayRestDayOvertimeRate
            * (
                1
                + ($nightShiftDifferentialRate / 100)
            );

        /*
         * -------------------------------------------------
         * ALLOWANCES / DEDUCTIONS
         * -------------------------------------------------
         */

        $allowanceItems = $record
            ->allowanceItems()
            ->get();

        $deductionItems = $record
            ->deductions()
            ->get();

        /*
         * -------------------------------------------------
         * RETURN DATA
         * -------------------------------------------------
         */

        return [

            'employee' =>
                $employee,

            'payrollPeriod' =>
                $payrollPeriod,

            'attendanceRecords' =>
                $attendanceRecords,

            'formatMinutes' =>
                $formatMinutes,

            'money' =>
                $money,

            /*
             * Attendance
             */

            'totalRegularMinutes' =>
                $totalRegularMinutes,

            'totalLateMinutes' =>
                $totalLateMinutes,

            'totalUndertimeMinutes' =>
                $totalUndertimeMinutes,

            'totalRestDayMinutes' =>
                $totalRestDayMinutes,

            'totalRegularHolidayMinutes' =>
                $totalRegularHolidayMinutes,

            'regularHolidayAmount' =>
                $regularHolidayAmount,

            'totalDetectedOtMinutes' =>
                $totalDetectedOtMinutes,

            'totalApprovedOtMinutes' =>
                $totalApprovedOtMinutes,

            'totalNsdMinutes' =>
                $totalNsdMinutes,

            /*
             * Payroll
             */

            'basicSalary' =>
                $basicSalary,

            'basicPay' =>
                $basicPay,

            'allowances' =>
                $allowances,

            'overtimePay' =>
                $overtimePay,

            'grossPay' =>
                $grossPay,

            'totalDeductions' =>
                $totalDeductions,

            'netPay' =>
                $netPay,

            /*
             * Salary basis
             */

            'dailyRate' =>
                $dailyRate,

            'hourlyRate' =>
                $hourlyRate,

            /*
             * Rates
             */

            'regularDayRate' =>
                $regularDayRate,

            'regularDayOvertimeRate' =>
                $regularDayOvertimeRate,

            'restDayRate' =>
                $restDayRate,

            'restDayOvertimeRate' =>
                $restDayOvertimeRate,

            'specialHolidayRate' =>
                $specialHolidayRate,

            'specialHolidayOvertimeRate' =>
                $specialHolidayOvertimeRate,

            'specialHolidayRestDayRate' =>
                $specialHolidayRestDayRate,

            'specialHolidayRestDayOvertimeRate' =>
                $specialHolidayRestDayOvertimeRate,

            'regularHolidayRate' =>
                $regularHolidayRate,

            'regularHolidayOvertimeRate' =>
                $regularHolidayOvertimeRate,

            'regularHolidayRestDayRate' =>
                $regularHolidayRestDayRate,

            'regularHolidayRestDayOvertimeRate' =>
                $regularHolidayRestDayOvertimeRate,

            'nightShiftDifferentialRate' =>
                $nightShiftDifferentialRate,

            /*
             * Hourly amounts
             */

            'regularHourlyAmount' =>
                $regularHourlyAmount,

            'regularOtHourlyAmount' =>
                $regularOtHourlyAmount,

            'restDayHourlyAmount' =>
                $restDayHourlyAmount,

            'restDayOtHourlyAmount' =>
                $restDayOtHourlyAmount,

            'specialHolidayHourlyAmount' =>
                $specialHolidayHourlyAmount,

            'specialHolidayOtHourlyAmount' =>
                $specialHolidayOtHourlyAmount,

            'specialHolidayRestDayHourlyAmount' =>
                $specialHolidayRestDayHourlyAmount,

            'specialHolidayRestDayOtHourlyAmount' =>
                $specialHolidayRestDayOtHourlyAmount,

            'regularHolidayHourlyAmount' =>
                $regularHolidayHourlyAmount,

            'regularHolidayOtHourlyAmount' =>
                $regularHolidayOtHourlyAmount,

            'regularHolidayRestDayHourlyAmount' =>
                $regularHolidayRestDayHourlyAmount,

            'regularHolidayRestDayOtHourlyAmount' =>
                $regularHolidayRestDayOtHourlyAmount,

            'nsdHourlyAmount' =>
                $nsdHourlyAmount,

            /*
             * Actual amounts
             */

            'regularDayOvertimeAmount' =>
                $regularDayOvertimeAmount,

            'restDayAmount' =>
                $restDayAmount,

            'restDayOvertimeAmount' =>
                $restDayOvertimeAmount,

            'nightShiftDifferentialAmount' =>
                $nightShiftDifferentialAmount,

            /*
             * OT + NSD
             */

            'weekdayOtNsdMinutes' =>
                $weekdayOtNsdMinutes,

            'restDayOtNsdMinutes' =>
                $restDayOtNsdMinutes,

            'specialHolidayOtNsdMinutes' =>
                $specialHolidayOtNsdMinutes,

            'specialHolidayRestDayOtNsdMinutes' =>
                $specialHolidayRestDayOtNsdMinutes,

            'regularHolidayOtNsdMinutes' =>
                $regularHolidayOtNsdMinutes,

            'regularHolidayRestDayOtNsdMinutes' =>
                $regularHolidayRestDayOtNsdMinutes,

            'totalOtNsdMinutes' =>
                $totalOtNsdMinutes,

            'weekdayOtNsdHourlyAmount' =>
                $weekdayOtNsdHourlyAmount,

            'restDayOtNsdHourlyAmount' =>
                $restDayOtNsdHourlyAmount,

            'specialHolidayOtNsdHourlyAmount' =>
                $specialHolidayOtNsdHourlyAmount,

            'specialHolidayRestDayOtNsdHourlyAmount' =>
                $specialHolidayRestDayOtNsdHourlyAmount,

            'regularHolidayOtNsdHourlyAmount' =>
                $regularHolidayOtNsdHourlyAmount,

            'regularHolidayRestDayOtNsdHourlyAmount' =>
                $regularHolidayRestDayOtNsdHourlyAmount,

            'weekdayOtNsdAmount' =>
                $weekdayOtNsdAmount,

            'restDayOtNsdAmount' =>
                $restDayOtNsdAmount,

            'specialHolidayOtNsdAmount' =>
                $specialHolidayOtNsdAmount,

            'specialHolidayRestDayOtNsdAmount' =>
                $specialHolidayRestDayOtNsdAmount,

            'regularHolidayOtNsdAmount' =>
                $regularHolidayOtNsdAmount,

            'regularHolidayRestDayOtNsdAmount' =>
                $regularHolidayRestDayOtNsdAmount,

            'totalOtNsdAmount' =>
                $totalOtNsdAmount,

            /*
             * Combined rates
             */

            'weekdayOtNsdRate' =>
                $weekdayOtNsdRate,

            'restDayOtNsdRate' =>
                $restDayOtNsdRate,

            'specialHolidayOtNsdRate' =>
                $specialHolidayOtNsdRate,

            'specialHolidayRestDayOtNsdRate' =>
                $specialHolidayRestDayOtNsdRate,

            'regularHolidayOtNsdRate' =>
                $regularHolidayOtNsdRate,

            'regularHolidayRestDayOtNsdRate' =>
                $regularHolidayRestDayOtNsdRate,

            /*
             * Items
             */

            'allowanceItems' =>
                $allowanceItems,

            'deductionItems' =>
                $deductionItems,

            /*
             * OT
             */

            'weekdayOt' =>
                $weekdayOt,

            'restDayOt' =>
                $restDayOt,

            /*
             * Display-only OT values.
             */

            'weekdayOtDisplayMinutes' =>
                $weekdayOtDisplayMinutes,

            'restDayOtDisplayMinutes' =>
                $restDayOtDisplayMinutes,

            'weekdayOtDisplayAmount' =>
                $weekdayOtDisplayAmount,

            'restDayOtDisplayAmount' =>
                $restDayOtDisplayAmount,

            'displayOvertimeAmount' =>
                $displayOvertimeAmount,
        ];
    }


    /**
     * Get the actual overtime intervals for an attendance record.
     *
     * Regular working day:
     *
     *     Official Time Out → Time Out
     *
     * Rest Day:
     *
     *     Time In → Official Time In
     *     Official Time Out → Time Out
     *
     * This allows Rest Day OT to exist both before and after
     * the official 8-hour schedule.
     */
    private function getOvertimeIntervals(
        AttendanceRecord $attendance,
        Carbon $timeIn,
        Carbon $timeOut
    ): array {

        $intervals = [];

        /*
         * -------------------------------------------------
         * REGULAR WORKING DAY
         * -------------------------------------------------
         */

        if (
            in_array(
                $attendance->status,
                [
                    'present',
                    'half_day',
                    'half_day_vl',
                    'half_day_sl',
                ],
                true
            )
        ) {

            $officialTimeOut =
                Setting::getValue(
                    'attendance',
                    'official_time_out',
                    '17:00'
                );

            $officialTimeOutDateTime =
                $this->buildDateTimeFromTime(
                    $attendance->attendance_date,
                    $officialTimeOut
                );

            /*
             * If official time-out is earlier than/equal to
             * Time In, treat it as an overnight boundary.
             */

            if (
                $officialTimeOutDateTime
                    ->lessThanOrEqualTo($timeIn)
            ) {
                $officialTimeOutDateTime->addDay();
            }

            $otStart =
                $officialTimeOutDateTime;

            if (
                $otStart->lessThan($timeIn)
            ) {
                $otStart =
                    $timeIn->copy();
            }

            if (
                $timeOut->greaterThan($otStart)
            ) {

                $intervals[] = [
                    'start' =>
                        $otStart->copy(),

                    'end' =>
                        $timeOut->copy(),
                ];
            }

            return $intervals;
        }

        /*
         * -------------------------------------------------
         * REST DAY
         * -------------------------------------------------
         *
         * IMPORTANT:
         *
         * Rest Day regular hours are based on the official
         * schedule configured in Attendance Settings.
         *
         * They are NOT the first 8 hours after Time In.
         * -------------------------------------------------
         */

        if ($attendance->status === 'rest_day') {

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

            $officialStart =
                $this->buildDateTimeFromTime(
                    $attendance->attendance_date,
                    $officialTimeIn
                );

            $officialEnd =
                $this->buildDateTimeFromTime(
                    $attendance->attendance_date,
                    $officialTimeOut
                );

            /*
             * -------------------------------------------------
             * PRE-SCHEDULE REST DAY OT
             * -------------------------------------------------
             *
             * Example:
             *
             * 05:28 → 09:00
             *
             * = 3h 32m Rest Day OT
             */

            $preScheduleStart =
                $timeIn->copy();

            $preScheduleEnd =
                $timeOut->lessThan($officialStart)
                    ? $timeOut->copy()
                    : $officialStart->copy();

            if (
                $preScheduleEnd
                    ->greaterThan($preScheduleStart)
            ) {

                $intervals[] = [
                    'start' =>
                        $preScheduleStart,

                    'end' =>
                        $preScheduleEnd,
                ];
            }

            /*
             * -------------------------------------------------
             * POST-SCHEDULE REST DAY OT
             * -------------------------------------------------
             *
             * Example:
             *
             * 17:00 → 21:00
             *
             * = 4h Rest Day OT
             */

            $postScheduleStart =
                $timeIn->greaterThan($officialEnd)
                    ? $timeIn->copy()
                    : $officialEnd->copy();

            $postScheduleEnd =
                $timeOut->copy();

            if (
                $postScheduleEnd
                    ->greaterThan($postScheduleStart)
            ) {

                $intervals[] = [
                    'start' =>
                        $postScheduleStart,

                    'end' =>
                        $postScheduleEnd,
                ];
            }

            return $intervals;
        }

        /*
         * -------------------------------------------------
         * HOLIDAY
         * -------------------------------------------------
         *
         * Preserve the existing behavior for holiday
         * classifications.
         *
         * Holiday handling can be refined separately if
         * holiday + rest-day scheduling needs to use the
         * official schedule in the same way as Rest Day.
         * -------------------------------------------------
         */

        $otStart =
            $timeIn->copy()->addHours(8);

        if (
            $timeOut->greaterThan($otStart)
        ) {

            $intervals[] = [
                'start' =>
                    $otStart,

                'end' =>
                    $timeOut->copy(),
            ];
        }

        return $intervals;
    }


    /**
     * Calculate approved OT minutes that overlap NSD.
     *
     * The supplied OT intervals represent the actual detected
     * OT periods.
     *
     * Approved OT minutes are allocated chronologically across
     * those intervals.
     *
     * This is especially important for Rest Day attendance,
     * where OT can be split into:
     *
     *     Time In → Official Time In
     *     Official Time Out → Time Out
     *
     * Example:
     *
     *     05:28 → 09:00 = 212 minutes
     *     17:00 → 21:00 = 240 minutes
     *
     * Total:
     *
     *     452 minutes
     *
     * If all 452 minutes are approved, the entire two intervals
     * are approved, including the 05:28 → 06:00 NSD period.
     */
    private function calculateApprovedOtNightMinutesFromIntervals(
        array $otIntervals,
        int $approvedOtMinutes
    ): int {

        if (
            $approvedOtMinutes <= 0
            || empty($otIntervals)
        ) {
            return 0;
        }

        $remainingApprovedMinutes =
            $approvedOtMinutes;

        $nightMinutes = 0;

        foreach ($otIntervals as $interval) {

            if ($remainingApprovedMinutes <= 0) {
                break;
            }

            $intervalStart =
                $interval['start'];

            $intervalEnd =
                $interval['end'];

            if (
                ! $intervalStart instanceof Carbon
                || ! $intervalEnd instanceof Carbon
            ) {
                continue;
            }

            if (
                $intervalEnd
                    ->lessThanOrEqualTo($intervalStart)
            ) {
                continue;
            }

            $intervalMinutes =
                $intervalStart->diffInMinutes(
                    $intervalEnd
                );

            if ($intervalMinutes <= 0) {
                continue;
            }

            $approvedInThisInterval =
                min(
                    $remainingApprovedMinutes,
                    $intervalMinutes
                );

            if ($approvedInThisInterval <= 0) {
                continue;
            }

            /*
             * Approved portion begins at the start of this
             * OT interval and continues chronologically.
             */

            $approvedStart =
                $intervalStart->copy();

            $approvedEnd =
                $approvedStart->copy()->addMinutes(
                    $approvedInThisInterval
                );

            $nightMinutes +=
                $this->calculateNightOverlapMinutes(
                    $approvedStart,
                    $approvedEnd
                );

            $remainingApprovedMinutes -=
                $approvedInThisInterval;
        }

        return (int) $nightMinutes;
    }


    /**
     * Calculate approved OT minutes that overlap
     * with the configured NSD period.
     *
     * This method now uses the actual OT intervals instead
     * of assuming that all OT is immediately before Time Out.
     */
    private function calculateApprovedOtNightMinutes(
        AttendanceRecord $attendance,
        int $approvedOtMinutes
    ): int {

        if (
            $approvedOtMinutes <= 0
            || ! $attendance->time_in
            || ! $attendance->time_out
        ) {
            return 0;
        }

        $timeIn =
            $this->buildAttendanceDateTime(
                $attendance,
                'time_in'
            );

        $timeOut =
            $this->buildAttendanceDateTime(
                $attendance,
                'time_out'
            );

        /*
         * Overnight attendance.
         */

        if (
            $timeOut->lessThanOrEqualTo($timeIn)
        ) {
            $timeOut->addDay();
        }

        /*
         * Build the actual OT intervals.
         */

        $otIntervals =
            $this->getOvertimeIntervals(
                $attendance,
                $timeIn,
                $timeOut
            );

        if (empty($otIntervals)) {
            return 0;
        }

        return $this->calculateApprovedOtNightMinutesFromIntervals(
            $otIntervals,
            $approvedOtMinutes
        );
    }


    /**
     * Build an attendance DateTime safely.
     *
     * Handles:
     *
     * - Carbon
     * - DateTime
     * - plain time strings
     * - datetime strings
     */
    private function buildAttendanceDateTime(
        AttendanceRecord $attendance,
        string $field
    ): Carbon {

        $attendanceDate =
            $attendance->attendance_date instanceof Carbon
                ? $attendance->attendance_date->format('Y-m-d')
                : Carbon::parse(
                    $attendance->attendance_date
                )->format('Y-m-d');

        $value =
            $attendance->{$field};

        if ($value instanceof Carbon) {

            $time =
                $value->format('H:i:s');

        } elseif (
            $value instanceof \DateTimeInterface
        ) {

            $time =
                $value->format('H:i:s');

        } else {

            $value =
                trim((string) $value);

            /*
             * If the value already contains a date,
             * extract only the time portion.
             *
             * Prevents:
             *
             * 2026-08-22 2026-09-09 05:28:00
             */

            if (
                preg_match(
                    '/(?:^|\s|T)(\d{2}:\d{2}(?::\d{2})?)/',
                    $value,
                    $matches
                )
            ) {

                $time =
                    $matches[1];

            } else {

                $time =
                    Carbon::parse($value)
                        ->format('H:i:s');
            }

            if (strlen($time) === 5) {
                $time .= ':00';
            }
        }

        return Carbon::parse(
            $attendanceDate . ' ' . $time
        );
    }


    /**
     * Build a DateTime from an attendance date
     * and a time value.
     */
    private function buildDateTimeFromTime(
        $attendanceDate,
        $time
    ): Carbon {

        $date =
            $attendanceDate instanceof Carbon
                ? $attendanceDate->format('Y-m-d')
                : Carbon::parse(
                    $attendanceDate
                )->format('Y-m-d');

        if ($time instanceof Carbon) {

            $timeString =
                $time->format('H:i:s');

        } elseif (
            $time instanceof \DateTimeInterface
        ) {

            $timeString =
                $time->format('H:i:s');

        } else {

            $timeString =
                Carbon::parse(
                    (string) $time
                )->format('H:i:s');
        }

        return Carbon::parse(
            $date . ' ' . $timeString
        );
    }


    /**
     * Determine whether an attendance date
     * is an employee rest day.
     */
    private function isRestDay(
        $attendanceDate
    ): bool {

        $dayName =
            strtolower(
                Carbon::parse(
                    $attendanceDate
                )->englishDayOfWeek
            );

        $workdays =
            Setting::getValue(
                'attendance',
                'workdays',
                [
                    'monday',
                    'tuesday',
                    'wednesday',
                    'thursday',
                    'friday',
                ]
            );

        if (! is_array($workdays)) {
            $workdays = [
                'monday',
                'tuesday',
                'wednesday',
                'thursday',
                'friday',
            ];
        }

        $workdays =
            array_map(
                fn ($day) =>
                    strtolower(trim($day)),
                $workdays
            );

        return ! in_array(
            $dayName,
            $workdays,
            true
        );
    }


    /**
     * Calculate overlap with configured NSD windows.
     *
     * Supports overnight NSD periods such as:
     *
     * 22:00 → 06:00
     */
    private function calculateNightOverlapMinutes(
        Carbon $start,
        Carbon $end
    ): int {

        if (
            $end->lessThanOrEqualTo($start)
        ) {
            return 0;
        }

        $nightStartTime =
            Setting::getValue(
                'attendance',
                'night_shift_differential_start',
                '22:00'
            );

        $nightEndTime =
            Setting::getValue(
                'attendance',
                'night_shift_differential_end',
                '06:00'
            );

        $totalMinutes = 0;

        /*
         * Start one day before the attendance interval
         * to safely handle overnight NSD.
         */

        $cursor =
            $start
                ->copy()
                ->startOfDay()
                ->subDay();

        $lastDay =
            $end
                ->copy()
                ->startOfDay()
                ->addDay();

        while (
            $cursor->lessThanOrEqualTo($lastDay)
        ) {

            $nightStart =
                $cursor
                    ->copy()
                    ->setTimeFromTimeString(
                        $nightStartTime
                    );

            $nightEnd =
                $cursor
                    ->copy()
                    ->setTimeFromTimeString(
                        $nightEndTime
                    );

            /*
             * Overnight NSD window.
             */

            if (
                $nightEnd
                    ->lessThanOrEqualTo($nightStart)
            ) {
                $nightEnd->addDay();
            }

            $overlapStart =
                $start->greaterThan($nightStart)
                    ? $start
                    : $nightStart;

            $overlapEnd =
                $end->lessThan($nightEnd)
                    ? $end
                    : $nightEnd;

            if (
                $overlapEnd
                    ->greaterThan($overlapStart)
            ) {

                $totalMinutes +=
                    $overlapStart->diffInMinutes(
                        $overlapEnd
                    );
            }

            $cursor->addDay();
        }

        return (int) $totalMinutes;
    }
}