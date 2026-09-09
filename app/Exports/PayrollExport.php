<?php

namespace App\Exports;

use Maatwebsite\Excel\Concerns\Exportable;
use Maatwebsite\Excel\Concerns\Export;
use Maatwebsite\Excel\Concerns\WithMultipleSheets;

class PayrollExport implements Export, WithMultipleSheets
{
    use Exportable;

    public function __construct(
        protected int $payrollPeriodId
    ) {
    }

    public function sheets(): array
    {
        return [
            new RegularPayrollSheet($this->payrollPeriodId),
            new InOutSheet($this->payrollPeriodId),
        ];
    }
}