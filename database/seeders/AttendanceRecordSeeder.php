<?php

namespace Database\Seeders;

use App\Models\AttendanceRecord;
use App\Models\Employee;
use App\Services\Attendance\AttendanceCalculator;
use Carbon\Carbon;
use Illuminate\Database\Seeder;

class AttendanceRecordSeeder extends Seeder
{
    public function run(): void
    {
        $sqlFile = database_path('seeders/attendances.sql');

        if (! file_exists($sqlFile)) {
            $this->command?->error(
                "SQL file not found: {$sqlFile}"
            );

            return;
        }

        $sql = file_get_contents($sqlFile);

        if ($sql === false) {
            $this->command?->error(
                'Unable to read the attendance SQL file.'
            );

            return;
        }

        /*
         * Only process INSERT statements for the legacy
         * attendances table.
         */
        preg_match_all(
            '/INSERT INTO `attendances`.*?VALUES\s*(.*?);/is',
            $sql,
            $matches
        );

        if (empty($matches[1])) {
            $this->command?->error(
                'No attendance INSERT statements were found.'
            );

            return;
        }

        $calculator = app(AttendanceCalculator::class);

        $imported = 0;
        $skippedEmployee9 = 0;
        $skippedMalformed = 0;
        $skippedMissingEmployee = 0;

        foreach ($matches[1] as $valuesBlock) {
            /*
             * Extract each (...) row from the INSERT statement.
             */
            preg_match_all(
                '/\((?:[^\'()]|\'(?:\\\\.|[^\'])*\')*\)/s',
                $valuesBlock,
                $rowMatches
            );

            foreach ($rowMatches[0] as $row) {
                $fields = $this->parseRow($row);

                if (count($fields) < 12) {
                    continue;
                }

                $employeeId = (int) $fields[1];

                $attendanceDate = $this->cleanValue($fields[2]);
                $timeIn = $this->cleanValue($fields[3]);
                $timeOut = $this->cleanValue($fields[4]);
                $status = $this->cleanValue($fields[7]);
                $remarks = $this->cleanValue($fields[8]);

                /*
                 * Employee 9 was intentionally excluded.
                 */
                if ($employeeId === 9) {
                    $skippedEmployee9++;

                    continue;
                }

                /*
                 * Skip malformed zero dates.
                 */
                if (
                    empty($attendanceDate)
                    || $attendanceDate === '0000-00-00'
                ) {
                    $skippedMalformed++;

                    continue;
                }

                $employee = Employee::find($employeeId);

                if (! $employee) {
                    $skippedMissingEmployee++;

                    $this->command?->warn(
                        "Employee ID {$employeeId} not found. "
                        . "Skipping attendance for {$attendanceDate}."
                    );

                    continue;
                }

                /*
                 * Legacy timestamps are UTC.
                 * Convert them to Philippine time (+8).
                 */
                $timeIn = $this->convertToPhilippineTime($timeIn);
                $timeOut = $this->convertToPhilippineTime($timeOut);

                $mappedStatus = $this->mapStatus($status);

                $attendance = AttendanceRecord::updateOrCreate(
                    [
                        'employee_id' => $employee->id,
                        'attendance_date' => $attendanceDate,
                    ],
                    [
                        'time_in' => $timeIn,
                        'time_out' => $timeOut,

                        'break_in' => null,
                        'break_out' => null,

                        'status' => $mappedStatus,
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
                        'overtime_approved_by' => null,
                        'overtime_approved_at' => null,
                        'overtime_remarks' => null,
                    ]
                );

                /*
                 * Let the current attendance calculator determine:
                 *
                 * - worked minutes
                 * - regular minutes
                 * - late minutes
                 * - undertime
                 * - detected overtime
                 * - night shift minutes
                 * - rest-day minutes
                 */
                if (
                    $attendance->time_in &&
                    $attendance->time_out
                ) {
                    $calculator->calculate($attendance);

                    /*
                     * The calculator derives status from the
                     * attendance times. Restore legacy statuses
                     * that must remain explicitly classified.
                     */
                    if (in_array($mappedStatus, [
                        'half_day',
                        'vl',
                        'sl',
                        'sil',
                        'regular_holiday',
                        'special_non_working_holiday',
                        'emergency_leave',
                        'lwop',
                    ], true)) {
                        $attendance->update([
                            'status' => $mappedStatus,
                        ]);
                    }
                }

                $imported++;
            }
        }

        $this->command?->newLine();

        $this->command?->info(
            "Imported {$imported} attendance records."
        );

        $this->command?->info(
            "Excluded {$skippedEmployee9} records for employee 9."
        );

        $this->command?->info(
            "Excluded {$skippedMalformed} malformed attendance records."
        );

        if ($skippedMissingEmployee > 0) {
            $this->command?->warn(
                "Skipped {$skippedMissingEmployee} records because the employee does not exist."
            );
        }

        $this->command?->newLine();
    }

    /**
     * Parse one SQL value row.
     */
    private function parseRow(string $row): array
    {
        $row = trim($row);

        if (
            str_starts_with($row, '(') &&
            str_ends_with($row, ')')
        ) {
            $row = substr($row, 1, -1);
        }

        $fields = [];

        $current = '';
        $insideQuotes = false;
        $escaped = false;

        $length = strlen($row);

        for ($i = 0; $i < $length; $i++) {
            $char = $row[$i];

            if ($insideQuotes) {
                $current .= $char;

                if ($escaped) {
                    $escaped = false;

                    continue;
                }

                if ($char === '\\') {
                    $escaped = true;

                    continue;
                }

                if ($char === "'") {
                    $insideQuotes = false;
                }

                continue;
            }

            if ($char === "'") {
                $insideQuotes = true;
                $current .= $char;

                continue;
            }

            if ($char === ',') {
                $fields[] = trim($current);
                $current = '';

                continue;
            }

            $current .= $char;
        }

        $fields[] = trim($current);

        return $fields;
    }

    /**
     * Convert SQL value into a normal PHP value.
     */
    private function cleanValue(?string $value): ?string
    {
        if ($value === null) {
            return null;
        }

        $value = trim($value);

        if (strtoupper($value) === 'NULL') {
            return null;
        }

        if (
            strlen($value) >= 2 &&
            $value[0] === "'" &&
            $value[strlen($value) - 1] === "'"
        ) {
            $value = substr($value, 1, -1);

            $value = str_replace(
                ["\\'", "\\\\"],
                ["'", "\\"],
                $value
            );
        }

        return $value;
    }

    /**
     * Convert legacy UTC timestamp to Philippine time.
     */
    private function convertToPhilippineTime(?string $value): ?string
    {
        if (empty($value)) {
            return null;
        }

        try {
            return Carbon::parse($value, 'UTC')
                ->setTimezone('Asia/Manila')
                ->format('H:i:s');
        } catch (\Throwable) {
            return null;
        }
    }

    /**
     * Convert legacy attendance statuses to the
     * statuses used by the new attendance system.
     */
    private function mapStatus(?string $status): string
    {
        return match ($status) {
            'Present' => 'present',

            'Pending',
            'Absent' => 'absent',

            'Late' => 'present',

            'Half Day - VL',
            'Half Day - SL' => 'half_day',

            'Vacation Leave' => 'vl',

            'Sick Leave' => 'sl',

            'Regular Holiday' => 'regular_holiday',

            'Special Non-Working Holiday' =>
                'special_non_working_holiday',

            default => 'absent',
        };
    }
}
