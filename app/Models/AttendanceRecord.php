<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AttendanceRecord extends Model
{
    protected $fillable = [
        'employee_id',
        'attendance_date',

        'time_in',
        'time_out',

        'break_in',
        'break_out',

        'status',

        'worked_minutes',
        'late_minutes',
        'undertime_minutes',
        'overtime_minutes',

        'remarks',
    ];

    protected $casts = [
        'attendance_date' => 'date',

        'worked_minutes' => 'integer',
        'late_minutes' => 'integer',
        'undertime_minutes' => 'integer',
        'overtime_minutes' => 'integer',
    ];

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }
}
