<x-filament-panels::page>

    {{-- ========================================================= --}}
    {{-- FILTER --}}
    {{-- ========================================================= --}}

    <div>

        {{ $this->form }}

    </div>


    {{-- ========================================================= --}}
    {{-- NO EMPLOYEE SELECTED --}}
    {{-- ========================================================= --}}

    @if (! $this->selectedEmployee)

        <div class="mt-6 rounded-xl border border-gray-200 bg-white p-12 text-center shadow-sm">

            <div class="mx-auto flex h-12 w-12 items-center justify-center rounded-full bg-gray-100">

                <svg
                    class="h-6 w-6 text-gray-400"
                    fill="none"
                    stroke="currentColor"
                    viewBox="0 0 24 24"
                >
                    <path
                        stroke-linecap="round"
                        stroke-linejoin="round"
                        stroke-width="2"
                        d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"
                    />
                </svg>

            </div>

            <h3 class="mt-4 text-sm font-semibold text-gray-900">
                Select an Employee
            </h3>

            <p class="mt-1 text-sm text-gray-500">
                Select an employee above to view attendance records.
            </p>

        </div>

    @else

        {{-- ========================================================= --}}
        {{-- EMPLOYEE HEADER --}}
        {{-- ========================================================= --}}

        <div class="mt-6 overflow-hidden rounded-xl border border-gray-200 bg-white shadow-sm">

            <div class="border-b border-gray-200 px-6 py-5">

                <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">

                    <div>

                        <h2 class="text-lg font-semibold text-gray-900">
                            {{ $this->selectedEmployee->full_name }}
                        </h2>

                        <p class="mt-1 text-sm text-gray-500">
                            Employee ID:
                            {{ $this->selectedEmployee->employee_id }}
                        </p>

                    </div>

                    <div class="sm:text-right">

                        <p class="text-sm font-semibold text-gray-900">
                            {{ \Carbon\Carbon::createFromFormat(
                                'Y-m',
                                $this->data['month'] ?? now()->format('Y-m')
                            )->format('F Y') }}
                        </p>

                        <p class="mt-1 text-sm text-gray-500">

                            @if (($this->data['period'] ?? '1-15') === '1-15')
                                1–15
                            @else
                                16–End of Month
                            @endif

                        </p>

                    </div>

                </div>

            </div>


            {{-- ===================================================== --}}
            {{-- SUMMARY --}}
            {{-- ===================================================== --}}

            <div class="grid grid-cols-2 divide-x divide-y border-b border-gray-200 sm:grid-cols-4 sm:divide-y-0">

                {{-- Worked --}}
                <div class="px-5 py-4">

                    <p class="text-xs font-medium uppercase tracking-wide text-gray-400">
                        Worked
                    </p>

                    <p class="mt-1 text-xl font-semibold text-gray-900">
                        {{ number_format($this->totalWorkedMinutes / 60, 2) }}
                    </p>

                    <p class="text-xs text-gray-400">
                        hours
                    </p>

                </div>


                {{-- Late --}}
                <div class="px-5 py-4">

                    <p class="text-xs font-medium uppercase tracking-wide text-gray-400">
                        Late
                    </p>

                    <p class="mt-1 text-xl font-semibold text-gray-900">
                        {{ $this->totalLateMinutes }}
                    </p>

                    <p class="text-xs text-gray-400">
                        minutes
                    </p>

                </div>


                {{-- Undertime --}}
                <div class="px-5 py-4">

                    <p class="text-xs font-medium uppercase tracking-wide text-gray-400">
                        Undertime
                    </p>

                    <p class="mt-1 text-xl font-semibold text-gray-900">
                        {{ $this->totalUndertimeMinutes }}
                    </p>

                    <p class="text-xs text-gray-400">
                        minutes
                    </p>

                </div>


                {{-- Overtime --}}
                <div class="px-5 py-4">

                    <p class="text-xs font-medium uppercase tracking-wide text-gray-400">
                        Overtime
                    </p>

                    <p class="mt-1 text-xl font-semibold text-gray-900">
                        {{ $this->totalOvertimeMinutes }}
                    </p>

                    <p class="text-xs text-gray-400">
                        minutes
                    </p>

                </div>

            </div>


            {{-- ===================================================== --}}
            {{-- TABLE --}}
            {{-- ===================================================== --}}

            <div class="overflow-x-auto">

                <table class="min-w-full">

                    <thead class="bg-gray-50">

                        <tr>

                            <th class="px-5 py-3 text-left text-xs font-semibold uppercase tracking-wide text-gray-500">
                                Date
                            </th>

                            <th class="px-5 py-3 text-left text-xs font-semibold uppercase tracking-wide text-gray-500">
                                Day
                            </th>

                            <th class="px-5 py-3 text-left text-xs font-semibold uppercase tracking-wide text-gray-500">
                                Time In
                            </th>

                            <th class="px-5 py-3 text-left text-xs font-semibold uppercase tracking-wide text-gray-500">
                                Time Out
                            </th>

                            <th class="px-5 py-3 text-left text-xs font-semibold uppercase tracking-wide text-gray-500">
                                Hours
                            </th>

                            <th class="px-5 py-3 text-left text-xs font-semibold uppercase tracking-wide text-gray-500">
                                Late
                            </th>

                            <th class="px-5 py-3 text-left text-xs font-semibold uppercase tracking-wide text-gray-500">
                                Undertime
                            </th>

                            <th class="px-5 py-3 text-left text-xs font-semibold uppercase tracking-wide text-gray-500">
                                Overtime
                            </th>

                            <th class="px-5 py-3 text-left text-xs font-semibold uppercase tracking-wide text-gray-500">
                                Status
                            </th>

                        </tr>

                    </thead>


                    <tbody class="divide-y divide-gray-100">

                        @forelse ($this->attendanceRecords as $attendance)

                            <tr class="transition hover:bg-gray-50">

                                {{-- Date --}}
                                <td class="whitespace-nowrap px-5 py-4 text-sm text-gray-700">
                                    {{ $attendance->attendance_date->format('M d, Y') }}
                                </td>


                                {{-- Day --}}
                                <td class="whitespace-nowrap px-5 py-4 text-sm text-gray-500">
                                    {{ $attendance->attendance_date->format('D') }}
                                </td>


                                {{-- Time In --}}
                                <td class="whitespace-nowrap px-5 py-4 text-sm text-gray-700">

                                    @if ($attendance->time_in)

                                        {{ \Carbon\Carbon::parse(
                                            $attendance->time_in
                                        )->format('h:i A') }}

                                    @else

                                        <span class="text-gray-400">
                                            —
                                        </span>

                                    @endif

                                </td>


                                {{-- Time Out --}}
                                <td class="whitespace-nowrap px-5 py-4 text-sm text-gray-700">

                                    @if ($attendance->time_out)

                                        {{ \Carbon\Carbon::parse(
                                            $attendance->time_out
                                        )->format('h:i A') }}

                                    @else

                                        <span class="text-gray-400">
                                            —
                                        </span>

                                    @endif

                                </td>


                                {{-- Hours --}}
                                <td class="whitespace-nowrap px-5 py-4 text-sm font-medium text-gray-900">
                                    {{ number_format(
                                        $attendance->worked_minutes / 60,
                                        2
                                    ) }}
                                </td>


                                {{-- Late --}}
                                <td class="whitespace-nowrap px-5 py-4 text-sm text-gray-700">
                                    {{ $attendance->late_minutes }}
                                    <span class="text-xs text-gray-400">
                                        min
                                    </span>
                                </td>


                                {{-- Undertime --}}
                                <td class="whitespace-nowrap px-5 py-4 text-sm text-gray-700">
                                    {{ $attendance->undertime_minutes }}
                                    <span class="text-xs text-gray-400">
                                        min
                                    </span>
                                </td>


                                {{-- Overtime --}}
                                <td class="whitespace-nowrap px-5 py-4 text-sm text-gray-700">
                                    {{ $attendance->overtime_minutes }}
                                    <span class="text-xs text-gray-400">
                                        min
                                    </span>
                                </td>


                                {{-- Status --}}
                                <td class="whitespace-nowrap px-5 py-4">

                                    @switch($attendance->status)

                                        @case('present')

                                            <span class="inline-flex rounded-full bg-green-50 px-3 py-1 text-xs font-medium text-green-700">
                                                Present
                                            </span>

                                            @break


                                        @case('rest_day')

                                            <span class="inline-flex rounded-full bg-purple-50 px-3 py-1 text-xs font-medium text-purple-700">
                                                Rest Day
                                            </span>

                                            @break


                                        @case('absent')

                                            <span class="inline-flex rounded-full bg-red-50 px-3 py-1 text-xs font-medium text-red-700">
                                                Absent
                                            </span>

                                            @break


                                        @default

                                            <span class="inline-flex rounded-full bg-gray-100 px-3 py-1 text-xs font-medium text-gray-600">
                                                {{ ucfirst($attendance->status) }}
                                            </span>

                                    @endswitch

                                </td>

                            </tr>

                        @empty

                            <tr>

                                <td
                                    colspan="9"
                                    class="px-5 py-16 text-center"
                                >

                                    <p class="text-sm font-medium text-gray-700">
                                        No attendance records found
                                    </p>

                                    <p class="mt-1 text-sm text-gray-400">
                                        There are no attendance records for this employee and period.
                                    </p>

                                </td>

                            </tr>

                        @endforelse

                    </tbody>

                </table>

            </div>

        </div>

    @endif

</x-filament-panels::page>
