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

    /*
     * -------------------------------------------------------------
     * OVERTIME REMARKS MODAL
     * -------------------------------------------------------------
     */

    public bool $showOvertimeRemarksModal = false;

    public ?string $overtimeRemarks = null;

    public ?int $overtimeAttendanceId = null;


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
     * -------------------------------------------------------------
     * TIME IN
     * -------------------------------------------------------------
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
            $this->errorMessage =
                'Selected employee is not active.';

            return;
        }

        $now = $this->now();

        $today = $now->toDateString();


        /*
         * ---------------------------------------------------------
         * CREATE TODAY'S ATTENDANCE IF IT DOES NOT EXIST
         * ---------------------------------------------------------
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
         * ---------------------------------------------------------
         * PREVENT DUPLICATE TIME IN
         * ---------------------------------------------------------
         */

        if ($attendance->time_in) {

            $this->todayAttendance =
                $attendance->fresh();

            $this->errorMessage =
                "{$employee->full_name} has already timed in today.";

            return;
        }


        /*
         * ---------------------------------------------------------
         * SAVE TIME IN
         * ---------------------------------------------------------
         */

        $attendance->update([
            'time_in' => $now->format('H:i:s'),
            'time_out' => null,
            'status' => 'present',
        ]);


        /*
         * Reload attendance.
         */
        $this->todayAttendance =
            $attendance->fresh();


        /*
         * Success message.
         */
        $this->successMessage =
            "{$employee->full_name} successfully timed in at "
            . $now->format('h:i A')
            . '.';


        /*
         * Tell Livewire/other listeners that
         * attendance has changed.
         */
        $this->dispatch(
            'attendance-updated'
        );
    }


    /**
     * -------------------------------------------------------------
     * TIME OUT
     * -------------------------------------------------------------
     *
     * If Time Out is 6:00 PM or later:
     *
     * 1. Save Time Out.
     * 2. Calculate attendance.
     * 3. Open OT Remarks modal.
     *
     * Otherwise:
     *
     * 1. Save Time Out.
     * 2. Calculate attendance.
     * 3. Show normal success message.
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

            $this->errorMessage =
                'Selected employee is not active.';

            return;
        }

        $now = $this->now();

        $today = $now->toDateString();


        /*
         * ---------------------------------------------------------
         * FIND TODAY'S ATTENDANCE
         * ---------------------------------------------------------
         */

        $attendance = AttendanceRecord::query()
            ->where('employee_id', $employee->id)
            ->where('attendance_date', $today)
            ->first();


        /*
         * ---------------------------------------------------------
         * MUST TIME IN FIRST
         * ---------------------------------------------------------
         */

        if (! $attendance || ! $attendance->time_in) {

            $this->todayAttendance =
                $attendance?->fresh();

            $this->errorMessage =
                "{$employee->full_name} has not timed in today.";

            return;
        }


        /*
         * ---------------------------------------------------------
         * PREVENT DUPLICATE TIME OUT
         * ---------------------------------------------------------
         */

        if ($attendance->time_out) {

            $this->todayAttendance =
                $attendance->fresh();

            $this->errorMessage =
                "{$employee->full_name} has already timed out today.";

            return;
        }


        /*
         * ---------------------------------------------------------
         * SAVE TIME OUT + CALCULATE ATTENDANCE
         * ---------------------------------------------------------
         */

        DB::transaction(function () use (
            $attendance,
            $now
        ) {

            /*
             * Save Time Out.
             */
            $attendance->update([
                'time_out' => $now->format('H:i:s'),
            ]);


            /*
             * Calculate attendance immediately.
             *
             * This calculates:
             *
             * - Worked Minutes
             * - Regular Minutes
             * - Rest-Day Minutes
             * - Rest-Day OT
             * - Late Minutes
             * - Undertime Minutes
             * - Detected OT
             * - NSD
             * - Attendance Status
             */
            app(AttendanceCalculator::class)
                ->calculate(
                    $attendance->fresh()
                );
        });


        /*
         * ---------------------------------------------------------
         * RELOAD CALCULATED ATTENDANCE
         * ---------------------------------------------------------
         */

        $this->todayAttendance =
            $attendance->fresh();


        /*
         * ---------------------------------------------------------
         * CHECK IF TIME OUT IS 6:00 PM OR LATER
         * ---------------------------------------------------------
         */

        if ($now->format('H:i') >= '18:00') {

            /*
             * Store the attendance ID so the modal knows
             * which attendance record to update.
             */
            $this->overtimeAttendanceId =
                $attendance->id;


            /*
             * Preserve any existing OT remarks.
             */
            $this->overtimeRemarks =
                $attendance->overtime_remarks;


            /*
             * Open modal.
             */
            $this->showOvertimeRemarksModal = true;


            /*
             * Do not show the normal success message yet.
             *
             * The success message will be displayed after
             * the employee saves the OT remarks.
             */
            return;
        }


        /*
         * ---------------------------------------------------------
         * NORMAL TIME OUT
         * ---------------------------------------------------------
         */

        $this->successMessage =
            "{$employee->full_name} successfully timed out at "
            . $now->format('h:i A')
            . '.';


        $this->dispatch(
            'attendance-updated'
        );
    }


    /**
     * -------------------------------------------------------------
     * SAVE OVERTIME REMARKS
     * -------------------------------------------------------------
     */
    public function saveOvertimeRemarks(): void
    {
        $this->validate([
            'overtimeRemarks' => [
                'required',
                'string',
                'max:1000',
            ],
        ], [
            'overtimeRemarks.required' =>
                'Please provide the reason for your overtime.',
        ]);


        /*
         * Make sure we have an attendance record.
         */
        if (! $this->overtimeAttendanceId) {

            $this->errorMessage =
                'Unable to identify the attendance record.';

            return;
        }


        /*
         * Find the attendance record belonging
         * to the selected employee.
         */
        $attendance = AttendanceRecord::query()
            ->whereKey($this->overtimeAttendanceId)
            ->where('employee_id', $this->employeeId)
            ->first();


        if (! $attendance) {

            $this->errorMessage =
                'Attendance record could not be found.';

            return;
        }


        /*
         * Save OT remarks.
         */
        $attendance->update([
            'overtime_remarks' =>
                trim($this->overtimeRemarks),
        ]);


        /*
         * Reload.
         */
        $this->todayAttendance =
            $attendance->fresh();


        /*
         * Get employee.
         */
        $employee = Employee::find(
            $this->employeeId
        );


        /*
         * Close modal.
         */
        $this->showOvertimeRemarksModal = false;

        $this->overtimeAttendanceId = null;

        $this->overtimeRemarks = null;


        /*
         * Success notification.
         */
        $timeOut = $attendance->time_out
            ? Carbon::parse(
                $attendance->time_out
            )->format('h:i A')
            : null;

        $this->successMessage =
            "{$employee?->full_name} successfully timed out"
            . ($timeOut ? " at {$timeOut}" : '')
            . '. OT remarks recorded.';


        /*
         * Refresh listeners.
         */
        $this->dispatch(
            'attendance-updated'
        );
    }


    /**
     * -------------------------------------------------------------
     * CLOSE OT REMARKS MODAL
     * -------------------------------------------------------------
     */
    public function closeOvertimeRemarksModal(): void
    {
        /*
         * Do not allow the employee to bypass
         * the required OT remarks by simply closing
         * the modal.
         *
         * Instead, keep the modal open.
         *
         * This method is intentionally empty for now.
         */
    }


    /**
     * -------------------------------------------------------------
     * CLEAR MESSAGES
     * -------------------------------------------------------------
     */
    private function clearMessages(): void
    {
        $this->successMessage = null;

        $this->errorMessage = null;
    }


    /**
     * -------------------------------------------------------------
     * TODAY'S ATTENDANCE
     * -------------------------------------------------------------
     */
    public function getTodayAttendancesProperty()
    {
        $today = $this->now()->toDateString();

        return Employee::query()
            ->where(
                'employment_status',
                'active'
            )
            ->orderBy('last_name')
            ->orderBy('first_name')
            ->with([
                'attendanceRecords' => function ($query) use ($today) {

                    $query->where(
                        'attendance_date',
                        $today
                    );

                },
            ])
            ->get();
    }


    /**
     * -------------------------------------------------------------
     * PRESENT COUNT
     * -------------------------------------------------------------
     */
    public function getPresentCountProperty(): int
    {
        return $this->todayAttendances
            ->filter(
                fn ($employee) =>
                    $employee
                        ->attendanceRecords
                        ->isNotEmpty()
            )
            ->count();
    }


    /**
     * -------------------------------------------------------------
     * TIMED IN COUNT
     * -------------------------------------------------------------
     */
    public function getTimedInCountProperty(): int
    {
        return $this->todayAttendances
            ->filter(function ($employee) {

                $attendance =
                    $employee
                        ->attendanceRecords
                        ->first();

                return $attendance
                    && $attendance->time_in
                    && ! $attendance->time_out;
            })
            ->count();
    }


    /**
     * -------------------------------------------------------------
     * COMPLETED COUNT
     * -------------------------------------------------------------
     */
    public function getCompletedCountProperty(): int
    {
        return $this->todayAttendances
            ->filter(function ($employee) {

                $attendance =
                    $employee
                        ->attendanceRecords
                        ->first();

                return $attendance
                    && $attendance->time_in
                    && $attendance->time_out;
            })
            ->count();
    }


    /**
     * -------------------------------------------------------------
     * RENDER
     * -------------------------------------------------------------
     */
    public function render()
    {
        return view(
            'livewire.attendance.employee-time-clock',
            [
                'employees' => Employee::query()
                    ->where(
                        'employment_status',
                        'active'
                    )
                    ->orderBy('last_name')
                    ->orderBy('first_name')
                    ->get(),
            ]
        );
    }
}