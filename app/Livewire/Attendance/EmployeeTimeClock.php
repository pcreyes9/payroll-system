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
     * Time In.
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

        /*
         * Create today's attendance record if it does not exist.
         */
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
         * Prevent duplicate Time In.
         */
        if ($attendance->time_in) {
            $this->todayAttendance = $attendance->fresh();

            $this->errorMessage =
                "{$employee->full_name} has already timed in today.";

            return;
        }

        /*
         * Save Time In.
         *
         * Time-based attendance calculations are performed
         * when Time Out is recorded.
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
     * Time Out.
     *
     * Saves the Time Out and immediately runs the
     * AttendanceCalculator to calculate all attendance metrics.
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

        /*
         * Find today's attendance record.
         */
        $attendance = AttendanceRecord::query()
            ->where('employee_id', $employee->id)
            ->where('attendance_date', $today)
            ->first();

        /*
         * Employee must have a Time In first.
         */
        if (! $attendance || ! $attendance->time_in) {
            $this->todayAttendance = $attendance?->fresh();

            $this->errorMessage =
                "{$employee->full_name} has not timed in today.";

            return;
        }

        /*
         * Prevent duplicate Time Out.
         */
        if ($attendance->time_out) {
            $this->todayAttendance = $attendance->fresh();

            $this->errorMessage =
                "{$employee->full_name} has already timed out today.";

            return;
        }

        /*
         * Save Time Out and immediately calculate:
         *
         * - Worked Minutes
         * - Regular Minutes
         * - Rest-Day Minutes
         * - Rest-Day Overtime
         * - Late Minutes
         * - Undertime Minutes
         * - Detected Overtime
         * - Approved Overtime
         * - Night Shift Differential
         * - Attendance Status
         *
         * Overtime remains pending until approved
         * from the Attendance Records page.
         */
        DB::transaction(function () use ($attendance, $now) {

            $attendance->update([
                'time_out' => $now->format('H:i:s'),
            ]);

            /*
             * Run the attendance calculator immediately
             * after Time Out has been saved.
             */
            app(AttendanceCalculator::class)
                ->calculate($attendance->fresh());
        });

        /*
         * Reload the calculated attendance record so
         * the Time Clock displays the latest values.
         */
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
     * Summary:
     * Employees with an attendance record today.
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
     * Summary:
     * Employees currently timed in.
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

    /**
     * Summary:
     * Employees who have completed their attendance today.
     */
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
     * Render the Time Clock.
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
