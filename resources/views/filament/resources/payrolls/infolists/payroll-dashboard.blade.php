<div class="payroll-dashboard">
    <div class="payroll-dashboard-actions">
        <div>
            <div class="payroll-action-title">
                Payroll Calculation
            </div>

            <div class="payroll-action-description">
                Recalculate this employee's payroll using the latest attendance,
                approved overtime, allowances, and deductions.
            </div>
        </div>

        <button
            type="button"
            wire:click="calculatePayroll"
            wire:loading.attr="disabled"
            class="payroll-calculate-button"
        >
            <span wire:loading.remove wire:target="calculatePayroll">
                Calculate Payroll
            </span>

            <span wire:loading wire:target="calculatePayroll">
                Calculating...
            </span>
        </button>
    </div>

    {{-- ================================================================
         PAYROLL INFORMATION
         ================================================================ --}}

    <div class="payroll-card">

        <div class="payroll-info-grid">

            <div>
                <div class="payroll-info-label">
                    Payroll Period
                </div>

                <div class="payroll-info-value">
                    {{ $payrollPeriod?->name ?? '—' }}
                </div>

                @if ($payrollPeriod)
                    <div class="payroll-info-subvalue">
                        {{ $payrollPeriod->period_start?->format('M d, Y') }}
                        –
                        {{ $payrollPeriod->period_end?->format('M d, Y') }}
                    </div>
                @endif
            </div>

            <div>
                <div class="payroll-info-label">
                    Pay Date
                </div>

                <div class="payroll-info-value">
                    {{ $payrollPeriod?->pay_date?->format('M d, Y') ?? '—' }}
                </div>
            </div>

            <div>
                <div class="payroll-info-label">
                    Payroll Status
                </div>

                <div class="payroll-info-value">
                    {{ ucfirst($record->status ?? 'Draft') }}
                </div>
            </div>

        </div>

    </div>


    {{-- ================================================================
         EMPLOYEE HEADER
         ================================================================ --}}

    <div class="payroll-card">

        <div class="employee-header">

            <div class="employee-left">

                <div class="employee-avatar">
                    {{ strtoupper(substr($employee?->first_name ?? 'E', 0, 1)) }}{{ strtoupper(substr($employee?->last_name ?? '', 0, 1)) }}
                </div>

                <div>

                    <div class="employee-name">
                        {{ $employee?->full_name ?? 'Employee' }}
                    </div>

                    <div class="employee-id">
                        {{ $employee?->employee_id ?? '—' }}
                    </div>

                    <div class="employee-details">

                        @if ($employee?->department)
                            <span class="employee-badge">
                                {{ $employee->department->name }}
                            </span>
                        @endif

                        @if ($employee?->position)
                            <span class="employee-badge">
                                {{ $employee->position->name }}
                            </span>
                        @endif

                    </div>

                </div>

            </div>

            <div class="employee-salary">

                <div class="employee-salary-label">
                    Monthly Basic Salary
                </div>

                <div class="employee-salary-value">
                    {{ $money($basicSalary) }}
                </div>

            </div>

        </div>

    </div>


    {{-- ================================================================
         SUMMARY
         ================================================================ --}}

    <div class="payroll-summary-grid">

        <div class="summary-card">

            <div class="summary-label">
                Basic Pay
            </div>

            <div class="summary-value">
                {{ $money($basicPay) }}
            </div>

        </div>


        <div class="summary-card">

            <div class="summary-label">
                Allowances
            </div>

            <div class="summary-value summary-primary">
                {{ $money($allowances) }}
            </div>

        </div>


        <div class="summary-card">

            <div class="summary-label">
                Deductions
            </div>

            <div class="summary-value summary-danger">
                {{ $money($totalDeductions) }}
            </div>

        </div>


        <div class="summary-card">

            <div class="summary-label">
                Gross Pay
            </div>

            <div class="summary-value">
                {{ $money($grossPay) }}
            </div>

        </div>


        <div class="summary-card summary-success">

            <div class="summary-label">
                Net Pay
            </div>

            <div class="summary-value">
                {{ $money($netPay) }}
            </div>

        </div>

    </div>


    {{-- ================================================================
         SALARY RATE BASIS
         ================================================================ --}}

    <div class="payroll-card">

        <h3 class="payroll-section-title">
            Salary Rate Basis
        </h3>

        <div class="payroll-section-description">
            Employee salary converted to the daily and hourly basis used for premium pay calculations.
        </div>

        <div class="monitoring-grid">

            <div class="monitoring-card">

                <div class="monitoring-label">
                    Monthly Salary
                </div>

                <div class="monitoring-value">
                    {{ $money($basicSalary) }}
                </div>

            </div>


            <div class="monitoring-card">

                <div class="monitoring-label">
                    Daily Rate
                </div>

                <div class="monitoring-value">
                    {{ $money($dailyRate) }}
                </div>

            </div>


            <div class="monitoring-card">

                <div class="monitoring-label">
                    Hourly Rate
                </div>

                <div class="monitoring-value">
                    {{ $money($hourlyRate) }}
                </div>

            </div>

        </div>

    </div>


    {{-- ================================================================
         ATTENDANCE MONITORING
         ================================================================ --}}

    <div class="payroll-card">

        <h3 class="payroll-section-title">
            Attendance Monitoring
        </h3>

        <div class="payroll-section-description">
            Attendance totals for this payroll period.
        </div>

        <div class="monitoring-grid">

            <div class="monitoring-card">

                <div class="monitoring-label">
                    Regular Hours
                </div>

                <div class="monitoring-value">
                    {{ $formatMinutes($totalRegularMinutes) }}
                </div>

            </div>


            <div class="monitoring-card">

                <div class="monitoring-label">
                    Late
                </div>

                <div class="monitoring-value">
                    {{ $formatMinutes($totalLateMinutes) }}
                </div>

            </div>


            <div class="monitoring-card">

                <div class="monitoring-label">
                    Undertime
                </div>

                <div class="monitoring-value">
                    {{ $formatMinutes($totalUndertimeMinutes) }}
                </div>

            </div>


            <div class="monitoring-card">

                <div class="monitoring-label">
                    Approved OT
                </div>

                <div class="monitoring-value">
                    {{ $formatMinutes($totalApprovedOtMinutes) }}
                </div>

            </div>


            <div class="monitoring-card">

                <div class="monitoring-label">
                    Rest Day Hours
                </div>

                <div class="monitoring-value">
                    {{ $formatMinutes($totalRestDayMinutes) }}
                </div>

            </div>


            <div class="monitoring-card">

                <div class="monitoring-label">
                    NSD
                </div>

                <div class="monitoring-value">
                    {{ $formatMinutes($totalNsdMinutes) }}
                </div>

            </div>

        </div>

    </div>


    {{-- ================================================================
         PREMIUM PAY
         ================================================================ --}}

    <div class="payroll-card">

        <h3 class="payroll-section-title">
            Premium Pay Summary
        </h3>

        <div class="payroll-section-description">
            Premium rates are shown with their corresponding hourly peso value based on the employee's salary.
        </div>

        <div class="payroll-table-wrapper">

            <table class="payroll-table premium-pay-table">

                <thead>
                    <tr>
                        <th>Description</th>

                        <th class="text-right">
                            Regular
                            <span class="table-rate-title">
                                ({{ number_format($regularDayRate, 0) }}%)
                            </span>
                        </th>

                        <th class="text-right">
                            Rest Day
                            <span class="table-rate-title">
                                ({{ number_format($restDayRate, 0) }}%)
                            </span>
                        </th>

                        <th class="text-right">
                            Special NW Holiday
                            <span class="table-rate-title">
                                (130%)
                            </span>
                        </th>

                        <th class="text-right">
                            Regular Holiday
                            <span class="table-rate-title">
                                (200%)
                            </span>
                        </th>

                        <th class="text-right">Total</th>
                    </tr>
                </thead>

                <tbody>
                    <tr>
                        <td><strong>Hours</strong></td>

                        <td class="text-right">
                            {{ $formatMinutes($totalRegularMinutes) }}
                        </td>

                        <td class="text-right">
                            {{ $formatMinutes($totalRestDayMinutes) }}
                        </td>

                        <td class="text-right">0.00</td>
                        <td class="text-right">0.00</td>

                        <td class="text-right">
                            <strong>
                                {{ $formatMinutes(
                                    $totalRegularMinutes + $totalRestDayMinutes
                                ) }}
                            </strong>
                        </td>
                    </tr>

                    <tr>
                        <td><strong>Hourly Rate</strong></td>

                        <td class="text-right">
                            {{ $money($regularHourlyAmount) }}
                        </td>

                        <td class="text-right">
                            {{ $money($restDayHourlyAmount) }}
                        </td>

                        <td class="text-right">
                            {{ $money($hourlyRate * 1.30) }}
                        </td>

                        <td class="text-right">
                            {{ $money($hourlyRate * 2.00) }}
                        </td>

                        <td class="text-right">—</td>
                    </tr>

                    <tr class="premium-pay-amount-row">
                        <td><strong>Amount</strong></td>

                        <td class="text-right">
                            <span class="payroll-muted">
                                Included in Basic Pay
                            </span>
                        </td>

                        <td class="text-right">
                            <strong>{{ $money($restDayAmount) }}</strong>
                        </td>

                        <td class="text-right">
                            <strong>₱0.00</strong>
                        </td>

                        <td class="text-right">
                            <strong>₱0.00</strong>
                        </td>

                        <td class="text-right">
                            <strong>
                                {{ $money($restDayAmount) }}
                            </strong>
                        </td>
                    </tr>
                </tbody>

            </table>

        </div>

    </div>


    {{-- ================================================================
         OVERTIME
         ================================================================ --}}

    <div class="payroll-card">

        <h3 class="payroll-section-title">
            Overtime Summary
        </h3>

        <div class="payroll-section-description">
            Approved overtime is calculated using the applicable payroll rate for each workday classification.
        </div>

        <div class="payroll-table-wrapper">

            <table class="payroll-table overtime-summary-table">

                <thead>
                    <tr>
                        <th>Description</th>

                        <th class="text-right">
                            Weekday
                            <span class="table-rate-title">
                                ({{ number_format($regularDayOvertimeRate, 0) }}%)
                            </span>
                        </th>

                        <th class="text-right">
                            Rest Day
                            <span class="table-rate-title">
                                ({{ number_format($restDayOvertimeRate, 0) }}%)
                            </span>
                        </th>

                        <th class="text-right">
                            Special NW Holiday
                            <span class="table-rate-title">
                                (169%)
                            </span>
                        </th>

                        <th class="text-right">
                            Regular Holiday
                            <span class="table-rate-title">
                                (260%)
                            </span>
                        </th>

                        <th class="text-right">Total</th>
                    </tr>
                </thead>

                <tbody>
                    <tr>
                        <td><strong>Hours</strong></td>

                        <td class="text-right">
                            {{ $formatMinutes($weekdayOt) }}
                        </td>

                        <td class="text-right">
                            {{ $formatMinutes($restDayOt) }}
                        </td>

                        <td class="text-right">0.00</td>
                        <td class="text-right">0.00</td>

                        <td class="text-right">
                            <strong>
                                {{ $formatMinutes($totalApprovedOtMinutes) }}
                            </strong>
                        </td>
                    </tr>

                    <tr>
                        <td><strong>Rate</strong></td>

                        <td class="text-right">
                            {{ $money($regularOtHourlyAmount) }}
                        </td>

                        <td class="text-right">
                            {{ $money($restDayOtHourlyAmount) }}
                        </td>

                        <td class="text-right">
                            {{ $money($hourlyRate * 1.69) }}
                        </td>

                        <td class="text-right">
                            {{ $money($hourlyRate * 2.60) }}
                        </td>

                        <td class="text-right">—</td>
                    </tr>

                    <tr class="overtime-amount-row">
                        <td><strong>Amount</strong></td>

                        <td class="text-right">
                            <strong>{{ $money($regularDayOvertimeAmount) }}</strong>
                        </td>

                        <td class="text-right">
                            <strong>{{ $money($restDayOvertimeAmount) }}</strong>
                        </td>

                        <td class="text-right">
                            <strong>₱0.00</strong>
                        </td>

                        <td class="text-right">
                            <strong>₱0.00</strong>
                        </td>

                        <td class="text-right">
                            <strong>{{ $money($overtimePay) }}</strong>
                        </td>
                    </tr>
                </tbody>

            </table>

        </div>

    </div>


    {{-- ================================================================
        OT + NSD
        ================================================================ --}}

    <div class="payroll-card">

        <h3 class="payroll-section-title">
            Overtime + Night Differential Summary
        </h3>

        <div class="payroll-section-description">
            Only approved overtime hours that actually overlap with the
            night differential period are shown here.
            These amounts are already included in Overtime and NSD above.
        </div>

        <div class="payroll-table-wrapper">

            <table class="payroll-table overtime-nsd-table">

                <thead>
                    <tr>

                        <th>
                            Description
                        </th>

                        <th class="text-right">
                            Weekday
                            <span class="table-rate-title">
                                ({{ number_format(
                                    $regularDayOvertimeRate
                                    + $nightShiftDifferentialRate,
                                    1
                                ) }}%)
                            </span>
                        </th>

                        <th class="text-right">
                            Rest Day
                            <span class="table-rate-title">
                                ({{ number_format(
                                    $restDayOvertimeRate
                                    + $nightShiftDifferentialRate,
                                    1
                                ) }}%)
                            </span>
                        </th>

                        <th class="text-right">
                            Special NW Holiday
                        </th>

                        <th class="text-right">
                            Regular Holiday
                        </th>

                        <th class="text-right">
                            Total
                        </th>

                    </tr>
                </thead>

                <tbody>

                    {{-- HOURS --}}

                    <tr>

                        <td>
                            <strong>Hours</strong>
                        </td>

                        <td class="text-right">

                            {{ $formatMinutes(
                                $weekdayOtNsdMinutes
                            ) }}

                        </td>

                        <td class="text-right">

                            {{ $formatMinutes(
                                $restDayOtNsdMinutes
                            ) }}

                        </td>

                        <td class="text-right">
                            0.00
                        </td>

                        <td class="text-right">
                            0.00
                        </td>

                        <td class="text-right">

                            <strong>
                                {{ $formatMinutes(
                                    $totalOtNsdMinutes
                                ) }}
                            </strong>

                        </td>

                    </tr>


                    {{-- RATE --}}

                    <tr>

                        <td>
                            <strong>Rate</strong>
                        </td>

                        <td class="text-right">

                            {{ $money(
                                $weekdayOtNsdHourlyAmount
                            ) }}

                        </td>

                        <td class="text-right">

                            {{ $money(
                                $restDayOtNsdHourlyAmount
                            ) }}

                        </td>

                        <td class="text-right">
                            —
                        </td>

                        <td class="text-right">
                            —
                        </td>

                        <td class="text-right">
                            —
                        </td>

                    </tr>


                    {{-- AMOUNT --}}

                    <tr class="ot-nsd-amount-row">

                        <td>
                            <strong>Amount</strong>
                        </td>

                        <td class="text-right">

                            <strong>
                                {{ $money(
                                    $weekdayOtNsdAmount
                                ) }}
                            </strong>

                        </td>

                        <td class="text-right">

                            <strong>
                                {{ $money(
                                    $restDayOtNsdAmount
                                ) }}
                            </strong>

                        </td>

                        <td class="text-right">
                            ₱0.00
                        </td>

                        <td class="text-right">
                            ₱0.00
                        </td>

                        <td class="text-right">

                            <strong>
                                {{ $money(
                                    $totalOtNsdAmount
                                ) }}
                            </strong>

                        </td>

                    </tr>


                    {{-- INFORMATION --}}

                    <tr>

                        <td colspan="6">

                            <span class="payroll-muted">
                                OT + NSD represents only the hours where
                                approved overtime overlaps with the
                                configured NSD period. It is a display
                                breakdown and is not added again to Gross Pay.
                            </span>

                        </td>

                    </tr>

                </tbody>

            </table>

        </div>

    </div>


    {{-- ================================================================
         DAILY ATTENDANCE
         ================================================================ --}}

    <div class="payroll-card">

        <h3 class="payroll-section-title">
            Daily Attendance Records
        </h3>

        <div class="payroll-section-description">
            Detailed attendance records for this payroll period.
        </div>

        @if ($attendanceRecords->isEmpty())

            <div class="payroll-empty-state">
                No attendance records found.
            </div>

        @else

            <div class="payroll-table-wrapper">

                <table class="payroll-table attendance-table">

                    <thead>

                        <tr>

                            <th class="sticky-column attendance-date">
                                Date
                            </th>

                            <th class="sticky-column attendance-time-in">
                                Time In
                            </th>

                            <th class="sticky-column attendance-time-out">
                                Time Out
                            </th>

                            <th class="text-right">
                                Regular
                            </th>

                            <th class="text-right">
                                Late
                            </th>

                            <th class="text-right">
                                Undertime
                            </th>

                            <th class="text-right">
                                Rest Day
                            </th>

                            <th class="text-right">
                                Detected OT
                            </th>

                            <th class="text-right">
                                Approved OT
                            </th>

                            <th class="text-right">
                                NSD
                            </th>

                            <th class="text-center">
                                OT Status
                            </th>

                            <th class="text-center">
                                Status
                            </th>

                        </tr>

                    </thead>


                    <tbody>

                        @foreach ($attendanceRecords as $attendance)

                            <tr>

                                <td class="sticky-column attendance-date">
                                    {{ $attendance->attendance_date?->format('M d, Y') }}
                                </td>

                                <td class="sticky-column attendance-time-in">
                                    {{ $attendance->time_in?->format('h:i A') ?? '—' }}
                                </td>

                                <td class="sticky-column attendance-time-out">
                                    {{ $attendance->time_out?->format('h:i A') ?? '—' }}
                                </td>

                                <td class="text-right">
                                    {{ $formatMinutes($attendance->regular_minutes) }}
                                </td>

                                <td class="text-right">

                                    @if ($attendance->late_minutes > 0)

                                        <span class="text-danger-strong">
                                            {{ $formatMinutes($attendance->late_minutes) }}
                                        </span>

                                    @else

                                        —

                                    @endif

                                </td>

                                <td class="text-right">
                                    {{ $formatMinutes($attendance->undertime_minutes) }}
                                </td>

                                <td class="text-right">
                                    {{ $formatMinutes($attendance->rest_day_minutes) }}
                                </td>

                                <td class="text-right">
                                    {{ $formatMinutes($attendance->detected_overtime_minutes) }}
                                </td>

                                <td class="text-right">

                                    @if ($attendance->approved_overtime_minutes > 0)

                                        <span class="text-success-strong">
                                            {{ $formatMinutes($attendance->approved_overtime_minutes) }}
                                        </span>

                                    @else

                                        —

                                    @endif

                                </td>

                                <td class="text-right">
                                    {{ $formatMinutes($attendance->night_shift_minutes) }}
                                </td>

                                <td class="text-center">

                                    @if ($attendance->overtime_status === 'approved')

                                        <span class="status-badge status-approved">
                                            Approved
                                        </span>

                                    @elseif ($attendance->overtime_status === 'pending')

                                        <span class="status-badge status-pending">
                                            Pending
                                        </span>

                                    @elseif ($attendance->overtime_status === 'rejected')

                                        <span class="status-badge status-rejected">
                                            Rejected
                                        </span>

                                    @else

                                        —

                                    @endif

                                </td>

                                <td class="text-center">

                                    @if ($attendance->status === 'present')

                                        <span class="status-badge status-present">
                                            Present
                                        </span>

                                    @elseif ($attendance->status === 'rest_day')

                                        <span class="status-badge status-rest">
                                            Rest Day
                                        </span>

                                    @else

                                        {{ ucfirst($attendance->status ?? '—') }}

                                    @endif

                                </td>

                            </tr>

                        @endforeach

                    </tbody>

                </table>

            </div>

        @endif

    </div>


    {{-- ================================================================
         ALLOWANCES + DEDUCTIONS
         ================================================================ --}}

    <div class="two-column-grid">


        {{-- ALLOWANCES --}}

        <div class="payroll-card">

            <h3 class="payroll-section-title">
                Allowance Breakdown
            </h3>

            <div class="payroll-table-wrapper">

                <table class="payroll-table breakdown-table">

                    <thead>

                        <tr>

                            <th>
                                Description
                            </th>

                            <th class="text-right">
                                Amount
                            </th>

                        </tr>

                    </thead>


                    <tbody>

                        @forelse ($allowanceItems as $item)

                            <tr>

                                <td>

                                    <strong>
                                        {{ $item->description }}
                                    </strong>

                                    {{-- @if ($item->quantity || $item->rate)

                                        <div class="breakdown-subvalue">
                                            Qty: {{ $item->quantity ?? 1 }}
                                            ×
                                            {{ $money($item->rate ?? 0) }}
                                        </div>

                                    @endif --}}

                                </td>

                                <td class="text-right">
                                    {{ $money($item->amount) }}
                                </td>

                            </tr>

                        @empty

                            <tr>

                                <td colspan="2" class="text-center">
                                    No allowances.
                                </td>

                            </tr>

                        @endforelse


                        <tr>

                            <td>
                                <strong>Total Allowances</strong>
                            </td>

                            <td class="text-right">

                                <strong>
                                    {{ $money($allowances) }}
                                </strong>

                            </td>

                        </tr>

                    </tbody>

                </table>

            </div>

        </div>


        {{-- DEDUCTIONS --}}

        <div class="payroll-card">

            <h3 class="payroll-section-title">
                Deductions
            </h3>

            <div class="payroll-table-wrapper">

                <table class="payroll-table breakdown-table">

                    <thead>

                        <tr>

                            <th>
                                Description
                            </th>

                            <th class="text-right">
                                Amount
                            </th>

                        </tr>

                    </thead>


                    <tbody>

                        @forelse ($deductionItems as $item)

                            <tr>

                                <td>

                                    <strong>
                                        {{ $item->description }}
                                    </strong>

                                    {{-- @if ($item->quantity || $item->rate)

                                        <div class="breakdown-subvalue">
                                            Qty: {{ $item->quantity ?? 1 }}
                                            ×
                                            {{ $money($item->rate ?? 0) }}
                                        </div>

                                    @endif --}}

                                </td>

                                <td class="text-right">
                                    {{ $money($item->amount) }}
                                </td>

                            </tr>

                        @empty

                            <tr>

                                <td colspan="2" class="text-center">
                                    No deductions.
                                </td>

                            </tr>

                        @endforelse


                        <tr>

                            <td>
                                <strong>Total Deductions</strong>
                            </td>

                            <td class="text-right">

                                <strong>
                                    {{ $money($totalDeductions) }}
                                </strong>

                            </td>

                        </tr>

                    </tbody>

                </table>

            </div>

        </div>

    </div>


    {{-- ================================================================
         NET PAY
         ================================================================ --}}

    <div class="net-pay-card">

        <div>

            <div class="net-pay-label">
                Net Pay
            </div>

            <div class="net-pay-description">
                Gross Pay {{ $money($grossPay) }}
                −
                Total Deductions {{ $money($totalDeductions) }}
            </div>

        </div>

        <div class="net-pay-value">
            {{ $money($netPay) }}
        </div>

    </div>

</div>
