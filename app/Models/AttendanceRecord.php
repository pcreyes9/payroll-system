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
        'regular_minutes',
        'rest_day_minutes',
        'rest_day_overtime_minutes',
        'night_shift_minutes',
        'late_minutes',
        'undertime_minutes',
        'overtime_minutes',
        'remarks',
        'detected_overtime_minutes',
        'approved_overtime_minutes',
        'overtime_status',
        'overtime_approved_by',
        'overtime_approved_at',
        'overtime_remarks',
    ];

    protected $casts = [
        'attendance_date' => 'date',
        'time_in' => 'datetime:H:i',
        'time_out' => 'datetime:H:i',
        'break_in' => 'datetime:H:i',
        'break_out' => 'datetime:H:i',

        'worked_minutes' => 'integer',
        'regular_minutes' => 'integer',
        'rest_day_minutes' => 'integer',
        'rest_day_overtime_minutes' => 'integer',
        'night_shift_minutes' => 'integer',

        'late_minutes' => 'integer',
        'undertime_minutes' => 'integer',
        'overtime_minutes' => 'integer',

        'detected_overtime_minutes' => 'integer',
        'approved_overtime_minutes' => 'integer',
        'overtime_approved_at' => 'datetime',
    ];

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }
    public function overtimeApprovedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'overtime_approved_by');
    }
}
