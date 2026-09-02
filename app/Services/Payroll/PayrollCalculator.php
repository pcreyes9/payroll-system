<?php

namespace App\Services\Payroll;

use App\Models\Payroll;
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
             *
             * This prevents duplicate items when recalculating.
             */

            $payroll->items()->delete();

            /*
             * -------------------------------------------------
             * BASIC PAY
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

                $allowancesTotal += $amount;

                /*
                 * Create allowance payroll item
                 */

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
             * OVERTIME / OTHER EARNINGS
             * -------------------------------------------------
             */

            $overtimePay = 0;
            $otherEarnings = 0;

            /*
             * -------------------------------------------------
             * GROSS PAY
             * -------------------------------------------------
             */

            $grossPay =
                $basicPay
                + $allowancesTotal
                + $overtimePay
                + $otherEarnings;

            /*
             * -------------------------------------------------
             * DEDUCTIONS
             * -------------------------------------------------
             *
             * All deductions are handled by the
             * DeductionCalculator.
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
     * Calculate basic pay based on employee pay frequency.
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
}
