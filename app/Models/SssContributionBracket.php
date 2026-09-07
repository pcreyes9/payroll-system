<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SssContributionBracket extends Model
{
    protected $fillable = [
        'salary_from',
        'salary_to',
        'monthly_salary_credit',
        'employee_share',
        'employer_share',
        'ec_contribution',
        'effective_date',
        'is_active',
    ];

    protected $casts = [
        'salary_from' => 'decimal:2',
        'salary_to' => 'decimal:2',
        'monthly_salary_credit' => 'decimal:2',
        'employee_share' => 'decimal:2',
        'employer_share' => 'decimal:2',
        'ec_contribution' => 'decimal:2',
        'effective_date' => 'date',
        'is_active' => 'boolean',
    ];

    /**
     * Find the bracket that applies to a given compensation amount,
     * as of a given date. Falls back to the highest open-ended
     * bracket if the salary exceeds every defined upper bound.
     */
    public static function findForSalary(float $salary, ?string $asOfDate = null): ?self
    {
        $asOfDate ??= now()->toDateString();

        return static::query()
            ->where('is_active', true)
            ->where('effective_date', '<=', $asOfDate)
            ->where('salary_from', '<=', $salary)
            ->where(function ($query) use ($salary) {
                $query->whereNull('salary_to')
                    ->orWhere('salary_to', '>=', $salary);
            })
            ->orderByDesc('effective_date')
            ->orderByDesc('salary_from')
            ->first();
    }
}
