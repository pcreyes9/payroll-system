<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Payroll extends Model
{
    public const STATUS_DRAFT = 'draft';
    public const STATUS_CALCULATING = 'calculating';
    public const STATUS_CALCULATED = 'calculated';
    public const STATUS_APPROVED = 'approved';
    public const STATUS_PAID = 'paid';
    public const STATUS_CANCELLED = 'cancelled';
    protected $fillable = [
        'payroll_period_id',
        'employee_id',

        'basic_salary',
        'basic_pay',

        'allowances',
        'overtime_pay',
        'other_earnings',
        'gross_pay',

        'sss_contribution',
        'philhealth_contribution',
        'pagibig_contribution',

        'withholding_tax',

        'other_deductions',
        'total_deductions',
        'net_pay',

        'status',

        'calculated_at',
        'approved_at',
        'paid_at',

        'notes',
    ];

    protected $casts = [
        'basic_salary' => 'decimal:2',
        'basic_pay' => 'decimal:2',

        'allowances' => 'decimal:2',
        'overtime_pay' => 'decimal:2',
        'other_earnings' => 'decimal:2',
        'gross_pay' => 'decimal:2',

        'sss_contribution' => 'decimal:2',
        'philhealth_contribution' => 'decimal:2',
        'pagibig_contribution' => 'decimal:2',

        'withholding_tax' => 'decimal:2',

        'other_deductions' => 'decimal:2',
        'total_deductions' => 'decimal:2',
        'net_pay' => 'decimal:2',

        'calculated_at' => 'datetime',
        'approved_at' => 'datetime',
        'paid_at' => 'datetime',
    ];

    public function payrollPeriod(): BelongsTo
    {
        return $this->belongsTo(PayrollPeriod::class);
    }

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }
    public function items(): HasMany
    {
        return $this->hasMany(PayrollItem::class);
    }
    public function allowances(): HasMany
    {
        return $this->hasMany(PayrollItem::class)
            ->where('item_type', 'earning')
            ->where('code', 'ALLOWANCE')
            ->orderBy('sort_order');
    }

    public function deductions(): HasMany
    {
        return $this->hasMany(PayrollItem::class)
            ->where('item_type', 'deduction')
            ->orderBy('sort_order');
    }
    public function allowanceItems(): HasMany
    {
        return $this->hasMany(PayrollItem::class)
            ->where('item_type', 'earning')
            ->where('code', 'ALLOWANCE')
            ->orderBy('sort_order');
    }
}
