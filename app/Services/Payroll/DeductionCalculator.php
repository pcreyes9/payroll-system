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
        float $taxableGrossPay,
        float $tardinessDeduction = 0.00
    ): array {

        /*
         * -------------------------------------------------
         * INITIALIZE DEDUCTIONS
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
         *
         * SSS, PhilHealth, and Pag-IBIG are deducted according
         * to the same payroll-period statutory contribution schedule.
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
                    'description' => 'SSS Contribution',
                    'quantity' => 1,
                    'rate' => round($sss, 2),
                    'amount' => round($sss, 2),
                    'sort_order' => $sortOrder,
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
                    'description' => 'PhilHealth Contribution',
                    'quantity' => 1,
                    'rate' => round($philhealth, 2),
                    'amount' => round($philhealth, 2),
                    'sort_order' => $sortOrder,
                ]);

                $sortOrder++;
            }
        /*
         * -------------------------------------------------
         * PAG-IBIG
         * -------------------------------------------------
         *
         * Current system rule:
         *
         * ₱200.00 per payroll period.
         *
         * Pag-IBIG follows the same statutory deduction timing
         * as SSS and PhilHealth.
         *
         * If you later want Pag-IBIG to be calculated from
         * salary brackets instead, this can be changed here.
         */

        $pagibig = 200.00;


        $payroll->items()->create([
            'item_type' => 'deduction',
            'code' => 'PAGIBIG',
            'description' => 'Pag-IBIG Fund Contribution',
            'quantity' => 1,
            'rate' => 200.00,
            'amount' => 200.00,
            'sort_order' => $sortOrder,
        ]);

        $sortOrder++;


        }


        /*
         * -------------------------------------------------
         * TARDINESS
         * -------------------------------------------------
         *
         * Tardiness is deducted BEFORE withholding tax.
         *
         * It is an attendance-based deduction calculated
         * by PayrollCalculator:
         *
         * Late Minutes ÷ 60 × Hourly Rate
         */

        if ($tardinessDeduction > 0) {

            $payroll->items()->create([
                'item_type' => 'deduction',
                'code' => 'TARDINESS',
                'description' => 'Tardiness',
                'quantity' => 1,
                'rate' => round(
                    $tardinessDeduction,
                    2
                ),
                'amount' => round(
                    $tardinessDeduction,
                    2
                ),
                'sort_order' => 90,
            ]);
        }


        /*
         * -------------------------------------------------
         * OTHER EMPLOYEE DEDUCTIONS
         * -------------------------------------------------
         *
         * Current payroll rule:
         *
         * Employee/company deductions are deducted AFTER
         * withholding tax is calculated.
         *
         * Examples include company loans, cash advances,
         * salary loans, and other employee deductions.
         * They reduce Net Pay, but do NOT reduce taxable
         * compensation used for withholding tax.
         */

        foreach (
            $employee->deductions as $employeeDeduction
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


            $amount = round(
                $amount,
                2
            );


            $otherDeductions += $amount;


            $payroll->items()->create([
                'item_type' => 'deduction',

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

                'sort_order' => $sortOrder,
            ]);

            $sortOrder++;
        }


        /*
         * -------------------------------------------------
         * BIR TAXABLE COMPENSATION
         * -------------------------------------------------
         *
         * taxableGrossPay contains ONLY:
         *
         *   Basic Pay
         *   + taxable allowances
         *   + premium pay
         *   + overtime
         *   + NSD
         *   + other taxable earnings
         *
         * Non-taxable allowances are excluded by
         * PayrollCalculator before this value is passed.
         *
         * The deductions below are applied BEFORE
         * withholding tax:
         *
         *   SSS
         *   PhilHealth
         *   Pag-IBIG
         *   Tardiness
         *
         * Other employee/company deductions are NOT included
         * here. They are deducted only after withholding tax.
         */

        $netTaxableCompensation =
            max(
                0,
                $taxableGrossPay
                - $sss
                - $philhealth
                - $pagibig
                - $tardinessDeduction
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
                'code' => 'WITHHOLDING_TAX',
                'description' => 'Withholding Tax',
                'quantity' => 1,
                'rate' => round(
                    $withholdingTax,
                    2
                ),
                'amount' => round(
                    $withholdingTax,
                    2
                ),
                'sort_order' => $sortOrder,
            ]);

            $sortOrder++;
        }


        /*
         * -------------------------------------------------
         * TOTAL OTHER DEDUCTIONS
         * -------------------------------------------------
         *
         * other_deductions includes:
         *
         *   Employee deductions
         *   + Tardiness
         *
         * Tardiness is also returned separately so the
         * payroll dashboard can display it independently.
         */

        $otherDeductionsWithTardiness =
            $otherDeductions
            + $tardinessDeduction;

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
            + $otherDeductionsWithTardiness;


        /*
         * -------------------------------------------------
         * RETURN
         * -------------------------------------------------
         */

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
                    $otherDeductionsWithTardiness,
                    2
                ),

            'tardiness' =>
                round(
                    $tardinessDeduction,
                    2
                ),

            'total_deductions' =>
                round(
                    $totalDeductions,
                    2
                ),

            /*
             * Useful for debugging / payroll reports.
             */

            'taxable_gross_pay' =>
                round(
                    $taxableGrossPay,
                    2
                ),

            'net_taxable_compensation' =>
                round(
                    $netTaxableCompensation,
                    2
                ),
        ];
    }


    /*
     * =================================================
     * BIR WITHHOLDING TAX
     * =================================================
     *
     * Current BIR withholding-tax tables:
     *
     * Effective January 1, 2023 onwards.
     *
     * The applicable table is selected according to
     * employee payroll frequency.
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


        $frequency =
            strtolower(
                str_replace(
                    ['-', ' '],
                    '_',
                    (string) $employee->pay_frequency
                )
            );


        return match ($frequency) {

            'daily' =>
                $this->calculateDailyTax(
                    $taxableCompensation
                ),

            'weekly' =>
                $this->calculateWeeklyTax(
                    $taxableCompensation
                ),

            'semi_monthly' =>
                $this->calculateSemiMonthlyTax(
                    $taxableCompensation
                ),

            'monthly' =>
                $this->calculateMonthlyTax(
                    $taxableCompensation
                ),

            /*
             * BIR does not have a dedicated bi-weekly
             * withholding table.
             *
             * Convert the bi-weekly amount to the
             * semi-monthly equivalent and convert the
             * resulting tax back to the bi-weekly period.
             */

            'bi_weekly' =>
                $this->calculateBiWeeklyTax(
                    $taxableCompensation
                ),

            default =>
                $this->calculateSemiMonthlyTax(
                    $taxableCompensation
                ),
        };
    }


    /*
     * =================================================
     * SEMI-MONTHLY
     * =================================================
     *
     * BIR Annex E:
     *
     * ₱10,417 and below
     *               = ₱0
     *
     * Over ₱10,417 to ₱16,666
     *               = 15% of excess over ₱10,417
     *
     * Over ₱16,667 to ₱33,332
     *               = ₱937.50
     *                 + 20% of excess over ₱16,667
     *
     * Over ₱33,333 to ₱83,332
     *               = ₱4,270.70
     *                 + 25% of excess over ₱33,333
     *
     * Over ₱83,333 to ₱333,332
     *               = ₱16,770.70
     *                 + 30% of excess over ₱83,333
     *
     * ₱333,333 and above
     *               = ₱91,770.70
     *                 + 35% of excess over ₱333,333
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
     * =================================================
     * MONTHLY
     * =================================================
     *
     * BIR Annex E:
     *
     * ₱20,833 and below
     *               = ₱0
     *
     * Over ₱20,833 to ₱33,332
     *               = 15% of excess over ₱20,833
     *
     * Over ₱33,333 to ₱66,666
     *               = ₱1,875
     *                 + 20% of excess over ₱33,333
     *
     * Over ₱66,667 to ₱166,666
     *               = ₱8,541.80
     *                 + 25% of excess over ₱66,667
     *
     * Over ₱166,667 to ₱666,666
     *               = ₱33,541.80
     *                 + 30% of excess over ₱166,667
     *
     * ₱666,667 and above
     *               = ₱183,541.80
     *                 + 35% of excess over ₱666,667
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
     * =================================================
     * WEEKLY
     * =================================================
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
     * =================================================
     * DAILY
     * =================================================
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
     * =================================================
     * BI-WEEKLY
     * =================================================
     *
     * No dedicated BIR bi-weekly table is used here.
     *
     * Convert:
     *
     * bi-weekly → semi-monthly equivalent
     *
     * 26 bi-weekly periods / 24 semi-monthly periods
     */

    private function calculateBiWeeklyTax(
        float $taxable
    ): float {

        $semiMonthlyEquivalent =
            $taxable
            * 26
            / 24;


        $semiMonthlyTax =
            $this->calculateSemiMonthlyTax(
                $semiMonthlyEquivalent
            );


        return round(
            $semiMonthlyTax
            * 24
            / 26,
            2
        );
    }


    /*
     * =================================================
     * STATUTORY CONTRIBUTION SCHEDULE
     * =================================================
     */

    private function shouldDeductStatutoryContributions(
        Employee $employee,
        PayrollPeriod $period
    ): bool {

        $frequency =
            strtolower(
                str_replace(
                    ['-', ' '],
                    '_',
                    $employee->pay_frequency
                )
            );


        /*
         * Non-semi-monthly employees:
         *
         * Deduct every payroll period.
         */

        if ($frequency !== 'semi_monthly') {
            return true;
        }


        /*
         * Semi-monthly:
         *
         * SSS and PhilHealth are deducted during the
         * first payroll period of the month.
         */

        $periodEndDay =
            Carbon::parse(
                $period->period_end
            )->day;


        return $periodEndDay <= 15;
    }


    /*
     * =================================================
     * SSS GROSS BASIS
     * =================================================
     *
     * For semi-monthly employees, combine the first
     * and second payroll periods to determine the
     * monthly SSS salary basis.
     */

    private function resolveSssGrossBasis(
        Employee $employee,
        PayrollPeriod $period,
        float $currentGrossPay
    ): float {

        $frequency =
            strtolower(
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
     * =================================================
     * MONTHLY BASIC PAY
     * =================================================
     */

    private function resolveMonthlyBasicPay(
        Employee $employee,
        PayrollPeriod $period,
        float $currentBasicPay
    ): float {

        $frequency =
            strtolower(
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
     * =================================================
     * SSS
     * =================================================
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
     * =================================================
     * PHILHEALTH
     * =================================================
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


        $basis =
            max(
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
     * =================================================
     * OTHER EMPLOYEE DEDUCTIONS
     * =================================================
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


        /*
         * One-time deduction:
         *
         * Do not deduct again if already processed.
         */

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


        /*
         * Installment:
         *
         * Stop when the remaining balance reaches zero.
         */

        if (
            $deduction->schedule_type ===
                'installment'
            && (float)
                $deduction->remaining_balance <= 0
        ) {
            return false;
        }


        /*
         * Installment:
         *
         * Stop after the configured number of
         * installments.
         */

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


        /*
         * ONE-TIME
         */

        if ($scheduleType === 'one_time') {

            return max(
                0,
                (float) $deduction->amount
            );
        }


        /*
         * INSTALLMENT
         */

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


        /*
         * RECURRING
         */

        return max(
            0,
            (float) $deduction->amount
        );
    }


    /*
     * =================================================
     * CHECK PREVIOUS ONE-TIME DEDUCTION
     * =================================================
     */

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