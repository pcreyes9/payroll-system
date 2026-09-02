<?php

namespace App\Filament\Pages;

use App\Models\AttendanceRecord;
use App\Models\Employee;
use Filament\Forms\Components\Select;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Pages\Page;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Illuminate\Database\Eloquent\Collection;
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

    public ?array $data = [];

    public function mount(): void
    {
        $this->form->fill([
            'employee_id' => null,
            'month' => now()->format('Y-m'),
            'period' => '1-15',
        ]);
    }

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

    public function getSelectedEmployeeProperty(): ?Employee
    {
        $employeeId = $this->data['employee_id'] ?? null;

        if (! $employeeId) {
            return null;
        }

        return Employee::find($employeeId);
    }

    public function getAttendanceRecordsProperty(): Collection
    {
        $employeeId = $this->data['employee_id'] ?? null;

        if (! $employeeId) {
            return new Collection();
        }

        $month = $this->data['month'] ?? now()->format('Y-m');

        $period = $this->data['period'] ?? '1-15';

        $startDate = $month . '-01';

        if ($period === '1-15') {

            $endDate = $month . '-15';

        } else {

            $endDate = \Carbon\Carbon::createFromFormat(
                'Y-m',
                $month
            )
                ->endOfMonth()
                ->format('Y-m-d');
        }

        return AttendanceRecord::query()
            ->where('employee_id', $employeeId)
            ->whereBetween(
                'attendance_date',
                [
                    $startDate,
                    $endDate,
                ]
            )
            ->orderBy('attendance_date')
            ->get();
    }

    public function getTotalWorkedMinutesProperty(): int
    {
        return (int) $this->attendanceRecords->sum(
            'worked_minutes'
        );
    }

    public function getTotalLateMinutesProperty(): int
    {
        return (int) $this->attendanceRecords->sum(
            'late_minutes'
        );
    }

    public function getTotalUndertimeMinutesProperty(): int
    {
        return (int) $this->attendanceRecords->sum(
            'undertime_minutes'
        );
    }

    public function getTotalOvertimeMinutesProperty(): int
    {
        return (int) $this->attendanceRecords->sum(
            'overtime_minutes'
        );
    }
}
