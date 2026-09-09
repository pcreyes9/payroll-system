<x-filament-panels::page>

    {{-- ================================================================
         FILTER
         ================================================================ --}}

         <button
            type="button"
            wire:click="openAddAttendanceModal"
            class="inline-flex items-center gap-2 rounded-lg bg-primary-600 px-4 py-2 text-sm font-semibold text-white hover:bg-primary-700"
        >
            <x-heroicon-o-plus class="h-5 w-5" />
            Add Attendance
        </button>

    <div class="mb-6">
        {{ $this->form }}
        
    </div>


    {{-- ================================================================
         NO EMPLOYEE SELECTED
         ================================================================ --}}

    @if (! $this->selectedEmployee)

        <div class="flex min-h-[500px] items-center justify-center rounded-2xl border border-gray-200 bg-white dark:border-gray-700 dark:bg-gray-900">

            <div class="text-center">

                <div class="mb-4 text-5xl">
                    📅
                </div>

                <h2 class="text-xl font-semibold text-gray-900 dark:text-white">
                    No Employee Selected
                </h2>

                <p class="mt-2 text-sm text-gray-500 dark:text-gray-400">
                    Select an employee to view attendance records.
                </p>

            </div>

        </div>

    @else

        {{-- ============================================================
             EMPLOYEE HEADER
             ============================================================ --}}

        <div class="overflow-hidden rounded-2xl border border-gray-200 bg-white shadow-sm dark:border-gray-700 dark:bg-gray-900">

            {{-- HEADER --}}

            <div class="flex flex-col justify-between gap-4 border-b border-gray-200 px-6 py-5 md:flex-row md:items-center dark:border-gray-700">

                <div>

                    <h2 class="text-xl font-semibold text-gray-900 dark:text-white">
                        {{ $this->selectedEmployee->full_name }}
                    </h2>

                    <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">
                        Employee ID:
                        {{ $this->selectedEmployee->employee_id }}
                    </p>

                </div>


                <div class="text-left md:text-right">

                    <div class="text-base font-semibold text-gray-900 dark:text-white">
                        {{ Carbon\Carbon::parse($this->data['month'])->format('F Y') }}
                    </div>

                    <div class="mt-1 text-sm text-gray-500 dark:text-gray-400">

                        @if (($this->data['period'] ?? '1-15') === '1-15')
                            1–15
                        @else
                            16–End
                        @endif

                    </div>

                </div>

            </div>


            {{-- ========================================================
                 SUMMARY
                 ======================================================== --}}

            <div class="grid grid-cols-2 divide-x divide-y divide-gray-200 border-b border-gray-200 md:grid-cols-4 md:divide-y-0 dark:divide-gray-700 dark:border-gray-700">

                {{-- WORKED --}}

                <div class="px-6 py-5">

                    <div class="text-xs font-medium uppercase tracking-wide text-gray-400">
                        Worked
                    </div>

                    <div class="mt-1 text-2xl font-semibold text-gray-900 dark:text-white">
                        {{ number_format($this->totalWorkedMinutes / 60, 2) }}
                    </div>

                    <div class="text-xs text-gray-400">
                        hours
                    </div>

                </div>


                {{-- LATE --}}

                <div class="px-6 py-5">

                    <div class="text-xs font-medium uppercase tracking-wide text-gray-400">
                        Late
                    </div>

                    <div class="mt-1 text-2xl font-semibold text-gray-900 dark:text-white">
                        {{ $this->totalLateMinutes }}
                    </div>

                    <div class="text-xs text-gray-400">
                        minutes
                    </div>

                </div>


                {{-- UNDERTIME --}}

                <div class="px-6 py-5">

                    <div class="text-xs font-medium uppercase tracking-wide text-gray-400">
                        Undertime
                    </div>

                    <div class="mt-1 text-2xl font-semibold text-gray-900 dark:text-white">
                        {{ $this->totalUndertimeMinutes }}
                    </div>

                    <div class="text-xs text-gray-400">
                        minutes
                    </div>

                </div>


                {{-- APPROVED OT --}}

                <div class="px-6 py-5">

                    <div class="text-xs font-medium uppercase tracking-wide text-gray-400">
                        Approved OT
                    </div>

                    <div class="mt-1 text-2xl font-semibold text-gray-900 dark:text-white">
                        {{ $this->totalOvertimeMinutes }}
                    </div>

                    <div class="text-xs text-gray-400">
                        minutes
                    </div>

                </div>

            </div>


            {{-- ========================================================
                 ATTENDANCE TABLE
                 ======================================================== --}}

            <div class="overflow-x-auto">

                <table class="w-full min-w-[1500px] text-sm">

                    <thead class="border-b border-gray-200 bg-gray-50 dark:border-gray-700 dark:bg-gray-800">

                        <tr>

                            <th class="sticky left-0 z-20 w-[160px] whitespace-nowrap bg-gray-50 px-5 py-4 text-left text-xs font-semibold uppercase tracking-wide text-gray-500 dark:bg-gray-800 dark:text-gray-400">
                                Date
                            </th>

                            <th class="sticky left-[160px] z-20 w-[110px] whitespace-nowrap bg-gray-50 px-5 py-4 text-left text-xs font-semibold uppercase tracking-wide text-gray-500 dark:bg-gray-800 dark:text-gray-400">
                                Time In
                            </th>

                            <th class="sticky left-[270px] z-20 w-[110px] whitespace-nowrap bg-gray-50 px-5 py-4 text-left text-xs font-semibold uppercase tracking-wide text-gray-500 dark:bg-gray-800 dark:text-gray-400">
                                Time Out
                            </th>

                            <th class="sticky left-[380px] z-20 w-[100px] whitespace-nowrap bg-gray-50 px-5 py-4 text-right text-xs font-semibold uppercase tracking-wide text-gray-500 shadow-[4px_0_6px_-2px_rgba(0,0,0,0.08)] dark:bg-gray-800 dark:text-gray-400">
                                Hours
                            </th>

                            {{-- <th class="px-5 py-4 text-right text-xs font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">
                                Late
                            </th> --}}

                            {{-- <th class="px-5 py-4 text-right text-xs font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">
                                Undertime
                            </th> --}}

                            <th class="px-5 py-4 text-right text-xs font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">
                                Detected OT
                            </th>
{{--
                            <th class="px-5 py-4 text-right text-xs font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">
                                Approved OT
                            </th> --}}

                            <th class="px-5 py-4 text-right text-xs font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">
                                NSD
                            </th>

                            <th class="px-5 py-4 text-center text-xs font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">
                                OT Status
                            </th>

                            <th class="px-5 py-4 text-center text-xs font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">
                                Status
                            </th>

                            <th class="px-5 py-4 text-center text-xs font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">
                                Action
                            </th>

                        </tr>

                    </thead>


                    <tbody class="divide-y divide-gray-100 dark:divide-gray-800">

                        @forelse ($this->attendanceRecords as $attendance)

                            <tr class="transition hover:bg-gray-50 dark:hover:bg-gray-800/50">

                                {{-- DATE --}}

                                <td class="sticky left-0 z-10 w-[160px] whitespace-nowrap bg-white px-5 py-4 dark:bg-gray-900">

                                    <div class="font-medium text-gray-900 dark:text-white">
                                        {{ $attendance->attendance_date?->format('M d, Y') }}
                                    </div>

                                    <div class="text-xs uppercase tracking-wide text-gray-400">
                                        {{ $attendance->attendance_date?->format('l') }}
                                    </div>

                                </td>


                                {{-- TIME IN --}}

                                <td class="sticky left-[160px] z-10 w-[110px] whitespace-nowrap bg-white px-5 py-4 text-gray-600 dark:bg-gray-900 dark:text-gray-300">

                                    {{ $attendance->time_in?->format('h:i A') ?? '—' }}

                                </td>


                                {{-- TIME OUT --}}

                                <td class="sticky left-[270px] z-10 w-[110px] whitespace-nowrap bg-white px-5 py-4 text-gray-600 dark:bg-gray-900 dark:text-gray-300">

                                    {{ $attendance->time_out?->format('h:i A') ?? '—' }}

                                </td>


                                {{-- HOURS --}}

                                <td class="sticky left-[380px] z-10 w-[100px] whitespace-nowrap bg-white px-5 py-4 text-right font-semibold text-gray-700 shadow-[4px_0_6px_-2px_rgba(0,0,0,0.08)] dark:bg-gray-900 dark:text-gray-200">

                                    {{ number_format($attendance->worked_minutes / 60, 2) }}

                                </td>


                                {{-- LATE --}}

                                {{-- <td class="whitespace-nowrap px-5 py-4 text-right">

                                    @if ($attendance->late_minutes > 0)

                                        <span class="font-medium text-red-600 dark:text-red-400">
                                            {{ $attendance->late_minutes }}
                                            min
                                        </span>

                                    @else

                                        <span class="text-gray-400">
                                            —
                                        </span>

                                    @endif

                                </td> --}}


                                {{-- UNDERTIME --}}

                                {{-- <td class="whitespace-nowrap px-5 py-4 text-right">

                                    @if ($attendance->undertime_minutes > 0)

                                        <span class="font-medium text-orange-600 dark:text-orange-400">
                                            {{ $attendance->undertime_minutes }}
                                            min
                                        </span>

                                    @else

                                        <span class="text-gray-400">
                                            —
                                        </span>

                                    @endif

                                </td> --}}


                                {{-- DETECTED OT --}}

                                <td class="whitespace-nowrap px-5 py-4 text-right font-medium">

                                    @if ($attendance->detected_overtime_minutes > 0)

                                        <span class="text-orange-600 dark:text-orange-400">
                                            {{ $this->formatMinutes($attendance->detected_overtime_minutes) }}
                                        </span>

                                    @else

                                        <span class="text-gray-400">
                                            —
                                        </span>

                                    @endif

                                </td>


                                {{-- APPROVED OT --}}

                                {{-- <td class="whitespace-nowrap px-5 py-4 text-right">

                                    @if ($attendance->approved_overtime_minutes > 0)

                                        <span class="font-semibold text-green-600 dark:text-green-400">
                                            {{ $this->formatMinutes($attendance->approved_overtime_minutes) }}
                                        </span>

                                    @else

                                        <span class="text-gray-400">
                                            —
                                        </span>

                                    @endif

                                </td> --}}


                                {{-- NSD --}}

                                <td class="whitespace-nowrap px-5 py-4 text-right">

                                    @if ($attendance->night_shift_minutes > 0)

                                        {{ $this->formatMinutes($attendance->night_shift_minutes) }}

                                    @else

                                        <span class="text-gray-400">
                                            —
                                        </span>

                                    @endif

                                </td>


                                {{-- OT STATUS --}}

                                <td class="whitespace-nowrap px-5 py-4 text-center">

                                    @if ($attendance->overtime_status === 'approved')

                                        <span class="inline-flex items-center rounded-full bg-green-50 px-3 py-1 text-xs font-medium text-green-700 dark:bg-green-500/10 dark:text-green-400">
                                            Approved
                                        </span>

                                    @elseif ($attendance->overtime_status === 'pending')

                                        <span class="inline-flex items-center rounded-full bg-yellow-50 px-3 py-1 text-xs font-medium text-yellow-700 dark:bg-yellow-500/10 dark:text-yellow-400">
                                            Pending
                                        </span>

                                    @elseif ($attendance->overtime_status === 'rejected')

                                        <span class="inline-flex items-center rounded-full bg-red-50 px-3 py-1 text-xs font-medium text-red-700 dark:bg-red-500/10 dark:text-red-400">
                                            Rejected
                                        </span>

                                    @else

                                        <span class="text-gray-400">
                                            —
                                        </span>

                                    @endif

                                </td>


                                {{-- STATUS --}}

                                <td class="whitespace-nowrap px-5 py-4 text-center">

                                    @switch($attendance->status)

                                        @case('present')

                                            <span class="inline-flex items-center rounded-full bg-green-50 px-3 py-1 text-xs font-medium text-green-700 dark:bg-green-500/10 dark:text-green-400">
                                                Present
                                            </span>

                                            @break


                                        @case('absent')

                                            <span class="inline-flex items-center rounded-full bg-red-50 px-3 py-1 text-xs font-medium text-red-700 dark:bg-red-500/10 dark:text-red-400">
                                                Absent
                                            </span>

                                            @break


                                        @case('vl')

                                            <span class="inline-flex items-center rounded-full bg-blue-50 px-3 py-1 text-xs font-medium text-blue-700 dark:bg-blue-500/10 dark:text-blue-400">
                                                VL
                                            </span>

                                            @break


                                        @case('sl')

                                            <span class="inline-flex items-center rounded-full bg-purple-50 px-3 py-1 text-xs font-medium text-purple-700 dark:bg-purple-500/10 dark:text-purple-400">
                                                SL
                                            </span>

                                            @break


                                        @case('half_day_vl')

                                            <span class="inline-flex items-center rounded-full bg-blue-50 px-3 py-1 text-xs font-medium text-blue-700 dark:bg-blue-500/10 dark:text-blue-400">
                                                Half Day VL
                                            </span>

                                            @break


                                        @case('half_day_sl')

                                            <span class="inline-flex items-center rounded-full bg-purple-50 px-3 py-1 text-xs font-medium text-purple-700 dark:bg-purple-500/10 dark:text-purple-400">
                                                Half Day SL
                                            </span>

                                            @break


                                        @case('half_day')

                                            <span class="inline-flex items-center rounded-full bg-yellow-50 px-3 py-1 text-xs font-medium text-yellow-700 dark:bg-yellow-500/10 dark:text-yellow-400">
                                                Half Day
                                            </span>

                                            @break


                                        @case('rest_day')

                                            <span class="inline-flex items-center rounded-full bg-indigo-50 px-3 py-1 text-xs font-medium text-indigo-700 dark:bg-indigo-500/10 dark:text-indigo-400">
                                                Rest Day
                                            </span>

                                            @break


                                        @case('regular_holiday')

                                            <span class="inline-flex items-center rounded-full bg-pink-50 px-3 py-1 text-xs font-medium text-pink-700 dark:bg-pink-500/10 dark:text-pink-400">
                                                Regular Holiday
                                            </span>

                                            @break


                                        @case('special_non_working_holiday')

                                            <span class="inline-flex items-center rounded-full bg-orange-50 px-3 py-1 text-xs font-medium text-orange-700 dark:bg-orange-500/10 dark:text-orange-400">
                                                Special Holiday
                                            </span>

                                            @break


                                        @case('emergency_leave')

                                            <span class="inline-flex items-center rounded-full bg-cyan-50 px-3 py-1 text-xs font-medium text-cyan-700 dark:bg-cyan-500/10 dark:text-cyan-400">
                                                Emergency Leave
                                            </span>

                                            @break


                                        @case('lwop')

                                            <span class="inline-flex items-center rounded-full bg-gray-100 px-3 py-1 text-xs font-medium text-gray-600 dark:bg-gray-700 dark:text-gray-300">
                                                LWOP
                                            </span>

                                            @break


                                        @default

                                            <span class="text-gray-500">
                                                {{ ucfirst(str_replace('_', ' ', $attendance->status ?? '—')) }}
                                            </span>

                                    @endswitch

                                </td>


                                {{-- ACTION --}}

                                <td class="whitespace-nowrap px-5 py-4 text-center">

                                    @if ($attendance->detected_overtime_minutes > 0)

                                        <button
                                            type="button"
                                            wire:click="openAttendanceModal({{ $attendance->id }})"
                                            class="inline-flex items-center rounded-lg bg-gray-900 px-4 py-2 text-xs font-medium text-white transition hover:bg-gray-800 dark:bg-white dark:text-gray-900 dark:hover:bg-gray-100"
                                        >
                                            Review
                                        </button>

                                    @else

                                        <button
                                            type="button"
                                            wire:click="openAttendanceModal({{ $attendance->id }})"
                                            class="inline-flex items-center rounded-lg bg-blue-50 px-4 py-2 text-xs font-medium text-blue-700 transition hover:bg-blue-100 dark:bg-blue-500/10 dark:text-blue-400"
                                        >
                                            Edit
                                        </button>

                                    @endif

                                </td>

                            </tr>

                        @empty

                            <tr>

                                <td
                                    colspan="12"
                                    class="px-5 py-16 text-center"
                                >

                                    <p class="text-sm font-medium text-gray-700 dark:text-gray-200">
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


    {{-- ================================================================
         COMBINED EDIT / OT REVIEW MODAL
         ================================================================ --}}

    @if ($showAttendanceModal)

        @teleport('body')

            <div
                class="fixed inset-0 z-[99999]"
                wire:keydown.escape="closeAttendanceModal"
            >

                {{-- BACKDROP --}}

                <div
                    class="absolute inset-0 bg-black/60 backdrop-blur-sm"
                    wire:click="closeAttendanceModal"
                ></div>


                {{-- CENTER --}}

                <div class="relative flex min-h-screen items-center justify-center p-4">

                    {{-- MODAL --}}

                    <div
                        class="relative w-full max-w-2xl overflow-hidden rounded-2xl bg-white shadow-2xl dark:bg-gray-900"
                        wire:click.stop
                    >

                        @if ($this->selectedAttendance)

                            {{-- =================================================
                                 HEADER
                                 ================================================= --}}

                            <div class="flex items-center justify-between border-b border-gray-200 px-6 py-5 dark:border-gray-700">

                                <div>

                                    <h2 class="text-lg font-semibold text-gray-900 dark:text-white">
                                        Edit Attendance
                                    </h2>

                                    <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">
                                        {{ $this->selectedAttendance->attendance_date?->format('F d, Y') }}
                                    </p>

                                </div>


                                <button
                                    type="button"
                                    wire:click="closeAttendanceModal"
                                    class="rounded-lg p-2 text-gray-400 transition hover:bg-gray-100 hover:text-gray-700 dark:hover:bg-gray-800 dark:hover:text-gray-200"
                                >
                                    <svg
                                        class="h-5 w-5"
                                        fill="none"
                                        stroke="currentColor"
                                        viewBox="0 0 24 24"
                                    >
                                        <path
                                            stroke-linecap="round"
                                            stroke-linejoin="round"
                                            stroke-width="2"
                                            d="M6 18L18 6M6 6l12 12"
                                        />
                                    </svg>
                                </button>

                            </div>


                            {{-- =================================================
                                 FORM BODY
                                 ================================================= --}}

                            <div class="max-h-[70vh] space-y-6 overflow-y-auto px-6 py-6">

                                {{-- DATE --}}

                                <div>

                                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300">
                                        Attendance Date
                                    </label>

                                    <input
                                        type="date"
                                        wire:model="editAttendanceData.attendance_date"
                                        class="mt-1 block w-full rounded-xl border-gray-300 bg-white px-3 py-2.5 text-sm shadow-sm focus:border-primary-500 focus:ring-primary-500 dark:border-gray-600 dark:bg-gray-800 dark:text-white"
                                    >

                                    @error('editAttendanceData.attendance_date')
                                        <p class="mt-1 text-xs text-red-600">
                                            {{ $message }}
                                        </p>
                                    @enderror

                                </div>


                                {{-- TIME IN / TIME OUT --}}

                                <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">

                                    <div>

                                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300">
                                            Time In
                                        </label>

                                        <input
                                            type="time"
                                            wire:model="editAttendanceData.time_in"
                                            class="mt-1 block w-full rounded-xl border-gray-300 bg-white px-3 py-2.5 text-sm shadow-sm focus:border-primary-500 focus:ring-primary-500 dark:border-gray-600 dark:bg-gray-800 dark:text-white"
                                        >

                                        @error('editAttendanceData.time_in')
                                            <p class="mt-1 text-xs text-red-600">
                                                {{ $message }}
                                            </p>
                                        @enderror

                                    </div>


                                    <div>

                                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300">
                                            Time Out
                                        </label>

                                        <input
                                            type="time"
                                            wire:model="editAttendanceData.time_out"
                                            class="mt-1 block w-full rounded-xl border-gray-300 bg-white px-3 py-2.5 text-sm shadow-sm focus:border-primary-500 focus:ring-primary-500 dark:border-gray-600 dark:bg-gray-800 dark:text-white"
                                        >

                                        @error('editAttendanceData.time_out')
                                            <p class="mt-1 text-xs text-red-600">
                                                {{ $message }}
                                            </p>
                                        @enderror

                                    </div>

                                </div>


                                {{-- STATUS --}}

                                <div>

                                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300">
                                        Status
                                    </label>

                                    <select
                                        wire:model.live="editAttendanceData.status"
                                        class="mt-1 block w-full rounded-xl border-gray-300 bg-white px-3 py-2.5 text-sm shadow-sm focus:border-primary-500 focus:ring-primary-500 dark:border-gray-600 dark:bg-gray-800 dark:text-white"
                                    >

                                        <option value="present">
                                            Present
                                        </option>

                                        <option value="absent">
                                            Absent
                                        </option>

                                        <option value="vl">
                                            VL – Vacation Leave
                                        </option>

                                        <option value="sl">
                                            SL – Sick Leave
                                        </option>

                                        <option value="half_day_vl">
                                            Half Day VL – 0.5 Credit
                                        </option>

                                        <option value="half_day_sl">
                                            Half Day SL – 0.5 Credit
                                        </option>

                                        <option value="half_day">
                                            Half Day
                                        </option>

                                        <option value="rest_day">
                                            Rest Day
                                        </option>

                                        <option value="regular_holiday">
                                            Regular Holiday
                                        </option>

                                        <option value="special_non_working_holiday">
                                            Special Non-Working Holiday
                                        </option>

                                        <option value="emergency_leave">
                                            Emergency Leave
                                        </option>

                                        <option value="lwop">
                                            LWOP – Leave Without Pay
                                        </option>

                                    </select>

                                    @error('editAttendanceData.status')
                                        <p class="mt-1 text-xs text-red-600">
                                            {{ $message }}
                                        </p>
                                    @enderror

                                </div>


                                {{-- =================================================
                                     OT REVIEW
                                     ================================================= --}}

                                @if (
                                    $this->selectedAttendance->detected_overtime_minutes > 0
                                )

                                    <div class="rounded-xl border border-gray-200 bg-gray-50 p-5 dark:border-gray-700 dark:bg-gray-800/50">

                                        <div class="mb-4">

                                            <h3 class="text-sm font-semibold text-gray-900 dark:text-white">
                                                Overtime Review
                                            </h3>

                                            <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">
                                                Review and approve the overtime for this attendance record.
                                            </p>

                                        </div>


                                        {{-- OT INFORMATION --}}

                                        <div class="grid grid-cols-2 gap-4">

                                            {{-- DETECTED OT --}}

                                            <div class="rounded-xl border border-gray-200 bg-white p-4 dark:border-gray-700 dark:bg-gray-900">

                                                <div class="text-xs uppercase tracking-wide text-gray-400">
                                                    Detected OT
                                                </div>

                                                <div class="mt-1 text-lg font-semibold text-gray-900 dark:text-white">
                                                    {{ $this->formatMinutes(
                                                        $this->selectedAttendance->detected_overtime_minutes
                                                    ) }}
                                                </div>

                                            </div>


                                            {{-- NSD --}}

                                            <div class="rounded-xl border border-gray-200 bg-white p-4 dark:border-gray-700 dark:bg-gray-900">

                                                <div class="text-xs uppercase tracking-wide text-gray-400">
                                                    NSD
                                                </div>

                                                <div class="mt-1 text-lg font-semibold text-gray-900 dark:text-white">
                                                    {{ $this->formatMinutes(
                                                        $this->selectedAttendance->night_shift_minutes
                                                    ) }}
                                                </div>

                                            </div>

                                        </div>


                                        {{-- APPROVED OT --}}

                                        <div class="mt-5">

                                            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300">
                                                Approved Overtime
                                            </label>

                                            <div class="mt-1 flex items-center gap-3">

                                                <input
                                                    type="number"
                                                    min="0"
                                                    max="{{ $this->selectedAttendance->detected_overtime_minutes }}"
                                                    wire:model="overtimeApprovalData.approved_overtime_minutes"
                                                    class="block w-full rounded-xl border-gray-300 bg-white px-3 py-2.5 text-sm shadow-sm focus:border-primary-500 focus:ring-primary-500 dark:border-gray-600 dark:bg-gray-800 dark:text-white"
                                                >

                                                <span class="text-sm text-gray-500 dark:text-gray-400">
                                                    minutes
                                                </span>

                                            </div>

                                            <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">

                                                Maximum:
                                                {{ $this->formatMinutes(
                                                    $this->selectedAttendance->detected_overtime_minutes
                                                ) }}

                                            </p>

                                        </div>


                                        {{-- OT STATUS --}}

                                        <div class="mt-4 flex items-center gap-2 text-sm">

                                            <span class="text-gray-500 dark:text-gray-400">
                                                OT Status:
                                            </span>

                                            @if ($this->selectedAttendance->overtime_status === 'approved')

                                                <span class="font-medium text-green-600 dark:text-green-400">
                                                    Approved
                                                </span>

                                            @elseif ($this->selectedAttendance->overtime_status === 'pending')

                                                <span class="font-medium text-yellow-600 dark:text-yellow-400">
                                                    Pending
                                                </span>

                                            @elseif ($this->selectedAttendance->overtime_status === 'rejected')

                                                <span class="font-medium text-red-600 dark:text-red-400">
                                                    Rejected
                                                </span>

                                            @else

                                                <span class="text-gray-400">
                                                    —
                                                </span>

                                            @endif

                                        </div>


                                        {{-- OT REMARKS --}}

                                        <div class="mt-5">

                                            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300">
                                                OT Remarks
                                            </label>

                                            <textarea
                                                rows="2"
                                                wire:model="overtimeApprovalData.overtime_remarks"
                                                placeholder="Optional overtime remarks..."
                                                class="mt-1 block w-full rounded-xl border-gray-300 bg-white px-3 py-2.5 text-sm shadow-sm focus:border-primary-500 focus:ring-primary-500 dark:border-gray-600 dark:bg-gray-800 dark:text-white"
                                            ></textarea>

                                        </div>

                                    </div>

                                @endif


                                {{-- REMARKS --}}

                                <div>

                                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300">
                                        Remarks
                                    </label>

                                    <textarea
                                        rows="3"
                                        wire:model="editAttendanceData.remarks"
                                        placeholder="Optional remarks..."
                                        class="mt-1 block w-full rounded-xl border-gray-300 bg-white px-3 py-2.5 text-sm shadow-sm focus:border-primary-500 focus:ring-primary-500 dark:border-gray-600 dark:bg-gray-800 dark:text-white"
                                    ></textarea>

                                    @error('editAttendanceData.remarks')
                                        <p class="mt-1 text-xs text-red-600">
                                            {{ $message }}
                                        </p>
                                    @enderror

                                </div>

                            </div>


                            {{-- =================================================
                                 FOOTER
                                 ================================================= --}}

                            <div class="flex flex-wrap items-center justify-between gap-3 border-t border-gray-200 bg-gray-50 px-6 py-4 dark:border-gray-700 dark:bg-gray-800/50">

                                <div>

                                    @if (
                                        $this->selectedAttendance->detected_overtime_minutes > 0
                                        && $this->selectedAttendance->overtime_status !== 'rejected'
                                    )

                                        <button
                                            type="button"
                                            wire:click="rejectOvertime"
                                            wire:loading.attr="disabled"
                                            class="rounded-xl border border-red-300 px-4 py-2.5 text-sm font-medium text-red-600 transition hover:bg-red-50 disabled:opacity-50 dark:border-red-700 dark:text-red-400 dark:hover:bg-red-500/10"
                                        >
                                            Reject OT
                                        </button>

                                    @endif

                                </div>


                                <div class="flex flex-wrap gap-2">

                                    <button
                                        type="button"
                                        wire:click="closeAttendanceModal"
                                        class="rounded-xl border border-gray-300 bg-white px-4 py-2.5 text-sm font-medium text-gray-700 transition hover:bg-gray-50 dark:border-gray-600 dark:bg-gray-900 dark:text-gray-300 dark:hover:bg-gray-800"
                                    >
                                        Cancel
                                    </button>


                                    {{-- SAVE --}}

                                    <button
                                        type="button"
                                        wire:click="saveAttendance"
                                        wire:loading.attr="disabled"
                                        class="rounded-xl bg-blue-600 px-4 py-2.5 text-sm font-medium text-white transition hover:bg-blue-700 disabled:opacity-50"
                                    >
                                        <span wire:loading.remove wire:target="saveAttendance">
                                            Save Changes
                                        </span>

                                        <span wire:loading wire:target="saveAttendance">
                                            Saving...
                                        </span>

                                    </button>


                                    {{-- APPROVE OT --}}

                                    @if (
                                        $this->selectedAttendance->detected_overtime_minutes > 0
                                        && $this->selectedAttendance->overtime_status !== 'approved'
                                    )

                                        <button
                                            type="button"
                                            wire:click="approveOvertime"
                                            wire:loading.attr="disabled"
                                            class="rounded-xl bg-gray-900 px-4 py-2.5 text-sm font-medium text-white transition hover:bg-gray-800 disabled:opacity-50 dark:bg-white dark:text-gray-900 dark:hover:bg-gray-100"
                                        >
                                            <span wire:loading.remove wire:target="approveOvertime">
                                                Approve OT
                                            </span>

                                            <span wire:loading wire:target="approveOvertime">
                                                Approving...
                                            </span>

                                        </button>

                                    @endif

                                </div>

                            </div>

                        @endif

                    </div>

                </div>

            </div>

        @endteleport

    @endif


    {{-- ================================================================
         ADD ATTENDANCE MODAL
         ================================================================ --}}

    @if ($showAddAttendanceModal)

        @teleport('body')

            <div
                class="fixed inset-0 z-[99999]"
                wire:keydown.escape="closeAddAttendanceModal"
            >

                {{-- BACKDROP --}}

                <div
                    class="absolute inset-0 bg-black/60 backdrop-blur-sm"
                    wire:click="closeAddAttendanceModal"
                ></div>


                {{-- MODAL --}}

                <div class="relative flex min-h-screen items-center justify-center p-4">

                    <div
                        class="relative w-full max-w-2xl overflow-hidden rounded-2xl bg-white shadow-2xl dark:bg-gray-900"
                        wire:click.stop
                    >

                        {{-- HEADER --}}

                        <div class="flex items-center justify-between border-b border-gray-200 px-6 py-5 dark:border-gray-700">

                            <div>

                                <h2 class="text-lg font-semibold text-gray-900 dark:text-white">
                                    Add Attendance
                                </h2>

                                <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">
                                    Add attendance for one or more employees.
                                </p>

                            </div>


                            <button
                                type="button"
                                wire:click="closeAddAttendanceModal"
                                class="rounded-lg p-2 text-gray-400 transition hover:bg-gray-100 hover:text-gray-700 dark:hover:bg-gray-800 dark:hover:text-gray-200"
                            >

                                <x-heroicon-o-x-mark class="h-5 w-5" />

                            </button>

                        </div>


                        {{-- FORM BODY --}}

                        <div class="max-h-[75vh] space-y-6 overflow-y-auto px-6 py-6">

                            {{-- ATTENDANCE DATE --}}

                            <div>

                                <label class="block text-sm font-medium text-gray-700 dark:text-gray-300">
                                    Attendance Date
                                </label>

                                <input
                                    type="date"
                                    wire:model="addAttendanceData.attendance_date"
                                    class="mt-1 block w-full rounded-xl border-gray-300 bg-white px-3 py-2.5 text-sm shadow-sm focus:border-primary-500 focus:ring-primary-500 dark:border-gray-600 dark:bg-gray-800 dark:text-white"
                                >

                                @error('addAttendanceData.attendance_date')
                                    <p class="mt-1 text-xs text-red-600">
                                        {{ $message }}
                                    </p>
                                @enderror

                            </div>


                            {{-- EMPLOYEES --}}

                            <div>

                                <div class="mb-3 flex items-center justify-between">

                                    <div>

                                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300">
                                            Employees
                                        </label>

                                        <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">
                                            Select the employees who should receive this attendance date.
                                        </p>

                                    </div>


                                    {{-- SELECT ALL --}}

                                    <label class="inline-flex cursor-pointer items-center gap-2 text-sm font-medium text-gray-700 dark:text-gray-300">

                                        <input
                                            type="checkbox"
                                            wire:model.live="selectAllEmployees"
                                            wire:change="toggleAllEmployees"
                                            class="h-4 w-4 rounded border-gray-300 text-primary-600 focus:ring-primary-500"
                                        >

                                        Select All

                                    </label>

                                </div>


                                {{-- EMPLOYEE CHECKLIST --}}

                                <div class="max-h-64 overflow-y-auto rounded-xl border border-gray-200 dark:border-gray-700">

                                    @forelse ($this->activeEmployees as $employee)

                                        <label
                                            class="flex cursor-pointer items-center gap-3 border-b border-gray-200 bg-white px-4 py-3 last:border-b-0 transition hover:bg-gray-50 dark:border-gray-700 dark:bg-gray-900 dark:hover:bg-gray-800"
                                        >

                                            <input
                                                type="checkbox"
                                                value="{{ $employee->id }}"
                                                wire:model.live="addAttendanceData.employee_ids"
                                                class="h-4 w-4 rounded border-gray-300 text-primary-600 focus:ring-primary-500"
                                            >


                                            <div class="min-w-0">

                                                <div class="text-sm font-medium text-gray-900 dark:text-white">
                                                    {{ $employee->full_name }}
                                                </div>

                                                <div class="text-xs text-gray-500 dark:text-gray-400">
                                                    {{ $employee->employee_id }}
                                                </div>

                                            </div>

                                        </label>

                                    @empty

                                        <div class="px-4 py-8 text-center text-sm text-gray-500 dark:text-gray-400">
                                            No active employees found.
                                        </div>

                                    @endforelse

                                </div>


                                <div class="mt-2 flex items-center justify-between">

                                    <p class="text-xs text-gray-500 dark:text-gray-400">
                                        {{ count($addAttendanceData['employee_ids'] ?? []) }}
                                        employee(s) selected
                                    </p>

                                    @error('addAttendanceData.employee_ids')
                                        <p class="text-xs text-red-600">
                                            {{ $message }}
                                        </p>
                                    @enderror

                                </div>


                                @error('addAttendanceData.employee_ids.*')
                                    <p class="mt-1 text-xs text-red-600">
                                        {{ $message }}
                                    </p>
                                @enderror

                            </div>


                            {{-- STATUS --}}

                            <div>

                                <label class="block text-sm font-medium text-gray-700 dark:text-gray-300">
                                    Status
                                </label>

                                <select
                                    wire:model.live="addAttendanceData.status"
                                    class="mt-1 block w-full rounded-xl border-gray-300 bg-white px-3 py-2.5 text-sm shadow-sm focus:border-primary-500 focus:ring-primary-500 dark:border-gray-600 dark:bg-gray-800 dark:text-white"
                                >

                                    <option value="present">
                                        Present
                                    </option>

                                    <option value="absent">
                                        Absent
                                    </option>

                                    <option value="vl">
                                        VL – Vacation Leave
                                    </option>

                                    <option value="sl">
                                        SL – Sick Leave
                                    </option>

                                    <option value="half_day_vl">
                                        Half Day VL – 0.5 Credit
                                    </option>

                                    <option value="half_day_sl">
                                        Half Day SL – 0.5 Credit
                                    </option>

                                    <option value="half_day">
                                        Half Day
                                    </option>

                                    <option value="rest_day">
                                        Rest Day
                                    </option>

                                    <option value="regular_holiday">
                                        Regular Holiday
                                    </option>

                                    <option value="special_non_working_holiday">
                                        Special Non-Working Holiday
                                    </option>

                                    <option value="emergency_leave">
                                        Emergency Leave
                                    </option>

                                    <option value="lwop">
                                        LWOP – Leave Without Pay
                                    </option>

                                </select>

                                @error('addAttendanceData.status')
                                    <p class="mt-1 text-xs text-red-600">
                                        {{ $message }}
                                    </p>
                                @enderror

                            </div>


                            {{-- TIME IN / TIME OUT --}}

                            <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">

                                <div>

                                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300">
                                        Time In
                                    </label>

                                    <input
                                        type="time"
                                        wire:model="addAttendanceData.time_in"
                                        class="mt-1 block w-full rounded-xl border-gray-300 bg-white px-3 py-2.5 text-sm shadow-sm focus:border-primary-500 focus:ring-primary-500 dark:border-gray-600 dark:bg-gray-800 dark:text-white"
                                    >

                                    @error('addAttendanceData.time_in')
                                        <p class="mt-1 text-xs text-red-600">
                                            {{ $message }}
                                        </p>
                                    @enderror

                                </div>


                                <div>

                                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300">
                                        Time Out
                                    </label>

                                    <input
                                        type="time"
                                        wire:model="addAttendanceData.time_out"
                                        class="mt-1 block w-full rounded-xl border-gray-300 bg-white px-3 py-2.5 text-sm shadow-sm focus:border-primary-500 focus:ring-primary-500 dark:border-gray-600 dark:bg-gray-800 dark:text-white"
                                    >

                                    @error('addAttendanceData.time_out')
                                        <p class="mt-1 text-xs text-red-600">
                                            {{ $message }}
                                        </p>
                                    @enderror

                                </div>

                            </div>


                            {{-- INFORMATION --}}

                            <div class="rounded-xl border border-blue-200 bg-blue-50 px-4 py-3 dark:border-blue-800 dark:bg-blue-500/10">

                                <p class="text-xs leading-relaxed text-blue-700 dark:text-blue-300">
                                    Attendance will be automatically calculated using your current attendance rules.
                                    Existing attendance for the selected date will be skipped.
                                </p>

                            </div>


                            {{-- REMARKS --}}

                            <div>

                                <label class="block text-sm font-medium text-gray-700 dark:text-gray-300">
                                    Remarks
                                </label>

                                <textarea
                                    rows="3"
                                    wire:model="addAttendanceData.remarks"
                                    placeholder="Optional remarks..."
                                    class="mt-1 block w-full rounded-xl border-gray-300 bg-white px-3 py-2.5 text-sm shadow-sm focus:border-primary-500 focus:ring-primary-500 dark:border-gray-600 dark:bg-gray-800 dark:text-white"
                                ></textarea>

                                @error('addAttendanceData.remarks')
                                    <p class="mt-1 text-xs text-red-600">
                                        {{ $message }}
                                    </p>
                                @enderror

                            </div>

                        </div>


                        {{-- FOOTER --}}

                        <div class="flex items-center justify-end gap-2 border-t border-gray-200 bg-gray-50 px-6 py-4 dark:border-gray-700 dark:bg-gray-800/50">

                            <button
                                type="button"
                                wire:click="closeAddAttendanceModal"
                                class="rounded-xl border border-gray-300 bg-white px-4 py-2.5 text-sm font-medium text-gray-700 transition hover:bg-gray-50 dark:border-gray-600 dark:bg-gray-900 dark:text-gray-300 dark:hover:bg-gray-800"
                            >
                                Cancel
                            </button>


                            <button
                                type="button"
                                wire:click="addAttendance"
                                wire:loading.attr="disabled"
                                class="rounded-xl bg-primary-600 px-4 py-2.5 text-sm font-medium text-white transition hover:bg-primary-700 disabled:opacity-50"
                            >

                                <span
                                    wire:loading.remove
                                    wire:target="addAttendance"
                                >
                                    Add Attendance
                                </span>

                                <span
                                    wire:loading
                                    wire:target="addAttendance"
                                >
                                    Adding...
                                </span>

                            </button>

                        </div>

                    </div>

                </div>

            </div>

        @endteleport

    @endif

</x-filament-panels::page>