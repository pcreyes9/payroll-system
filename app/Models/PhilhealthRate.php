<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PhilhealthRate extends Model
{
    protected $fillable = [
        'premium_rate',
        'employee_share_rate',
        'employer_share_rate',
        'salary_floor',
        'salary_ceiling',
        'effective_date',
        'is_active',
    ];

    protected $casts = [
        'premium_rate' => 'decimal:2',
        'employee_share_rate' => 'decimal:2',
        'employer_share_rate' => 'decimal:2',
        'salary_floor' => 'decimal:2',
        'salary_ceiling' => 'decimal:2',
        'effective_date' => 'date',
        'is_active' => 'boolean',
    ];

    /**
     * Get the rate configuration in effect as of a given date.
     */
    public static function currentAsOf(?string $asOfDate = null): ?self
    {
        $asOfDate ??= now()->toDateString();

        return static::query()
            ->where('is_active', true)
            ->where('effective_date', '<=', $asOfDate)
            ->orderByDesc('effective_date')
            ->first();
    }
}
