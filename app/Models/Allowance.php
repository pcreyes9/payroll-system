<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Allowance extends Model
{
    protected $fillable = [
        'code',
        'name',
        'description',
        'calculation_type',
        'frequency',
        'default_amount',
        'is_taxable',
        'is_active',
    ];

    protected $casts = [
        'default_amount' => 'decimal:2',
        'is_taxable' => 'boolean',
        'is_active' => 'boolean',
    ];

    public function employeeAllowances(): HasMany
    {
        return $this->hasMany(EmployeeAllowance::class);
    }
}