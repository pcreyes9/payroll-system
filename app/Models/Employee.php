<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Employee extends Model
{
    protected static function booted(): void
    {
        static::creating(function (Employee $employee) {
            if (empty($employee->employee_id)) {
                $lastEmployeeId = static::query()
                    ->orderByDesc('id')
                    ->value('employee_id');

                $nextNumber = $lastEmployeeId
                    ? ((int) preg_replace('/\D/', '', $lastEmployeeId)) + 1
                    : 1;

                $employee->employee_id = 'EMP-' . str_pad(
                    $nextNumber,
                    4,
                    '0',
                    STR_PAD_LEFT
                );
            }
        });

        static::created(function (Employee $employee) {
            if ($employee->basic_salary > 0) {
                $employee->salaryHistories()->create([
                    'basic_salary' => $employee->basic_salary,
                    'effective_date' => $employee->date_hired
                        ?? now()->toDateString(),
                    'reason' => 'Initial Salary',
                ]);
            }
        });
    }

    protected $fillable = [
        'employee_id',

        'first_name',
        'middle_name',
        'last_name',
        'suffix',

        'date_of_birth',
        'sex',
        'civil_status',

        'email',
        'mobile_number',
        'address',

        'department_id',
        'position_id',
        'employment_type_id',

        'date_hired',
        'date_regularized',
        'employment_status',

        'basic_salary',
        'pay_frequency',
        'payroll_enabled',
    ];

    protected $casts = [
        'date_of_birth' => 'date',
        'date_hired' => 'date',
        'date_regularized' => 'date',
        'basic_salary' => 'decimal:2',
        'payroll_enabled' => 'boolean',
    ];

    public function department(): BelongsTo
    {
        return $this->belongsTo(Department::class);
    }

    public function position(): BelongsTo
    {
        return $this->belongsTo(Position::class);
    }

    public function employmentType(): BelongsTo
    {
        return $this->belongsTo(EmploymentType::class);
    }

    public function salaryHistories(): HasMany
    {
        return $this->hasMany(EmployeeSalaryHistory::class);
    }

    public function getFullNameAttribute(): string
    {
        return trim(
            "{$this->first_name} {$this->middle_name} {$this->last_name} {$this->suffix}"
        );
    }
    public function allowances(): HasMany
    {
        return $this->hasMany(EmployeeAllowance::class);
    }
    public function deductions(): HasMany
    {
        return $this->hasMany(EmployeeDeduction::class);
    }
    public function attendanceRecords(): HasMany
    {
        return $this->hasMany(AttendanceRecord::class);
    }


}
