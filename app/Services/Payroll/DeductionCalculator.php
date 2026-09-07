<?php

namespace App\Services\Payroll;

use App\Models\Employee;
use App\Models\EmployeeDeduction;
use App\Models\Payroll;
use App\Models\PayrollPeriod;
use App\Models\PhilhealthRate;
use App\Models\SssContributionBracket;
use Carbon\Carbon;

class DeductionCalculator
{
    public function calculate(
        Payroll $payroll,
        Employee $employee,
        float $grossPay,
        float $basicPay,
        PayrollPeriod $period,
        float $taxableGrossPay
    ): array {

        /*
         * -------------------------------------------------
         * STATUTORY DEDUCTIONS
         * -------------------------------------------------
         */

        $sss = 0.00;
        $philhealth = 0.00;
        $pagibig = 0.00;
        $withholdingTax = 0.00;

        $otherDeductions = 0.00;

        $sortOrder = 100;


        /*
         * -------------------------------------------------
         * STATUTORY CONTRIBUTIONS
         * -------------------------------------------------
         */

        $shouldDeductStatutory =
            $this->shouldDeductStatutoryContributions(
                $employee,
                $period
            );


        if ($shouldDeductStatutory) {

            /*
             * -------------------------------------------------
             * SSS
             * -------------------------------------------------
             */

            $sssGrossBasis =
                $this->resolveSssGrossBasis(
                    $employee,
                    $period,
                    $grossPay
                );


            $sss =
                $this->calculateSss(
                    $sssGrossBasis,
                    $period
                );


            if ($sss > 0) {

                $payroll->items()->create([
                    'item_type' => 'deduction',
                    'code' => 'SSS',
                    'description' =>
                        'SSS Contribution',
                    'quantity' => 1,
                    'rate' =>
                        round($sss, 2),
                    'amount' =>
                        round($sss, 2),
                    'sort_order' =>
                        $sortOrder,
                ]);

                $sortOrder++;
            }


            /*
             * -------------------------------------------------
             * PHILHEALTH
             * -------------------------------------------------
             */

            $monthlyBasicPay =
                $this->resolveMonthlyBasicPay(
                    $employee,
                    $period,
                    $basicPay
                );


            $philhealth =
                $this->calculatePhilhealth(
                    $monthlyBasicPay,
                    $period
                );


            if ($philhealth > 0) {

                $payroll->items()->create([
                    'item_type' => 'deduction',
                    'code' => 'PHILHEALTH',
                    'description' =>
                        'PhilHealth Contribution',
                    'quantity' => 1,
                    'rate' =>
                        round(
                            $philhealth,
                            2
                        ),
                    'amount' =>
                        round(
                            $philhealth,
                            2
                        ),
                    'sort_order' =>
                        $sortOrder,
                ]);

                $sortOrder++;
            }
        }


        /*
         * -------------------------------------------------
         * PAG-IBIG
         * -------------------------------------------------
         *
         * Your current calculator does not yet calculate
         * Pag-IBIG, so this remains 0.00.
         *
         * We are intentionally not changing that here.
         */


        /*
         * -------------------------------------------------
         * BIR TAXABLE COMPENSATION
         * -------------------------------------------------
         *
         * taxableGrossPay already contains:
         *
         *   Basic Pay
         *   + taxable allowances
         *   + holiday/rest-day premium
         *   + overtime
         *   + NSD
         *   + other taxable earnings
         *
         * It excludes:
         *
         *   non-taxable allowances
         *
         * Then mandatory employee contributions are
         * deducted before applying the BIR table.
         */

        $netTaxableCompensation =
            max(
                0,
                $taxableGrossPay
                - $sss
                - $philhealth
                - $pagibig
            );


        /*
         * -------------------------------------------------
         * BIR WITHHOLDING TAX
         * -------------------------------------------------
         */

        $withholdingTax =
            $this->calculateWithholdingTax(
                $employee,
                $netTaxableCompensation
            );


        /*
         * -------------------------------------------------
         * CREATE WITHHOLDING TAX ITEM
         * -------------------------------------------------
         */

        if ($withholdingTax > 0) {

            $payroll->items()->create([
                'item_type' => 'deduction',
                'code' =>
                    'WITHHOLDING_TAX',
                'description' =>
                    'Withholding Tax',
                'quantity' => 1,
                'rate' =>
                    round(
                        $withholdingTax,
                        2
                    ),
                'amount' =>
                    round(
                        $withholdingTax,
                        2
                    ),
                'sort_order' =>
                    $sortOrder,
            ]);

            $sortOrder++;
        }


        /*
         * -------------------------------------------------
         * OTHER EMPLOYEE DEDUCTIONS
         * -------------------------------------------------
         *
         * These remain deductions from NET PAY.
         *
         * They are NOT subtracted from BIR taxable
         * compensation.
         */

        foreach (
            $employee->deductions
            as $employeeDeduction
        ) {

            if (
                ! $this->isApplicable(
                    $employeeDeduction,
                    $period
                )
            ) {
                continue;
            }


            $amount =
                $this->calculateDeductionAmount(
                    $employeeDeduction
                );


            if ($amount <= 0) {
                continue;
            }


            $amount =
                round(
                    $amount,
                    2
                );


            $otherDeductions +=
                $amount;


            $payroll->items()->create([
                'item_type' =>
                    'deduction',

                'code' =>
                    $employeeDeduction
                        ->deduction?->code
                    ?? 'DEDUCTION',

                'description' =>
                    $employeeDeduction
                        ->deduction?->name
                    ?? 'Deduction',

                'reference_id' =>
                    $employeeDeduction
                        ->deduction_id,

                'quantity' => 1,

                'rate' => $amount,

                'amount' => $amount,

                'sort_order' =>
                    $sortOrder,
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
            'sss' =>
                round(
                    $sss,
                    2
                ),

            'philhealth' =>
                round(
                    $philhealth,
                    2
                ),

            'pagibig' =>
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
        ];
    }


    /*
     * -------------------------------------------------
     * BIR WITHHOLDING TAX
     * -------------------------------------------------
     */

    private function calculateWithholdingTax(
        Employee $employee,
        float $taxableCompensation
    ): float {

        $taxableCompensation =
            max(
                0,
                $taxableCompensation
            );


        $frequency = strtolower(
            str_replace(
                ['-', ' '],
                '_',
                (string)
                    $employee->pay_frequency
            )
        );


        /*
         * SEMI-MONTHLY
         */

        if (
            $frequency === 'semi_monthly'
        ) {

            return $this
                ->calculateSemiMonthlyTax(
                    $taxableCompensation
                );
        }


        /*
         * MONTHLY
         */

        if (
            $frequency === 'monthly'
        ) {

            return $this
                ->calculateMonthlyTax(
                    $taxableCompensation
                );
        }


        /*
         * WEEKLY
         */

        if (
            $frequency === 'weekly'
        ) {

            return $this
                ->calculateWeeklyTax(
                    $taxableCompensation
                );
        }


        /*
         * DAILY
         */

        if (
            $frequency === 'daily'
        ) {

            return $this
                ->calculateDailyTax(
                    $taxableCompensation
                );
        }


        /*
         * BI-WEEKLY
         *
         * BIR does not provide a dedicated bi-weekly
         * table. Use the semi-monthly equivalent.
         */

        if (
            $frequency === 'bi_weekly'
        ) {

            $semiMonthlyEquivalent =
                $taxableCompensation
                * 26
                / 24;


            $tax =
                $this
                    ->calculateSemiMonthlyTax(
                        $semiMonthlyEquivalent
                    );


            return round(
                $tax * 24 / 26,
                2
            );
        }


        /*
         * DEFAULT
         */

        return $this
            ->calculateSemiMonthlyTax(
                $taxableCompensation
            );
    }


    /*
     * -------------------------------------------------
     * BIR ANNEX E
     * SEMI-MONTHLY
     * -------------------------------------------------
     */

    private function calculateSemiMonthlyTax(
        float $taxable
    ): float {

        if ($taxable <= 10417) {
            return 0.00;
        }


        if ($taxable <= 16666) {

            return round(
                ($taxable - 10417)
                * 0.15,
                2
            );
        }


        if ($taxable <= 33332) {

            return round(
                937.50
                + (
                    ($taxable - 16667)
                    * 0.20
                ),
                2
            );
        }


        if ($taxable <= 83332) {

            return round(
                4270.70
                + (
                    ($taxable - 33333)
                    * 0.25
                ),
                2
            );
        }


        if ($taxable <= 333332) {

            return round(
                16770.70
                + (
                    ($taxable - 83333)
                    * 0.30
                ),
                2
            );
        }


        return round(
            91770.70
            + (
                ($taxable - 333333)
                * 0.35
            ),
            2
        );
    }


    /*
     * -------------------------------------------------
     * BIR ANNEX E
     * MONTHLY
     * -------------------------------------------------
     */

    private function calculateMonthlyTax(
        float $taxable
    ): float {

        if ($taxable <= 20833) {
            return 0.00;
        }


        if ($taxable <= 33332) {

            return round(
                ($taxable - 20833)
                * 0.15,
                2
            );
        }


        if ($taxable <= 66666) {

            return round(
                1875.00
                + (
                    ($taxable - 33333)
                    * 0.20
                ),
                2
            );
        }


        if ($taxable <= 166666) {

            return round(
                8541.80
                + (
                    ($taxable - 66667)
                    * 0.25
                ),
                2
            );
        }


        if ($taxable <= 666666) {

            return round(
                33541.80
                + (
                    ($taxable - 166667)
                    * 0.30
                ),
                2
            );
        }


        return round(
            183541.80
            + (
                ($taxable - 666667)
                * 0.35
            ),
            2
        );
    }


    /*
     * -------------------------------------------------
     * BIR ANNEX E
     * WEEKLY
     * -------------------------------------------------
     */

    private function calculateWeeklyTax(
        float $taxable
    ): float {

        if ($taxable <= 4808) {
            return 0.00;
        }


        if ($taxable <= 7691) {

            return round(
                ($taxable - 4808)
                * 0.15,
                2
            );
        }


        if ($taxable <= 15384) {

            return round(
                432.60
                + (
                    ($taxable - 7692)
                    * 0.20
                ),
                2
            );
        }


        if ($taxable <= 38461) {

            return round(
                1971.20
                + (
                    ($taxable - 15385)
                    * 0.25
                ),
                2
            );
        }


        if ($taxable <= 153845) {

            return round(
                7740.45
                + (
                    ($taxable - 38462)
                    * 0.30
                ),
                2
            );
        }


        return round(
            42355.65
            + (
                ($taxable - 153846)
                * 0.35
            ),
            2
        );
    }


    /*
     * -------------------------------------------------
     * BIR ANNEX E
     * DAILY
     * -------------------------------------------------
     */

    private function calculateDailyTax(
        float $taxable
    ): float {

        if ($taxable <= 685) {
            return 0.00;
        }


        if ($taxable <= 1095) {

            return round(
                ($taxable - 685)
                * 0.15,
                2
            );
        }


        if ($taxable <= 2191) {

            return round(
                61.65
                + (
                    ($taxable - 1096)
                    * 0.20
                ),
                2
            );
        }


        if ($taxable <= 5478) {

            return round(
                280.85
                + (
                    ($taxable - 2192)
                    * 0.25
                ),
                2
            );
        }


        if ($taxable <= 21917) {

            return round(
                1102.60
                + (
                    ($taxable - 5479)
                    * 0.30
                ),
                2
            );
        }


        return round(
            6034.00
            + (
                ($taxable - 21918)
                * 0.35
            ),
            2
        );
    }


    /*
     * -------------------------------------------------
     * STATUTORY CONTRIBUTION SCHEDULE
     * -------------------------------------------------
     */

    private function shouldDeductStatutoryContributions(
        Employee $employee,
        PayrollPeriod $period
    ): bool {

        $frequency = strtolower(
            str_replace(
                ['-', ' '],
                '_',
                $employee->pay_frequency
            )
        );


        if ($frequency !== 'semi_monthly') {
            return true;
        }


        $periodEndDay =
            Carbon::parse(
                $period->period_end
            )->day;


        return $periodEndDay <= 15;
    }


    /*
     * -------------------------------------------------
     * SSS GROSS BASIS
     * -------------------------------------------------
     */

    private function resolveSssGrossBasis(
        Employee $employee,
        PayrollPeriod $period,
        float $currentGrossPay
    ): float {

        $frequency = strtolower(
            str_replace(
                ['-', ' '],
                '_',
                $employee->pay_frequency
            )
        );


        if ($frequency !== 'semi_monthly') {
            return $currentGrossPay;
        }


        $periodStart =
            Carbon::parse(
                $period->period_start
            );


        $previousPeriod =
            PayrollPeriod::query()
                ->where(
                    'pay_frequency',
                    'semi_monthly'
                )
                ->where(
                    'period_start',
                    '<',
                    $periodStart->toDateString()
                )
                ->orderByDesc(
                    'period_start'
                )
                ->first();


        if (! $previousPeriod) {
            return $currentGrossPay;
        }


        $previousPayroll =
            Payroll::query()
                ->where(
                    'employee_id',
                    $employee->id
                )
                ->where(
                    'payroll_period_id',
                    $previousPeriod->id
                )
                ->first();


        if (
            ! $previousPayroll
            || $previousPayroll->gross_pay === null
        ) {
            return $currentGrossPay;
        }


        return
            $currentGrossPay
            + (float)
                $previousPayroll->gross_pay;
    }


    /*
     * -------------------------------------------------
     * MONTHLY BASIC PAY
     * -------------------------------------------------
     */

    private function resolveMonthlyBasicPay(
        Employee $employee,
        PayrollPeriod $period,
        float $currentBasicPay
    ): float {

        $frequency = strtolower(
            str_replace(
                ['-', ' '],
                '_',
                $employee->pay_frequency
            )
        );


        if ($frequency !== 'semi_monthly') {
            return $currentBasicPay;
        }


        $periodStart =
            Carbon::parse(
                $period->period_start
            );


        $otherPeriod =
            PayrollPeriod::query()
                ->where(
                    'pay_frequency',
                    'semi_monthly'
                )
                ->where(
                    'id',
                    '!=',
                    $period->id
                )
                ->whereYear(
                    'period_start',
                    $periodStart->year
                )
                ->whereMonth(
                    'period_start',
                    $periodStart->month
                )
                ->orderBy(
                    'period_start'
                )
                ->first();


        if (! $otherPeriod) {
            return $currentBasicPay;
        }


        $otherPayroll =
            Payroll::query()
                ->where(
                    'employee_id',
                    $employee->id
                )
                ->where(
                    'payroll_period_id',
                    $otherPeriod->id
                )
                ->first();


        if (
            ! $otherPayroll
            || $otherPayroll->basic_pay === null
        ) {
            return $currentBasicPay;
        }


        return
            $currentBasicPay
            + (float)
                $otherPayroll->basic_pay;
    }


    /*
     * -------------------------------------------------
     * SSS
     * -------------------------------------------------
     */

    private function calculateSss(
        float $grossPay,
        PayrollPeriod $period
    ): float {

        $bracket =
            SssContributionBracket::findForSalary(
                $grossPay,
                $period->period_end
            );


        if (! $bracket) {
            return 0.00;
        }


        return (float)
            $bracket->employee_share;
    }


    /*
     * -------------------------------------------------
     * PHILHEALTH
     * -------------------------------------------------
     */

    private function calculatePhilhealth(
        float $monthlyBasicPay,
        PayrollPeriod $period
    ): float {

        $rate =
            PhilhealthRate::currentAsOf(
                $period->period_end
            );


        if (! $rate) {
            return 0.00;
        }


        $basis = max(
            (float) $rate->salary_floor,
            min(
                $monthlyBasicPay,
                (float) $rate->salary_ceiling
            )
        );


        return round(
            $basis
            * (
                (float)
                    $rate->employee_share_rate
                / 100
            ),
            2
        );
    }


    /*
     * -------------------------------------------------
     * OTHER EMPLOYEE DEDUCTIONS
     * -------------------------------------------------
     */

    private function isApplicable(
        EmployeeDeduction $deduction,
        PayrollPeriod $period
    ): bool {

        if (! $deduction->is_active) {
            return false;
        }


        $startDate =
            $deduction->start_date
            ?? $deduction->effective_date;


        if ($startDate) {

            if (
                Carbon::parse($startDate)
                    ->gt(
                        Carbon::parse(
                            $period->period_end
                        )
                    )
            ) {
                return false;
            }
        }


        if ($deduction->end_date) {

            if (
                Carbon::parse(
                    $deduction->end_date
                )->lt(
                    Carbon::parse(
                        $period->period_start
                    )
                )
            ) {
                return false;
            }
        }


        if (
            $deduction->schedule_type ===
                'one_time'
            && $this->hasAlreadyBeenDeducted(
                $deduction,
                $period->id
            )
        ) {
            return false;
        }


        if (
            $deduction->schedule_type ===
                'installment'
            && (float)
                $deduction->remaining_balance <= 0
        ) {
            return false;
        }


        if (
            $deduction->schedule_type ===
                'installment'
            && $deduction->total_installments !== null
            && $deduction->paid_installments >=
                $deduction->total_installments
        ) {
            return false;
        }


        return true;
    }


    private function calculateDeductionAmount(
        EmployeeDeduction $deduction
    ): float {

        $scheduleType =
            $deduction->schedule_type
            ?? 'recurring';


        if ($scheduleType === 'one_time') {

            return max(
                0,
                (float) $deduction->amount
            );
        }


        if ($scheduleType === 'installment') {

            $remainingBalance =
                (float) (
                    $deduction->remaining_balance
                    ?? 0
                );


            $installmentAmount =
                (float) (
                    $deduction->installment_amount
                    ?? $deduction->amount
                    ?? 0
                );


            if (
                $remainingBalance <= 0
                || $installmentAmount <= 0
            ) {
                return 0.00;
            }


            return min(
                $installmentAmount,
                $remainingBalance
            );
        }


        return max(
            0,
            (float) $deduction->amount
        );
    }


    private function hasAlreadyBeenDeducted(
        EmployeeDeduction $employeeDeduction,
        int $payrollPeriodId
    ): bool {

        return Payroll::query()
            ->where(
                'employee_id',
                $employeeDeduction->employee_id
            )
            ->whereHas(
                'items',
                function ($query)
                use ($employeeDeduction) {

                    $query
                        ->where(
                            'item_type',
                            'deduction'
                        )
                        ->where(
                            'reference_id',
                            $employeeDeduction
                                ->deduction_id
                        );
                }
            )
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