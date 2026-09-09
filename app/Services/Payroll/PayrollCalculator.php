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
             * DAILY RATE
             * -------------------------------------------------
             */

            $dailyRate = $this->calculateDailyRate(
                $basicSalary
            );

            /*
             * -------------------------------------------------
             * VL / SL
             * -------------------------------------------------
             *
             * VL and SL are paid leave and must NOT reduce
             * the employee's Basic Pay.
             *
             * This applies to both full-day and half-day leave:
             *   vl           = no deduction
             *   sl           = no deduction
             *   half_day_vl  = no deduction
             *   half_day_sl  = no deduction
             *
             * Unpaid absences/leave are handled separately by
             * their applicable attendance/payroll rules.
             */

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

                if (
                    $allowance->calculation_type ===
                    'percentage_basic'
                ) {

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

                if ((bool) $allowance->is_taxable) {
                    $taxableAllowanceTotal += $amount;
                }

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
             *
             * getPayrollRate() returns decimal values:
             *
             * 100% = 1.00
             * 125% = 1.25
             * 130% = 1.30
             * 169% = 1.69
             */

            $regularDayRate =
                $this->getPayrollRate(
                    'regular_day_rate',
                    100
                );

            $regularDayOvertimeRate =
                $this->getPayrollRate(
                    'regular_day_overtime_rate',
                    125
                );

            $restDayRate =
                $this->getPayrollRate(
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

            $specialHolidayRestDayRate =
                $this->getPayrollRate(
                    'special_holiday_rest_day_rate',
                    150
                );

            $specialHolidayRestDayOvertimeRate =
                $this->getPayrollRate(
                    'special_holiday_rest_day_overtime_rate',
                    195
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

            $regularHolidayRestDayRate =
                $this->getPayrollRate(
                    'regular_holiday_rest_day_rate',
                    260
                );

            $regularHolidayRestDayOvertimeRate =
                $this->getPayrollRate(
                    'regular_holiday_rest_day_overtime_rate',
                    338
                );

            $nightShiftDifferentialRate =
                $this->getPayrollRate(
                    'night_shift_differential_rate',
                    10
                );

            /*
             * -------------------------------------------------
             * ATTENDANCE SETTINGS
             * -------------------------------------------------
             */

            $workdays = Setting::getValue(
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

            $workdays = array_map(
                fn ($day) => strtolower(trim($day)),
                $workdays
            );

            /*
             * -------------------------------------------------
             * HOURLY RATE
             * -------------------------------------------------
             */

            $hourlyRate =
                $dailyRate / 8;

            /*
             * -------------------------------------------------
             * TARDINESS
             * -------------------------------------------------
             *
             * AttendanceCalculator already calculates and
             * stores late_minutes.
             *
             * Example:
             *
             * Official Time In = 09:00
             * Grace Period      = 15 minutes
             *
             * 09:15 = 0 late
             * 09:16 = 16 late
             * 09:20 = 20 late
             * 09:30 = 30 late
             *
             * Payroll converts the accumulated late minutes
             * into a peso deduction.
             *
             * Formula:
             *
             * Late Minutes ÷ 60 × Hourly Rate
             */

            $totalLateMinutes = 0;

            foreach ($attendanceRecords as $attendance) {

                $totalLateMinutes += max(
                    0,
                    (int) $attendance->late_minutes
                );
            }

            $tardinessDeduction = round(
                ($totalLateMinutes / 60)
                * $hourlyRate,
                2
            );

            /*
             * -------------------------------------------------
             * PREMIUM PAY
             * -------------------------------------------------
             */

            $regularDayPay = 0.00;
            $restDayPay = 0.00;
            $specialHolidayPay = 0.00;
            $regularHolidayPay = 0.00;

            /*
             * Overtime base pay.
             */

            $overtimePay = 0.00;

            /*
             * Additional NSD premium.
             */

            $nightShiftPay = 0.00;

            /*
             * -------------------------------------------------
             * PROCESS ATTENDANCE
             * -------------------------------------------------
             */

            foreach ($attendanceRecords as $attendance) {

                $workedMinutes =
                    (int) $attendance->worked_minutes;

                $approvedOtMinutes =
                    $attendance->overtime_status === 'approved'
                        ? (int) $attendance->approved_overtime_minutes
                        : 0;

                $nightMinutes =
                    (int) $attendance->night_shift_minutes;

                /*
                 * -------------------------------------------------
                 * DETERMINE REST DAY
                 * -------------------------------------------------
                 */

                $attendanceDate =
                    $attendance->attendance_date instanceof Carbon
                        ? $attendance->attendance_date
                        : Carbon::parse(
                            $attendance->attendance_date
                        );

                $dayName =
                    strtolower(
                        $attendanceDate->englishDayOfWeek
                    );

                $isNormalRestDay =
                    ! in_array(
                        $dayName,
                        $workdays,
                        true
                    );

                /*
                 * -------------------------------------------------
                 * REST DAY
                 * -------------------------------------------------
                 */

                if (
                    $attendance->status === 'rest_day'
                ) {

                    $restDayMinutes =
                        min(
                            (int) $attendance->rest_day_minutes,
                            8 * 60
                        );

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

                    if ($approvedOtMinutes > 0) {

                        $otHours =
                            $approvedOtMinutes / 60;

                        $overtimePay +=
                            $otHours
                            * $hourlyRate
                            * $restDayOvertimeRate;
                    }

                    /*
                     * NSD classification.
                     */

                    $otNightMinutes =
                        $this->calculateApprovedOtNightMinutes(
                            $attendance,
                            $approvedOtMinutes,
                            8 * 60
                        );

                    $regularNightMinutes =
                        max(
                            0,
                            $nightMinutes
                            - $otNightMinutes
                        );

                    /*
                     * Rest-day NSD during regular
                     * rest-day hours.
                     */

                    if ($regularNightMinutes > 0) {

                        $nightHours =
                            $regularNightMinutes / 60;

                        $nightShiftPay +=
                            $nightHours
                            * $hourlyRate
                            * $restDayRate
                            * $nightShiftDifferentialRate;
                    }

                    /*
                     * Rest-day NSD during OT.
                     */

                    if ($otNightMinutes > 0) {

                        $nightHours =
                            $otNightMinutes / 60;

                        $nightShiftPay +=
                            $nightHours
                            * $hourlyRate
                            * $restDayOvertimeRate
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

                    /*
                     * Regular Holiday Pay:
                     *
                     * Pay only the ACTUAL completed hours worked,
                     * up to a maximum of 8 hours.
                     *
                     * Examples:
                     *   4:00 hours worked = 4:00 × 200%
                     *   6:30 hours worked = 6:30 × 200%
                     *   8:00 hours worked = 8:00 × 200%
                     *   9:00 hours worked = 8:00 × 200%
                     *
                     * The hours beyond the first 8 hours are handled
                     * separately as approved holiday overtime.
                     */
                    $holidayMinutes = min(
                        max(0, $workedMinutes),
                        8 * 60
                    );

                    $isHolidayRestDay =
                        $isNormalRestDay;

                    $firstEightRate =
                        $isHolidayRestDay
                            ? $specialHolidayRestDayRate
                            : $specialHolidayRate;

                    $holidayOvertimeRate =
                        $isHolidayRestDay
                            ? $specialHolidayRestDayOvertimeRate
                            : $specialHolidayOvertimeRate;

                    if ($holidayMinutes > 0) {

                        $holidayHours =
                            $holidayMinutes / 60;

                        $specialHolidayPay +=
                            $holidayHours
                            * $hourlyRate
                            * $firstEightRate;
                    }

                    /*
                     * Approved holiday OT.
                     */

                    if ($approvedOtMinutes > 0) {

                        $otHours =
                            $approvedOtMinutes / 60;

                        $overtimePay +=
                            $otHours
                            * $hourlyRate
                            * $holidayOvertimeRate;
                    }

                    /*
                     * NSD classification.
                     */

                    $otNightMinutes =
                        $this->calculateApprovedOtNightMinutes(
                            $attendance,
                            $approvedOtMinutes,
                            8 * 60
                        );

                    $regularNightMinutes =
                        max(
                            0,
                            $nightMinutes
                            - $otNightMinutes
                        );

                    /*
                     * NSD during first 8 hours.
                     */

                    if ($regularNightMinutes > 0) {

                        $nightHours =
                            $regularNightMinutes / 60;

                        $nightShiftPay +=
                            $nightHours
                            * $hourlyRate
                            * $firstEightRate
                            * $nightShiftDifferentialRate;
                    }

                    /*
                     * NSD during OT.
                     */

                    if ($otNightMinutes > 0) {

                        $nightHours =
                            $otNightMinutes / 60;

                        $nightShiftPay +=
                            $nightHours
                            * $hourlyRate
                            * $holidayOvertimeRate
                            * $nightShiftDifferentialRate;
                    }

                    continue;
                }

                /*
                 * -------------------------------------------------
                 * REGULAR HOLIDAY
                 * -------------------------------------------------
                 *
                 * Regular Holiday Pay is based on actual completed
                 * hours worked, capped at 8 hours.
                 *
                 * Example:
                 * 4 hours worked = 4 hours × 200%, NOT 8 hours × 200%.
                 */

                if (
                    $attendance->status ===
                    'regular_holiday'
                ) {

                    /*
                     * Regular Holiday Pay:
                     *
                     * Use the ACTUAL elapsed time between Time In
                     * and Time Out, capped at 8 hours.
                     *
                     * Do not depend on worked_minutes here because
                     * a manually classified holiday record may have
                     * a stale/zero calculated worked_minutes value.
                     *
                     * Examples:
                     *   3:15 worked = 3:15 × 200%
                     *   6:30 worked = 6:30 × 200%
                     *   8:00 worked = 8:00 × 200%
                     *   9:00 worked = 8:00 × 200%
                     *
                     * Hours beyond the first 8 hours are handled
                     * separately as approved holiday overtime.
                     */

                    $holidayMinutes = 0;

                    if (
                        $attendance->time_in
                        && $attendance->time_out
                    ) {
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

                        $holidayMinutes = min(
                            max(
                                0,
                                $holidayTimeIn->diffInMinutes(
                                    $holidayTimeOut
                                )
                            ),
                            8 * 60
                        );
                    }

                    $isHolidayRestDay =
                        $isNormalRestDay;

                    $firstEightRate =
                        $isHolidayRestDay
                            ? $regularHolidayRestDayRate
                            : $regularHolidayRate;

                    $holidayOvertimeRate =
                        $isHolidayRestDay
                            ? $regularHolidayRestDayOvertimeRate
                            : $regularHolidayOvertimeRate;

                    if ($holidayMinutes > 0) {

                        $holidayHours =
                            $holidayMinutes / 60;

                        $regularHolidayPay +=
                            $holidayHours
                            * $hourlyRate
                            * $firstEightRate;
                    }

                    /*
                     * Approved holiday OT.
                     */

                    if ($approvedOtMinutes > 0) {

                        $otHours =
                            $approvedOtMinutes / 60;

                        $overtimePay +=
                            $otHours
                            * $hourlyRate
                            * $holidayOvertimeRate;
                    }

                    /*
                     * NSD classification.
                     */

                    $otNightMinutes =
                        $this->calculateApprovedOtNightMinutes(
                            $attendance,
                            $approvedOtMinutes,
                            8 * 60
                        );

                    $regularNightMinutes =
                        max(
                            0,
                            $nightMinutes
                            - $otNightMinutes
                        );

                    /*
                     * NSD during regular holiday hours.
                     */

                    if ($regularNightMinutes > 0) {

                        $nightHours =
                            $regularNightMinutes / 60;

                        $nightShiftPay +=
                            $nightHours
                            * $hourlyRate
                            * $firstEightRate
                            * $nightShiftDifferentialRate;
                    }

                    /*
                     * NSD during holiday OT.
                     */

                    if ($otNightMinutes > 0) {

                        $nightHours =
                            $otNightMinutes / 60;

                        $nightShiftPay +=
                            $nightHours
                            * $hourlyRate
                            * $holidayOvertimeRate
                            * $nightShiftDifferentialRate;
                    }

                    continue;
                }

                /*
                 * -------------------------------------------------
                 * REGULAR WORKING DAY
                 * -------------------------------------------------
                 *
                 * Includes normal attendance and half-day VL/SL.
                 * Approved OT on these statuses is paid at the
                 * regular weekday overtime rate.
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

                    /*
                     * Regular attendance is already
                     * included in Basic Pay.
                     */

                    if ($approvedOtMinutes > 0) {

                        $otHours =
                            $approvedOtMinutes / 60;

                        $overtimePay +=
                            $otHours
                            * $hourlyRate
                            * $regularDayOvertimeRate;
                    }

                    /*
                     * NSD during approved OT.
                     */

                    $otNightMinutes =
                        $this->calculateApprovedOtNightMinutes(
                            $attendance,
                            $approvedOtMinutes,
                            0
                        );

                    /*
                     * NSD outside approved OT.
                     */

                    $regularNightMinutes =
                        max(
                            0,
                            $nightMinutes
                            - $otNightMinutes
                        );

                    /*
                     * Ordinary NSD:
                     *
                     * 100% × 10%
                     * = additional 10%
                     */

                    if ($regularNightMinutes > 0) {

                        $nightHours =
                            $regularNightMinutes / 60;

                        $nightShiftPay +=
                            $nightHours
                            * $hourlyRate
                            * $regularDayRate
                            * $nightShiftDifferentialRate;
                    }

                    /*
                     * Ordinary-day OT + NSD:
                     *
                     * 125% × 10%
                     * = additional 12.5%
                     *
                     * Combined = 137.5%
                     */

                    if ($otNightMinutes > 0) {

                        $nightHours =
                            $otNightMinutes / 60;

                        $nightShiftPay +=
                            $nightHours
                            * $hourlyRate
                            * $regularDayOvertimeRate
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
             * IMPORTANT:
             *
             * Tardiness is NOT deducted from gross pay.
             *
             * It remains a separate deduction.
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
             */

            $taxableGrossPay =
                $basicPay
                + $taxableAllowanceTotal
                + $premiumPay
                + $otherEarnings;

            /*
             * -------------------------------------------------
             * STATUTORY / EMPLOYEE DEDUCTIONS
             * -------------------------------------------------
             */

            $deductions =
                $this->deductionCalculator->calculate(
                    $payroll,
                    $employee,
                    $grossPay,
                    $basicPay,
                    $period,
                    $taxableGrossPay,
                    $tardinessDeduction
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

            /*
             * Tardiness is already included in
             * other_deductions by DeductionCalculator.
             *
             * It is also returned separately for reports.
             */

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
     * -------------------------------------------------
     * BUILD ATTENDANCE DATETIME
     * -------------------------------------------------
     *
     * IMPORTANT:
     *
     * TIME columns may be returned as:
     *
     * 05:28:00
     *
     * or:
     *
     * 2026-09-09 05:28:00
     *
     * This method extracts ONLY the time portion and
     * combines it with the attendance date.
     *
     * This prevents:
     *
     * 2026-08-22 2026-09-09 05:28:00
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

        /*
         * Carbon / DateTime values.
         */

        if ($value instanceof Carbon) {

            $time =
                $value->format('H:i:s');

        } elseif ($value instanceof \DateTimeInterface) {

            $time =
                $value->format('H:i:s');

        } else {

            /*
             * String value.
             *
             * Extract ONLY HH:MM:SS.
             */

            $value =
                trim((string) $value);

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

                /*
                 * Last fallback.
                 */

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
     * Build a Carbon datetime from an attendance
     * date and a time setting.
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

        } elseif ($time instanceof \DateTimeInterface) {

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
     * Calculate approved OT minutes that overlap
     * with the configured NSD period.
     */
    private function calculateApprovedOtNightMinutes(
        AttendanceRecord $attendance,
        int $approvedOtMinutes,
        int $regularMinutesBeforeOt
    ): int {

        if (
            $approvedOtMinutes <= 0
            || ! $attendance->time_in
            || ! $attendance->time_out
        ) {
            return 0;
        }

        /*
         * SAFE DATETIME CONSTRUCTION
         */

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
         * Handle overnight attendance.
         */

        if (
            $timeOut->lessThanOrEqualTo($timeIn)
        ) {
            $timeOut->addDay();
        }

        /*
         * Determine OT start.
         */

        if ($regularMinutesBeforeOt > 0) {

            $otStart =
                $timeIn->copy()->addMinutes(
                    $regularMinutesBeforeOt
                );

        } else {

            /*
             * Regular working day.
             *
             * OT starts at official Time Out.
             */

            $officialTimeOut =
                Setting::getValue(
                    'attendance',
                    'official_time_out',
                    '17:00'
                );

            $otStart =
                $this->buildDateTimeFromTime(
                    $attendance->attendance_date,
                    $officialTimeOut
                );

            /*
             * Handle overnight shift.
             */

            if (
                $otStart->lessThanOrEqualTo($timeIn)
            ) {
                $otStart->addDay();
            }
        }

        if (
            $otStart->lessThan($timeIn)
        ) {
            $otStart =
                $timeIn->copy();
        }

        if (
            $timeOut->lessThanOrEqualTo($otStart)
        ) {
            return 0;
        }

        /*
         * Approved OT is the final approved
         * portion immediately before Time Out.
         */

        $approvedOtStart =
            $timeOut->copy()->subMinutes(
                min(
                    $approvedOtMinutes,
                    $otStart->diffInMinutes(
                        $timeOut
                    )
                )
            );

        if (
            $approvedOtStart->lessThan($otStart)
        ) {
            $approvedOtStart =
                $otStart->copy();
        }

        return $this->calculateNightOverlapMinutes(
            $approvedOtStart,
            $timeOut
        );
    }


    /**
     * Calculate overlap with the configured
     * NSD period.
     *
     * Default:
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
                $this->buildDateTimeFromTime(
                    $cursor,
                    $nightStartTime
                );

            $nightEnd =
                $this->buildDateTimeFromTime(
                    $cursor,
                    $nightEndTime
                );

            /*
             * Overnight NSD window.
             */

            if (
                $nightEnd->lessThanOrEqualTo(
                    $nightStart
                )
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
                $overlapEnd->greaterThan(
                    $overlapStart
                )
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


    /**
     * Convert payroll percentage to decimal.
     *
     * Example:
     *
     * 100 → 1.00
     * 125 → 1.25
     * 169 → 1.69
     * 10  → 0.10
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
     * Calculate allowance amount according
     * to frequency.
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