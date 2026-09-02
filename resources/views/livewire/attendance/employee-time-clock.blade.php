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

                        <span>PSA Timekeeping System</span>
                    </div>
                </div>


                {{-- RIGHT --}}
                <div class="flex flex-col items-start gap-4 sm:flex-row sm:items-center md:justify-end">

                    {{-- Clock --}}
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


                    {{-- Login Button --}}
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

                        <span>Login</span>
                    </a>

                </div>

            </div>
        </div>


        {{-- ========================================================= --}}
        {{-- EMPLOYEE CLOCK CONTROLS --}}
        {{-- ========================================================= --}}

        <div class="mb-5 rounded-2xl border border-gray-200 bg-white p-5 shadow-sm">

            <div class="grid gap-5 lg:grid-cols-[1fr_390px]">

                {{-- Employee --}}
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


                {{-- Actions --}}
                <div class="flex gap-3">

                    {{-- TIME IN --}}
                    <button
                        type="button"
                        wire:click="timeIn"
                        @disabled($todayAttendance?->time_in)
                        class="flex-1 rounded-xl px-5 py-3 text-sm font-semibold transition
                            {{ $todayAttendance?->time_in
                                ? 'cursor-not-allowed bg-gray-100 text-gray-400'
                                : 'bg-green-600 text-white hover:bg-green-700' }}"
                    >
                        {{ $todayAttendance?->time_in ? 'Timed In' : 'Time In' }}
                    </button>

                    {{-- TIME OUT --}}
                    <button
                        type="button"
                        wire:click="timeOut"
                        @disabled(! $todayAttendance?->time_in || $todayAttendance?->time_out)
                        class="flex-1 rounded-xl px-5 py-3 text-sm font-semibold transition
                            {{ (! $todayAttendance?->time_in || $todayAttendance?->time_out)
                                ? 'cursor-not-allowed bg-gray-100 text-gray-400'
                                : 'bg-red-600 text-white hover:bg-red-700' }}"
                    >
                        {{ $todayAttendance?->time_out ? 'Timed Out' : 'Time Out' }}
                    </button>

                </div>

            </div>


            {{-- Messages --}}
            @if ($successMessage)
                <div class="mt-4 rounded-xl border border-green-200 bg-green-50 px-4 py-3 text-sm text-green-700">
                    {{ $successMessage }}
                </div>
            @endif

            @if ($errorMessage)
                <div class="mt-4 rounded-xl border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-700">
                    {{ $errorMessage }}
                </div>
            @endif

        </div>


        {{-- ========================================================= --}}
        {{-- SUMMARY --}}
        {{-- ========================================================= --}}

        <div class="mb-5 grid gap-4 sm:grid-cols-3">

            {{-- Present --}}
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


            {{-- Timed In --}}
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


            {{-- Completed --}}
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


            {{-- Desktop table --}}
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
                                $attendance = $employee->attendanceRecords->first();

                                $workedMinutes = $attendance?->worked_minutes ?? 0;

                                $hours = $workedMinutes > 0
                                    ? number_format($workedMinutes / 60, 2)
                                    : '0.00';

                                $status = match (true) {
                                    ! $attendance => 'Pending',
                                    $attendance->time_in && $attendance->time_out => 'Completed',
                                    $attendance->time_in => 'Present',
                                    default => 'Pending',
                                };
                            @endphp

                            <tr class="transition hover:bg-gray-50">

                                {{-- Employee --}}
                                <td class="whitespace-nowrap px-5 py-4">

                                    <div class="flex items-center gap-3">

                                        <div class="flex h-9 w-9 shrink-0 items-center justify-center rounded-full bg-gray-100 text-xs font-semibold text-gray-600">
                                            {{ strtoupper(substr($employee->first_name ?? '', 0, 1) . substr($employee->last_name ?? '', 0, 1)) }}
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


                                {{-- Time In --}}
                                <td class="whitespace-nowrap px-5 py-4 text-sm text-gray-600">
                                    @if ($attendance?->time_in)
                                        {{ \Carbon\Carbon::parse($attendance->time_in)->format('h:i A') }}
                                    @else
                                        <span class="text-gray-400">—</span>
                                    @endif
                                </td>


                                {{-- Time Out --}}
                                <td class="whitespace-nowrap px-5 py-4 text-sm text-gray-600">
                                    @if ($attendance?->time_out)
                                        {{ \Carbon\Carbon::parse($attendance->time_out)->format('h:i A') }}
                                    @else
                                        <span class="text-gray-400">—</span>
                                    @endif
                                </td>


                                {{-- Hours --}}
                                <td class="whitespace-nowrap px-5 py-4 text-sm font-medium text-gray-900">
                                    {{ $hours }}
                                </td>


                                {{-- Status --}}
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


            {{-- Mobile --}}
            <div class="divide-y divide-gray-100 md:hidden">

                @foreach ($this->todayAttendances as $employee)

                    @php
                        $attendance = $employee->attendanceRecords->first();

                        $workedMinutes = $attendance?->worked_minutes ?? 0;

                        $hours = $workedMinutes > 0
                            ? number_format($workedMinutes / 60, 2)
                            : '0.00';

                        $status = match (true) {
                            ! $attendance => 'Pending',
                            $attendance->time_in && $attendance->time_out => 'Completed',
                            $attendance->time_in => 'Present',
                            default => 'Pending',
                        };
                    @endphp

                    <div class="p-4">

                        <div class="flex items-start justify-between gap-4">

                            <div class="flex items-center gap-3">

                                <div class="flex h-10 w-10 shrink-0 items-center justify-center rounded-full bg-gray-100 text-xs font-semibold text-gray-600">
                                    {{ strtoupper(substr($employee->first_name ?? '', 0, 1) . substr($employee->last_name ?? '', 0, 1)) }}
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
                                        ? \Carbon\Carbon::parse($attendance->time_in)->format('h:i A')
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
                                        ? \Carbon\Carbon::parse($attendance->time_out)->format('h:i A')
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


        {{-- Footer --}}
        <div class="py-6 text-center text-xs text-gray-400">
            © {{ now()->year }} PSA Timekeeping System. All rights reserved.
        </div>

    </div>


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
