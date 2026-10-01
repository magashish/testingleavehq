<x-app-layout>
<style>
.report-wrap { overflow-x:auto;-webkit-overflow-scrolling:touch; }
.report-wrap table { min-width:600px; }
.report-wrap th, .report-wrap td { white-space:nowrap; }
.remaining-bar { display:inline-block;vertical-align:middle;margin-left:6px;background:#f0f0f0;border-radius:999px;height:4px;width:64px; }
.remaining-bar-fill { height:4px;border-radius:999px; }
</style>
<div class="page">
    <div class="page-header">
        <h2>Reports</h2>
        <p>Attendance and leave analytics</p>
    </div>

    {{-- Filter form --}}
    <div class="card" style="margin-bottom:20px;"
         x-data="{ report: '{{ $reportType }}' }">
        <form method="GET" action="{{ route('reports.index') }}" style="display:flex;flex-wrap:wrap;gap:14px;align-items:flex-end;">
            <div class="form-group" style="margin:0;min-width:200px;">
                <label class="form-label">Report type</label>
                <select name="report" class="form-select" x-model="report">
                    <option value="late">Late arrivals (after 09:00)</option>
                    <option value="leave_summary">Annual leave summary</option>
                    <option value="leave_balance">Leave balance by employee</option>
                    <option value="leave_history">Leave history by employee</option>
                </select>
            </div>

            {{-- Employee (optional) — late arrivals --}}
            <div class="form-group" style="margin:0;min-width:180px;" x-show="report === 'late'">
                <label class="form-label">Employee</label>
                <select name="employee_id" class="form-select" :disabled="report !== 'late'">
                    <option value="">All employees</option>
                    @foreach($employees as $emp)
                        <option value="{{ $emp->id }}" {{ ($reportType === 'late' && $employeeId == $emp->id) ? 'selected' : '' }}>{{ $emp->name }}</option>
                    @endforeach
                </select>
            </div>

            {{-- Date range — late arrivals --}}
            <div class="form-group" style="margin:0;" x-show="report === 'late'">
                <label class="form-label">From</label>
                <input type="date" name="from" class="form-input" value="{{ $from }}"
                       :required="report === 'late'">
            </div>
            <div class="form-group" style="margin:0;" x-show="report === 'late'">
                <label class="form-label">To</label>
                <input type="date" name="to" class="form-input" value="{{ $to }}"
                       :required="report === 'late'">
            </div>

            {{-- Employee (required) + Leave type — leave history --}}
            <div class="form-group" style="margin:0;min-width:180px;" x-show="report === 'leave_history'">
                <label class="form-label">Employee <span style="color:#ef4444;">*</span></label>
                <select name="employee_id" class="form-select"
                        :disabled="report !== 'leave_history'"
                        :required="report === 'leave_history'">
                    <option value="">Select employee…</option>
                    @foreach($employees as $emp)
                        <option value="{{ $emp->id }}" {{ ($reportType === 'leave_history' && $employeeId == $emp->id) ? 'selected' : '' }}>{{ $emp->name }}</option>
                    @endforeach
                </select>
            </div>
            <div class="form-group" style="margin:0;min-width:180px;" x-show="report === 'leave_history'">
                <label class="form-label">Leave type</label>
                <select name="leave_type_id" class="form-select">
                    <option value="">All types</option>
                    @foreach($leaveTypes as $lt)
                        <option value="{{ $lt->id }}" {{ ($leaveTypeId ?? '') == $lt->id ? 'selected' : '' }}>{{ $lt->name }}</option>
                    @endforeach
                </select>
            </div>

            {{-- Employee + Year — leave balance --}}
            <div class="form-group" style="margin:0;min-width:180px;" x-show="report === 'leave_balance'">
                <label class="form-label">Employee <span style="color:#ef4444;">*</span></label>
                <select name="employee_id" class="form-select"
                        :disabled="report !== 'leave_balance'"
                        :required="report === 'leave_balance'">
                    <option value="">Select employee…</option>
                    @foreach($employees as $emp)
                        <option value="{{ $emp->id }}" {{ ($reportType === 'leave_balance' && $employeeId == $emp->id) ? 'selected' : '' }}>
                            {{ $emp->display_name ?? $emp->name }}
                        </option>
                    @endforeach
                </select>
            </div>
            <div class="form-group" style="margin:0;" x-show="report === 'leave_balance' || report === 'leave_summary'">
                <label class="form-label">Year</label>
                <select name="year" class="form-select" style="width:auto;">
                    @foreach($years as $y)
                        <option value="{{ $y }}" {{ $year == $y ? 'selected' : '' }}>{{ $y }}</option>
                    @endforeach
                </select>
            </div>

            <div style="padding-bottom:1px;">
                <button type="submit" class="btn btn-primary">Run report</button>
            </div>
        </form>
    </div>

    {{-- ── Leave balance results ── --}}
    @if($reportType === 'leave_balance' && $balanceData !== null)
        @php
            $emp         = $balanceEmployee;
            $hasAllowance = $emp->hasHolidayAllowance();
            $allowed     = $emp->days_allowed;
            $approved    = $balanceData['approved_allowance'];
            $pending     = $balanceData['pending_allowance'];
            $remaining   = $hasAllowance ? max(0, $allowed - $approved) : null;
            $remainColor = !$hasAllowance ? '#aaa' : ($remaining <= 5 ? '#ef4444' : ($remaining <= 10 ? '#f97316' : '#059669'));
        @endphp

        {{-- Stat cards --}}
        <div style="display:flex;gap:16px;flex-wrap:wrap;margin-bottom:16px;">
            <div class="stat-card" style="flex:1;min-width:120px;">
                <div class="stat-label">Allowance</div>
                <div class="stat-val">{{ $hasAllowance ? $allowed : '—' }}</div>
                <div class="stat-sub">days {{ $year }}</div>
            </div>
            <div class="stat-card" style="flex:1;min-width:120px;">
                <div class="stat-label">Approved</div>
                <div class="stat-val" style="color:#059669;">{{ number_format($approved, 1) }}</div>
                <div class="stat-sub">days taken</div>
            </div>
            <div class="stat-card" style="flex:1;min-width:120px;">
                <div class="stat-label">Pending</div>
                <div class="stat-val" style="color:#f97316;">{{ number_format($pending, 1) }}</div>
                <div class="stat-sub">awaiting approval</div>
            </div>
            <div class="stat-card" style="flex:1;min-width:120px;">
                <div class="stat-label">Remaining</div>
                <div class="stat-val" style="color:{{ $remainColor }};">
                    {{ $hasAllowance ? number_format($remaining, 1) : '—' }}
                </div>
                <div class="stat-sub">{{ $hasAllowance ? 'days left' : 'no allowance' }}</div>
            </div>
            @if($hasAllowance && $pending > 0)
            <div class="stat-card" style="flex:1;min-width:120px;">
                <div class="stat-label">After pending</div>
                @php $afterPending = max(0, $remaining - $pending); @endphp
                <div class="stat-val" style="color:{{ $afterPending <= 5 ? '#ef4444' : '#888' }};">
                    {{ number_format($afterPending, 1) }}
                </div>
                <div class="stat-sub">if all approved</div>
            </div>
            @endif
        </div>

        {{-- By leave type breakdown --}}
        @if($balanceData['by_type']->isNotEmpty())
        <div class="card" style="margin-bottom:16px;">
            <div class="card-title" style="margin-bottom:12px;">Breakdown by leave type</div>
            <table>
                <thead>
                    <tr>
                        <th>Leave type</th>
                        <th style="text-align:center;">Counts toward allowance</th>
                        <th style="text-align:center;">Approved</th>
                        <th style="text-align:center;">Pending</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($balanceData['by_type'] as $lt)
                        <tr>
                            <td>
                                <span style="font-size:12px;padding:2px 10px;border-radius:999px;font-weight:500;background:{{ $lt['color'] }}33;color:{{ $lt['color'] }};">
                                    {{ $lt['name'] }}
                                </span>
                            </td>
                            <td style="text-align:center;font-size:12px;">
                                @if($lt['counts'])
                                    <span style="color:#059669;font-weight:500;">Yes</span>
                                @else
                                    <span style="color:#aaa;">No</span>
                                @endif
                            </td>
                            <td style="text-align:center;font-weight:500;font-size:13px;">
                                {{ $lt['approved'] > 0 ? number_format($lt['approved'], 1) : '—' }}
                            </td>
                            <td style="text-align:center;font-size:13px;color:#f97316;">
                                {{ $lt['pending'] > 0 ? number_format($lt['pending'], 1) : '—' }}
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
        @endif

        {{-- All leave requests for the year --}}
        <div class="card">
            <div class="card-title" style="margin-bottom:12px;">
                {{ $emp->name }} — all leave in {{ $year }}
                <span style="font-size:12px;font-weight:400;color:#888;margin-left:4px;">
                    {{ $balanceData['approved_count'] }} approved &bull; {{ $balanceData['pending_count'] }} pending &bull; {{ $balanceData['rejected_count'] }} rejected
                </span>
            </div>

            @if($balanceData['leaves']->isEmpty())
                <div class="empty-state" style="padding:30px 0;">No leave records for {{ $year }}.</div>
            @else
                <div class="report-wrap">
                <table>
                    <thead>
                        <tr>
                            <th>Leave type</th>
                            <th>Start</th>
                            <th>End</th>
                            <th style="text-align:center;">Days</th>
                            <th style="text-align:center;">Status</th>
                            <th>Reason</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($balanceData['leaves'] as $lr)
                            @php
                                $sc = match($lr->status) {
                                    'approved' => 'background:#d1fae5;color:#065f46;',
                                    'rejected' => 'background:#fee2e2;color:#991b1b;',
                                    default    => 'background:#fef3c7;color:#92400e;',
                                };
                                $isFuture = $lr->start_date->isFuture();
                            @endphp
                            <tr style="{{ $lr->status === 'rejected' ? 'opacity:0.5;' : '' }}">
                                <td>
                                    @if($lr->leaveType)
                                        <span style="font-size:11px;padding:2px 8px;border-radius:999px;font-weight:500;background:{{ $lr->leaveType->color }}33;color:{{ $lr->leaveType->color }};">
                                            {{ $lr->leaveType->name }}
                                        </span>
                                    @else
                                        <span style="color:#bbb;font-size:12px;">Annual leave</span>
                                    @endif
                                </td>
                                <td style="font-size:13px;">
                                    {{ $lr->start_date->format('j M Y') }}
                                    @if($isFuture && $lr->status === 'pending')
                                        <span style="font-size:10px;color:#f97316;margin-left:4px;">upcoming</span>
                                    @endif
                                </td>
                                <td style="font-size:13px;">{{ $lr->end_date->format('j M Y') }}</td>
                                <td style="text-align:center;font-weight:500;font-size:13px;">{{ $lr->days }}</td>
                                <td style="text-align:center;">
                                    <span style="font-size:11px;padding:2px 8px;border-radius:999px;font-weight:500;{{ $sc }}">
                                        {{ $lr->status }}
                                    </span>
                                </td>
                                <td style="font-size:12px;color:#888;max-width:200px;">{{ $lr->reason ?: '—' }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
                </div>
            @endif
        </div>

    {{-- ── Late arrivals results ── --}}
    @elseif($reportType === 'late' && $results !== null)
        {{-- Summary strip --}}
        <div style="display:flex;gap:16px;flex-wrap:wrap;margin-bottom:16px;">
            <div class="stat-card" style="flex:1;min-width:140px;">
                <div class="stat-label">Late arrivals</div>
                <div class="stat-val" style="color:{{ $summary['total_late'] > 0 ? '#ef4444' : '#059669' }}">{{ $summary['total_late'] }}</div>
                <div class="stat-sub">{{ $summary['from'] }} – {{ $summary['to'] }}</div>
            </div>
            <div class="stat-card" style="flex:1;min-width:140px;">
                <div class="stat-label">Days recorded</div>
                <div class="stat-val">{{ $summary['total_days'] }}</div>
                <div class="stat-sub">with a sign-in</div>
            </div>
            <div class="stat-card" style="flex:1;min-width:140px;">
                <div class="stat-label">On-time rate</div>
                <div class="stat-val" style="color:#059669;">
                    {{ $summary['total_days'] > 0 ? round((($summary['total_days'] - $summary['total_late']) / $summary['total_days']) * 100) : 100 }}%
                </div>
                <div class="stat-sub">{{ $summary['employee'] }}</div>
            </div>
            <div class="stat-card" style="flex:1;min-width:140px;">
                <div class="stat-label">Total time late</div>
                <div class="stat-val" style="color:{{ $summary['total_minutes_late'] > 0 ? '#ef4444' : '#059669' }};">
                    @if($summary['total_hours_late'] > 0)
                        {{ $summary['total_hours_late'] }}h {{ $summary['remaining_mins_late'] }}m
                    @else
                        {{ $summary['remaining_mins_late'] }}m
                    @endif
                </div>
                <div class="stat-sub">combined late time</div>
            </div>
        </div>

        <div class="card">
            <div class="card-title" style="margin-bottom:12px;">
                Late arrivals — {{ $summary['employee'] }}
                <span style="font-size:12px;font-weight:400;color:#888;">{{ $summary['from'] }} to {{ $summary['to'] }}</span>
                <a href="{{ route('reports.export', ['report' => $reportType, 'employee_id' => $employeeId, 'from' => $from, 'to' => $to]) }}"
                   class="btn btn-outline btn-sm" style="margin-left:auto;">
                    &#8595; Export CSV
                </a>
            </div>

            @if($results->isEmpty())
                <div class="empty-state" style="padding:30px 0;">No late arrivals in this period.</div>
            @else
                <table>
                    <thead>
                        <tr>
                            <th>Employee</th>
                            <th>Date</th>
                            <th>Signed in</th>
                            <th>Minutes late</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($results as $row)
                            <tr>
                                <td style="font-weight:500;">{{ $row['employee'] }}</td>
                                <td style="font-size:13px;">{{ $row['date'] }}</td>
                                <td>
                                    <span style="font-size:13px;font-weight:600;color:#ef4444;">{{ $row['signed_in_at'] }}</span>
                                </td>
                                <td>
                                    <span style="font-size:12px;padding:2px 8px;border-radius:99px;background:#fee2e2;color:#b91c1c;font-weight:500;">
                                        +{{ $row['minutes_late'] }} min
                                    </span>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            @endif
        </div>

    {{-- ── Annual leave summary results ── --}}
    @elseif($reportType === 'leave_summary' && $leaveData !== null)
        <div class="card">
            <div class="card-title" style="margin-bottom:12px;">
                Annual leave summary — {{ $year }}
                <a href="{{ route('reports.export', ['report' => 'leave_summary', 'year' => $year]) }}"
                   class="btn btn-outline btn-sm" style="margin-left:auto;">
                    &#8595; Export CSV
                </a>
            </div>

            @if($leaveData->isEmpty())
                <div class="empty-state" style="padding:30px 0;">No employees found.</div>
            @else
            <div class="report-wrap">
            <table>
                <thead>
                    <tr>
                        <th>Employee</th>
                        <th style="text-align:center;">Allowed</th>
                        <th style="text-align:center;">Used</th>
                        <th style="text-align:center;">Remaining</th>
                        @foreach($leaveTypes as $lt)
                            <th style="text-align:center;">
                                <span style="padding:2px 8px;border-radius:999px;font-size:11px;font-weight:500;background:{{ $lt->color }}33;">
                                    {{ $lt->name }}
                                </span>
                            </th>
                        @endforeach
                    </tr>
                </thead>
                <tbody>
                    @foreach($leaveData as $emp)
                        @php
                            $remaining   = $emp['annual_remaining'] === PHP_INT_MAX ? null : $emp['annual_remaining'];
                            $allowed     = $emp['days_allowed'];
                            $pct         = ($emp['has_allowance'] && $allowed > 0)
                                ? min(100, round(($emp['annual_used'] / $allowed) * 100)) : 0;
                            $remainColor = !$emp['has_allowance'] ? '#aaa'
                                : ($remaining <= 5 ? '#ef4444' : ($remaining <= 10 ? '#f97316' : '#059669'));
                            $barColor    = $pct >= 80 ? '#ef4444' : ($pct >= 60 ? '#f97316' : '#38bdf8');
                        @endphp
                        <tr>
                            <td>
                                <div style="display:flex;align-items:center;gap:8px;">
                                    @if($emp['photo'])
                                        <img src="{{ $emp['photo'] }}" alt="{{ $emp['name'] }}"
                                             style="width:28px;height:28px;border-radius:50%;object-fit:cover;flex-shrink:0;">
                                    @else
                                        <div class="avatar" style="width:28px;height:28px;font-size:10px;flex-shrink:0;background:{{ $emp['color'] ?? '#38bdf8' }}33;color:{{ $emp['color'] ?? '#38bdf8' }};">
                                            {{ $emp['initials'] }}
                                        </div>
                                    @endif
                                    <div>
                                        <div style="font-weight:500;font-size:13px;">{{ $emp['name'] }}</div>
                                        <div style="font-size:11px;color:#888;">{{ $emp['role_label'] }}</div>
                                    </div>
                                </div>
                            </td>
                            <td style="text-align:center;font-size:13px;">
                                @if($emp['has_allowance']) {{ $emp['days_allowed'] }} @else <span style="color:#bbb;">—</span> @endif
                            </td>
                            <td style="text-align:center;font-size:13px;">
                                @if($emp['has_allowance']) {{ number_format($emp['annual_used'], 1) }} @else <span style="color:#bbb;">—</span> @endif
                            </td>
                            <td style="text-align:center;">
                                @if($emp['has_allowance'])
                                    <span style="font-size:13px;font-weight:600;color:{{ $remainColor }};">
                                        {{ number_format($remaining, 1) }}
                                    </span>
                                    <div class="remaining-bar">
                                        <div class="remaining-bar-fill" style="width:{{ $pct }}%;background:{{ $barColor }};"></div>
                                    </div>
                                @else
                                    <span style="color:#bbb;font-size:12px;">No allowance</span>
                                @endif
                            </td>
                            @foreach($leaveTypes as $lt)
                                @php $days = $emp['by_type'][$lt->id] ?? 0; @endphp
                                <td style="text-align:center;font-size:13px;">
                                    @if($days > 0)
                                        <span style="font-weight:500;">{{ number_format($days, 1) }}</span>
                                    @else
                                        <span style="color:#ddd;">—</span>
                                    @endif
                                </td>
                            @endforeach
                        </tr>
                    @endforeach
                </tbody>
            </table>
            </div>
            @endif
        </div>

    {{-- ── Leave history results ── --}}
    @elseif($reportType === 'leave_history' && $historyData !== null)
        @php
            $empName  = $historyEmployee->name;
            $typeName = $historyLeaveType?->name ?? 'All types';
            $approved = $historyData->where('status', 'approved');
            $usedDays = $approved->sum('days');
            $showAllowance = $historyLeaveType?->counts_toward_allowance && $historyEmployee->hasHolidayAllowance();
        @endphp

        @if($showAllowance)
            <div style="display:flex;gap:16px;flex-wrap:wrap;margin-bottom:16px;">
                <div class="stat-card" style="flex:1;min-width:120px;">
                    <div class="stat-label">Allowed</div>
                    <div class="stat-val">{{ $historyEmployee->days_allowed }}</div>
                    <div class="stat-sub">days</div>
                </div>
                <div class="stat-card" style="flex:1;min-width:120px;">
                    <div class="stat-label">Used</div>
                    <div class="stat-val">{{ number_format($usedDays, 1) }}</div>
                    <div class="stat-sub">approved</div>
                </div>
                <div class="stat-card" style="flex:1;min-width:120px;">
                    @php $remaining = max(0, $historyEmployee->days_allowed - $usedDays); @endphp
                    <div class="stat-label">Remaining</div>
                    <div class="stat-val" style="color:{{ $remaining <= 5 ? '#ef4444' : ($remaining <= 10 ? '#f97316' : '#059669') }}">
                        {{ number_format($remaining, 1) }}
                    </div>
                    <div class="stat-sub">days left</div>
                </div>
            </div>
        @endif

        <div class="card">
            <div class="card-title" style="margin-bottom:12px;">
                {{ $empName }} — {{ $typeName }}
                <a href="{{ route('reports.export', ['report' => 'leave_history', 'employee_id' => $employeeId, 'leave_type_id' => $leaveTypeId]) }}"
                   class="btn btn-outline btn-sm" style="margin-left:auto;">
                    &#8595; Export CSV
                </a>
            </div>

            @if($historyData->isEmpty())
                <div class="empty-state" style="padding:30px 0;">No leave records found.</div>
            @else
                <div class="report-wrap">
                <table>
                    <thead>
                        <tr>
                            @if(!$historyLeaveType)<th>Leave type</th>@endif
                            <th>Start date</th>
                            <th>End date</th>
                            <th style="text-align:center;">Days</th>
                            <th style="text-align:center;">Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($historyData as $lr)
                            <tr>
                                @if(!$historyLeaveType)
                                    <td>
                                        @if($lr->leaveType)
                                            <span style="font-size:11px;padding:2px 8px;border-radius:999px;font-weight:500;background:{{ $lr->leaveType->color }}33;color:{{ $lr->leaveType->color }}">
                                                {{ $lr->leaveType->name }}
                                            </span>
                                        @else
                                            <span style="color:#bbb;font-size:12px;">—</span>
                                        @endif
                                    </td>
                                @endif
                                <td style="font-size:13px;">{{ $lr->start_date->format('j M Y') }}</td>
                                <td style="font-size:13px;">{{ $lr->end_date->format('j M Y') }}</td>
                                <td style="text-align:center;font-weight:500;">{{ $lr->days }}</td>
                                <td style="text-align:center;">
                                    @php
                                        $sc = match($lr->status) {
                                            'approved' => 'background:#d1fae5;color:#065f46;',
                                            'rejected' => 'background:#fee2e2;color:#991b1b;',
                                            default    => 'background:#fef3c7;color:#92400e;',
                                        };
                                    @endphp
                                    <span style="font-size:11px;padding:2px 8px;border-radius:999px;font-weight:500;{{ $sc }}">
                                        {{ $lr->status }}
                                    </span>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
                </div>
            @endif
        </div>

    @else
        <div class="card">
            <div class="empty-state" style="padding:40px 0;">Select options and run the report.</div>
        </div>
    @endif
</div>
</x-app-layout>
