<?php

namespace App\Exports;

use App\Models\Payroll;
use App\Models\PayrollPeriod;
use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithEvents;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithStyles;
use Maatwebsite\Excel\Concerns\WithTitle;
use Maatwebsite\Excel\Events\AfterSheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Style\NumberFormat;
use PhpOffice\PhpSpreadsheet\Worksheet\PageSetup;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

class RegularPayrollSheet implements
    FromCollection,
    WithHeadings,
    WithTitle,
    WithStyles,
    WithEvents,
    ShouldAutoSize
{
    protected ?PayrollPeriod $period = null;

    public function __construct(
        protected int $payrollPeriodId
    ) {
        $this->period = PayrollPeriod::find($payrollPeriodId);
    }

    public function collection(): Collection
    {
        return Payroll::query()
            ->with([
                'employee',
                'items',
            ])
            ->where('payroll_period_id', $this->payrollPeriodId)
            ->orderBy('employee_id')
            ->get()
            ->map(function (Payroll $payroll) {
                $employee = $payroll->employee;

                return [
                    $employee?->full_name ?? 'Unknown Employee',
                    $employee?->employee_id ?? '',

                    (float) $payroll->basic_salary,
                    (float) $payroll->basic_pay,

                    (float) $payroll->allowances,

                    (float) $payroll->overtime_pay,

                    (float) $payroll->other_earnings,

                    (float) $payroll->gross_pay,

                    (float) $payroll->sss_contribution,
                    (float) $payroll->philhealth_contribution,
                    (float) $payroll->pagibig_contribution,

                    (float) $payroll->withholding_tax,

                    (float) $payroll->other_deductions,

                    (float) $payroll->total_deductions,

                    (float) $payroll->net_pay,

                    strtoupper((string) $payroll->status),
                ];
            });
    }

    public function headings(): array
    {
        return [
            'EMPLOYEE NAME',
            'EMPLOYEE ID',
            'MONTHLY BASIC',
            'BASIC PAY',
            'ALLOWANCES',
            'OVERTIME',
            'OTHER EARNINGS',
            'GROSS PAY',
            'SSS',
            'PHILHEALTH',
            'PAG-IBIG',
            'WITHHOLDING TAX',
            'OTHER DEDUCTIONS',
            'TOTAL DEDUCTIONS',
            'NET PAY',
            'STATUS',
        ];
    }

    public function title(): string
    {
        return 'REGULAR PAYROLL';
    }

    public function styles(Worksheet $sheet): array
    {
        return [
            1 => [
                'font' => [
                    'bold' => true,
                ],
            ],
        ];
    }

    public function registerEvents(): array
    {
        return [
            AfterSheet::class => function (AfterSheet $event) {
                $sheet = $event->sheet->getDelegate();

                $lastRow = $sheet->getHighestRow();
                $lastColumn = 'P';

                /*
                 * ---------------------------------------------------------
                 * TITLE AREA
                 * ---------------------------------------------------------
                 */

                $sheet->insertNewRowBefore(1, 5);

                $sheet->mergeCells('A1:P1');
                $sheet->mergeCells('A2:P2');
                $sheet->mergeCells('A3:P3');
                $sheet->mergeCells('A4:P4');

                $sheet->setCellValue(
                    'A1',
                    'PHILIPPINE SOCIETY OF ANESTHESIOLOGISTS, INC.'
                );

                $sheet->setCellValue(
                    'A2',
                    'PAYROLL REGISTER'
                );

                $periodName = $this->period?->name ?? 'Payroll Period';

                $periodStart = $this->period?->period_start
                    ? date('F j, Y', strtotime($this->period->period_start))
                    : '';

                $periodEnd = $this->period?->period_end
                    ? date('F j, Y', strtotime($this->period->period_end))
                    : '';

                $sheet->setCellValue(
                    'A3',
                    $periodStart && $periodEnd
                        ? "Payroll Period: {$periodStart} – {$periodEnd}"
                        : $periodName
                );

                $sheet->setCellValue(
                    'A4',
                    'Generated: ' . now()->format('F j, Y h:i A')
                );

                /*
                 * ---------------------------------------------------------
                 * TITLE FORMATTING
                 * ---------------------------------------------------------
                 */

                $sheet->getStyle('A1:P1')->applyFromArray([
                    'font' => [
                        'bold' => true,
                        'size' => 16,
                    ],
                    'alignment' => [
                        'horizontal' => Alignment::HORIZONTAL_CENTER,
                        'vertical' => Alignment::VERTICAL_CENTER,
                    ],
                ]);

                $sheet->getStyle('A2:P2')->applyFromArray([
                    'font' => [
                        'bold' => true,
                        'size' => 14,
                    ],
                    'alignment' => [
                        'horizontal' => Alignment::HORIZONTAL_CENTER,
                        'vertical' => Alignment::VERTICAL_CENTER,
                    ],
                ]);

                $sheet->getStyle('A3:P4')->applyFromArray([
                    'font' => [
                        'size' => 10,
                    ],
                    'alignment' => [
                        'horizontal' => Alignment::HORIZONTAL_CENTER,
                        'vertical' => Alignment::VERTICAL_CENTER,
                    ],
                ]);

                $sheet->getRowDimension(1)->setRowHeight(26);
                $sheet->getRowDimension(2)->setRowHeight(24);
                $sheet->getRowDimension(3)->setRowHeight(20);
                $sheet->getRowDimension(4)->setRowHeight(18);

                /*
                 * ---------------------------------------------------------
                 * SUMMARY
                 * ---------------------------------------------------------
                 */

                $summaryRow = 5;

                $sheet->mergeCells("A{$summaryRow}:D{$summaryRow}");
                $sheet->mergeCells("E{$summaryRow}:H{$summaryRow}");
                $sheet->mergeCells("I{$summaryRow}:L{$summaryRow}");
                $sheet->mergeCells("M{$summaryRow}:P{$summaryRow}");

                $payrolls = Payroll::query()
                    ->where('payroll_period_id', $this->payrollPeriodId)
                    ->get();

                $employeeCount = $payrolls->count();
                $grossTotal = (float) $payrolls->sum('gross_pay');
                $deductionTotal = (float) $payrolls->sum('total_deductions');
                $netTotal = (float) $payrolls->sum('net_pay');

                $sheet->setCellValue(
                    "A{$summaryRow}",
                    "EMPLOYEES: {$employeeCount}"
                );

                $sheet->setCellValue(
                    "E{$summaryRow}",
                    'GROSS PAYROLL: ' . number_format($grossTotal, 2)
                );

                $sheet->setCellValue(
                    "I{$summaryRow}",
                    'TOTAL DEDUCTIONS: ' . number_format($deductionTotal, 2)
                );

                $sheet->setCellValue(
                    "M{$summaryRow}",
                    'NET PAYROLL: ' . number_format($netTotal, 2)
                );

                $sheet->getStyle("A{$summaryRow}:P{$summaryRow}")
                    ->applyFromArray([
                        'font' => [
                            'bold' => true,
                        ],
                        'alignment' => [
                            'horizontal' => Alignment::HORIZONTAL_CENTER,
                            'vertical' => Alignment::VERTICAL_CENTER,
                        ],
                        'fill' => [
                            'fillType' => Fill::FILL_SOLID,
                            'color' => [
                                'rgb' => 'F3F4F6',
                            ],
                        ],
                        'borders' => [
                            'allBorders' => [
                                'borderStyle' => Border::BORDER_THIN,
                            ],
                        ],
                    ]);

                $sheet->getRowDimension($summaryRow)->setRowHeight(24);

                /*
                 * ---------------------------------------------------------
                 * HEADER
                 * ---------------------------------------------------------
                 */

                $headerRow = 6;

                $sheet->getStyle("A{$headerRow}:P{$headerRow}")
                    ->applyFromArray([
                        'font' => [
                            'bold' => true,
                            'color' => [
                                'rgb' => 'FFFFFF',
                            ],
                        ],
                        'fill' => [
                            'fillType' => Fill::FILL_SOLID,
                            'color' => [
                                'rgb' => '1F2937',
                            ],
                        ],
                        'alignment' => [
                            'horizontal' => Alignment::HORIZONTAL_CENTER,
                            'vertical' => Alignment::VERTICAL_CENTER,
                            'wrapText' => true,
                        ],
                        'borders' => [
                            'allBorders' => [
                                'borderStyle' => Border::BORDER_THIN,
                            ],
                        ],
                    ]);

                $sheet->getRowDimension($headerRow)->setRowHeight(36);

                /*
                 * ---------------------------------------------------------
                 * DATA
                 * ---------------------------------------------------------
                 */

                $dataStartRow = 7;

                if ($lastRow >= $dataStartRow) {
                    $sheet->getStyle(
                        "A{$dataStartRow}:P{$lastRow}"
                    )->applyFromArray([
                        'alignment' => [
                            'vertical' => Alignment::VERTICAL_CENTER,
                        ],
                        'borders' => [
                            'allBorders' => [
                                'borderStyle' => Border::BORDER_THIN,
                            ],
                        ],
                    ]);

                    $sheet->getStyle(
                        "B{$dataStartRow}:B{$lastRow}"
                    )->getAlignment()->setHorizontal(
                        Alignment::HORIZONTAL_CENTER
                    );

                    $sheet->getStyle(
                        "P{$dataStartRow}:P{$lastRow}"
                    )->getAlignment()->setHorizontal(
                        Alignment::HORIZONTAL_CENTER
                    );

                    /*
                     * Currency columns
                     */
                    $currencyColumns = [
                        'C',
                        'D',
                        'E',
                        'F',
                        'G',
                        'H',
                        'I',
                        'J',
                        'K',
                        'L',
                        'M',
                        'N',
                        'O',
                    ];

                    foreach ($currencyColumns as $column) {
                        $sheet->getStyle(
                            "{$column}{$dataStartRow}:{$column}{$lastRow}"
                        )->getNumberFormat()
                            ->setFormatCode('#,##0.00');
                    }
                }

                /*
                 * ---------------------------------------------------------
                 * TOTAL ROW
                 * ---------------------------------------------------------
                 */

                $totalRow = $lastRow + 1;

                $sheet->setCellValue(
                    "A{$totalRow}",
                    'TOTAL'
                );

                foreach ([
                    'C',
                    'D',
                    'E',
                    'F',
                    'G',
                    'H',
                    'I',
                    'J',
                    'K',
                    'L',
                    'M',
                    'N',
                    'O',
                ] as $column) {
                    $sheet->setCellValue(
                        "{$column}{$totalRow}",
                        "=SUM({$column}{$dataStartRow}:{$column}{$lastRow})"
                    );
                }

                $sheet->getStyle(
                    "A{$totalRow}:P{$totalRow}"
                )->applyFromArray([
                    'font' => [
                        'bold' => true,
                    ],
                    'fill' => [
                        'fillType' => Fill::FILL_SOLID,
                        'color' => [
                            'rgb' => 'E5E7EB',
                        ],
                    ],
                    'borders' => [
                        'top' => [
                            'borderStyle' => Border::BORDER_MEDIUM,
                        ],
                        'bottom' => [
                            'borderStyle' => Border::BORDER_THIN,
                        ],
                    ],
                ]);

                /*
                 * ---------------------------------------------------------
                 * COLUMN WIDTHS
                 * ---------------------------------------------------------
                 */

                $widths = [
                    'A' => 30,
                    'B' => 16,
                    'C' => 15,
                    'D' => 15,
                    'E' => 15,
                    'F' => 15,
                    'G' => 16,
                    'H' => 16,
                    'I' => 14,
                    'J' => 16,
                    'K' => 14,
                    'L' => 18,
                    'M' => 18,
                    'N' => 18,
                    'O' => 17,
                    'P' => 14,
                ];

                foreach ($widths as $column => $width) {
                    $sheet->getColumnDimension($column)
                        ->setWidth($width);
                }

                /*
                 * ---------------------------------------------------------
                 * FREEZE PANES
                 * ---------------------------------------------------------
                 */

                $sheet->freezePane('C7');

                /*
                 * ---------------------------------------------------------
                 * FILTER
                 * ---------------------------------------------------------
                 */

                $sheet->setAutoFilter(
                    "A6:P{$lastRow}"
                );

                /*
                 * ---------------------------------------------------------
                 * PAGE SETUP
                 * ---------------------------------------------------------
                 */

                $sheet->getPageSetup()
                    ->setOrientation(PageSetup::ORIENTATION_LANDSCAPE);

                $sheet->getPageSetup()
                    ->setPaperSize(PageSetup::PAPERSIZE_A4);

                $sheet->getPageSetup()
                    ->setFitToWidth(1);

                $sheet->getPageSetup()
                    ->setFitToHeight(0);

                $sheet->getPageMargins()->setTop(0.4);
                $sheet->getPageMargins()->setBottom(0.4);
                $sheet->getPageMargins()->setLeft(0.3);
                $sheet->getPageMargins()->setRight(0.3);

                $sheet->getHeaderFooter()
                    ->setOddFooter(
                        '&LPSA Payroll&CPage &P of &N&RGenerated: &D'
                    );

                $sheet->getPageSetup()
                    ->setPrintArea("A1:P{$totalRow}");

                /*
                 * ---------------------------------------------------------
                 * VIEW
                 * ---------------------------------------------------------
                 */

                $sheet->setShowGridlines(false);
            },
        ];
    }
}