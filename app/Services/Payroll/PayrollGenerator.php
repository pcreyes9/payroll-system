<?php

namespace App\Services\Payroll;

use App\Models\Employee;
use App\Models\Payroll;
use App\Models\PayrollPeriod;
use Illuminate\Support\Facades\DB;

class PayrollGenerator
{
    public function generate(PayrollPeriod $period): int
    {
        if ($period->status !== 'draft') {
            throw new \RuntimeException(
                'Payroll can only be generated from a draft payroll period.'
            );
        }

        return DB::transaction(function () use ($period) {
            $employees = Employee::query()
                ->where('payroll_enabled', true)
                ->where('employment_status', 'active')
                ->get();

            $created = 0;

            foreach ($employees as $employee) {
                $payroll = Payroll::firstOrCreate(
                    [
                        'payroll_period_id' => $period->id,
                        'employee_id' => $employee->id,
                    ],
                    [
                        'basic_salary' => $employee->basic_salary ?? 0,
                        'status' => Payroll::STATUS_DRAFT,
                    ]
                );

                if ($payroll->wasRecentlyCreated) {
                    $created++;
                }
            }

            $period->update([
                'status' => 'processing',
            ]);

            return $created;
        });
    }
}
