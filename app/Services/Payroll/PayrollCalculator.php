<?php

namespace App\Services\Payroll;

use App\Models\AttendanceRecord;
use App\Models\Payroll;
use App\Models\Setting;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class PayrollCalculator
{
    public function __construct(
        protected DeductionCalculator $deductionCalculator
    ) {}

    public function calculate(Payroll $payroll): Payroll
    {
        return DB::transaction(function () use ($payroll) {

            /*
             * -------------------------------------------------
             * LOAD RELATIONSHIPS
             * -------------------------------------------------
             */

            $payroll->load([
                'employee.allowances.allowance',
                'employee.deductions.deduction',
                'payrollPeriod',
            ]);

            $employee = $payroll->employee;
            $period = $payroll->payrollPeriod;

            /*
             * -------------------------------------------------
             * CLEAR PREVIOUS ITEMS
             * -------------------------------------------------
             */

            $payroll->items()->delete();

            /*
             * -------------------------------------------------
             * BASIC SALARY
             * -------------------------------------------------
             */

            $salaryHistory = $employee->salaryHistories()
                ->whereDate(
                    'effective_date',
                    '<=',
                    $period->period_end
                )
                ->orderByDesc('effective_date')
                ->first();

            $basicSalary = (float) (
                $salaryHistory?->basic_salary
                ?? $employee->basic_salary
                ?? 0
            );

            $basicPay = $this->calculateBasicPay(
                $basicSalary,
                $employee->pay_frequency
            );

            /*
             * -------------------------------------------------
             * ATTENDANCE
             * -------------------------------------------------
             */

            $attendanceRecords = AttendanceRecord::query()
                ->where('employee_id', $employee->id)
                ->whereBetween('attendance_date', [
                    $period->period_start,
                    $period->period_end,
                ])
                ->orderBy('attendance_date')
                ->get();

            /*
             * -------------------------------------------------
             * VL / SL DEDUCTION
             * -------------------------------------------------
             *
             * VL and SL reduce basic pay.
             */

            $dailyRate = $this->calculateDailyRate(
                $basicSalary
            );

            $leaveDeduction = 0.00;

            foreach ($attendanceRecords as $attendance) {

                if (in_array(
                    $attendance->status,
                    ['vl', 'sl'],
                    true
                )) {
                    $leaveDeduction += $dailyRate;
                }
            }

            $basicPay = max(
                0,
                $basicPay - $leaveDeduction
            );

            /*
             * -------------------------------------------------
             * BASIC PAY ITEM
             * -------------------------------------------------
             */

            $payroll->items()->create([
                'item_type' => 'earning',
                'code' => 'BASIC',
                'description' => 'Basic Pay',
                'quantity' => 1,
                'rate' => round($basicPay, 2),
                'amount' => round($basicPay, 2),
                'sort_order' => 10,
            ]);

            /*
             * -------------------------------------------------
             * ALLOWANCES
             * -------------------------------------------------
             *
             * IMPORTANT:
             *
             * $allowancesTotal
             *     = ALL allowances
             *
             * $taxableAllowanceTotal
             *     = only allowances where
             *       allowance.is_taxable = true
             *
             * Non-taxable allowances still go into gross pay,
             * but do NOT go into BIR taxable compensation.
             */

            $allowancesTotal = 0.00;
            $taxableAllowanceTotal = 0.00;

            $allowanceSortOrder = 20;

            foreach ($employee->allowances as $employeeAllowance) {

                if (! $employeeAllowance->is_active) {
                    continue;
                }

                if (
                    $employeeAllowance->effective_date
                    && $employeeAllowance->effective_date >
                        $period->period_end
                ) {
                    continue;
                }

                if (
                    $employeeAllowance->end_date
                    && $employeeAllowance->end_date <
                        $period->period_start
                ) {
                    continue;
                }

                $allowance =
                    $employeeAllowance->allowance;

                if (! $allowance) {
                    continue;
                }

                /*
                 * -------------------------------------------------
                 * CALCULATE ALLOWANCE
                 * -------------------------------------------------
                 */

                if (
                    $allowance->calculation_type ===
                    'percentage_basic'
                ) {

                    /*
                     * Employee-specific percentage.
                     *
                     * Example:
                     *
                     * ₱20,604.83 × 5%
                     * = ₱1,030.24
                     */

                    $percentage = (float) (
                        $employeeAllowance->percentage
                        ?? 0
                    );

                    if ($percentage <= 0) {
                        continue;
                    }

                    $amount =
                        $basicSalary
                        * ($percentage / 100);

                } else {

                    $amount =
                        (float) $employeeAllowance->amount;
                }

                if ($amount <= 0) {
                    continue;
                }

                /*
                 * Monthly allowance on semi-monthly payroll
                 * is divided by two.
                 */

                $amount =
                    $this->calculateAllowanceAmount(
                        $amount,
                        $allowance->frequency,
                        $employee->pay_frequency
                    );

                $amount = round(
                    $amount,
                    2
                );

                $allowancesTotal += $amount;

                /*
                 * ONLY TAXABLE ALLOWANCES
                 * go into taxable compensation.
                 */

                if ((bool) $allowance->is_taxable) {
                    $taxableAllowanceTotal += $amount;
                }

                /*
                 * Payroll item.
                 */

                $payroll->items()->create([
                    'item_type' => 'earning',
                    'code' => 'ALLOWANCE',

                    'description' =>
                        $allowance->name
                        ?? 'Allowance',

                    'reference_id' =>
                        $employeeAllowance->allowance_id,

                    'quantity' => 1,

                    'rate' => $amount,

                    'amount' => $amount,

                    'sort_order' =>
                        $allowanceSortOrder,
                ]);

                $allowanceSortOrder++;
            }

            /*
             * -------------------------------------------------
             * PAYROLL RATES
             * -------------------------------------------------
             */

            $regularDayRate = $this->getPayrollRate(
                'regular_day_rate',
                100
            );

            $regularDayOvertimeRate =
                $this->getPayrollRate(
                    'regular_day_overtime_rate',
                    125
                );

            $restDayRate = $this->getPayrollRate(
                'rest_day_rate',
                130
            );

            $restDayOvertimeRate =
                $this->getPayrollRate(
                    'rest_day_overtime_rate',
                    169
                );

            $specialHolidayRate =
                $this->getPayrollRate(
                    'special_holiday_rate',
                    130
                );

            $specialHolidayOvertimeRate =
                $this->getPayrollRate(
                    'special_holiday_overtime_rate',
                    169
                );

            $regularHolidayRate =
                $this->getPayrollRate(
                    'regular_holiday_rate',
                    200
                );

            $regularHolidayOvertimeRate =
                $this->getPayrollRate(
                    'regular_holiday_overtime_rate',
                    260
                );

            $nightShiftDifferentialRate =
                $this->getPayrollRate(
                    'night_shift_differential_rate',
                    10
                );

            /*
             * -------------------------------------------------
             * DAILY / HOURLY RATE
             * -------------------------------------------------
             */

            $dailyRate =
                $this->calculateDailyRate(
                    $basicSalary
                );

            $hourlyRate =
                $dailyRate / 8;

            /*
             * -------------------------------------------------
             * PREMIUM PAY
             * -------------------------------------------------
             */

            $regularDayPay = 0.00;
            $restDayPay = 0.00;
            $specialHolidayPay = 0.00;
            $regularHolidayPay = 0.00;

            $overtimePay = 0.00;
            $nightShiftPay = 0.00;

            foreach ($attendanceRecords as $attendance) {

                /*
                 * -------------------------------------------------
                 * REST DAY
                 * -------------------------------------------------
                 */

                if ($attendance->status === 'rest_day') {

                    $restDayMinutes =
                        (int) $attendance->rest_day_minutes;

                    if ($restDayMinutes > 0) {

                        $restDayHours =
                            $restDayMinutes / 60;

                        $restDayPay +=
                            $restDayHours
                            * $hourlyRate
                            * $restDayRate;
                    }

                    /*
                     * Approved rest-day OT.
                     */

                    $approvedOtMinutes =
                        (int) $attendance
                            ->approved_overtime_minutes;

                    if (
                        $attendance->overtime_status ===
                            'approved'
                        && $approvedOtMinutes > 0
                    ) {

                        $otHours =
                            $approvedOtMinutes / 60;

                        $overtimePay +=
                            $otHours
                            * $hourlyRate
                            * $restDayOvertimeRate;
                    }

                    /*
                     * NSD.
                     */

                    $nightMinutes =
                        (int) $attendance
                            ->night_shift_minutes;

                    if ($nightMinutes > 0) {

                        $nightHours =
                            $nightMinutes / 60;

                        $nightShiftPay +=
                            $nightHours
                            * $hourlyRate
                            * $nightShiftDifferentialRate;
                    }

                    continue;
                }

                /*
                 * -------------------------------------------------
                 * SPECIAL NON-WORKING HOLIDAY
                 * -------------------------------------------------
                 */

                if (
                    $attendance->status ===
                    'special_non_working_holiday'
                ) {

                    $workedMinutes =
                        (int) $attendance->worked_minutes;

                    $holidayMinutes =
                        min(
                            $workedMinutes,
                            8 * 60
                        );

                    if ($holidayMinutes > 0) {

                        $holidayHours =
                            $holidayMinutes / 60;

                        $specialHolidayPay +=
                            $holidayHours
                            * $hourlyRate
                            * $specialHolidayRate;
                    }

                    /*
                     * Approved holiday OT.
                     */

                    $approvedOtMinutes =
                        (int) $attendance
                            ->approved_overtime_minutes;

                    if (
                        $attendance->overtime_status ===
                            'approved'
                        && $approvedOtMinutes > 0
                    ) {

                        $otHours =
                            $approvedOtMinutes / 60;

                        $overtimePay +=
                            $otHours
                            * $hourlyRate
                            * $specialHolidayOvertimeRate;
                    }

                    /*
                     * NSD.
                     */

                    $nightMinutes =
                        (int) $attendance
                            ->night_shift_minutes;

                    if ($nightMinutes > 0) {

                        $nightHours =
                            $nightMinutes / 60;

                        $nightShiftPay +=
                            $nightHours
                            * $hourlyRate
                            * $nightShiftDifferentialRate;
                    }

                    continue;
                }

                /*
                 * -------------------------------------------------
                 * REGULAR HOLIDAY
                 * -------------------------------------------------
                 */

                if (
                    $attendance->status ===
                    'regular_holiday'
                ) {

                    $workedMinutes =
                        (int) $attendance->worked_minutes;

                    $holidayMinutes =
                        min(
                            $workedMinutes,
                            8 * 60
                        );

                    if ($holidayMinutes > 0) {

                        $holidayHours =
                            $holidayMinutes / 60;

                        $regularHolidayPay +=
                            $holidayHours
                            * $hourlyRate
                            * $regularHolidayRate;
                    }

                    /*
                     * Approved holiday OT.
                     */

                    $approvedOtMinutes =
                        (int) $attendance
                            ->approved_overtime_minutes;

                    if (
                        $attendance->overtime_status ===
                            'approved'
                        && $approvedOtMinutes > 0
                    ) {

                        $otHours =
                            $approvedOtMinutes / 60;

                        $overtimePay +=
                            $otHours
                            * $hourlyRate
                            * $regularHolidayOvertimeRate;
                    }

                    /*
                     * NSD.
                     */

                    $nightMinutes =
                        (int) $attendance
                            ->night_shift_minutes;

                    if ($nightMinutes > 0) {

                        $nightHours =
                            $nightMinutes / 60;

                        $nightShiftPay +=
                            $nightHours
                            * $hourlyRate
                            * $nightShiftDifferentialRate;
                    }

                    continue;
                }

                /*
                 * -------------------------------------------------
                 * REGULAR WORKING DAY
                 * -------------------------------------------------
                 */

                if ($attendance->status === 'present') {

                    /*
                     * Regular attendance is already included
                     * in Basic Pay.
                     */

                    $approvedOtMinutes =
                        (int) $attendance
                            ->approved_overtime_minutes;

                    /*
                     * ALL approved OT is taxable.
                     */

                    if (
                        $attendance->overtime_status ===
                            'approved'
                        && $approvedOtMinutes > 0
                    ) {

                        $otHours =
                            $approvedOtMinutes / 60;

                        $overtimePay +=
                            $otHours
                            * $hourlyRate
                            * $regularDayOvertimeRate;
                    }

                    /*
                     * NSD.
                     *
                     * NSD is also taxable.
                     */

                    $nightMinutes =
                        (int) $attendance
                            ->night_shift_minutes;

                    if ($nightMinutes > 0) {

                        $nightHours =
                            $nightMinutes / 60;

                        $nightShiftPay +=
                            $nightHours
                            * $hourlyRate
                            * $nightShiftDifferentialRate;
                    }
                }
            }

            /*
             * -------------------------------------------------
             * PAYROLL ITEMS
             * -------------------------------------------------
             */

            if ($restDayPay > 0) {

                $payroll->items()->create([
                    'item_type' => 'earning',
                    'code' => 'REST_DAY',
                    'description' => 'Rest Day Pay',
                    'quantity' => 1,
                    'rate' => $restDayRate * 100,
                    'amount' => round(
                        $restDayPay,
                        2
                    ),
                    'sort_order' => 30,
                ]);
            }

            if ($specialHolidayPay > 0) {

                $payroll->items()->create([
                    'item_type' => 'earning',
                    'code' => 'SPECIAL_HOLIDAY',
                    'description' =>
                        'Special Non-Working Holiday Pay',
                    'quantity' => 1,
                    'rate' =>
                        $specialHolidayRate * 100,
                    'amount' =>
                        round(
                            $specialHolidayPay,
                            2
                        ),
                    'sort_order' => 31,
                ]);
            }

            if ($regularHolidayPay > 0) {

                $payroll->items()->create([
                    'item_type' => 'earning',
                    'code' => 'REGULAR_HOLIDAY',
                    'description' =>
                        'Regular Holiday Pay',
                    'quantity' => 1,
                    'rate' =>
                        $regularHolidayRate * 100,
                    'amount' =>
                        round(
                            $regularHolidayPay,
                            2
                        ),
                    'sort_order' => 32,
                ]);
            }

            if ($overtimePay > 0) {

                $payroll->items()->create([
                    'item_type' => 'earning',
                    'code' => 'OVERTIME',
                    'description' =>
                        'Approved Overtime Pay',
                    'quantity' => 1,
                    'rate' => 0,
                    'amount' =>
                        round(
                            $overtimePay,
                            2
                        ),
                    'sort_order' => 40,
                ]);
            }

            if ($nightShiftPay > 0) {

                $payroll->items()->create([
                    'item_type' => 'earning',
                    'code' => 'NSD',
                    'description' =>
                        'Night Shift Differential',
                    'quantity' => 1,
                    'rate' =>
                        $nightShiftDifferentialRate * 100,
                    'amount' =>
                        round(
                            $nightShiftPay,
                            2
                        ),
                    'sort_order' => 50,
                ]);
            }

            /*
             * -------------------------------------------------
             * OTHER EARNINGS
             * -------------------------------------------------
             */

            $otherEarnings = 0.00;

            /*
             * -------------------------------------------------
             * PREMIUM PAY
             * -------------------------------------------------
             */

            $premiumPay =
                $regularDayPay
                + $restDayPay
                + $specialHolidayPay
                + $regularHolidayPay
                + $overtimePay
                + $nightShiftPay;

            /*
             * -------------------------------------------------
             * GROSS PAY
             * -------------------------------------------------
             *
             * Includes BOTH taxable and non-taxable allowances.
             */

            $grossPay =
                $basicPay
                + $allowancesTotal
                + $premiumPay
                + $otherEarnings;

            /*
             * -------------------------------------------------
             * TAXABLE COMPENSATION
             * -------------------------------------------------
             *
             * Basic Pay                    TAXABLE
             * Taxable allowances           TAXABLE
             * Non-taxable allowances       EXCLUDED
             * Regular/rest/holiday pay    TAXABLE
             * Overtime                     TAXABLE
             * NSD                          TAXABLE
             * Other taxable earnings       TAXABLE
             */

            $taxableGrossPay =
                $basicPay
                + $taxableAllowanceTotal
                + $premiumPay
                + $otherEarnings;

            /*
             * -------------------------------------------------
             * DEDUCTIONS
             * -------------------------------------------------
             */

            $deductions =
                $this->deductionCalculator->calculate(
                    $payroll,
                    $employee,
                    $grossPay,
                    $basicPay,
                    $period,
                    $taxableGrossPay
                );

            $sss =
                $deductions['sss'];

            $philhealth =
                $deductions['philhealth'];

            $pagibig =
                $deductions['pagibig'];

            $withholdingTax =
                $deductions['withholding_tax'];

            $otherDeductions =
                $deductions['other_deductions'];

            $totalDeductions =
                $deductions['total_deductions'];

            /*
             * -------------------------------------------------
             * NET PAY
             * -------------------------------------------------
             */

            $netPay =
                $grossPay
                - $totalDeductions;

            /*
             * -------------------------------------------------
             * SAVE PAYROLL
             * -------------------------------------------------
             */

            $payroll->update([
                'basic_salary' =>
                    round(
                        $basicSalary,
                        2
                    ),

                'basic_pay' =>
                    round(
                        $basicPay,
                        2
                    ),

                'allowances' =>
                    round(
                        $allowancesTotal,
                        2
                    ),

                'overtime_pay' =>
                    round(
                        $overtimePay,
                        2
                    ),

                'other_earnings' =>
                    round(
                        $otherEarnings,
                        2
                    ),

                'gross_pay' =>
                    round(
                        $grossPay,
                        2
                    ),

                'sss_contribution' =>
                    round(
                        $sss,
                        2
                    ),

                'philhealth_contribution' =>
                    round(
                        $philhealth,
                        2
                    ),

                'pagibig_contribution' =>
                    round(
                        $pagibig,
                        2
                    ),

                'withholding_tax' =>
                    round(
                        $withholdingTax,
                        2
                    ),

                'other_deductions' =>
                    round(
                        $otherDeductions,
                        2
                    ),

                'total_deductions' =>
                    round(
                        $totalDeductions,
                        2
                    ),

                'net_pay' =>
                    round(
                        $netPay,
                        2
                    ),

                'status' =>
                    Payroll::STATUS_CALCULATED,

                'calculated_at' =>
                    now(),
            ]);

            return $payroll->fresh([
                'employee',
                'items',
                'payrollPeriod',
            ]);
        });
    }


    /**
     * Convert payroll percentage to decimal.
     */
    private function getPayrollRate(
        string $key,
        float $default
    ): float {
        return (
            (float) Setting::getValue(
                'payroll',
                $key,
                $default
            )
        ) / 100;
    }


    /**
     * Calculate Basic Pay.
     */
    private function calculateBasicPay(
        float $monthlySalary,
        string $frequency
    ): float {
        $frequency = strtolower(
            str_replace(
                ['-', ' '],
                '_',
                $frequency
            )
        );

        return match ($frequency) {

            'monthly' =>
                $monthlySalary,

            'semi_monthly' =>
                $monthlySalary / 2,

            'weekly' =>
                ($monthlySalary * 12) / 52,

            'bi_weekly' =>
                ($monthlySalary * 12) / 26,

            'daily' =>
                $monthlySalary,

            default =>
                $monthlySalary / 2,
        };
    }


    /**
     * Monthly Salary × 12 ÷ 260.
     */
    private function calculateDailyRate(
        float $monthlySalary
    ): float {
        return round(
            ($monthlySalary * 12) / 260,
            2
        );
    }


    /**
     * Calculate allowance amount according to frequency.
     */
    private function calculateAllowanceAmount(
        float $amount,
        ?string $allowanceFrequency,
        string $employeePayFrequency
    ): float {

        $allowanceFrequency = strtolower(
            str_replace(
                ['-', ' '],
                '_',
                $allowanceFrequency ?? 'monthly'
            )
        );

        $employeePayFrequency = strtolower(
            str_replace(
                ['-', ' '],
                '_',
                $employeePayFrequency
            )
        );

        if (
            $allowanceFrequency === 'monthly'
            && $employeePayFrequency ===
                'semi_monthly'
        ) {
            return $amount / 2;
        }

        return $amount;
    }
}