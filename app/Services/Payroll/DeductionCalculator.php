<?php

namespace App\Services\Payroll;

use App\Models\Employee;
use App\Models\EmployeeDeduction;
use App\Models\Payroll;
use App\Models\PayrollPeriod;
use Carbon\Carbon;

class DeductionCalculator
{
    public function calculate(
        Payroll $payroll,
        Employee $employee,
        float $grossPay,
        PayrollPeriod $period
    ): array {
        /*
         * -------------------------------------------------
         * STATUTORY DEDUCTIONS
         * -------------------------------------------------
         *
         * These remain zero until their respective
         * calculators are implemented.
         */

        $sss = 0.00;
        $philhealth = 0.00;
        $pagibig = 0.00;
        $withholdingTax = 0.00;

        /*
         * -------------------------------------------------
         * OTHER EMPLOYEE DEDUCTIONS
         * -------------------------------------------------
         */

        $otherDeductions = 0.00;
        $sortOrder = 100;

        foreach ($employee->deductions as $employeeDeduction) {

            if (! $this->isApplicable(
                $employeeDeduction,
                $period
            )) {
                continue;
            }

            $amount = $this->calculateDeductionAmount(
                $employeeDeduction
            );

            if ($amount <= 0) {
                continue;
            }

            $otherDeductions += $amount;

            /*
             * -------------------------------------------------
             * CREATE PAYROLL ITEM
             * -------------------------------------------------
             */

            $payroll->items()->create([
                'item_type' => 'deduction',

                'code' =>
                    $employeeDeduction->deduction?->code
                    ?? 'DEDUCTION',

                'description' =>
                    $employeeDeduction->deduction?->name
                    ?? 'Deduction',

                'reference_id' =>
                    $employeeDeduction->deduction_id,

                'quantity' => 1,

                'rate' => $amount,

                'amount' => $amount,

                'sort_order' => $sortOrder,
            ]);

            $sortOrder++;
        }

        /*
         * -------------------------------------------------
         * TOTAL DEDUCTIONS
         * -------------------------------------------------
         */

        $totalDeductions =
            $sss
            + $philhealth
            + $pagibig
            + $withholdingTax
            + $otherDeductions;

        return [
            'sss' => round($sss, 2),

            'philhealth' => round($philhealth, 2),

            'pagibig' => round($pagibig, 2),

            'withholding_tax' => round($withholdingTax, 2),

            'other_deductions' => round(
                $otherDeductions,
                2
            ),

            'total_deductions' => round(
                $totalDeductions,
                2
            ),
        ];
    }

    /*
     * -------------------------------------------------
     * CHECK WHETHER DEDUCTION APPLIES
     * -------------------------------------------------
     */

    private function isApplicable(
        EmployeeDeduction $deduction,
        PayrollPeriod $period
    ): bool {
        /*
         * Must be active.
         */

        if (! $deduction->is_active) {
            return false;
        }

        /*
         * Start date.
         *
         * Prefer start_date when available.
         * Fall back to effective_date for older records.
         */

        $startDate =
            $deduction->start_date
            ?? $deduction->effective_date;

        if ($startDate) {
            $startDate = Carbon::parse($startDate);

            if ($startDate->gt(
                Carbon::parse($period->period_end)
            )) {
                return false;
            }
        }

        /*
         * End date.
         */

        if ($deduction->end_date) {
            $endDate = Carbon::parse(
                $deduction->end_date
            );

            if ($endDate->lt(
                Carbon::parse($period->period_start)
            )) {
                return false;
            }
        }

        /*
         * One-time deductions.
         *
         * A one-time deduction should only be processed
         * once. We check whether this deduction has already
         * been used in a previous calculated payroll.
         */

        if (
            $deduction->schedule_type === 'one_time'
            && $this->hasAlreadyBeenDeducted(
                $deduction,
                $payrollPeriodId = $period->id
            )
        ) {
            return false;
        }

        /*
         * Installment deductions stop automatically
         * when their balance reaches zero.
         */

        if (
            $deduction->schedule_type === 'installment'
            && (float) $deduction->remaining_balance <= 0
        ) {
            return false;
        }

        /*
         * Installment deductions also stop when all
         * installments have been completed.
         */

        if (
            $deduction->schedule_type === 'installment'
            && $deduction->total_installments !== null
            && $deduction->paid_installments >=
                $deduction->total_installments
        ) {
            return false;
        }

        return true;
    }

    /*
     * -------------------------------------------------
     * CALCULATE DEDUCTION AMOUNT
     * -------------------------------------------------
     */

    private function calculateDeductionAmount(
        EmployeeDeduction $deduction
    ): float {
        $scheduleType =
            $deduction->schedule_type ?? 'recurring';

        /*
         * -------------------------------------------------
         * ONE TIME
         * -------------------------------------------------
         */

        if ($scheduleType === 'one_time') {
            return max(
                0,
                (float) $deduction->amount
            );
        }

        /*
         * -------------------------------------------------
         * INSTALLMENT
         * -------------------------------------------------
         */

        if ($scheduleType === 'installment') {

            $remainingBalance = (float) (
                $deduction->remaining_balance
                ?? 0
            );

            $installmentAmount = (float) (
                $deduction->installment_amount
                ?? $deduction->amount
                ?? 0
            );

            if ($remainingBalance <= 0) {
                return 0;
            }

            if ($installmentAmount <= 0) {
                return 0;
            }

            /*
             * Never deduct more than the remaining balance.
             */

            return min(
                $installmentAmount,
                $remainingBalance
            );
        }

        /*
         * -------------------------------------------------
         * RECURRING
         * -------------------------------------------------
         */

        return max(
            0,
            (float) $deduction->amount
        );
    }

    /*
     * -------------------------------------------------
     * CHECK PREVIOUS PAYROLLS
     * -------------------------------------------------
     */

    private function hasAlreadyBeenDeducted(
        EmployeeDeduction $employeeDeduction,
        int $payrollPeriodId
    ): bool {
        return Payroll::query()
            ->where('employee_id', $employeeDeduction->employee_id)
            ->whereHas('items', function ($query) use (
                $employeeDeduction
            ) {
                $query
                    ->where('item_type', 'deduction')
                    ->where(
                        'reference_id',
                        $employeeDeduction->deduction_id
                    );
            })
            ->where(
                'payroll_period_id',
                '!=',
                $payrollPeriodId
            )
            ->whereIn('status', [
                Payroll::STATUS_CALCULATED,
                Payroll::STATUS_APPROVED,
                Payroll::STATUS_PAID,
            ])
            ->exists();
    }
}
