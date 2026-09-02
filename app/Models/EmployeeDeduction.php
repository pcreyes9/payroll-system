<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class EmployeeDeduction extends Model
{
    protected $fillable = [
        'employee_id',
        'deduction_id',

        'amount',
        'original_amount',
        'remaining_balance',
        'installment_amount',

        'total_installments',
        'paid_installments',

        'schedule_type',

        'start_date',
        'effective_date',
        'end_date',

        'is_active',
    ];

    protected $casts = [
        'amount' => 'decimal:2',
        'original_amount' => 'decimal:2',
        'remaining_balance' => 'decimal:2',
        'installment_amount' => 'decimal:2',

        'total_installments' => 'integer',
        'paid_installments' => 'integer',

        'start_date' => 'date',
        'effective_date' => 'date',
        'end_date' => 'date',

        'is_active' => 'boolean',
    ];

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }

    public function deduction(): BelongsTo
    {
        return $this->belongsTo(Deduction::class);
    }
}
