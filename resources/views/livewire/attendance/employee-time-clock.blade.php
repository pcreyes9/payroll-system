<div
    class="min-h-screen bg-gray-50 px-4 py-6 sm:px-6 lg:px-8"
    x-data="attendanceClock()"
>
    <div class="mx-auto max-w-7xl">

        {{-- ========================================================= --}}
        {{-- HEADER --}}
        {{-- ========================================================= --}}

        <div class="mb-5 overflow-hidden rounded-2xl bg-gradient-to-r from-blue-600 to-indigo-600 shadow-sm">

            <div class="flex flex-col gap-5 px-6 py-6 sm:px-8 md:flex-row md:items-center md:justify-between">

                {{-- LEFT --}}
                <div>

                    <h1 class="text-2xl font-bold tracking-tight text-white sm:text-3xl">
                        Attendance Monitoring
                    </h1>

                    <div class="mt-1 flex items-center gap-2 text-sm text-blue-100">

                        <svg
                            class="h-4 w-4"
                            fill="none"
                            stroke="currentColor"
                            viewBox="0 0 24 24"
                        >
                            <path
                                stroke-linecap="round"
                                stroke-linejoin="round"
                                stroke-width="2"
                                d="M12 8v4l3 2m6-2a9 9 0 11-18 0 9 9 0 0118 0z"
                            />
                        </svg>

                        <span>
                            PSA Timekeeping System
                        </span>

                    </div>

                </div>


                {{-- RIGHT --}}
                <div class="flex flex-col items-start gap-4 sm:flex-row sm:items-center md:justify-end">

                    {{-- CLOCK --}}
                    <div class="text-left sm:text-right">

                        <div
                            x-text="time"
                            class="text-4xl font-bold tracking-tight text-white sm:text-5xl"
                        ></div>

                        <div
                            x-text="date"
                            class="mt-1 text-sm text-blue-100"
                        ></div>

                    </div>


                    {{-- LOGIN --}}
                    <a
                        href="{{ url('/admin/login') }}"
                        class="inline-flex items-center gap-2 rounded-xl bg-white/15 px-4 py-2.5 text-sm font-semibold text-white ring-1 ring-white/25 backdrop-blur-sm transition hover:bg-white/25"
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
                                d="M15 3h4a2 2 0 012 2v14a2 2 0 01-2 2h-4m-5-4l5-5m0 0l-5-5m5 5H3"
                            />
                        </svg>

                        <span>
                            Login
                        </span>

                    </a>

                </div>

            </div>

        </div>


        {{-- ========================================================= --}}
        {{-- EMPLOYEE CLOCK CONTROLS --}}
        {{-- ========================================================= --}}

        <div class="mb-5 rounded-2xl border border-gray-200 bg-white p-5 shadow-sm">

            <div class="grid gap-5 lg:grid-cols-[1fr_390px]">

                {{-- EMPLOYEE --}}
                <div>

                    <label class="mb-2 block text-sm font-medium text-gray-700">
                        Select Employee
                    </label>

                    <select
                        wire:model.live="employeeId"
                        class="block w-full rounded-xl border-gray-300 bg-white px-4 py-3 text-sm text-gray-900 shadow-sm outline-none transition focus:border-blue-500 focus:ring-2 focus:ring-blue-500/20"
                    >

                        <option value="">
                            Select Employee
                        </option>

                        @foreach ($employees as $employee)

                            <option value="{{ $employee->id }}">

                                {{ $employee->full_name }}
                                —
                                {{ $employee->employee_id }}

                            </option>

                        @endforeach

                    </select>

                    @error('employeeId')

                        <p class="mt-1 text-sm text-red-600">
                            {{ $message }}
                        </p>

                    @enderror

                </div>


                {{-- ACTIONS --}}
                <div class="flex gap-3">

                    {{-- TIME IN --}}
                    <button
                        type="button"
                        wire:click="timeIn"
                        wire:loading.attr="disabled"
                        wire:target="timeIn"
                        @disabled($todayAttendance?->time_in)
                        class="flex-1 rounded-xl px-5 py-3 text-sm font-semibold transition
                            {{ $todayAttendance?->time_in
                                ? 'cursor-not-allowed bg-gray-100 text-gray-400'
                                : 'bg-green-600 text-white hover:bg-green-700' }}"
                    >

                        <span
                            wire:loading.remove
                            wire:target="timeIn"
                        >
                            {{ $todayAttendance?->time_in
                                ? 'Timed In'
                                : 'Time In'
                            }}
                        </span>

                        <span
                            wire:loading
                            wire:target="timeIn"
                        >
                            Recording...
                        </span>

                    </button>


                    {{-- TIME OUT --}}
                    <button
                        type="button"
                        wire:click="timeOut"
                        wire:loading.attr="disabled"
                        wire:target="timeOut"
                        @disabled(
                            ! $todayAttendance?->time_in
                            || $todayAttendance?->time_out
                            || $showOvertimeRemarksModal
                        )
                        class="flex-1 rounded-xl px-5 py-3 text-sm font-semibold transition
                            {{ (! $todayAttendance?->time_in || $todayAttendance?->time_out || $showOvertimeRemarksModal)
                                ? 'cursor-not-allowed bg-gray-100 text-gray-400'
                                : 'bg-red-600 text-white hover:bg-red-700' }}"
                    >

                        <span
                            wire:loading.remove
                            wire:target="timeOut"
                        >
                            {{ $todayAttendance?->time_out
                                ? 'Timed Out'
                                : 'Time Out'
                            }}
                        </span>

                        <span
                            wire:loading
                            wire:target="timeOut"
                        >
                            Recording...
                        </span>

                    </button>

                </div>

            </div>


            {{-- ========================================================= --}}
            {{-- SUCCESS NOTIFICATION --}}
            {{-- ========================================================= --}}

            @if ($successMessage)

                <div
                    x-data="{ show: true }"
                    x-init="setTimeout(() => show = false, 5000)"
                    x-show="show"
                    x-transition:enter="transition ease-out duration-300"
                    x-transition:enter-start="opacity-0 translate-y-2"
                    x-transition:enter-end="opacity-100 translate-y-0"
                    x-transition:leave="transition ease-in duration-200"
                    x-transition:leave-start="opacity-100 translate-y-0"
                    x-transition:leave-end="opacity-0 translate-y-2"
                    class="mt-5 overflow-hidden rounded-2xl border border-green-200 bg-green-50 shadow-sm"
                >

                    <div class="flex items-center justify-between gap-4 px-5 py-4">

                        <div class="flex items-center gap-4">

                            <div class="flex h-11 w-11 shrink-0 items-center justify-center rounded-full bg-green-100">

                                <svg
                                    class="h-6 w-6 text-green-600"
                                    fill="none"
                                    stroke="currentColor"
                                    viewBox="0 0 24 24"
                                >
                                    <path
                                        stroke-linecap="round"
                                        stroke-linejoin="round"
                                        stroke-width="2"
                                        d="M5 13l4 4L19 7"
                                    />
                                </svg>

                            </div>

                            <div>

                                <p class="text-sm font-semibold text-green-800">
                                    Attendance Recorded
                                </p>

                                <p class="mt-0.5 text-sm text-green-700">
                                    {{ $successMessage }}
                                </p>

                            </div>

                        </div>


                        <button
                            type="button"
                            @click="show = false"
                            class="shrink-0 rounded-lg p-2 text-green-600 transition hover:bg-green-100"
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

                </div>

            @endif


            {{-- ========================================================= --}}
            {{-- ERROR NOTIFICATION --}}
            {{-- ========================================================= --}}

            @if ($errorMessage)

                <div
                    x-data="{ show: true }"
                    x-init="setTimeout(() => show = false, 5000)"
                    x-show="show"
                    x-transition:enter="transition ease-out duration-300"
                    x-transition:enter-start="opacity-0 translate-y-2"
                    x-transition:enter-end="opacity-100 translate-y-0"
                    x-transition:leave="transition ease-in duration-200"
                    x-transition:leave-start="opacity-100 translate-y-0"
                    x-transition:leave-end="opacity-0 translate-y-2"
                    class="mt-5 overflow-hidden rounded-2xl border border-red-200 bg-red-50 shadow-sm"
                >

                    <div class="flex items-center justify-between gap-4 px-5 py-4">

                        <div class="flex items-center gap-4">

                            <div class="flex h-11 w-11 shrink-0 items-center justify-center rounded-full bg-red-100">

                                <svg
                                    class="h-6 w-6 text-red-600"
                                    fill="none"
                                    stroke="currentColor"
                                    viewBox="0 0 24 24"
                                >
                                    <path
                                        stroke-linecap="round"
                                        stroke-linejoin="round"
                                        stroke-width="2"
                                        d="M12 9v2m0 4h.01M10.29 3.86l-8.18 14A2 2 0 003.82 21h16.36a2 2 0 001.71-3.14l-8.18-14a2 2 0 00-3.42 0z"
                                    />
                                </svg>

                            </div>

                            <div>

                                <p class="text-sm font-semibold text-red-800">
                                    Attendance Error
                                </p>

                                <p class="mt-0.5 text-sm text-red-700">
                                    {{ $errorMessage }}
                                </p>

                            </div>

                        </div>


                        <button
                            type="button"
                            @click="show = false"
                            class="shrink-0 rounded-lg p-2 text-red-600 transition hover:bg-red-100"
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

                </div>

            @endif

        </div>


        {{-- ========================================================= --}}
        {{-- SUMMARY --}}
        {{-- ========================================================= --}}

        <div class="mb-5 grid gap-4 sm:grid-cols-3">

            {{-- PRESENT --}}
            <div class="rounded-2xl border border-gray-200 bg-white p-5 shadow-sm">

                <div class="flex items-center gap-4">

                    <div class="flex h-12 w-12 shrink-0 items-center justify-center rounded-full bg-blue-50">

                        <svg
                            class="h-6 w-6 text-blue-600"
                            fill="none"
                            stroke="currentColor"
                            viewBox="0 0 24 24"
                        >
                            <path
                                stroke-linecap="round"
                                stroke-linejoin="round"
                                stroke-width="2"
                                d="M17 20h5v-2a4 4 0 00-4-4h-1M9 20H4v-2a4 4 0 014-4h1m4-8a4 4 0 11-8 0 4 4 0 018 0zm6 2a3 3 0 10-6 0"
                            />
                        </svg>

                    </div>

                    <div>

                        <p class="text-sm text-gray-500">
                            Present Today
                        </p>

                        <p class="mt-1 text-3xl font-bold text-blue-600">
                            {{ $this->presentCount }}
                        </p>

                    </div>

                </div>

            </div>


            {{-- TIMED IN --}}
            <div class="rounded-2xl border border-gray-200 bg-white p-5 shadow-sm">

                <div class="flex items-center gap-4">

                    <div class="flex h-12 w-12 shrink-0 items-center justify-center rounded-full bg-orange-50">

                        <svg
                            class="h-6 w-6 text-orange-500"
                            fill="none"
                            stroke="currentColor"
                            viewBox="0 0 24 24"
                        >
                            <path
                                stroke-linecap="round"
                                stroke-linejoin="round"
                                stroke-width="2"
                                d="M12 8v4l3 2m6-2a9 9 0 11-18 0 9 9 0 0118 0z"
                            />
                        </svg>

                    </div>

                    <div>

                        <p class="text-sm text-gray-500">
                            Timed In
                        </p>

                        <p class="mt-1 text-3xl font-bold text-orange-500">
                            {{ $this->timedInCount }}
                        </p>

                    </div>

                </div>

            </div>


            {{-- COMPLETED --}}
            <div class="rounded-2xl border border-gray-200 bg-white p-5 shadow-sm">

                <div class="flex items-center gap-4">

                    <div class="flex h-12 w-12 shrink-0 items-center justify-center rounded-full bg-green-50">

                        <svg
                            class="h-6 w-6 text-green-600"
                            fill="none"
                            stroke="currentColor"
                            viewBox="0 0 24 24"
                        >
                            <path
                                stroke-linecap="round"
                                stroke-linejoin="round"
                                stroke-width="2"
                                d="M5 13l4 4L19 7"
                            />
                        </svg>

                    </div>

                    <div>

                        <p class="text-sm text-gray-500">
                            Completed
                        </p>

                        <p class="mt-1 text-3xl font-bold text-green-600">
                            {{ $this->completedCount }}
                        </p>

                    </div>

                </div>

            </div>

        </div>


        {{-- ========================================================= --}}
        {{-- ATTENDANCE TABLE --}}
        {{-- ========================================================= --}}

        <div class="overflow-hidden rounded-2xl border border-gray-200 bg-white shadow-sm">

            <div class="border-b border-gray-200 px-5 py-4">

                <h2 class="text-base font-semibold text-gray-900">
                    Today's Attendance
                </h2>

                <p class="mt-1 text-sm text-gray-500">
                    Attendance status of all active employees.
                </p>

            </div>


            {{-- DESKTOP --}}
            <div class="hidden overflow-x-auto md:block">

                <table class="min-w-full">

                    <thead class="bg-gray-50">

                        <tr>

                            <th class="px-5 py-3 text-left text-xs font-semibold uppercase tracking-wider text-gray-500">
                                Employee
                            </th>

                            <th class="px-5 py-3 text-left text-xs font-semibold uppercase tracking-wider text-gray-500">
                                Time In
                            </th>

                            <th class="px-5 py-3 text-left text-xs font-semibold uppercase tracking-wider text-gray-500">
                                Time Out
                            </th>

                            <th class="px-5 py-3 text-left text-xs font-semibold uppercase tracking-wider text-gray-500">
                                Hours
                            </th>

                            <th class="px-5 py-3 text-left text-xs font-semibold uppercase tracking-wider text-gray-500">
                                Status
                            </th>

                        </tr>

                    </thead>


                    <tbody class="divide-y divide-gray-100">

                        @forelse ($this->todayAttendances as $employee)

                            @php

                                $attendance =
                                    $employee
                                        ->attendanceRecords
                                        ->first();

                                $workedMinutes =
                                    $attendance?->worked_minutes
                                    ?? 0;

                                $hours =
                                    $workedMinutes > 0
                                        ? number_format(
                                            $workedMinutes / 60,
                                            2
                                        )
                                        : '0.00';

                                $status = match (true) {

                                    ! $attendance =>
                                        'Pending',

                                    $attendance->time_in
                                    && $attendance->time_out =>
                                        'Completed',

                                    $attendance->time_in =>
                                        'Present',

                                    default =>
                                        'Pending',
                                };

                            @endphp


                            <tr class="transition hover:bg-gray-50">

                                {{-- EMPLOYEE --}}
                                <td class="whitespace-nowrap px-5 py-4">

                                    <div class="flex items-center gap-3">

                                        <div class="flex h-9 w-9 shrink-0 items-center justify-center rounded-full bg-gray-100 text-xs font-semibold text-gray-600">

                                            {{ strtoupper(
                                                substr(
                                                    $employee->first_name ?? '',
                                                    0,
                                                    1
                                                )
                                                .
                                                substr(
                                                    $employee->last_name ?? '',
                                                    0,
                                                    1
                                                )
                                            ) }}

                                        </div>

                                        <div>

                                            <div class="text-sm font-medium text-gray-900">
                                                {{ $employee->full_name }}
                                            </div>

                                            <div class="text-xs text-gray-500">
                                                {{ $employee->employee_id }}
                                            </div>

                                        </div>

                                    </div>

                                </td>


                                {{-- TIME IN --}}
                                <td class="whitespace-nowrap px-5 py-4 text-sm text-gray-600">

                                    @if ($attendance?->time_in)

                                        {{ \Carbon\Carbon::parse(
                                            $attendance->time_in
                                        )->format('h:i A') }}

                                    @else

                                        <span class="text-gray-400">
                                            —
                                        </span>

                                    @endif

                                </td>


                                {{-- TIME OUT --}}
                                <td class="whitespace-nowrap px-5 py-4 text-sm text-gray-600">

                                    @if ($attendance?->time_out)

                                        {{ \Carbon\Carbon::parse(
                                            $attendance->time_out
                                        )->format('h:i A') }}

                                    @else

                                        <span class="text-gray-400">
                                            —
                                        </span>

                                    @endif

                                </td>


                                {{-- HOURS --}}
                                <td class="whitespace-nowrap px-5 py-4 text-sm font-medium text-gray-900">
                                    {{ $hours }}
                                </td>


                                {{-- STATUS --}}
                                <td class="whitespace-nowrap px-5 py-4">

                                    @if ($status === 'Completed')

                                        <span class="inline-flex rounded-full bg-blue-50 px-3 py-1 text-xs font-medium text-blue-700">
                                            Completed
                                        </span>

                                    @elseif ($status === 'Present')

                                        <span class="inline-flex rounded-full bg-green-50 px-3 py-1 text-xs font-medium text-green-700">
                                            Present
                                        </span>

                                    @else

                                        <span class="inline-flex rounded-full bg-gray-100 px-3 py-1 text-xs font-medium text-gray-600">
                                            Pending
                                        </span>

                                    @endif

                                </td>

                            </tr>

                        @empty

                            <tr>

                                <td
                                    colspan="5"
                                    class="px-5 py-12 text-center text-sm text-gray-500"
                                >
                                    No active employees found.
                                </td>

                            </tr>

                        @endforelse

                    </tbody>

                </table>

            </div>


            {{-- MOBILE --}}
            <div class="divide-y divide-gray-100 md:hidden">

                @foreach ($this->todayAttendances as $employee)

                    @php

                        $attendance =
                            $employee
                                ->attendanceRecords
                                ->first();

                        $workedMinutes =
                            $attendance?->worked_minutes
                            ?? 0;

                        $hours =
                            $workedMinutes > 0
                                ? number_format(
                                    $workedMinutes / 60,
                                    2
                                )
                                : '0.00';

                        $status = match (true) {

                            ! $attendance =>
                                'Pending',

                            $attendance->time_in
                            && $attendance->time_out =>
                                'Completed',

                            $attendance->time_in =>
                                'Present',

                            default =>
                                'Pending',
                        };

                    @endphp


                    <div class="p-4">

                        <div class="flex items-start justify-between gap-4">

                            <div class="flex items-center gap-3">

                                <div class="flex h-10 w-10 shrink-0 items-center justify-center rounded-full bg-gray-100 text-xs font-semibold text-gray-600">

                                    {{ strtoupper(
                                        substr(
                                            $employee->first_name ?? '',
                                            0,
                                            1
                                        )
                                        .
                                        substr(
                                            $employee->last_name ?? '',
                                            0,
                                            1
                                        )
                                    ) }}

                                </div>

                                <div>

                                    <p class="text-sm font-medium text-gray-900">
                                        {{ $employee->full_name }}
                                    </p>

                                    <p class="text-xs text-gray-500">
                                        {{ $employee->employee_id }}
                                    </p>

                                </div>

                            </div>


                            @if ($status === 'Completed')

                                <span class="rounded-full bg-blue-50 px-2.5 py-1 text-xs font-medium text-blue-700">
                                    Completed
                                </span>

                            @elseif ($status === 'Present')

                                <span class="rounded-full bg-green-50 px-2.5 py-1 text-xs font-medium text-green-700">
                                    Present
                                </span>

                            @else

                                <span class="rounded-full bg-gray-100 px-2.5 py-1 text-xs font-medium text-gray-600">
                                    Pending
                                </span>

                            @endif

                        </div>


                        <div class="mt-4 grid grid-cols-3 gap-3">

                            <div>

                                <p class="text-xs text-gray-400">
                                    Time In
                                </p>

                                <p class="mt-1 text-sm font-medium text-gray-700">

                                    {{ $attendance?->time_in
                                        ? \Carbon\Carbon::parse(
                                            $attendance->time_in
                                        )->format('h:i A')
                                        : '—'
                                    }}

                                </p>

                            </div>


                            <div>

                                <p class="text-xs text-gray-400">
                                    Time Out
                                </p>

                                <p class="mt-1 text-sm font-medium text-gray-700">

                                    {{ $attendance?->time_out
                                        ? \Carbon\Carbon::parse(
                                            $attendance->time_out
                                        )->format('h:i A')
                                        : '—'
                                    }}

                                </p>

                            </div>


                            <div>

                                <p class="text-xs text-gray-400">
                                    Hours
                                </p>

                                <p class="mt-1 text-sm font-medium text-gray-700">
                                    {{ $hours }}
                                </p>

                            </div>

                        </div>

                    </div>

                @endforeach

            </div>

        </div>


        {{-- ========================================================= --}}
        {{-- FOOTER --}}
        {{-- ========================================================= --}}

        <div class="py-6 text-center text-xs text-gray-400">

            © {{ now()->year }}
            PSA Timekeeping System.
            All rights reserved.

        </div>

    </div>


    {{-- ============================================================= --}}
    {{-- OVERTIME REMARKS MODAL --}}
    {{-- ============================================================= --}}

    @if ($showOvertimeRemarksModal)

        <div
            class="fixed inset-0 z-[99999] flex items-center justify-center px-4 py-6"
        >

            {{-- BACKDROP --}}
            <div
                class="absolute inset-0 bg-black/60 backdrop-blur-sm"
            ></div>


            {{-- MODAL --}}
            <div
                class="relative w-full max-w-lg overflow-hidden rounded-2xl bg-white shadow-2xl"
            >

                {{-- HEADER --}}
                <div class="border-b border-gray-200 px-6 py-5">

                    <div class="flex items-start gap-4">

                        <div class="flex h-12 w-12 shrink-0 items-center justify-center rounded-full bg-orange-100">

                            <svg
                                class="h-6 w-6 text-orange-600"
                                fill="none"
                                stroke="currentColor"
                                viewBox="0 0 24 24"
                            >
                                <path
                                    stroke-linecap="round"
                                    stroke-linejoin="round"
                                    stroke-width="2"
                                    d="M12 8v4l3 2m6-2a9 9 0 11-18 0 9 9 0 0118 0z"
                                />
                            </svg>

                        </div>


                        <div>

                            <h2 class="text-lg font-semibold text-gray-900">
                                Overtime Remarks Required
                            </h2>

                            <p class="mt-1 text-sm leading-relaxed text-gray-500">
                                Your Time Out is at or after 6:00 PM.
                                Please provide the reason for your overtime.
                            </p>

                        </div>

                    </div>

                </div>


                {{-- BODY --}}
                <div class="px-6 py-6">

                    {{-- TIME OUT INFORMATION --}}
                    <div class="rounded-xl border border-orange-200 bg-orange-50 px-4 py-3">

                        <div class="flex items-center justify-between gap-4">

                            <span class="text-sm font-medium text-orange-800">
                                Time Out
                            </span>

                            <span class="text-sm font-bold text-orange-900">

                                @if ($todayAttendance?->time_out)

                                    {{ \Carbon\Carbon::parse(
                                        $todayAttendance->time_out
                                    )->format('h:i A') }}

                                @endif

                            </span>

                        </div>

                    </div>


                    {{-- DETECTED OT --}}
                    @if ($todayAttendance?->detected_overtime_minutes > 0)

                        <div class="mt-3 rounded-xl border border-blue-200 bg-blue-50 px-4 py-3">

                            <div class="flex items-center justify-between gap-4">

                                <span class="text-sm font-medium text-blue-800">
                                    Detected Overtime
                                </span>

                                <span class="text-sm font-bold text-blue-900">

                                    {{ number_format(
                                        $todayAttendance->detected_overtime_minutes / 60,
                                        2
                                    ) }}
                                    hours

                                </span>

                            </div>

                        </div>

                    @endif


                    {{-- REMARKS --}}
                    <div class="mt-5">

                        <label class="mb-2 block text-sm font-medium text-gray-700">

                            Reason for Overtime

                            <span class="text-red-500">
                                *
                            </span>

                        </label>

                        <textarea
                            wire:model="overtimeRemarks"
                            rows="4"
                            maxlength="1000"
                            autofocus
                            placeholder="Please enter the reason for your overtime..."
                            class="block w-full rounded-xl border-gray-300 bg-white px-4 py-3 text-sm text-gray-900 shadow-sm outline-none transition placeholder:text-gray-400 focus:border-orange-500 focus:ring-2 focus:ring-orange-500/20"
                        ></textarea>

                        @error('overtimeRemarks')

                            <p class="mt-1 text-sm text-red-600">
                                {{ $message }}
                            </p>

                        @enderror

                    </div>


                    {{-- NOTICE --}}
                    <div class="mt-4 rounded-xl border border-blue-200 bg-blue-50 px-4 py-3">

                        <p class="text-xs leading-relaxed text-blue-700">

                            Your overtime will remain
                            <strong>Pending</strong>
                            until it is reviewed and approved by an
                            authorized payroll administrator.

                        </p>

                    </div>

                </div>


                {{-- FOOTER --}}
                <div class="flex items-center justify-end border-t border-gray-200 bg-gray-50 px-6 py-4">

                    <button
                        type="button"
                        wire:click="saveOvertimeRemarks"
                        wire:loading.attr="disabled"
                        wire:target="saveOvertimeRemarks"
                        class="rounded-xl bg-orange-600 px-5 py-2.5 text-sm font-semibold text-white transition hover:bg-orange-700 disabled:cursor-not-allowed disabled:opacity-50"
                    >

                        <span
                            wire:loading.remove
                            wire:target="saveOvertimeRemarks"
                        >
                            Submit OT Remarks
                        </span>

                        <span
                            wire:loading
                            wire:target="saveOvertimeRemarks"
                        >
                            Saving...
                        </span>

                    </button>

                </div>

            </div>

        </div>

    @endif


    {{-- ============================================================= --}}
    {{-- LIVE CLOCK --}}
    {{-- ============================================================= --}}

    <script>
        function attendanceClock() {
            return {
                time: '',
                date: '',

                init() {
                    this.updateClock();

                    setInterval(() => {
                        this.updateClock();
                    }, 1000);
                },

                updateClock() {
                    const now = new Date();

                    this.time = new Intl.DateTimeFormat('en-US', {
                        timeZone: 'Asia/Manila',
                        hour: 'numeric',
                        minute: '2-digit',
                        second: '2-digit',
                        hour12: true,
                    }).format(now);

                    this.date = new Intl.DateTimeFormat('en-US', {
                        timeZone: 'Asia/Manila',
                        weekday: 'long',
                        month: 'long',
                        day: 'numeric',
                        year: 'numeric',
                    }).format(now);
                }
            }
        }
    </script>

</div>