<?php

namespace App\Services\Payroll;

use App\Models\AttendanceRecord;
use App\Models\Payroll;
use App\Models\Setting;

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

            $hours = intdiv($minutes, 60);
            $remaining = $minutes % 60;

            if ($hours && $remaining) {
                return "{$hours} hr {$remaining} min";
            }

            if ($hours) {
                return "{$hours} hr";
            }

            return "{$remaining} min";
        };

        $money = static function ($amount): string {
            return '₱' . number_format((float) $amount, 2);
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
         * PAYROLL AMOUNTS
         * -------------------------------------------------
         */

        $basicSalary = (float) $record->basic_salary;
        $basicPay = (float) $record->basic_pay;
        $allowances = (float) $record->allowances;
        $overtimePay = (float) $record->overtime_pay;
        $grossPay = (float) $record->gross_pay;

        $totalDeductions = (float) $record->total_deductions;
        $netPay = (float) $record->net_pay;

        /*
         * -------------------------------------------------
         * PAYROLL RATES
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

        $specialHolidayRestDayRate = (float) Setting::getValue(
            'payroll',
            'special_holiday_rest_day_rate',
            150
        );

        $specialHolidayRestDayOvertimeRate = (float) Setting::getValue(
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

        $regularHolidayRestDayRate = (float) Setting::getValue(
            'payroll',
            'regular_holiday_rest_day_rate',
            260
        );

        $regularHolidayRestDayOvertimeRate = (float) Setting::getValue(
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
         * Monthly salary / 26 working days / 8 hours.
         */

        $dailyRate = $basicSalary / 26;
        $hourlyRate = $dailyRate / 8;

        /*
         * -------------------------------------------------
         * HOURLY EQUIVALENTS
         * -------------------------------------------------
         */

        $regularHourlyAmount =
            $hourlyRate * ($regularDayRate / 100);

        $regularOtHourlyAmount =
            $hourlyRate * ($regularDayOvertimeRate / 100);

        $restDayHourlyAmount =
            $hourlyRate * ($restDayRate / 100);

        $restDayOtHourlyAmount =
            $hourlyRate * ($restDayOvertimeRate / 100);

        $specialHolidayHourlyAmount =
            $hourlyRate * ($specialHolidayRate / 100);

        $specialHolidayOtHourlyAmount =
            $hourlyRate * ($specialHolidayOvertimeRate / 100);

        $specialHolidayRestDayHourlyAmount =
            $hourlyRate * ($specialHolidayRestDayRate / 100);

        $specialHolidayRestDayOtHourlyAmount =
            $hourlyRate * ($specialHolidayRestDayOvertimeRate / 100);

        $regularHolidayHourlyAmount =
            $hourlyRate * ($regularHolidayRate / 100);

        $regularHolidayOtHourlyAmount =
            $hourlyRate * ($regularHolidayOvertimeRate / 100);

        $regularHolidayRestDayHourlyAmount =
            $hourlyRate * ($regularHolidayRestDayRate / 100);

        $regularHolidayRestDayOtHourlyAmount =
            $hourlyRate * ($regularHolidayRestDayOvertimeRate / 100);

        $nsdHourlyAmount =
            $hourlyRate * ($nightShiftDifferentialRate / 100);

        /*
         * -------------------------------------------------
         * APPROVED OT
         * -------------------------------------------------
         */

        $weekdayOt = (int) $attendanceRecords
            ->filter(fn ($item) => $item->status === 'present')
            ->sum('approved_overtime_minutes');

        $restDayOt = (int) $attendanceRecords
            ->filter(fn ($item) => $item->status === 'rest_day')
            ->sum('approved_overtime_minutes');

        /*
         * -------------------------------------------------
         * ACTUAL PREMIUM PAY AMOUNTS
         * -------------------------------------------------
         */

        $regularDayOvertimeAmount =
            ($weekdayOt / 60)
            * $regularOtHourlyAmount;

        $restDayAmount =
            ($totalRestDayMinutes / 60)
            * $restDayHourlyAmount;

        $restDayOvertimeAmount =
            ($restDayOt / 60)
            * $restDayOtHourlyAmount;

        $nightShiftDifferentialAmount =
            ($totalNsdMinutes / 60)
            * $nsdHourlyAmount;

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
            'employee' => $employee,
            'payrollPeriod' => $payrollPeriod,
            'attendanceRecords' => $attendanceRecords,

            'formatMinutes' => $formatMinutes,
            'money' => $money,

            /*
             * Attendance
             */
            'totalRegularMinutes' => $totalRegularMinutes,
            'totalLateMinutes' => $totalLateMinutes,
            'totalUndertimeMinutes' => $totalUndertimeMinutes,
            'totalRestDayMinutes' => $totalRestDayMinutes,
            'totalDetectedOtMinutes' => $totalDetectedOtMinutes,
            'totalApprovedOtMinutes' => $totalApprovedOtMinutes,
            'totalNsdMinutes' => $totalNsdMinutes,

            /*
             * Payroll
             */
            'basicSalary' => $basicSalary,
            'basicPay' => $basicPay,
            'allowances' => $allowances,
            'overtimePay' => $overtimePay,
            'grossPay' => $grossPay,
            'totalDeductions' => $totalDeductions,
            'netPay' => $netPay,

            /*
             * Salary basis
             */
            'dailyRate' => $dailyRate,
            'hourlyRate' => $hourlyRate,

            /*
             * Rates
             */
            'regularDayRate' => $regularDayRate,
            'regularDayOvertimeRate' => $regularDayOvertimeRate,

            'restDayRate' => $restDayRate,
            'restDayOvertimeRate' => $restDayOvertimeRate,

            'specialHolidayRate' => $specialHolidayRate,
            'specialHolidayOvertimeRate' => $specialHolidayOvertimeRate,

            'specialHolidayRestDayRate' => $specialHolidayRestDayRate,
            'specialHolidayRestDayOvertimeRate' => $specialHolidayRestDayOvertimeRate,

            'regularHolidayRate' => $regularHolidayRate,
            'regularHolidayOvertimeRate' => $regularHolidayOvertimeRate,

            'regularHolidayRestDayRate' => $regularHolidayRestDayRate,
            'regularHolidayRestDayOvertimeRate' => $regularHolidayRestDayOvertimeRate,

            'nightShiftDifferentialRate' => $nightShiftDifferentialRate,

            /*
             * Hourly peso equivalents
             */
            'regularHourlyAmount' => $regularHourlyAmount,
            'regularOtHourlyAmount' => $regularOtHourlyAmount,

            'restDayHourlyAmount' => $restDayHourlyAmount,
            'restDayOtHourlyAmount' => $restDayOtHourlyAmount,

            'specialHolidayHourlyAmount' => $specialHolidayHourlyAmount,
            'specialHolidayOtHourlyAmount' => $specialHolidayOtHourlyAmount,

            'specialHolidayRestDayHourlyAmount' => $specialHolidayRestDayHourlyAmount,
            'specialHolidayRestDayOtHourlyAmount' => $specialHolidayRestDayOtHourlyAmount,

            'regularHolidayHourlyAmount' => $regularHolidayHourlyAmount,
            'regularHolidayOtHourlyAmount' => $regularHolidayOtHourlyAmount,

            'regularHolidayRestDayHourlyAmount' => $regularHolidayRestDayHourlyAmount,
            'regularHolidayRestDayOtHourlyAmount' => $regularHolidayRestDayOtHourlyAmount,

            'nsdHourlyAmount' => $nsdHourlyAmount,

            /*
             * Actual amounts
             */
            'regularDayOvertimeAmount' => $regularDayOvertimeAmount,
            'restDayAmount' => $restDayAmount,
            'restDayOvertimeAmount' => $restDayOvertimeAmount,
            'nightShiftDifferentialAmount' => $nightShiftDifferentialAmount,

            /*
             * Items
             */
            'allowanceItems' => $allowanceItems,
            'deductionItems' => $deductionItems,

            /*
             * OT
             */
            'weekdayOt' => $weekdayOt,
            'restDayOt' => $restDayOt,
        ];
    }
}
