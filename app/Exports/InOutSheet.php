<?php

namespace App\Exports;

use App\Models\AttendanceRecord;
use App\Models\Payroll;
use App\Models\PayrollPeriod;
use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithEvents;
use Maatwebsite\Excel\Concerns\WithStyles;
use Maatwebsite\Excel\Concerns\WithTitle;
use Maatwebsite\Excel\Events\AfterSheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Color;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Worksheet\PageSetup;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

class InOutSheet implements
    FromCollection,
    WithTitle,
    WithStyles,
    WithEvents,
    ShouldAutoSize
{
    protected ?PayrollPeriod $period = null;

    protected Collection $payrolls;

    protected Collection $attendance;

    public function __construct(
        protected ?int $payrollPeriodId = null
    ) {
        $this->period = $this->payrollPeriodId
            ? PayrollPeriod::find($this->payrollPeriodId)
            : null;

        $this->payrolls = Payroll::query()
            ->with([
                'employee',
                'items',
            ])
            ->when(
                $this->payrollPeriodId,
                fn ($query) => $query->where(
                    'payroll_period_id',
                    $this->payrollPeriodId
                )
            )
            ->get()
            ->sortBy(function ($payroll) {
                return strtolower(
                    $payroll->employee?->last_name
                    ?? $payroll->employee?->full_name
                    ?? ''
                );
            })
            ->values();

        $this->attendance = AttendanceRecord::query()
            ->with('employee')
            ->when(
                $this->period,
                fn ($query) => $query
                    ->whereDate(
                        'attendance_date',
                        '>=',
                        $this->period->period_start
                    )
                    ->whereDate(
                        'attendance_date',
                        '<=',
                        $this->period->period_end
                    )
            )
            ->orderBy('employee_id')
            ->orderBy('attendance_date')
            ->get();
    }

    public function collection(): Collection
    {
        return collect([
            [],
        ]);
    }

    public function title(): string
    {
        return 'IN & OUT';
    }

    public function styles(Worksheet $sheet): array
    {
        return [];
    }

    public function registerEvents(): array
    {
        return [
            AfterSheet::class => function (AfterSheet $event) {
                $this->buildSheet(
                    $event->sheet->getDelegate()
                );
            },
        ];
    }

    protected function buildSheet(Worksheet $sheet): void
    {
        $highestRow = $sheet->getHighestRow();

        if ($highestRow > 0) {
            $sheet->removeRow(1, $highestRow);
        }

        $currentRow = 1;

        foreach ($this->payrolls as $payroll) {
            $employee = $payroll->employee;

            if (! $employee) {
                continue;
            }

            $employeeAttendance = $this->attendance
                ->where('employee_id', $employee->id)
                ->values();

            $currentRow = $this->buildEmployeeSection(
                $sheet,
                $payroll,
                $employeeAttendance,
                $currentRow
            );

            $currentRow += 2;
        }

        if ($currentRow === 1) {
            $sheet->setCellValue(
                'A1',
                'No payroll records found for the selected period.'
            );

            $sheet->getStyle('A1')
                ->getFont()
                ->setBold(true);

            return;
        }

        $sheet->setShowGridlines(false);

        $sheet->getPageSetup()
            ->setOrientation(
                PageSetup::ORIENTATION_LANDSCAPE
            )
            ->setPaperSize(
                PageSetup::PAPERSIZE_A4
            )
            ->setFitToWidth(1)
            ->setFitToHeight(0);

        $sheet->getPageMargins()
            ->setTop(0.30)
            ->setBottom(0.30)
            ->setLeft(0.20)
            ->setRight(0.20);

        $sheet->getHeaderFooter()
            ->setOddFooter(
                '&LPSA Payroll System&RPage &P of &N'
            );

        $widths = [
            'A' => 13,
            'B' => 13,
            'C' => 12,
            'D' => 12,
            'E' => 10,
            'F' => 10,
            'G' => 32,
            'H' => 12,
            'I' => 10,
            'J' => 15,
            'K' => 12,
            'L' => 17,
            'M' => 3,
            'N' => 31,
            'O' => 15,
            'P' => 15,
        ];

        foreach ($widths as $column => $width) {
            $sheet->getColumnDimension($column)
                ->setWidth($width);
        }

        $lastRow = $sheet->getHighestRow();

        $sheet->getPageSetup()
            ->setPrintArea(
                "A1:P{$lastRow}"
            );
    }

    protected function buildEmployeeSection(
        Worksheet $sheet,
        Payroll $payroll,
        Collection $attendance,
        int $startRow
    ): int {
        $employee = $payroll->employee;

        $employeeName = $this->employeeName($employee);

        $periodText = $this->period
            ? $this->period->period_start->format('F j')
                . ' - '
                . $this->period->period_end->format('j, Y')
            : '';

        /*
         * =========================================================
         * LEFT SIDE - TIMESHEET
         * =========================================================
         */

        $sheet->mergeCells(
            "A{$startRow}:L{$startRow}"
        );

        $sheet->setCellValue(
            "A{$startRow}",
            'PHILIPPINE SOCIETY OF ANESTHESIOLOGISTS, INC.'
        );

        $sheet->getStyle(
            "A{$startRow}:L{$startRow}"
        )
            ->getFont()
            ->setBold(true)
            ->setSize(12);

        $titleRow = $startRow + 1;

        $sheet->mergeCells(
            "A{$titleRow}:L{$titleRow}"
        );

        $sheet->setCellValue(
            "A{$titleRow}",
            'TIMESHEET REPORT - From '
                . strtoupper($periodText)
        );

        $sheet->getStyle(
            "A{$titleRow}:L{$titleRow}"
        )
            ->getFont()
            ->setBold(true)
            ->setSize(11);

        $employeeRow = $startRow + 3;

        $sheet->mergeCells(
            "A{$employeeRow}:L{$employeeRow}"
        );

        $sheet->setCellValue(
            "A{$employeeRow}",
            'Employee: ' . $employeeName
        );

        $sheet->getStyle(
            "A{$employeeRow}:L{$employeeRow}"
        )
            ->getFont()
            ->setBold(true);

        $sheet->getStyle(
            "A{$employeeRow}:L{$employeeRow}"
        )
            ->getFill()
            ->setFillType(
                Fill::FILL_SOLID
            )
            ->getStartColor()
            ->setARGB('FFD9D9D9');

        /*
         * =========================================================
         * ATTENDANCE HEADER
         * =========================================================
         */

        $headerRow = $startRow + 4;

        $headers = [
            'A' => 'Date',
            'B' => 'Day',
            'C' => 'In',
            'D' => 'Out',
            'E' => 'SL Used',
            'F' => 'VL Used',
            'G' => 'Details',
            'H' => 'OT 125%',
            'I' => 'ND',
            'J' => 'Rest Day 130%',
            'K' => 'Rest Day ND',
            'L' => 'Rest Day OT 169%',
        ];

        foreach ($headers as $column => $header) {
            $sheet->setCellValue(
                "{$column}{$headerRow}",
                $header
            );
        }

        $sheet->getStyle(
            "A{$headerRow}:L{$headerRow}"
        )
            ->getFont()
            ->setBold(true)
            ->setColor(
                new Color('FFFFFFFF')
            );

        $sheet->getStyle(
            "A{$headerRow}:L{$headerRow}"
        )
            ->getFill()
            ->setFillType(
                Fill::FILL_SOLID
            )
            ->getStartColor()
            ->setARGB('FF000000');

        $sheet->getStyle(
            "A{$headerRow}:L{$headerRow}"
        )
            ->getAlignment()
            ->setHorizontal(
                Alignment::HORIZONTAL_CENTER
            )
            ->setVertical(
                Alignment::VERTICAL_CENTER
            )
            ->setWrapText(true);

        $sheet->getRowDimension($headerRow)
            ->setRowHeight(30);

        /*
         * =========================================================
         * ATTENDANCE DATA
         * =========================================================
         */

        $row = $headerRow + 1;

        $totalSl = 0.0;
        $totalVl = 0.0;

        $totalOtMinutes = 0;
        $totalNsdMinutes = 0;
        $totalRestDayMinutes = 0;
        $totalRestDayNsdMinutes = 0;
        $totalRestDayOtMinutes = 0;

        foreach ($attendance as $record) {
            $date = $record->attendance_date;

            $sheet->setCellValue(
                "A{$row}",
                $date?->format('m/d/Y')
            );

            $sheet->setCellValue(
                "B{$row}",
                $date?->format('l')
            );

            $sheet->setCellValue(
                "C{$row}",
                $this->formatTime(
                    $record->time_in
                )
            );

            $sheet->setCellValue(
                "D{$row}",
                $this->formatTime(
                    $record->time_out
                )
            );

            /*
             * Leave usage.
             */
            $slUsed = 0.0;
            $vlUsed = 0.0;

            if ($record->status === 'sl') {
                $slUsed = 1.0;
            }

            if ($record->status === 'vl') {
                $vlUsed = 1.0;
            }

            if ($record->status === 'half_day') {
                $remarks = strtolower(
                    (string) (
                        $record->remarks ?? ''
                    )
                );

                if (
                    str_contains(
                        $remarks,
                        'sick'
                    )
                ) {
                    $slUsed = 0.5;
                } else {
                    $vlUsed = 0.5;
                }
            }

            $totalSl += $slUsed;
            $totalVl += $vlUsed;

            $sheet->setCellValue(
                "E{$row}",
                $slUsed ?: ''
            );

            $sheet->setCellValue(
                "F{$row}",
                $vlUsed ?: ''
            );

            /*
             * Details.
             */
            $sheet->setCellValue(
                "G{$row}",
                $this->attendanceDetails(
                    $record
                )
            );

            /*
             * OT.
             */
            $otMinutes = (int) (
                $record->approved_overtime_minutes
                ?? $record->overtime_minutes
                ?? 0
            );

            /*
             * NSD.
             */
            $nsdMinutes = (int) (
                $record->night_shift_minutes
                ?? 0
            );

            /*
             * Rest day.
             */
            $restDayMinutes = (int) (
                $record->rest_day_minutes
                ?? 0
            );

            /*
             * Rest-day NSD is not separately stored.
             */
            $restDayNsdMinutes = 0;

            /*
             * Rest-day OT.
             */
            $restDayOtMinutes = (int) (
                $record->rest_day_overtime_minutes
                ?? 0
            );

            $totalOtMinutes += $otMinutes;
            $totalNsdMinutes += $nsdMinutes;
            $totalRestDayMinutes += $restDayMinutes;
            $totalRestDayNsdMinutes += $restDayNsdMinutes;
            $totalRestDayOtMinutes += $restDayOtMinutes;

            $sheet->setCellValue(
                "H{$row}",
                $this->formatHours(
                    $otMinutes
                )
            );

            $sheet->setCellValue(
                "I{$row}",
                $this->formatHours(
                    $nsdMinutes
                )
            );

            $sheet->setCellValue(
                "J{$row}",
                $this->formatHours(
                    $restDayMinutes
                )
            );

            $sheet->setCellValue(
                "K{$row}",
                $this->formatHours(
                    $restDayNsdMinutes
                )
            );

            $sheet->setCellValue(
                "L{$row}",
                $this->formatHours(
                    $restDayOtMinutes
                )
            );

            $sheet->getStyle(
                "A{$row}:F{$row}"
            )
                ->getAlignment()
                ->setHorizontal(
                    Alignment::HORIZONTAL_CENTER
                );

            $sheet->getStyle(
                "H{$row}:L{$row}"
            )
                ->getAlignment()
                ->setHorizontal(
                    Alignment::HORIZONTAL_CENTER
                );

            /*
             * Rest day row.
             */
            if (
                $record->status === 'rest_day'
            ) {
                $sheet->getStyle(
                    "A{$row}:L{$row}"
                )
                    ->getFill()
                    ->setFillType(
                        Fill::FILL_SOLID
                    )
                    ->getStartColor()
                    ->setARGB('FFE7E7E7');

                $sheet->getStyle(
                    "A{$row}:L{$row}"
                )
                    ->getFont()
                    ->setBold(true);
            }

            /*
             * Leave row.
             */
            if (
                in_array(
                    $record->status,
                    [
                        'sl',
                        'vl',
                        'sil',
                        'emergency_leave',
                        'unpaid_leave',
                        'lwop',
                    ],
                    true
                )
            ) {
                $sheet->getStyle(
                    "A{$row}:L{$row}"
                )
                    ->getFill()
                    ->setFillType(
                        Fill::FILL_SOLID
                    )
                    ->getStartColor()
                    ->setARGB('FFF2F2F2');
            }

            $row++;
        }

        if ($attendance->isEmpty()) {
            $sheet->setCellValue(
                "A{$row}",
                'No attendance records'
            );

            $sheet->mergeCells(
                "A{$row}:L{$row}"
            );

            $sheet->getStyle(
                "A{$row}:L{$row}"
            )
                ->getAlignment()
                ->setHorizontal(
                    Alignment::HORIZONTAL_CENTER
                );

            $row++;
        }

        /*
         * =========================================================
         * TOTALS
         * =========================================================
         */

        $totalRow = $row;

        $sheet->setCellValue(
            "D{$totalRow}",
            'Total Used'
        );

        $sheet->setCellValue(
            "E{$totalRow}",
            $totalSl ?: ''
        );

        $sheet->setCellValue(
            "F{$totalRow}",
            $totalVl ?: ''
        );

        $sheet->setCellValue(
            "G{$totalRow}",
            'TOTAL HOURS'
        );

        $sheet->setCellValue(
            "H{$totalRow}",
            $this->formatHours(
                $totalOtMinutes
            )
        );

        $sheet->setCellValue(
            "I{$totalRow}",
            $this->formatHours(
                $totalNsdMinutes
            )
        );

        $sheet->setCellValue(
            "J{$totalRow}",
            $this->formatHours(
                $totalRestDayMinutes
            )
        );

        $sheet->setCellValue(
            "K{$totalRow}",
            $this->formatHours(
                $totalRestDayNsdMinutes
            )
        );

        $sheet->setCellValue(
            "L{$totalRow}",
            $this->formatHours(
                $totalRestDayOtMinutes
            )
        );

        $sheet->getStyle(
            "D{$totalRow}:L{$totalRow}"
        )
            ->getFont()
            ->setBold(true);

        $sheet->getStyle(
            "D{$totalRow}:L{$totalRow}"
        )
            ->getFill()
            ->setFillType(
                Fill::FILL_SOLID
            )
            ->getStartColor()
            ->setARGB('FFE7E7E7');

        /*
         * =========================================================
         * RATES
         * =========================================================
         */

        $rateRow = $totalRow + 1;

        $sheet->setCellValue(
            "D{$rateRow}",
            'Rates'
        );

        $sheet->setCellValue(
            "G{$rateRow}",
            'RATES'
        );

        $sheet->setCellValue(
            "H{$rateRow}",
            $this->rate(
                config(
                    'payroll.regular_day_overtime_rate',
                    125
                )
            )
        );

        $sheet->setCellValue(
            "J{$rateRow}",
            $this->rate(
                config(
                    'payroll.rest_day_rate',
                    130
                )
            )
        );

        $sheet->setCellValue(
            "L{$rateRow}",
            $this->rate(
                config(
                    'payroll.rest_day_overtime_rate',
                    169
                )
            )
        );

        $sheet->getStyle(
            "D{$rateRow}:L{$rateRow}"
        )
            ->getFont()
            ->setBold(true);

        /*
         * =========================================================
         * TOTAL AMOUNT
         * =========================================================
         */

        $amountRow = $rateRow + 1;

        $sheet->setCellValue(
            "G{$amountRow}",
            'TOTAL AMOUNT'
        );

        $sheet->getStyle(
            "G{$amountRow}:L{$amountRow}"
        )
            ->getFont()
            ->setBold(true);

        $sheet->getStyle(
            "A{$headerRow}:L{$amountRow}"
        )
            ->getBorders()
            ->getAllBorders()
            ->setBorderStyle(
                Border::BORDER_THIN
            );

        /*
         * =========================================================
         * RIGHT SIDE - SALARY
         * =========================================================
         */

        $salaryStart = $startRow + 3;

        $sheet->mergeCells(
            "N{$salaryStart}:P{$salaryStart}"
        );

        $sheet->setCellValue(
            "N{$salaryStart}",
            'PHILIPPINE SOCIETY OF ANESTHESIOLOGISTS, INC.'
        );

        $sheet->getStyle(
            "N{$salaryStart}:P{$salaryStart}"
        )
            ->getFont()
            ->setBold(true)
            ->setColor(
                new Color('FFFFFFFF')
            );

        $sheet->getStyle(
            "N{$salaryStart}:P{$salaryStart}"
        )
            ->getFill()
            ->setFillType(
                Fill::FILL_SOLID
            )
            ->getStartColor()
            ->setARGB('FF000000');

        $salaryTitle = $salaryStart + 1;

        $sheet->mergeCells(
            "N{$salaryTitle}:P{$salaryTitle}"
        );

        $sheet->setCellValue(
            "N{$salaryTitle}",
            'SALARY FOR THE PERIOD '
                . strtoupper($periodText)
        );

        $sheet->getStyle(
            "N{$salaryTitle}:P{$salaryTitle}"
        )
            ->getFont()
            ->setBold(true)
            ->setColor(
                new Color('FFFFFFFF')
            );

        $sheet->getStyle(
            "N{$salaryTitle}:P{$salaryTitle}"
        )
            ->getFill()
            ->setFillType(
                Fill::FILL_SOLID
            )
            ->getStartColor()
            ->setARGB('FF000000');

        $salaryEmployee = $salaryTitle + 1;

        $sheet->mergeCells(
            "N{$salaryEmployee}:P{$salaryEmployee}"
        );

        $sheet->setCellValue(
            "N{$salaryEmployee}",
            $employeeName
        );

        $sheet->getStyle(
            "N{$salaryEmployee}:P{$salaryEmployee}"
        )
            ->getFont()
            ->setBold(true)
            ->setColor(
                new Color('FFFFFFFF')
            );

        $sheet->getStyle(
            "N{$salaryEmployee}:P{$salaryEmployee}"
        )
            ->getFill()
            ->setFillType(
                Fill::FILL_SOLID
            )
            ->getStartColor()
            ->setARGB('FF000000');

        /*
         * =========================================================
         * SALARY DETAILS
         * =========================================================
         */

        $salaryRow = $salaryEmployee + 1;

        /*
         * Basic pay.
         */
        $this->salaryLine(
            $sheet,
            $salaryRow++,
            'Basic pay',
            $payroll->basic_pay
        );

        /*
         * Allowances.
         */
        $allowanceItems = $payroll->items
            ->filter(function ($item) {
                return $item->item_type === 'earning'
                    && $item->code === 'ALLOWANCE';
            })
            ->values();

        foreach ($allowanceItems as $item) {
            $label = $item->description
                ?: $item->name
                ?: 'Allowance';

            $this->salaryLine(
                $sheet,
                $salaryRow++,
                $label,
                $item->amount
            );
        }

        /*
         * Fallback allowance.
         */
        if (
            $allowanceItems->isEmpty()
            && (float) $payroll->allowances !== 0.0
        ) {
            $this->salaryLine(
                $sheet,
                $salaryRow++,
                'Allowances',
                $payroll->allowances
            );
        }

        /*
         * Overtime.
         */
        $this->salaryLine(
            $sheet,
            $salaryRow++,
            'Overtime Pay',
            $payroll->overtime_pay
        );

        /*
         * Other earnings.
         */
        if (
            (float) $payroll->other_earnings !== 0.0
        ) {
            $this->salaryLine(
                $sheet,
                $salaryRow++,
                'Other Earnings',
                $payroll->other_earnings
            );
        }

        /*
         * Gross pay.
         */
        $grossRow = $salaryRow;

        $this->salaryLine(
            $sheet,
            $salaryRow++,
            'TOTAL',
            $payroll->gross_pay,
            true
        );

        /*
         * Blank row.
         */
        $salaryRow++;

        /*
         * Deductions heading.
         */
        $sheet->setCellValue(
            "N{$salaryRow}",
            'Deductions:'
        );

        $sheet->getStyle(
            "N{$salaryRow}:P{$salaryRow}"
        )
            ->getFont()
            ->setBold(true);

        $salaryRow++;

        /*
         * SSS.
         */
        $this->salaryLine(
            $sheet,
            $salaryRow++,
            'SSS',
            $this->negative(
                $payroll->sss_contribution
            )
        );

        /*
         * PhilHealth.
         */
        $this->salaryLine(
            $sheet,
            $salaryRow++,
            'Philhealth',
            $this->negative(
                $payroll->philhealth_contribution
            )
        );

        /*
         * Pag-IBIG.
         */
        $this->salaryLine(
            $sheet,
            $salaryRow++,
            'Pag-ibig Fund',
            $this->negative(
                $payroll->pagibig_contribution
            )
        );

        /*
         * Other deduction items.
         */
        $deductionItems = $payroll->items
            ->filter(function ($item) {
                return $item->item_type === 'deduction'
                    && ! in_array(
                        strtoupper(
                            (string) $item->code
                        ),
                        [
                            'SSS',
                            'PHILHEALTH',
                            'PAGIBIG',
                            'WITHHOLDING_TAX',
                            'WTAX',
                        ],
                        true
                    );
            })
            ->values();

        foreach ($deductionItems as $item) {
            $label = $item->description
                ?: $item->name
                ?: 'Other Deduction';

            $this->salaryLine(
                $sheet,
                $salaryRow++,
                $label,
                $this->negative(
                    $item->amount
                )
            );
        }

        /*
         * Withholding tax.
         */
        $this->salaryLine(
            $sheet,
            $salaryRow++,
            'Withholding tax on salaries',
            $this->negative(
                $payroll->withholding_tax
            )
        );

        /*
         * Other deductions field.
         */
        if (
            (float) $payroll->other_deductions !== 0.0
        ) {
            $this->salaryLine(
                $sheet,
                $salaryRow++,
                'Other deds',
                $this->negative(
                    $payroll->other_deductions
                )
            );
        }

        /*
         * Total deductions.
         */
        $deductionTotalRow = $salaryRow;

        $this->salaryLine(
            $sheet,
            $salaryRow++,
            'TOTAL DEDUCTION',
            $this->negative(
                $payroll->total_deductions
            ),
            true
        );

        /*
         * Net pay.
         */
        $netPayRow = $salaryRow;

        $this->salaryLine(
            $sheet,
            $salaryRow++,
            'NET PAY',
            $payroll->net_pay,
            true
        );

        /*
         * Salary borders.
         */
        $sheet->getStyle(
            "N{$salaryStart}:P{$netPayRow}"
        )
            ->getBorders()
            ->getAllBorders()
            ->setBorderStyle(
                Border::BORDER_THIN
            );

        /*
         * Gross highlight.
         */
        $sheet->getStyle(
            "N{$grossRow}:P{$grossRow}"
        )
            ->getFill()
            ->setFillType(
                Fill::FILL_SOLID
            )
            ->getStartColor()
            ->setARGB('FFF2F2F2');

        /*
         * Total deductions highlight.
         */
        $sheet->getStyle(
            "N{$deductionTotalRow}:P{$deductionTotalRow}"
        )
            ->getFill()
            ->setFillType(
                Fill::FILL_SOLID
            )
            ->getStartColor()
            ->setARGB('FFF2F2F2');

        /*
         * Net pay highlight.
         */
        $sheet->getStyle(
            "N{$netPayRow}:P{$netPayRow}"
        )
            ->getFill()
            ->setFillType(
                Fill::FILL_SOLID
            )
            ->getStartColor()
            ->setARGB('FFE7E7E7');

        return max(
            $amountRow,
            $netPayRow
        ) + 1;
    }

    protected function salaryLine(
        Worksheet $sheet,
        int $row,
        string $label,
        $amount,
        bool $bold = false
    ): void {
        $sheet->mergeCells(
            "N{$row}:O{$row}"
        );

        $sheet->setCellValue(
            "N{$row}",
            $label
        );

        $sheet->setCellValue(
            "P{$row}",
            (float) $amount
        );

        $sheet->getStyle(
            "N{$row}:P{$row}"
        )
            ->getAlignment()
            ->setVertical(
                Alignment::VERTICAL_CENTER
            );

        $sheet->getStyle(
            "P{$row}"
        )
            ->getAlignment()
            ->setHorizontal(
                Alignment::HORIZONTAL_RIGHT
            );

        $sheet->getStyle(
            "P{$row}"
        )
            ->getNumberFormat()
            ->setFormatCode(
                '#,##0.00;[Red]-#,##0.00;-'
            );

        if ($bold) {
            $sheet->getStyle(
                "N{$row}:P{$row}"
            )
                ->getFont()
                ->setBold(true);
        }
    }

    protected function employeeName($employee): string
    {
        if (
            isset($employee->full_name)
            && $employee->full_name
        ) {
            return $employee->full_name;
        }

        return trim(
            implode(
                ' ',
                array_filter([
                    $employee->last_name ?? null,
                    $employee->first_name ?? null,
                    $employee->middle_name ?? null,
                    $employee->suffix ?? null,
                ])
            )
        );
    }

    protected function formatTime($time): string
    {
        if (! $time) {
            return '';
        }

        try {
            if (
                $time instanceof \DateTimeInterface
            ) {
                return $time->format('g:i A');
            }

            return date(
                'g:i A',
                strtotime((string) $time)
            );
        } catch (\Throwable) {
            return (string) $time;
        }
    }

    protected function formatHours(
        int $minutes
    ): string {
        if ($minutes <= 0) {
            return '';
        }

        $hours = intdiv(
            $minutes,
            60
        );

        $remainingMinutes = $minutes % 60;

        return sprintf(
            '%d:%02d',
            $hours,
            $remainingMinutes
        );
    }

    protected function attendanceDetails(
        AttendanceRecord $record
    ): string {
        $details = [];

        $status = match ($record->status) {
            'present' => '',
            'rest_day' => 'RESTDAY',
            'absent' => 'ABSENT',
            'sl' => 'SICK LEAVE',
            'vl' => 'VACATION LEAVE',
            'sil' => 'SIL',
            'half_day' => 'HALF DAY',
            'regular_holiday' => 'REGULAR HOLIDAY',
            'special_non_working_holiday'
                => 'SPECIAL HOLIDAY',
            'emergency_leave' => 'EMERGENCY LEAVE',
            'lwop' => 'LWOP',
            'unpaid_leave' => 'UNPAID LEAVE',
            default => strtoupper(
                str_replace(
                    '_',
                    ' ',
                    (string) $record->status
                )
            ),
        };

        if ($status !== '') {
            $details[] = $status;
        }

        if (
            $record->remarks
            && trim($record->remarks) !== ''
        ) {
            $details[] = trim(
                $record->remarks
            );
        }

        if (
            $record->overtime_remarks
            && trim($record->overtime_remarks) !== ''
        ) {
            $details[] = 'OT: '
                . trim(
                    $record->overtime_remarks
                );
        }

        return implode(
            ' | ',
            $details
        );
    }

    protected function negative($value): float
    {
        return -abs(
            (float) $value
        );
    }

    protected function rate($value): string
    {
        return number_format(
            (float) $value,
            2
        ) . '%';
    }
}