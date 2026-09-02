<?php

namespace App\Livewire\Attendance;

use App\Models\AttendanceRecord;
use App\Models\Employee;
use App\Services\Attendance\AttendanceCalculator;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Livewire\Component;

class EmployeeTimeClock extends Component
{
    public ?int $employeeId = null;

    public ?AttendanceRecord $todayAttendance = null;

    public ?string $successMessage = null;

    public ?string $errorMessage = null;

    /**
     * Application timezone.
     *
     * Later this can come from the attendance/settings table.
     */
    private function timezone(): string
    {
        return config('app.timezone', 'Asia/Manila');
    }

    /**
     * Get the current date/time using the application timezone.
     */
    private function now(): Carbon
    {
        return Carbon::now($this->timezone());
    }

    /**
     * Load today's attendance for the selected employee.
     */
    private function loadTodayAttendance(): void
    {
        if (! $this->employeeId) {
            $this->todayAttendance = null;

            return;
        }

        $today = $this->now()->toDateString();

        $this->todayAttendance = AttendanceRecord::query()
            ->where('employee_id', $this->employeeId)
            ->where('attendance_date', $today)
            ->first();
    }

    /**
     * Employee selection changed.
     */
    public function updatedEmployeeId(): void
    {
        $this->clearMessages();

        $this->loadTodayAttendance();
    }

    /**
     * Time in.
     */
    public function timeIn(): void
    {
        $this->clearMessages();

        $this->validate([
            'employeeId' => [
                'required',
                'integer',
                'exists:employees,id',
            ],
        ]);

        $employee = Employee::query()
            ->whereKey($this->employeeId)
            ->where('employment_status', 'active')
            ->first();

        if (! $employee) {
            $this->errorMessage = 'Selected employee is not active.';

            return;
        }

        $now = $this->now();
        $today = $now->toDateString();

        $attendance = AttendanceRecord::firstOrCreate(
            [
                'employee_id' => $employee->id,
                'attendance_date' => $today,
            ],
            [
                'status' => 'present',
            ]
        );

        /*
         * Prevent duplicate time in.
         */
        if ($attendance->time_in) {
            $this->todayAttendance = $attendance->fresh();

            $this->errorMessage =
                "{$employee->full_name} has already timed in today.";

            return;
        }

        /*
         * A new time-in means the employee is currently working.
         */
        $attendance->update([
            'time_in' => $now->format('H:i:s'),
            'time_out' => null,
            'status' => 'present',
        ]);

        $this->todayAttendance = $attendance->fresh();

        $this->successMessage =
            "{$employee->full_name} timed in at {$now->format('h:i A')}.";

        $this->dispatch('attendance-updated');
    }

    /**
     * Time out.
     */
    public function timeOut(): void
    {
        $this->clearMessages();

        $this->validate([
            'employeeId' => [
                'required',
                'integer',
                'exists:employees,id',
            ],
        ]);

        $employee = Employee::query()
            ->whereKey($this->employeeId)
            ->where('employment_status', 'active')
            ->first();

        if (! $employee) {
            $this->errorMessage = 'Selected employee is not active.';

            return;
        }

        $now = $this->now();
        $today = $now->toDateString();

        $attendance = AttendanceRecord::query()
            ->where('employee_id', $employee->id)
            ->where('attendance_date', $today)
            ->first();

        /*
         * Employee must have a time-in first.
         */
        if (! $attendance || ! $attendance->time_in) {
            $this->todayAttendance = $attendance?->fresh();

            $this->errorMessage =
                "{$employee->full_name} has not timed in today.";

            return;
        }

        /*
         * Prevent duplicate time out.
         */
        if ($attendance->time_out) {
            $this->todayAttendance = $attendance->fresh();

            $this->errorMessage =
                "{$employee->full_name} has already timed out today.";

            return;
        }

        /*
         * Save time-out and calculate:
         *
         * - worked minutes
         * - late minutes
         * - undertime minutes
         * - overtime minutes
         * - attendance status
         */
        DB::transaction(function () use ($attendance, $now) {

            $attendance->update([
                'time_out' => $now->format('H:i:s'),
            ]);

            app(AttendanceCalculator::class)
                ->calculate($attendance->fresh());
        });

        $this->todayAttendance = $attendance->fresh();

        $this->successMessage =
            "{$employee->full_name} timed out at {$now->format('h:i A')}.";

        $this->dispatch('attendance-updated');
    }

    /**
     * Clear current messages.
     */
    private function clearMessages(): void
    {
        $this->successMessage = null;
        $this->errorMessage = null;
    }

    /**
     * Today's attendance for all active employees.
     */
    public function getTodayAttendancesProperty()
    {
        $today = $this->now()->toDateString();

        return Employee::query()
            ->where('employment_status', 'active')
            ->orderBy('last_name')
            ->orderBy('first_name')
            ->with([
                'attendanceRecords' => function ($query) use ($today) {
                    $query->where('attendance_date', $today);
                },
            ])
            ->get();
    }

    /**
     * Summary: employees with an attendance record today.
     */
    public function getPresentCountProperty(): int
    {
        return $this->todayAttendances
            ->filter(
                fn ($employee) =>
                    $employee->attendanceRecords->isNotEmpty()
            )
            ->count();
    }

    /**
     * Summary: employees currently timed in.
     */
    public function getTimedInCountProperty(): int
    {
        return $this->todayAttendances
            ->filter(function ($employee) {

                $attendance = $employee->attendanceRecords->first();

                return $attendance
                    && $attendance->time_in
                    && ! $attendance->time_out;
            })
            ->count();
    }
    public function getCompletedCountProperty(): int
    {
        return $this->todayAttendances
            ->filter(function ($employee) {
                $attendance = $employee->attendanceRecords->first();

                return $attendance
                    && $attendance->time_in
                    && $attendance->time_out;
            })
            ->count();
    }

    /**
     * Render the time clock.
     */
    public function render()
    {
        return view('livewire.attendance.employee-time-clock', [
            'employees' => Employee::query()
                ->where('employment_status', 'active')
                ->orderBy('last_name')
                ->orderBy('first_name')
                ->get(),
        ]);
    }
}
