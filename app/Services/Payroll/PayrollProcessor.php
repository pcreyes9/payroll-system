<?php

namespace App\Services\Payroll;

use App\Models\Payroll;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class PayrollProcessor
{
    public function __construct(
        protected PayrollCalculator $calculator
    ) {}

    /**
     * Calculate or recalculate a payroll.
     */
    public function calculate(Payroll $payroll): Payroll
    {
        if (in_array($payroll->status, [
            Payroll::STATUS_APPROVED,
            Payroll::STATUS_PAID,
        ], true)) {
            throw new RuntimeException(
                'Approved or paid payroll cannot be recalculated.'
            );
        }

        return $this->calculator->calculate($payroll);
    }

    /**
     * Approve a calculated payroll.
     */
    public function approve(Payroll $payroll): Payroll
    {
        return DB::transaction(function () use ($payroll) {

            $payroll->refresh();

            if ($payroll->status !== Payroll::STATUS_CALCULATED) {
                throw new RuntimeException(
                    'Only calculated payroll can be approved.'
                );
            }

            if ($payroll->net_pay < 0) {
                throw new RuntimeException(
                    'Payroll with negative net pay cannot be approved.'
                );
            }

            $payroll->update([
                'status' => Payroll::STATUS_APPROVED,
                'approved_at' => now(),
            ]);

            return $payroll->fresh([
                'employee',
                'items',
                'payrollPeriod',
            ]);
        });
    }

    /**
     * Mark an approved payroll as paid.
     */
    public function markAsPaid(Payroll $payroll): Payroll
    {
        return DB::transaction(function () use ($payroll) {

            $payroll->refresh();

            if ($payroll->status !== Payroll::STATUS_APPROVED) {
                throw new RuntimeException(
                    'Only approved payroll can be marked as paid.'
                );
            }

            $payroll->update([
                'status' => Payroll::STATUS_PAID,
                'paid_at' => now(),
            ]);

            return $payroll->fresh([
                'employee',
                'items',
                'payrollPeriod',
            ]);
        });
    }

    /**
     * Cancel a payroll.
     */
    public function cancel(Payroll $payroll): Payroll
    {
        return DB::transaction(function () use ($payroll) {

            $payroll->refresh();

            if ($payroll->status === Payroll::STATUS_PAID) {
                throw new RuntimeException(
                    'Paid payroll cannot be cancelled.'
                );
            }

            if ($payroll->status === Payroll::STATUS_CANCELLED) {
                throw new RuntimeException(
                    'Payroll is already cancelled.'
                );
            }

            $payroll->update([
                'status' => Payroll::STATUS_CANCELLED,
            ]);

            return $payroll->fresh();
        });
    }
}
