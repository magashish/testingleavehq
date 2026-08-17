<?php

namespace App\Http\Controllers;

use App\Models\BankHoliday;
use App\Models\LeaveRequest;
use App\Models\LeaveType;
use App\Models\TeamNotice;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Http\Request;

class TeamController extends Controller
{
    public function index(Request $request)
    {
        if (!Auth::user()->isManager()) {
            abort(403);
        }

        $today      = now()->toDateString();
        $weekDates  = $this->getWeekDates();

        // Month navigation: ?month=2026-05 to view other months
        $monthParam  = $request->get('month');
        $viewMonth   = $monthParam
            ? Carbon::createFromFormat('Y-m', $monthParam)->startOfMonth()
            : now()->startOfMonth();

        $monthStart = $viewMonth->toDateString();
        $monthEnd   = $viewMonth->copy()->endOfMonth()->toDateString();
        $monthDays  = $viewMonth->daysInMonth;

        $prevMonth      = $viewMonth->copy()->subMonth()->format('Y-m');
        $nextMonth      = $viewMonth->copy()->addMonth()->format('Y-m');
        $viewMonthLabel = $viewMonth->format('F Y');
        $isCurrentMonth = $viewMonth->isSameMonth(now());
        $initialView    = $request->get('view', 'today');
        $preloadFrom    = $request->get('from', '');
        $preloadTo      = $request->get('to', '');
        if ($preloadFrom && $preloadTo) {
            $initialView = 'custom';
        }

        // Colour to use for permanent-WFH cells — matches the WFH leave type setting
        $wfhLeaveColor  = LeaveType::where('name', 'like', '%work from home%')
            ->orWhere('name', 'like', '%working from home%')
            ->orWhere(fn($q) => $q->whereRaw('LOWER(name) = ?', ['wfh']))
            ->value('color');

        $monthDayInfo = collect(range(1, $monthDays))->map(function ($day) use ($viewMonth) {
            $d = $viewMonth->copy()->addDays($day - 1);
            return [
                'num'     => $day,
                'label'   => $d->format('D')[0],
                'weekend' => $d->isWeekend(),
            ];
        })->values()->toArray();

        // Load leaves covering either the viewed month or the current week
        $leaveFrom = min($weekDates[0], $monthStart);
        $leaveTo   = max($weekDates[4], $monthEnd);

        $publicHolidays = BankHoliday::whereBetween('date', [$monthStart, $monthEnd])
            ->pluck('date')
            ->map(fn($d) => $d->toDateString())
            ->toArray();

        $employees = User::active()->with([
            'leaveRequests' => fn($q) => $q
                ->with('leaveType')
                ->where('status', 'approved')
                ->where('start_date', '<=', $leaveTo)
                ->where('end_date', '>=', $leaveFrom),
            'checkins' => fn($q) => $q->where('date', $today),
        ])->orderBy('name')->get();

        $dayOfWeek = now()->dayOfWeekIso; // 1=Mon … 7=Sun
        $todayIdx  = ($dayOfWeek >= 1 && $dayOfWeek <= 5) ? $dayOfWeek - 1 : null;

        $teamData = $employees->map(function ($emp) use ($today, $weekDates, $monthStart, $monthEnd, $publicHolidays, $wfhLeaveColor) {
            $todayCheckin = $emp->checkins->first();
            $signedIn     = $todayCheckin && $todayCheckin->checked_in_at && !$todayCheckin->signed_out_at;
            return [
                'id'          => $emp->id,
                'name'        => $emp->name,
                'role'        => $emp->role,
                'color'       => $emp->color,
                'initials'    => $emp->initials(),
                'photo_url'   => $emp->photoUrl(),
                'location'    => $emp->work_location,
                'status'      => ($todayFull = $this->getUserStatusFull($emp, $today, $publicHolidays, $wfhLeaveColor))['s'],
                'statusColor' => $todayFull['c'],
                'statusTip'      => $todayFull['tip'],
                'statusLoc'      => $todayFull['loc'],
                'statusTypeName' => $todayFull['type_name'] ?? null,
                'is_medical'     => $todayFull['is_medical'] ?? false,
                'is_half_day'    => $todayFull['is_half_day'] ?? false,
                'signed_in'   => $signedIn,
                'time'        => $todayCheckin?->checked_in_at?->format('H:i') ?? '—',
                'week'        => array_map(fn($d) => $this->getUserStatusFull($emp, $d, $publicHolidays, $wfhLeaveColor), $weekDates),
                'month'       => $this->getMonthStats($emp, $monthStart, $monthEnd, $publicHolidays),
                'monthGrid'   => $this->getMonthDayStatuses($emp, $monthStart, $monthEnd, $publicHolidays, $wfhLeaveColor),
            ];
        })->values();

        $notices        = $this->buildNotices($employees, $today);
        $weekLabels     = array_map(fn($d) => Carbon::parse($d)->format('D'), $weekDates);
        $managerNotices = TeamNotice::with(['author', 'targetUser'])->latest()->get();
        $allEmployees   = $employees;

        return view('team.index', compact(
            'teamData', 'notices', 'todayIdx', 'weekLabels', 'managerNotices', 'allEmployees',
            'monthDayInfo', 'prevMonth', 'nextMonth', 'viewMonthLabel', 'isCurrentMonth', 'initialView',
            'preloadFrom', 'preloadTo'
        ));
    }

    public function custom(Request $request)
    {
        if (!Auth::user()->isManager()) abort(403);

        $validated = $request->validate([
            'from' => 'required|date',
            'to'   => 'required|date|after_or_equal:from',
        ]);

        $from = $validated['from'];
        $to   = $validated['to'];

        $publicHolidays = BankHoliday::whereBetween('date', [$from, $to])
            ->pluck('date')
            ->map(fn($d) => $d->toDateString())
            ->toArray();

        $wfhLeaveColor = LeaveType::where('name', 'like', '%work from home%')
            ->orWhere('name', 'like', '%working from home%')
            ->orWhere(fn($q) => $q->whereRaw('LOWER(name) = ?', ['wfh']))
            ->value('color');

        $employees = User::active()->with([
            'leaveRequests' => fn($q) => $q
                ->with('leaveType')
                ->where('status', 'approved')
                ->where('start_date', '<=', $to)
                ->where('end_date', '>=', $from),
        ])->orderBy('name')->get();

        // Build day-by-day column info
        $dayInfo = [];
        $d = Carbon::parse($from);
        $e = Carbon::parse($to);
        $prevMonthSeen = null;
        while ($d->lte($e)) {
            $monthLabel = ($prevMonthSeen !== $d->format('Y-m')) ? $d->format('M') : null;
            $prevMonthSeen = $d->format('Y-m');
            $dayInfo[] = [
                'date'       => $d->toDateString(),
                'num'        => (int) $d->format('j'),
                'label'      => $d->format('D')[0],
                'weekend'    => $d->isWeekend(),
                'monthLabel' => $monthLabel,
            ];
            $d->addDay();
        }

        $teamData = $employees->map(function ($emp) use ($from, $to, $publicHolidays, $wfhLeaveColor) {
            $leave = 0;
            $sick  = 0;

            foreach ($emp->leaveRequests as $l) {
                $start    = max($l->start_date->toDateString(), $from);
                $end      = min($l->end_date->toDateString(), $to);
                $typeName = strtolower($l->leaveType?->name ?? '');
                $isSick   = str_contains($typeName, 'sick');

                $d = Carbon::parse($start);
                $e = Carbon::parse($end);
                while ($d->lte($e)) {
                    if (!$d->isWeekend() && !in_array($d->toDateString(), $publicHolidays)) {
                        $isSick ? $sick++ : $leave++;
                    }
                    $d->addDay();
                }
            }

            $workingDays = $this->countWorkingDays($from, min($to, now()->toDateString()), $publicHolidays);
            $nonLeave    = max(0, $workingDays - $leave - $sick);
            $office      = $emp->work_location === 'office' ? $nonLeave : 0;
            $remote      = $emp->work_location === 'remote' ? $nonLeave : 0;
            $wfh         = $emp->work_location === 'wfh'    ? $nonLeave : 0;

            return [
                'id'       => $emp->id,
                'name'     => $emp->name,
                'role'     => $emp->role,
                'color'    => $emp->color,
                'initials' => $emp->initials(),
                'photo_url'=> $emp->photoUrl(),
                'office'   => $office,
                'remote'   => $remote,
                'wfh'      => $wfh,
                'leave'    => $leave,
                'sick'     => $sick,
                'dayGrid'  => $this->getMonthDayStatuses($emp, $from, $to, $publicHolidays, $wfhLeaveColor),
            ];
        })->values();

        $totals = [
            'office' => $teamData->sum('office'),
            'remote' => $teamData->sum('remote'),
            'wfh'    => $teamData->sum('wfh'),
            'leave'  => $teamData->sum('leave'),
            'sick'   => $teamData->sum('sick'),
        ];

        return response()->json(compact('teamData', 'totals', 'dayInfo'));
    }

    public function storeNotice(Request $request)
    {
        if (!Auth::user()->isManager()) abort(403);

        $validated = $request->validate([
            'message'        => 'required|string|max:500',
            'target_user_id' => 'nullable|exists:users,id',
        ]);

        TeamNotice::create([
            'created_by'     => Auth::id(),
            'target_user_id' => $validated['target_user_id'] ?? null,
            'message'        => $validated['message'],
        ]);

        return back()->with('success', 'Notice posted.');
    }

    public function destroyNotice(TeamNotice $notice)
    {
        if (!Auth::user()->isManager()) abort(403);
        $notice->delete();
        return back();
    }

    // ── Helpers ───────────────────────────────────────────────────────────────

    /**
     * Returns full cell data: status string, leave-type color, and tooltip text.
     */
    private function getUserStatusFull(User $emp, string $date, array $publicHolidays = [], ?string $wfhLeaveColor = null): array
    {
        if (in_array($date, $publicHolidays)) {
            return ['s' => 'holiday', 'c' => null, 'tip' => 'Public holiday', 'booked' => true, 'loc' => null, 'is_medical' => false];
        }

        $leaves = $emp->leaveRequests->filter(
            fn($l) => $l->start_date->toDateString() <= $date && $l->end_date->toDateString() >= $date
        );

        if ($leaves->isNotEmpty()) {
            // Priority: short leave (medical) > sick > other (WFH, annual, etc.)
            $leave = $leaves->first(fn($l) => $l->is_short_leave)
                ?? $leaves->first(fn($l) => str_contains(strtolower($l->leaveType?->name ?? ''), 'sick'))
                ?? $leaves->first();
            $color  = $leave->leaveType?->color;
            // If there's a WFH leave on the same day, use that as the effective location
            $wfhLeaveOnDay = $leaves->first(function ($l) {
                $n = strtolower($l->leaveType?->name ?? '');
                return str_contains($n, 'wfh') || str_contains($n, 'work from home') || str_contains($n, 'working from home');
            });
            $empLoc = $wfhLeaveOnDay ? 'wfh' : ($emp->work_location ?? 'office');

            if ($leave->is_short_leave) {
                $part   = $leave->short_leave_part;
                $status = $part ? "medical-{$part}" : 'medical';
                $tip    = 'Medical' . ($part ? ' (' . ($part === 'morning' ? 'AM' : 'PM') . ')' : '');
                if ($leave->short_leave_from && $leave->short_leave_to) {
                    $tip .= ' ' . substr($leave->short_leave_from, 0, 5) . '–' . substr($leave->short_leave_to, 0, 5);
                }
                if ($leave->reason) $tip .= ': ' . $leave->reason;
                // Medical: no cell color override — cell stays as employee's base location
                return ['s' => $status, 'c' => null, 'tip' => $tip, 'booked' => true, 'loc' => $empLoc, 'is_medical' => true];
            }

            $typeName  = strtolower($leave->leaveType?->name ?? '');
            $isSick    = str_contains($typeName, 'sick');
            $isWfh     = str_contains($typeName, 'wfh') || str_contains($typeName, 'work from home') || str_contains($typeName, 'working from home');
            $isMedical = str_contains($typeName, 'medical') || str_contains($typeName, 'appointment') || str_contains($typeName, 'dentist') || str_contains($typeName, 'doctor') || str_contains($typeName, 'hospital');

            if ($isMedical) {
                // Medical leave types: red corner only, no cell color override
                $tip = $leave->leaveType?->name ?? 'Medical Appointment';
                if ($leave->reason) $tip .= ': ' . $leave->reason;
                return ['s' => 'medical', 'c' => null, 'tip' => $tip, 'booked' => true, 'loc' => $empLoc, 'is_medical' => true];
            }

            $status    = $isSick ? 'sick' : ($isWfh ? 'wfh' : 'leave');
            $typeName  = $leave->leaveType?->name ?? ($isSick ? 'Sick leave' : 'Leave');
            $tip       = $typeName;
            if ($leave->is_half_day) $tip .= ' (half day)';
            if ($leave->reason) $tip .= ': ' . $leave->reason;
            return ['s' => $status, 'c' => $color, 'tip' => $tip, 'type_name' => $typeName, 'booked' => true, 'loc' => $empLoc, 'is_medical' => false, 'is_half_day' => $leave->is_half_day];
        }

        $loc   = $emp->work_location ?? 'unknown';
        $color = ($loc === 'wfh') ? $wfhLeaveColor : null;
        return ['s' => $loc, 'c' => $color, 'tip' => null, 'booked' => false, 'loc' => null, 'is_medical' => false];
    }

    private function getUserStatus(User $emp, string $date, array $publicHolidays = []): string
    {
        return $this->getUserStatusFull($emp, $date, $publicHolidays)['s'];
    }

    private function getWeekDates(): array
    {
        $monday = now()->startOfWeek(Carbon::MONDAY);

        return array_map(fn($i) => $monday->copy()->addDays($i)->toDateString(), range(0, 4));
    }

    private function countWorkingDays(string $from, string $to, array $publicHolidays = []): int
    {
        $count = 0;
        $d = Carbon::parse($from);
        $e = Carbon::parse($to);
        while ($d->lte($e)) {
            if (!$d->isWeekend() && !in_array($d->toDateString(), $publicHolidays)) {
                $count++;
            }
            $d->addDay();
        }
        return $count;
    }

    private function getMonthDayStatuses(User $emp, string $monthStart, string $monthEnd, array $publicHolidays = [], ?string $wfhLeaveColor = null): array
    {
        $statuses = [];
        $today    = now()->toDateString();
        $d        = Carbon::parse($monthStart);
        $end      = Carbon::parse($monthEnd);

        while ($d->lte($end)) {
            $dateStr = $d->toDateString();
            if ($d->isWeekend()) {
                $statuses[] = ['s' => 'weekend', 'c' => null, 'tip' => null];
            } else {
                $cell = $this->getUserStatusFull($emp, $dateStr, $publicHolidays, $wfhLeaveColor);
                // Future days: show if booked, or if employee has a permanent location (wfh/remote)
                if ($dateStr > $today && !($cell['booked'] ?? false) && !in_array($cell['s'], ['wfh', 'remote'])) {
                    $statuses[] = ['s' => 'unknown', 'c' => null, 'tip' => null, 'booked' => false];
                } else {
                    $statuses[] = $cell;
                }
            }
            $d->addDay();
        }

        return $statuses;
    }

    private function getMonthStats(User $emp, string $monthStart, string $monthEnd, array $publicHolidays = []): array
    {
        $leave = 0;
        $sick  = 0;

        foreach ($emp->leaveRequests as $l) {
            $start  = max($l->start_date->toDateString(), $monthStart);
            $end    = min($l->end_date->toDateString(), $monthEnd);
            $isSick = str_contains(strtolower($l->leaveType?->name ?? ''), 'sick');

            $d = Carbon::parse($start);
            $e = Carbon::parse($end);
            while ($d->lte($e)) {
                if (!$d->isWeekend() && !in_array($d->toDateString(), $publicHolidays)) {
                    $isSick ? $sick++ : $leave++;
                }
                $d->addDay();
            }
        }

        $workingDays = $this->countWorkingDays($monthStart, min($monthEnd, now()->toDateString()), $publicHolidays);
        $nonLeave    = max(0, $workingDays - $leave - $sick);
        $office      = $emp->work_location === 'office' ? $nonLeave : 0;
        $remote      = $emp->work_location === 'remote' ? $nonLeave : 0;
        $wfh         = $emp->work_location === 'wfh'    ? $nonLeave : 0;

        return compact('office', 'remote', 'wfh', 'leave', 'sick');
    }

    private function buildNotices(Collection $employees, string $today): array
    {
        $notices = [];

        foreach ($employees as $emp) {
            $leave = $emp->leaveRequests->first(
                fn($l) => $l->start_date->toDateString() <= $today && $l->end_date->toDateString() >= $today
            );

            if (!$leave) continue;

            $isSick = str_contains(strtolower($leave->leaveType?->name ?? ''), 'sick');

            if ($isSick) {
                $daysIn    = max(1, Carbon::parse($leave->start_date)->diffInWeekdays(now()) + 1);
                $notices[] = ['type' => 'warn', 'title' => "{$emp->name} on sick leave", 'meta' => "Day {$daysIn} of absence"];
            } else {
                $returns   = Carbon::parse($leave->end_date)->addWeekday()->format('D j M');
                $typeName  = $leave->leaveType?->name ?? 'leave';
                $notices[] = ['type' => 'warn', 'title' => "{$emp->name} on {$typeName}", 'meta' => "Returns {$returns}"];
            }
        }

        $pendingCount = LeaveRequest::where('status', 'pending')->count();
        if ($pendingCount > 0) {
            $notices[] = [
                'type'  => 'info',
                'title' => "{$pendingCount} pending leave request" . ($pendingCount > 1 ? 's' : ''),
                'meta'  => 'Awaiting your approval',
            ];
        }

        return $notices;
    }
}
