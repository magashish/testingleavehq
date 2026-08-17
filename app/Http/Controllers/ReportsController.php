<?php

namespace App\Http\Controllers;

use App\Models\DailyCheckin;
use App\Models\LeaveRequest;
use App\Models\LeaveType;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class ReportsController extends Controller
{
    public function index(Request $request)
    {
        if (!Auth::user()->isManager()) abort(403);

        $employees  = User::orderBy('name')->get()->map(function ($emp) {
            $emp->display_name = $emp->isArchived()
                ? $emp->name . ' (Left' . ($emp->finish_date ? ': ' . $emp->finish_date->format('M Y') : '') . ')'
                : $emp->name;
            return $emp;
        });
        $leaveTypes = LeaveType::orderBy('name')->get();
        $results         = null;
        $summary         = null;
        $leaveData       = null;
        $historyData     = null;
        $historyEmployee = null;
        $historyLeaveType = null;

        $employeeId  = $request->get('employee_id');
        $leaveTypeId = $request->get('leave_type_id');
        $from        = $request->get('from');
        $to          = $request->get('to');
        $reportType  = $request->get('report', 'late');
        $year        = (int) $request->get('year', now()->year);
        $years       = range(now()->year, max(now()->year - 4, 2020));

        if ($reportType === 'leave_history' && $request->has('report') && $employeeId) {
            $historyEmployee  = User::findOrFail($employeeId);
            $historyLeaveType = $leaveTypeId ? LeaveType::find($leaveTypeId) : null;

            $historyData = LeaveRequest::with('leaveType')
                ->where('employee_id', $employeeId)
                ->when($leaveTypeId, fn($q) => $q->where('leave_type_id', $leaveTypeId))
                ->orderBy('start_date')
                ->get();

        } elseif ($reportType === 'leave_summary' && $request->has('report')) {
            $leaveData = User::with(['leaveRequests' => fn($q) => $q
                ->where('status', 'approved')
                ->whereYear('start_date', $year)
                ->with('leaveType')
            ])
            ->orderBy('name')
            ->get()
            ->map(function ($emp) use ($leaveTypes) {
                $byType = $leaveTypes->mapWithKeys(fn($lt) => [
                    $lt->id => $emp->leaveRequests->where('leave_type_id', $lt->id)->sum('days'),
                ]);

                $annualUsed = $emp->leaveRequests
                    ->filter(fn($lr) => is_null($lr->leave_type_id)
                        || ($lr->leaveType && $lr->leaveType->counts_toward_allowance))
                    ->sum('days');

                $remaining = $emp->hasHolidayAllowance()
                    ? max(0, $emp->days_allowed - $annualUsed)
                    : PHP_INT_MAX;

                return [
                    'id'               => $emp->id,
                    'name'             => $emp->name,
                    'role_label'       => $emp->roleBadgeLabel(),
                    'color'            => $emp->color,
                    'initials'         => $emp->initials(),
                    'photo'            => $emp->photoUrl(),
                    'has_allowance'    => $emp->hasHolidayAllowance(),
                    'days_allowed'     => $emp->days_allowed,
                    'annual_used'      => $annualUsed,
                    'annual_remaining' => $remaining,
                    'by_type'          => $byType,
                ];
            })
            ->sortBy('annual_remaining')
            ->values();

        } elseif ($from && $to) {
            $query = DailyCheckin::with('user')
                ->whereBetween('date', [$from, $to])
                ->whereNotNull('checked_in_at')
                ->orderBy('date')
                ->orderBy('checked_in_at');

            if ($employeeId) {
                $query->where('user_id', $employeeId);
            }

            $checkins = $query->get();

            if ($reportType === 'late') {
                $lateThreshold = '09:00:00';

                $results = $checkins->filter(function ($c) {
                    $minutesLate = (int) Carbon::parse($c->date->toDateString() . ' 09:00:00')
                        ->diffInMinutes($c->checked_in_at);
                    return $minutesLate >= 1;
                })->map(function ($c) {
                    $minutesLate = (int) Carbon::parse($c->date->toDateString() . ' 09:00:00')
                        ->diffInMinutes($c->checked_in_at);
                    return [
                        'employee'     => $c->user->name,
                        'date'         => $c->date->format('l, j M Y'),
                        'signed_in_at' => $c->checked_in_at->format('H:i'),
                        'minutes_late' => $minutesLate,
                    ];
                })->values();

                $totalMinutesLate = $results->sum('minutes_late');

                $summary = [
                    'total_late'          => $results->count(),
                    'total_days'          => $checkins->count(),
                    'total_minutes_late'  => $totalMinutesLate,
                    'total_hours_late'    => floor($totalMinutesLate / 60),
                    'remaining_mins_late' => $totalMinutesLate % 60,
                    'employee'            => $employeeId ? User::find($employeeId)?->name : 'All employees',
                    'from'                => Carbon::parse($from)->format('j M Y'),
                    'to'                  => Carbon::parse($to)->format('j M Y'),
                ];
            }
        }

        return view('reports.index', compact(
            'employees', 'leaveTypes', 'results', 'summary', 'leaveData',
            'historyData', 'historyEmployee', 'historyLeaveType',
            'employeeId', 'leaveTypeId', 'from', 'to', 'reportType', 'year', 'years'
        ));
    }

    public function export(Request $request)
    {
        if (!Auth::user()->isManager()) abort(403);

        $employeeId = $request->get('employee_id');
        $from       = $request->get('from');
        $to         = $request->get('to');
        $reportType = $request->get('report', 'late');

        $year        = (int) $request->get('year', now()->year);
        $leaveTypeId = $request->get('leave_type_id');

        if ($reportType === 'leave_history') {
            if (!$employeeId) abort(400);

            $employee  = User::findOrFail($employeeId);
            $leaveType = $leaveTypeId ? LeaveType::find($leaveTypeId) : null;

            $rows = LeaveRequest::with('leaveType')
                ->where('employee_id', $employeeId)
                ->when($leaveTypeId, fn($q) => $q->where('leave_type_id', $leaveTypeId))
                ->orderBy('start_date')
                ->get()
                ->map(fn($lr) => [
                    'Employee'   => $employee->name,
                    'Leave Type' => $lr->leaveType?->name ?? 'Annual Leave',
                    'Start Date' => $lr->start_date->format('j M Y'),
                    'End Date'   => $lr->end_date->format('j M Y'),
                    'Days'       => $lr->days,
                    'Status'     => $lr->status,
                ]);

            $slug     = str($employee->name)->slug();
            $typePart = $leaveType ? '-' . str($leaveType->name)->slug() : '';
            $filename = "leave-history-{$slug}{$typePart}.csv";
            $headers  = [
                'Content-Type'        => 'text/csv',
                'Content-Disposition' => 'attachment; filename="' . $filename . '"',
            ];

            return response()->stream(function () use ($rows) {
                $handle = fopen('php://output', 'w');
                if ($rows->isNotEmpty()) fputcsv($handle, array_keys($rows->first()));
                foreach ($rows as $row) fputcsv($handle, $row);
                fclose($handle);
            }, 200, $headers);
        }

        if ($reportType === 'leave_summary') {
            $leaveTypes = LeaveType::orderBy('name')->get();
            $employees  = User::with(['leaveRequests' => fn($q) => $q
                ->where('status', 'approved')
                ->whereYear('start_date', $year)
                ->with('leaveType')
            ])->orderBy('name')->get();

            $rows = $employees->map(function ($emp) use ($leaveTypes) {
                $annualUsed = $emp->leaveRequests
                    ->filter(fn($lr) => is_null($lr->leave_type_id)
                        || ($lr->leaveType && $lr->leaveType->counts_toward_allowance))
                    ->sum('days');

                $row = [
                    'Employee'         => $emp->name,
                    'Role'             => $emp->roleBadgeLabel(),
                    'Annual Allowed'   => $emp->hasHolidayAllowance() ? $emp->days_allowed : 'N/A',
                    'Annual Used'      => $emp->hasHolidayAllowance() ? $annualUsed : 'N/A',
                    'Annual Remaining' => $emp->hasHolidayAllowance()
                        ? max(0, $emp->days_allowed - $annualUsed) : 'N/A',
                ];

                foreach ($leaveTypes as $lt) {
                    $row[$lt->name] = $emp->leaveRequests->where('leave_type_id', $lt->id)->sum('days');
                }

                return $row;
            });

            $filename = 'leave-summary-' . $year . '.csv';
            $headers  = [
                'Content-Type'        => 'text/csv',
                'Content-Disposition' => 'attachment; filename="' . $filename . '"',
            ];

            return response()->stream(function () use ($rows) {
                $handle = fopen('php://output', 'w');
                if ($rows->isNotEmpty()) fputcsv($handle, array_keys($rows->first()));
                foreach ($rows as $row) fputcsv($handle, $row);
                fclose($handle);
            }, 200, $headers);
        }

        if (!$from || !$to) abort(400);

        $query = DailyCheckin::with('user')
            ->whereBetween('date', [$from, $to])
            ->whereNotNull('checked_in_at')
            ->orderBy('date')
            ->orderBy('checked_in_at');

        if ($employeeId) {
            $query->where('user_id', $employeeId);
        }

        $checkins = $query->get();
        $rows     = collect();

        if ($reportType === 'late') {
            $rows = $checkins->filter(function ($c) {
                    return (int) Carbon::parse($c->date->toDateString() . ' 09:00:00')
                        ->diffInMinutes($c->checked_in_at) >= 1;
                })
                ->map(function ($c) {
                    $minutesLate = (int) Carbon::parse($c->date->toDateString() . ' 09:00:00')
                        ->diffInMinutes($c->checked_in_at);
                    return [
                        'Employee'     => $c->user->name,
                        'Date'         => $c->date->format('l, j M Y'),
                        'Signed In'    => $c->checked_in_at->format('H:i'),
                        'Minutes Late' => $minutesLate,
                    ];
                })->values();
        }

        $filename = 'late-arrivals-' . $from . '-to-' . $to . '.csv';

        $headers = [
            'Content-Type'        => 'text/csv',
            'Content-Disposition' => 'attachment; filename="' . $filename . '"',
        ];

        $callback = function () use ($rows) {
            $handle = fopen('php://output', 'w');
            if ($rows->isNotEmpty()) {
                fputcsv($handle, array_keys($rows->first()));
            }
            foreach ($rows as $row) {
                fputcsv($handle, $row);
            }
            fclose($handle);
        };

        return response()->stream($callback, 200, $headers);
    }
}
