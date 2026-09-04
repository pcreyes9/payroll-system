<?php

namespace App\Services\Payroll;

use App\Models\AttendanceRecord;
use App\Models\Payroll;
use App\Models\Setting;
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
             * LOAD REQUIRED RELATIONSHIPS
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
             * CLEAR PREVIOUS PAYROLL ITEMS
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
             * Create Basic Pay payroll item
             */

            $payroll->items()->create([
                'item_type' => 'earning',
                'code' => 'BASIC',
                'description' => 'Basic Pay',
                'quantity' => 1,
                'rate' => $basicPay,
                'amount' => $basicPay,
                'sort_order' => 10,
            ]);

            /*
             * -------------------------------------------------
             * ALLOWANCES
             * -------------------------------------------------
             */

            $allowancesTotal = 0;
            $allowanceSortOrder = 20;

            foreach ($employee->allowances as $employeeAllowance) {

                // Must be active
                if (! $employeeAllowance->is_active) {
                    continue;
                }

                // Must have started by payroll period end
                if (
                    $employeeAllowance->effective_date &&
                    $employeeAllowance->effective_date > $period->period_end
                ) {
                    continue;
                }

                // Must not have ended before payroll period
                if (
                    $employeeAllowance->end_date &&
                    $employeeAllowance->end_date < $period->period_start
                ) {
                    continue;
                }

                $amount = (float) $employeeAllowance->amount;

                if ($amount <= 0) {
                    continue;
                }

                $amount = $this->calculateAllowanceAmount(
                    $amount,
                    $employeeAllowance->allowance?->frequency,
                    $employee->pay_frequency
                );

                $allowancesTotal += $amount;

                $payroll->items()->create([
                    'item_type' => 'earning',
                    'code' => 'ALLOWANCE',

                    'description' =>
                        $employeeAllowance->allowance?->name
                        ?? 'Allowance',

                    'reference_id' => $employeeAllowance->allowance_id,

                    'quantity' => 1,
                    'rate' => $amount,
                    'amount' => $amount,

                    'sort_order' => $allowanceSortOrder,
                ]);

                $allowanceSortOrder++;
            }

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
             * PAYROLL RATES
             * -------------------------------------------------
             *
             * Rates are stored in the database as percentages.
             *
             * Example:
             *
             * 125 = 125%
             * 130 = 130%
             * 169 = 169%
             * 10  = 10%
             *
             * They are converted to decimal multipliers below.
             */

            $regularDayRate = $this->getPayrollRate(
                'regular_day_rate',
                100
            );

            $regularDayOvertimeRate = $this->getPayrollRate(
                'regular_day_overtime_rate',
                125
            );

            $restDayRate = $this->getPayrollRate(
                'rest_day_rate',
                130
            );

            $restDayOvertimeRate = $this->getPayrollRate(
                'rest_day_overtime_rate',
                169
            );

            $nightShiftDifferentialRate = $this->getPayrollRate(
                'night_shift_differential_rate',
                10
            );

            /*
             * -------------------------------------------------
             * HOURLY RATE
             * -------------------------------------------------
             *
             * 8 hours = 1 regular working day.
             *
             * Daily rate is derived from the monthly salary.
             */

            $dailyRate = $this->calculateDailyRate(
                $basicSalary,
                $employee->pay_frequency
            );

            $hourlyRate = $dailyRate / 8;

            /*
             * -------------------------------------------------
             * ATTENDANCE PAY
             * -------------------------------------------------
             */

            $regularDayPay = 0;
            $restDayPay = 0;
            $overtimePay = 0;
            $nightShiftPay = 0;

            foreach ($attendanceRecords as $attendance) {

                /*
                 * -------------------------------------------------
                 * REST DAY
                 * -------------------------------------------------
                 */

                if ($attendance->status === 'rest_day') {

                    /*
                     * Regular rest-day hours
                     *
                     * Example:
                     *
                     * 8 hours × hourly rate × 130%
                     */

                    $restDayMinutes = (int) $attendance->rest_day_minutes;

                    if ($restDayMinutes > 0) {

                        $restDayHours = $restDayMinutes / 60;

                        $amount = $restDayHours
                            * $hourlyRate
                            * $restDayRate;

                        $restDayPay += $amount;
                    }

                    /*
                     * Approved rest-day overtime
                     */

                    $approvedOtMinutes = (int) $attendance->approved_overtime_minutes;

                    if (
                        $attendance->overtime_status === 'approved'
                        && $approvedOtMinutes > 0
                    ) {
                        $otHours = $approvedOtMinutes / 60;

                        $amount = $otHours
                            * $hourlyRate
                            * $restDayOvertimeRate;

                        $overtimePay += $amount;
                    }

                    /*
                     * NSD on rest day
                     */

                    $nightMinutes = (int) $attendance->night_shift_minutes;

                    if ($nightMinutes > 0) {

                        $nightHours = $nightMinutes / 60;

                        $amount = $nightHours
                            * $hourlyRate
                            * $nightShiftDifferentialRate;

                        $nightShiftPay += $amount;
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
                     * Regular-day attendance is already included
                     * in the employee's basic salary.
                     *
                     * Therefore we do not add the 100% regular
                     * day amount again here.
                     */

                    $regularMinutes = (int) $attendance->regular_minutes;

                    if ($regularMinutes > 0) {
                        $regularDayPay += 0;
                    }

                    /*
                     * Approved regular-day overtime
                     */

                    $approvedOtMinutes = (int) $attendance->approved_overtime_minutes;

                    if (
                        $attendance->overtime_status === 'approved'
                        && $approvedOtMinutes > 0
                    ) {
                        $otHours = $approvedOtMinutes / 60;

                        $amount = $otHours
                            * $hourlyRate
                            * $regularDayOvertimeRate;

                        $overtimePay += $amount;
                    }

                    /*
                     * NSD
                     */

                    $nightMinutes = (int) $attendance->night_shift_minutes;

                    if ($nightMinutes > 0) {

                        $nightHours = $nightMinutes / 60;

                        $amount = $nightHours
                            * $hourlyRate
                            * $nightShiftDifferentialRate;

                        $nightShiftPay += $amount;
                    }
                }
            }

            /*
             * -------------------------------------------------
             * CREATE REST DAY PAY ITEM
             * -------------------------------------------------
             */

            if ($restDayPay > 0) {

                $payroll->items()->create([
                    'item_type' => 'earning',
                    'code' => 'REST_DAY',
                    'description' => 'Rest Day Pay',
                    'quantity' => 1,
                    'rate' => $restDayRate * 100,
                    'amount' => round($restDayPay, 2),
                    'sort_order' => 30,
                ]);
            }

            /*
             * -------------------------------------------------
             * CREATE OVERTIME PAY ITEM
             * -------------------------------------------------
             */

            if ($overtimePay > 0) {

                $payroll->items()->create([
                    'item_type' => 'earning',
                    'code' => 'OVERTIME',
                    'description' => 'Approved Overtime Pay',
                    'quantity' => 1,
                    'rate' => 0,
                    'amount' => round($overtimePay, 2),
                    'sort_order' => 40,
                ]);
            }

            /*
             * -------------------------------------------------
             * CREATE NSD PAY ITEM
             * -------------------------------------------------
             */

            if ($nightShiftPay > 0) {

                $payroll->items()->create([
                    'item_type' => 'earning',
                    'code' => 'NSD',
                    'description' => 'Night Shift Differential',
                    'quantity' => 1,
                    'rate' => $nightShiftDifferentialRate * 100,
                    'amount' => round($nightShiftPay, 2),
                    'sort_order' => 50,
                ]);
            }

            /*
             * -------------------------------------------------
             * TOTAL OTHER EARNINGS
             * -------------------------------------------------
             */

            $otherEarnings = 0;

            /*
             * -------------------------------------------------
             * TOTAL PREMIUM PAY
             * -------------------------------------------------
             */

            $premiumPay =
                $regularDayPay
                + $restDayPay
                + $overtimePay
                + $nightShiftPay;

            /*
             * -------------------------------------------------
             * GROSS PAY
             * -------------------------------------------------
             */

            $grossPay =
                $basicPay
                + $allowancesTotal
                + $premiumPay
                + $otherEarnings;

            /*
             * -------------------------------------------------
             * DEDUCTIONS
             * -------------------------------------------------
             */

            $deductions = $this->deductionCalculator->calculate(
                $payroll,
                $employee,
                $grossPay,
                $period
            );

            $sss = $deductions['sss'];

            $philhealth = $deductions['philhealth'];

            $pagibig = $deductions['pagibig'];

            $withholdingTax = $deductions['withholding_tax'];

            $otherDeductions = $deductions['other_deductions'];

            $totalDeductions = $deductions['total_deductions'];

            /*
             * -------------------------------------------------
             * NET PAY
             * -------------------------------------------------
             */

            $netPay = $grossPay - $totalDeductions;

            /*
             * -------------------------------------------------
             * SAVE PAYROLL
             * -------------------------------------------------
             */

            $payroll->update([
                'basic_salary' => $basicSalary,

                'basic_pay' => $basicPay,

                'allowances' => $allowancesTotal,

                'overtime_pay' => $overtimePay,

                'other_earnings' => $otherEarnings,

                'gross_pay' => $grossPay,

                'sss_contribution' => $sss,

                'philhealth_contribution' => $philhealth,

                'pagibig_contribution' => $pagibig,

                'withholding_tax' => $withholdingTax,

                'other_deductions' => $otherDeductions,

                'total_deductions' => $totalDeductions,

                'net_pay' => $netPay,

                'status' => Payroll::STATUS_CALCULATED,

                'calculated_at' => now(),
            ]);

            /*
             * -------------------------------------------------
             * RETURN FRESH PAYROLL
             * -------------------------------------------------
             */

            return $payroll->fresh([
                'employee',
                'items',
                'payrollPeriod',
            ]);
        });
    }

    /**
     * Get a payroll percentage from the settings table.
     *
     * Example:
     *
     * 125 stored in database
     * becomes
     * 1.25 for calculation.
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
     * Calculate basic pay according to employee pay frequency.
     */
    private function calculateBasicPay(
        float $monthlySalary,
        string $frequency
    ): float {
        return match (
            strtolower(
                str_replace(
                    ['-', ' '],
                    '_',
                    $frequency
                )
            )
        ) {
            'monthly' => $monthlySalary,

            'semi_monthly' => $monthlySalary / 2,

            'weekly' => ($monthlySalary * 12) / 52,

            'bi_weekly' => ($monthlySalary * 12) / 26,

            'daily' => ($monthlySalary * 12) / 313,

            default => $monthlySalary / 2,
        };
    }

    /**
     * Calculate the daily rate used for premium pay.
     *
     * For now, this uses the monthly salary / 26.
     *
     * This is appropriate for the current premium-pay
     * calculation where one working day is treated as
     * 8 hours.
     */
    private function calculateDailyRate(
        float $monthlySalary,
        string $frequency
    ): float {
        return match (
            strtolower(
                str_replace(
                    ['-', ' '],
                    '_',
                    $frequency
                )
            )
        ) {
            'daily' => $monthlySalary,

            default => $monthlySalary / 26,
        };
    }

    /**
     * Calculate the allowance amount for the current payroll period.
     *
     * Allowance amounts are stored per their own defined frequency
     * (e.g. "monthly" or "semi_monthly" on the allowance record).
     *
     * If the allowance is defined as "monthly" but the employee is
     * paid semi-monthly, the stored amount is split evenly across
     * both payroll runs. Otherwise the amount is used as-is.
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
            && $employeePayFrequency === 'semi_monthly'
        ) {
            return $amount / 2;
        }

        return $amount;
    }
}