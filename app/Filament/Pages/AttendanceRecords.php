<?php

namespace App\Filament\Pages;

use App\Models\AttendanceRecord;
use App\Models\Employee;
use App\Services\Attendance\AttendanceCalculator;
use Carbon\Carbon;
use Filament\Forms\Components\Select;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use UnitEnum;

class AttendanceRecords extends Page implements HasForms
{
    use InteractsWithForms;

    protected static ?string $title = 'Attendance Records';

    protected static ?string $navigationLabel = 'Attendance Records';

    protected static string|UnitEnum|null $navigationGroup = 'Attendance';

    protected static string|\BackedEnum|null $navigationIcon =
        'heroicon-o-calendar-days';

    protected string $view = 'filament.pages.attendance-records';

    /*
    |--------------------------------------------------------------------------
    | FILTER DATA
    |--------------------------------------------------------------------------
    */

    public ?array $data = [];

    /*
    |--------------------------------------------------------------------------
    | EDIT / REVIEW MODAL
    |--------------------------------------------------------------------------
    */

    public bool $showAttendanceModal = false;

    public ?int $editingAttendanceId = null;

    public string $editTimeIn = '';

    public string $editTimeOut = '';

    public string $editStatus = 'present';

    public string $editRemarks = '';

    public int $approvedOvertimeMinutes = 0;

    public string $overtimeRemarks = '';

    public ?int $selectedAttendanceId = null;

    public array $editAttendanceData = [
        'attendance_date' => null,
        'time_in' => null,
        'time_out' => null,
        'status' => 'present',
        'remarks' => null,
    ];

    public array $overtimeApprovalData = [
        'approved_overtime_minutes' => 0,
        'overtime_remarks' => null,
    ];

    /*
    |--------------------------------------------------------------------------
    | ADD ATTENDANCE MODAL
    |--------------------------------------------------------------------------
    */

    public bool $showAddAttendanceModal = false;

    public array $addAttendanceData = [
        'attendance_date' => null,
        'employee_ids' => [],
        'time_in' => '09:00',
        'time_out' => '17:00',
        'status' => 'present',
        'remarks' => null,
    ];

    public bool $selectAllEmployees = false;

    /*
    |--------------------------------------------------------------------------
    | MOUNT
    |--------------------------------------------------------------------------
    */

    public function mount(): void
    {
        $this->form->fill([
            'employee_id' => null,
            'month' => now()->format('Y-m'),
            'period' => '1-15',
        ]);

        $this->resetAttendanceModal();
        $this->resetAddAttendanceModal();
    }

    /*
    |--------------------------------------------------------------------------
    | FILTER FORM
    |--------------------------------------------------------------------------
    */

    public function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Attendance Filter')
                    ->description(
                        'Select an employee, month, and payroll period.'
                    )
                    ->schema([
                        Select::make('employee_id')
                            ->label('Employee')
                            ->options(
                                Employee::query()
                                    ->where('employment_status', 'active')
                                    ->orderBy('last_name')
                                    ->orderBy('first_name')
                                    ->get()
                                    ->mapWithKeys(
                                        fn (Employee $employee) => [
                                            $employee->id => sprintf(
                                                '<div class="leading-tight">
                                                    <div class="font-medium">%s</div>
                                                    <div class="text-xs text-gray-500">%s</div>
                                                </div>',
                                                e($employee->full_name),
                                                e($employee->employee_id),
                                            ),
                                        ]
                                    )
                                    ->toArray()
                            )
                            ->allowHtml()
                            ->searchable()
                            ->preload()
                            ->placeholder('Select Employee')
                            ->live(),

                        Select::make('month')
                            ->label('Month')
                            ->options(
                                collect(range(0, 11))
                                    ->mapWithKeys(
                                        function (int $monthsAgo) {
                                            $date = now()
                                                ->startOfMonth()
                                                ->subMonths($monthsAgo);

                                            return [
                                                $date->format('Y-m') =>
                                                    $date->format('F Y'),
                                            ];
                                        }
                                    )
                            )
                            ->default(now()->format('Y-m'))
                            ->live(),

                        Select::make('period')
                            ->label('Period')
                            ->options([
                                '1-15' => '1–15',
                                '16-end' => '16–End of Month',
                            ])
                            ->default('1-15')
                            ->live(),
                    ])
                    ->columns(3),
            ])
            ->statePath('data');
    }

    /*
    |--------------------------------------------------------------------------
    | ACTIVE EMPLOYEES
    |--------------------------------------------------------------------------
    */

    public function getActiveEmployeesProperty(): Collection
    {
        return Employee::query()
            ->where('employment_status', 'active')
            ->orderBy('last_name')
            ->orderBy('first_name')
            ->get();
    }

    /*
    |--------------------------------------------------------------------------
    | SELECTED EMPLOYEE
    |--------------------------------------------------------------------------
    */

    public function getSelectedEmployeeProperty(): ?Employee
    {
        $employeeId = $this->data['employee_id'] ?? null;

        if (! $employeeId) {
            return null;
        }

        return Employee::find($employeeId);
    }

    /*
    |--------------------------------------------------------------------------
    | ATTENDANCE RECORDS
    |--------------------------------------------------------------------------
    */

    public function getAttendanceRecordsProperty(): Collection
    {
        $employeeId = $this->data['employee_id'] ?? null;

        if (! $employeeId) {
            return new Collection();
        }

        $month = $this->data['month']
            ?? now()->format('Y-m');

        $period = $this->data['period']
            ?? '1-15';

        $startDate = $month . '-01';

        if ($period === '1-15') {
            $endDate = $month . '-15';
        } else {
            $endDate = Carbon::createFromFormat(
                'Y-m',
                $month
            )
                ->endOfMonth()
                ->format('Y-m-d');
        }

        return AttendanceRecord::query()
            ->with('employee')
            ->where('employee_id', $employeeId)
            ->whereBetween('attendance_date', [
                $startDate,
                $endDate,
            ])
            ->orderBy('attendance_date')
            ->get();
    }

    /*
    |--------------------------------------------------------------------------
    | SUMMARY
    |--------------------------------------------------------------------------
    */

    public function getTotalWorkedMinutesProperty(): int
    {
        return (int) $this->attendanceRecords
            ->sum('worked_minutes');
    }

    public function getTotalLateMinutesProperty(): int
    {
        return (int) $this->attendanceRecords
            ->sum('late_minutes');
    }

    public function getTotalUndertimeMinutesProperty(): int
    {
        return (int) $this->attendanceRecords
            ->sum('undertime_minutes');
    }

    public function getTotalOvertimeMinutesProperty(): int
    {
        return (int) $this->attendanceRecords
            ->sum('approved_overtime_minutes');
    }

    public function getTotalRegularMinutesProperty(): int
    {
        return (int) $this->attendanceRecords
            ->sum('regular_minutes');
    }

    public function getTotalRestDayMinutesProperty(): int
    {
        return (int) $this->attendanceRecords
            ->sum('rest_day_minutes');
    }

    /*
    |--------------------------------------------------------------------------
    | VL / SL USAGE
    |--------------------------------------------------------------------------
    |
    | Leave usage is derived directly from attendance_records.status.
    |
    | vl          = 1.00 VL day
    | half_day_vl = 0.50 VL day
    | sl          = 1.00 SL day
    | half_day_sl = 0.50 SL day
    |
    | No separate leave-balance table is required.
    |--------------------------------------------------------------------------
    */

    public function getTotalVlCreditsProperty(): float
    {
        return round(
            $this->attendanceRecords->sum(
                fn (AttendanceRecord $attendance): float =>
                    match ($attendance->status) {
                        'vl' => 1.00,
                        'half_day_vl' => 0.50,
                        default => 0.00,
                    }
            ),
            2
        );
    }

    public function getTotalSlCreditsProperty(): float
    {
        return round(
            $this->attendanceRecords->sum(
                fn (AttendanceRecord $attendance): float =>
                    match ($attendance->status) {
                        'sl' => 1.00,
                        'half_day_sl' => 0.50,
                        default => 0.00,
                    }
            ),
            2
        );
    }

    /*
    |--------------------------------------------------------------------------
    | ADD ATTENDANCE
    |--------------------------------------------------------------------------
    */

    public function openAddAttendanceModal(): void
    {
        $this->resetAddAttendanceModal();

        $month = $this->data['month']
            ?? now()->format('Y-m');

        $this->addAttendanceData['attendance_date'] =
            Carbon::createFromFormat(
                'Y-m',
                $month
            )
                ->startOfMonth()
                ->format('Y-m-d');

        $this->showAddAttendanceModal = true;
    }

    public function closeAddAttendanceModal(): void
    {
        $this->showAddAttendanceModal = false;

        $this->resetAddAttendanceModal();
    }

    protected function resetAddAttendanceModal(): void
    {
        $this->addAttendanceData = [
            'attendance_date' => now()->format('Y-m-d'),
            'employee_ids' => [],
            'time_in' => '09:00',
            'time_out' => '17:00',
            'status' => 'present',
            'remarks' => null,
        ];

        $this->selectAllEmployees = false;
    }

    /*
    |--------------------------------------------------------------------------
    | SELECT / DESELECT ALL EMPLOYEES
    |--------------------------------------------------------------------------
    */

    public function toggleAllEmployees(): void
    {
        $activeEmployeeIds = Employee::query()
            ->where('employment_status', 'active')
            ->orderBy('last_name')
            ->orderBy('first_name')
            ->pluck('id')
            ->map(fn ($id) => (int) $id)
            ->values()
            ->all();

        if ($this->selectAllEmployees) {
            $this->addAttendanceData['employee_ids'] =
                $activeEmployeeIds;
        } else {
            $this->addAttendanceData['employee_ids'] = [];
        }
    }

    public function updatedAddAttendanceDataEmployeeIds(): void
    {
        $activeEmployeeCount = Employee::query()
            ->where('employment_status', 'active')
            ->count();

        $selectedCount = count(
            $this->addAttendanceData['employee_ids'] ?? []
        );

        $this->selectAllEmployees =
            $activeEmployeeCount > 0
            && $selectedCount === $activeEmployeeCount;
    }

    /*
    |--------------------------------------------------------------------------
    | SAVE NEW ATTENDANCE FOR SELECTED EMPLOYEES
    |--------------------------------------------------------------------------
    */

    public function addAttendance(): void
    {
        $this->validate([
            'addAttendanceData.attendance_date' => [
                'required',
                'date',
            ],

            'addAttendanceData.employee_ids' => [
                'required',
                'array',
                'min:1',
            ],

            'addAttendanceData.employee_ids.*' => [
                'integer',
                Rule::exists('employees', 'id')
                    ->where(
                        fn ($query) => $query->where(
                            'employment_status',
                            'active'
                        )
                    ),
            ],

            'addAttendanceData.time_in' => [
                'nullable',
                'date_format:H:i',
            ],

            'addAttendanceData.time_out' => [
                'nullable',
                'date_format:H:i',
            ],

            'addAttendanceData.status' => [
                'required',
                Rule::in([
                    'present',
                    'absent',
                    'vl',
                    'sl',
                    'half_day',
                    'half_day_vl',
                    'half_day_sl',
                    'rest_day',
                    'regular_holiday',
                    'special_non_working_holiday',
                    'emergency_leave',
                    'lwop',
                ]),
            ],

            'addAttendanceData.remarks' => [
                'nullable',
                'string',
                'max:1000',
            ],
        ]);

        $date = Carbon::parse(
            $this->addAttendanceData['attendance_date']
        )->format('Y-m-d');

        $employeeIds = collect(
            $this->addAttendanceData['employee_ids'] ?? []
        )
            ->map(fn ($id) => (int) $id)
            ->unique()
            ->values();

        if ($employeeIds->isEmpty()) {
            Notification::make()
                ->title('No employees selected')
                ->body('Please select at least one employee.')
                ->warning()
                ->send();

            return;
        }

        $status = $this->addAttendanceData['status'];

        $timeIn = $this->addAttendanceData['time_in']
            ?: null;

        $timeOut = $this->addAttendanceData['time_out']
            ?: null;

        $remarks = $this->addAttendanceData['remarks']
            ?: null;

        /*
        |--------------------------------------------------------------------------
        | LEAVE / ABSENCE STATUSES
        |--------------------------------------------------------------------------
        |
        | These statuses should not produce worked hours,
        | late, undertime, or overtime.
        |
        */

        $zeroWorkStatuses = [
            'absent',
            'vl',
            'sl',
            'emergency_leave',
            'lwop',
        ];

        $created = 0;
        $skipped = 0;

        DB::transaction(function () use (
            $employeeIds,
            $date,
            $timeIn,
            $timeOut,
            $status,
            $remarks,
            $zeroWorkStatuses,
            &$created,
            &$skipped
        ) {
            foreach ($employeeIds as $employeeId) {

                $employee = Employee::find($employeeId);

                if (! $employee) {
                    continue;
                }

                /*
                |--------------------------------------------------------------------------
                | DUPLICATE CHECK
                |--------------------------------------------------------------------------
                */

                $exists = AttendanceRecord::query()
                    ->where('employee_id', $employeeId)
                    ->whereDate('attendance_date', $date)
                    ->exists();

                if ($exists) {
                    $skipped++;

                    continue;
                }

                /*
                |--------------------------------------------------------------------------
                | CREATE RECORD
                |--------------------------------------------------------------------------
                */

                $attendance = AttendanceRecord::create([
                    'employee_id' => $employeeId,
                    'attendance_date' => $date,

                    'time_in' => $timeIn,
                    'time_out' => $timeOut,

                    'status' => $status,

                    'remarks' => $remarks,

                    'worked_minutes' => 0,
                    'regular_minutes' => 0,
                    'rest_day_minutes' => 0,
                    'rest_day_overtime_minutes' => 0,
                    'night_shift_minutes' => 0,

                    'late_minutes' => 0,
                    'undertime_minutes' => 0,

                    'overtime_minutes' => 0,
                    'detected_overtime_minutes' => 0,
                    'approved_overtime_minutes' => 0,

                    'overtime_status' => 'none',
                ]);

                /*
                |--------------------------------------------------------------------------
                | LEAVE / ABSENT
                |--------------------------------------------------------------------------
                */

                if (
                    in_array(
                        $status,
                        $zeroWorkStatuses,
                        true
                    )
                ) {
                    $attendance->update([
                        'worked_minutes' => 0,
                        'regular_minutes' => 0,
                        'rest_day_minutes' => 0,
                        'rest_day_overtime_minutes' => 0,
                        'night_shift_minutes' => 0,

                        'late_minutes' => 0,
                        'undertime_minutes' => 0,

                        'detected_overtime_minutes' => 0,
                        'approved_overtime_minutes' => 0,
                        'overtime_minutes' => 0,

                        'overtime_status' => 'none',

                        'overtime_approved_by' => null,
                        'overtime_approved_at' => null,
                        'overtime_remarks' => null,
                    ]);
                } else {

                    /*
                    |--------------------------------------------------------------------------
                    | NORMAL ATTENDANCE CALCULATION
                    |--------------------------------------------------------------------------
                    */

                    app(AttendanceCalculator::class)
                        ->calculate($attendance);

                    $attendance->refresh();

                    /*
                    |--------------------------------------------------------------------------
                    | PRESERVE MANUAL STATUS
                    |--------------------------------------------------------------------------
                    */

                    if (
                        in_array(
                            $status,
                            [
                                'half_day',
                                'half_day_vl',
                                'half_day_sl',
                                'regular_holiday',
                                'special_non_working_holiday',
                                'rest_day',
                            ],
                            true
                        )
                    ) {
                        $attendance->update([
                            'status' => $status,
                        ]);
                    }
                }

                $created++;
            }
        });

        /*
        |--------------------------------------------------------------------------
        | NOTIFICATION
        |--------------------------------------------------------------------------
        */

        $body = "{$created} attendance record(s) added.";

        if ($skipped > 0) {
            $body .=
                " {$skipped} skipped because attendance already exists for this date.";
        }

        Notification::make()
            ->title('Attendance added')
            ->body($body)
            ->success()
            ->send();

        $this->closeAddAttendanceModal();
    }

    /*
    |--------------------------------------------------------------------------
    | OPEN ATTENDANCE / OT REVIEW
    |--------------------------------------------------------------------------
    */

    public function openAttendanceModal(int $attendanceId): void
    {
        $attendance = AttendanceRecord::findOrFail($attendanceId);

        $this->selectedAttendanceId = $attendance->id;

        $this->editAttendanceData = [
            'attendance_date' =>
                $attendance->attendance_date?->format('Y-m-d'),

            'time_in' => $attendance->time_in
                ? Carbon::parse($attendance->time_in)->format('H:i')
                : null,

            'time_out' => $attendance->time_out
                ? Carbon::parse($attendance->time_out)->format('H:i')
                : null,

            'status' => $attendance->status ?? 'present',

            'remarks' => $attendance->remarks,
        ];

        $this->overtimeApprovalData = [
            'approved_overtime_minutes' =>
                $attendance->overtime_status === 'approved'
                    ? (int) $attendance->approved_overtime_minutes
                    : (int) $attendance->detected_overtime_minutes,

            'overtime_remarks' =>
                $attendance->overtime_remarks,
        ];

        $this->showAttendanceModal = true;
    }

    /*
    |--------------------------------------------------------------------------
    | CLOSE MODAL
    |--------------------------------------------------------------------------
    */

    public function closeAttendanceModal(): void
    {
        $this->showAttendanceModal = false;

        $this->selectedAttendanceId = null;

        $this->resetAttendanceModal();
    }

    protected function resetAttendanceModal(): void
    {
        $this->editAttendanceData = [
            'attendance_date' => null,
            'time_in' => null,
            'time_out' => null,
            'status' => 'present',
            'remarks' => null,
        ];

        $this->overtimeApprovalData = [
            'approved_overtime_minutes' => 0,
            'overtime_remarks' => null,
        ];
    }

    /*
    |--------------------------------------------------------------------------
    | CURRENT ATTENDANCE
    |--------------------------------------------------------------------------
    */

    public function getSelectedAttendanceProperty(): ?AttendanceRecord
    {
        if (! $this->selectedAttendanceId) {
            return null;
        }

        return AttendanceRecord::find(
            $this->selectedAttendanceId
        );
    }

    /*
    |--------------------------------------------------------------------------
    | SAVE ATTENDANCE
    |--------------------------------------------------------------------------
    */

    public function saveAttendance(): void
    {
        if (! $this->selectedAttendanceId) {
            return;
        }

        $attendance = AttendanceRecord::findOrFail(
            $this->selectedAttendanceId
        );

        $this->validate([
            'editAttendanceData.attendance_date' => [
                'required',
                'date',
                Rule::unique(
                    'attendance_records',
                    'attendance_date'
                )
                    ->where(
                        fn ($query) => $query->where(
                            'employee_id',
                            $attendance->employee_id
                        )
                    )
                    ->ignore($attendance->id),
            ],

            'editAttendanceData.time_in' => [
                'nullable',
                'date_format:H:i',
            ],

            'editAttendanceData.time_out' => [
                'nullable',
                'date_format:H:i',
            ],

            'editAttendanceData.status' => [
                'required',
                Rule::in([
                    'present',
                    'absent',
                    'vl',
                    'sl',
                    'half_day',
                    'half_day_vl',
                    'half_day_sl',
                    'rest_day',
                    'regular_holiday',
                    'special_non_working_holiday',
                    'emergency_leave',
                    'lwop',
                ]),
            ],

            'editAttendanceData.remarks' => [
                'nullable',
                'string',
                'max:1000',
            ],
        ]);

        $oldDetectedOt =
            (int) $attendance->detected_overtime_minutes;

        $status =
            $this->editAttendanceData['status'];

        $attendance->update([
            'attendance_date' =>
                $this->editAttendanceData['attendance_date'],

            'time_in' =>
                $this->editAttendanceData['time_in']
                    ?: null,

            'time_out' =>
                $this->editAttendanceData['time_out']
                    ?: null,

            'status' => $status,

            'remarks' =>
                $this->editAttendanceData['remarks']
                    ?: null,
        ]);

        /*
        |--------------------------------------------------------------------------
        | LEAVE / ABSENCE STATUSES
        |--------------------------------------------------------------------------
        */

        if (
            in_array(
                $status,
                [
                    'absent',
                    'vl',
                    'sl',
                    'emergency_leave',
                    'lwop',
                ],
                true
            )
        ) {
            $attendance->update([
                'worked_minutes' => 0,
                'regular_minutes' => 0,
                'rest_day_minutes' => 0,
                'rest_day_overtime_minutes' => 0,
                'night_shift_minutes' => 0,

                'late_minutes' => 0,
                'undertime_minutes' => 0,

                'detected_overtime_minutes' => 0,
                'approved_overtime_minutes' => 0,
                'overtime_minutes' => 0,

                'overtime_status' => 'none',

                'overtime_approved_by' => null,
                'overtime_approved_at' => null,
                'overtime_remarks' => null,
            ]);

            $attendance->refresh();
        } else {

            /*
            |--------------------------------------------------------------------------
            | RECALCULATE ATTENDANCE
            |--------------------------------------------------------------------------
            */

            app(AttendanceCalculator::class)
                ->calculate($attendance);

            $attendance->refresh();

            /*
            |--------------------------------------------------------------------------
            | PRESERVE MANUAL STATUS
            |--------------------------------------------------------------------------
            */

            if (
                in_array(
                    $status,
                    [
                        'half_day',
                        'half_day_vl',
                        'half_day_sl',
                        'regular_holiday',
                        'special_non_working_holiday',
                        'rest_day',
                    ],
                    true
                )
            ) {
                $attendance->update([
                    'status' => $status,
                ]);
            }

            $attendance->refresh();
        }

        /*
        |--------------------------------------------------------------------------
        | DETECTED OT CHANGED
        |--------------------------------------------------------------------------
        */

        if (
            $oldDetectedOt !==
            (int) $attendance->detected_overtime_minutes
        ) {
            $this->overtimeApprovalData[
                'approved_overtime_minutes'
            ] =
                (int) $attendance->detected_overtime_minutes;
        }

        Notification::make()
            ->title('Attendance updated')
            ->body(
                'Attendance has been recalculated successfully.'
            )
            ->success()
            ->send();

        $this->closeAttendanceModal();
    }

    /*
    |--------------------------------------------------------------------------
    | APPROVE OT
    |--------------------------------------------------------------------------
    */

    public function approveOvertime(): void
    {
        if (! $this->selectedAttendanceId) {
            return;
        }

        /*
        |--------------------------------------------------------------------------
        | SAVE CURRENT ATTENDANCE EDITS FIRST
        |--------------------------------------------------------------------------
        */

        $this->persistAttendanceChanges();

        $attendance = AttendanceRecord::findOrFail(
            $this->selectedAttendanceId
        );

        $detectedMinutes =
            (int) $attendance->detected_overtime_minutes;

        if ($detectedMinutes <= 0) {
            Notification::make()
                ->title('No overtime to approve')
                ->warning()
                ->send();

            return;
        }

        $approvedMinutes = (int) (
            $this->overtimeApprovalData[
                'approved_overtime_minutes'
            ] ?? 0
        );

        /*
        |--------------------------------------------------------------------------
        | LIMIT APPROVED OT
        |--------------------------------------------------------------------------
        */

        $approvedMinutes = max(
            0,
            min(
                $approvedMinutes,
                $detectedMinutes
            )
        );

        /*
        |--------------------------------------------------------------------------
        | ZERO = REJECTED
        |--------------------------------------------------------------------------
        */

        if ($approvedMinutes === 0) {
            $this->markOvertimeRejected(
                $attendance
            );

            return;
        }

        /*
        |--------------------------------------------------------------------------
        | APPROVE
        |--------------------------------------------------------------------------
        */

        $attendance->update([
            'approved_overtime_minutes' =>
                $approvedMinutes,

            'overtime_status' =>
                'approved',

            'overtime_approved_by' =>
                auth()->id(),

            'overtime_approved_at' =>
                now(),

            'overtime_remarks' =>
                $this->overtimeApprovalData[
                    'overtime_remarks'
                ] ?? null,

            'overtime_minutes' =>
                $approvedMinutes,
        ]);

        Notification::make()
            ->title('Overtime approved')
            ->body(
                $this->formatMinutes(
                    $approvedMinutes
                ) . ' approved.'
            )
            ->success()
            ->send();

        $this->closeAttendanceModal();
    }

    /*
    |--------------------------------------------------------------------------
    | REJECT OT
    |--------------------------------------------------------------------------
    */

    public function rejectOvertime(): void
    {
        if (! $this->selectedAttendanceId) {
            return;
        }

        /*
        |--------------------------------------------------------------------------
        | SAVE CURRENT ATTENDANCE EDITS FIRST
        |--------------------------------------------------------------------------
        */

        $this->persistAttendanceChanges();

        $attendance = AttendanceRecord::findOrFail(
            $this->selectedAttendanceId
        );

        $this->markOvertimeRejected(
            $attendance
        );
    }

    /*
    |--------------------------------------------------------------------------
    | REJECT HELPER
    |--------------------------------------------------------------------------
    */

    protected function markOvertimeRejected(
        AttendanceRecord $attendance
    ): void {
        $attendance->update([
            'approved_overtime_minutes' => 0,

            'overtime_status' => 'rejected',

            'overtime_approved_by' =>
                auth()->id(),

            'overtime_approved_at' =>
                now(),

            'overtime_remarks' =>
                $this->overtimeApprovalData[
                    'overtime_remarks'
                ] ?? null,

            'overtime_minutes' => 0,
        ]);

        Notification::make()
            ->title('Overtime rejected')
            ->success()
            ->send();

        $this->closeAttendanceModal();
    }

    /*
    |--------------------------------------------------------------------------
    | SAVE ATTENDANCE WITHOUT CLOSING MODAL
    |--------------------------------------------------------------------------
    |
    | Used when Approve OT / Reject OT is clicked.
    |
    */

    protected function persistAttendanceChanges(): void
    {
        if (! $this->selectedAttendanceId) {
            return;
        }

        $attendance = AttendanceRecord::findOrFail(
            $this->selectedAttendanceId
        );

        $this->validate([
            'editAttendanceData.attendance_date' => [
                'required',
                'date',
                Rule::unique(
                    'attendance_records',
                    'attendance_date'
                )
                    ->where(
                        fn ($query) => $query->where(
                            'employee_id',
                            $attendance->employee_id
                        )
                    )
                    ->ignore($attendance->id),
            ],

            'editAttendanceData.time_in' => [
                'nullable',
                'date_format:H:i',
            ],

            'editAttendanceData.time_out' => [
                'nullable',
                'date_format:H:i',
            ],

            'editAttendanceData.status' => [
                'required',
                Rule::in([
                    'present',
                    'absent',
                    'vl',
                    'sl',
                    'half_day',
                    'half_day_vl',
                    'half_day_sl',
                    'rest_day',
                    'regular_holiday',
                    'special_non_working_holiday',
                    'emergency_leave',
                    'lwop',
                ]),
            ],

            'editAttendanceData.remarks' => [
                'nullable',
                'string',
                'max:1000',
            ],
        ]);

        $status =
            $this->editAttendanceData['status'];

        $attendance->update([
            'attendance_date' =>
                $this->editAttendanceData['attendance_date'],

            'time_in' =>
                $this->editAttendanceData['time_in']
                    ?: null,

            'time_out' =>
                $this->editAttendanceData['time_out']
                    ?: null,

            'status' => $status,

            'remarks' =>
                $this->editAttendanceData['remarks']
                    ?: null,
        ]);

        /*
        |--------------------------------------------------------------------------
        | LEAVE / ABSENT
        |--------------------------------------------------------------------------
        */

        if (
            in_array(
                $status,
                [
                    'absent',
                    'vl',
                    'sl',
                    'emergency_leave',
                    'lwop',
                ],
                true
            )
        ) {
            $attendance->update([
                'worked_minutes' => 0,
                'regular_minutes' => 0,
                'rest_day_minutes' => 0,
                'rest_day_overtime_minutes' => 0,
                'night_shift_minutes' => 0,

                'late_minutes' => 0,
                'undertime_minutes' => 0,

                'detected_overtime_minutes' => 0,
                'approved_overtime_minutes' => 0,
                'overtime_minutes' => 0,

                'overtime_status' => 'none',

                'overtime_approved_by' => null,
                'overtime_approved_at' => null,
                'overtime_remarks' => null,
            ]);

            $attendance->refresh();

            return;
        }

        /*
        |--------------------------------------------------------------------------
        | NORMAL CALCULATION
        |--------------------------------------------------------------------------
        */

        app(AttendanceCalculator::class)
            ->calculate($attendance);

        $attendance->refresh();

        /*
        |--------------------------------------------------------------------------
        | PRESERVE MANUAL STATUS
        |--------------------------------------------------------------------------
        */

        if (
            in_array(
                $status,
                [
                    'half_day',
                    'regular_holiday',
                    'special_non_working_holiday',
                    'rest_day',
                ],
                true
            )
        ) {
            $attendance->update([
                'status' => $status,
            ]);
        }

        $attendance->refresh();
    }

    /*
    |--------------------------------------------------------------------------
    | FORMAT MINUTES
    |--------------------------------------------------------------------------
    */

    public function formatMinutes(int $minutes): string
    {
        if ($minutes <= 0) {
            return '0 min';
        }

        $hours = intdiv(
            $minutes,
            60
        );

        $remainingMinutes =
            $minutes % 60;

        if ($hours === 0) {
            return $remainingMinutes . ' min';
        }

        if ($remainingMinutes === 0) {
            return $hours . ' hr';
        }

        return sprintf(
            '%d hr %02d min',
            $hours,
            $remainingMinutes
        );
    }
}