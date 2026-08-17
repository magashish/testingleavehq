<?php

namespace App\Http\Controllers;

use App\Models\BankHoliday;
use App\Models\Department;
use App\Models\LeaveRequest;
use App\Models\LeaveType;
use App\Models\User;
use App\Mail\LeaveRequested;
use App\Mail\LeaveStatusUpdated;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Mail;

class LeaveController extends Controller
{
    public function index()
    {
        $user = Auth::user();

        $leaves = LeaveRequest::with(['employee', 'leaveType', 'approvedBy'])
            ->when(!$user->isManager(), fn($q) => $q->where('employee_id', $user->id))
            ->latest()
            ->get();

        $bankHolidays = BankHoliday::orderBy('date')->get();
        $leaveTypes   = LeaveType::where('is_active', true)->orderBy('name')->get();

        $allEmployees = $user->isManager()
            ? User::active()->with(['leaveRequests' => fn($q) => $q
                ->where('status', 'approved')
                ->where(function ($sq) {
                    $sq->whereNull('leave_type_id')
                       ->orWhereHas('leaveType', fn($ltq) => $ltq->where('counts_toward_allowance', true));
                })
            ])->orderBy('name')->get()
                ->sortBy(fn($emp) => $emp->days_allowed - $emp->leaveRequests->sum('days'))
                ->values()
            : collect();

        $leavesData = $leaves->map(fn($l) => [
            'id'            => $l->id,
            'start_date'    => $l->start_date->toDateString(),
            'end_date'      => $l->end_date->toDateString(),
            'days'          => $l->days,
            'is_half_day'      => $l->is_half_day,
            'half_day_part'    => $l->half_day_part,
            'is_short_leave'   => $l->is_short_leave,
            'short_leave_from' => $l->short_leave_from,
            'short_leave_to'   => $l->short_leave_to,
            'short_leave_part' => $l->short_leave_part,
            'reason'        => $l->reason,
            'status'        => $l->status,
            'approved_by'   => $l->approvedBy ? $l->approvedBy->name : null,
            'approved_at'   => $l->approved_at ? $l->approved_at->format('d M Y') : null,
            'leave_type' => $l->leaveType ? [
                'id'    => $l->leaveType->id,
                'name'  => $l->leaveType->name,
                'color' => $l->leaveType->color,
            ] : null,
            'employee'   => [
                'id'    => $l->employee->id,
                'name'  => $l->employee->name,
                'role'  => $l->employee->role,
                'color' => $l->employee->color,
            ],
        ]);

        return view('leave.index', compact('user', 'leaves', 'leavesData', 'bankHolidays', 'allEmployees', 'leaveTypes'));
    }

    public function store(Request $request)
    {
        $user = Auth::user();

        $validated = $request->validate([
            'employee_id'      => 'required|exists:users,id',
            'leave_type_id'    => 'required|exists:leave_types,id',
            'start_date'       => 'required|date',
            'end_date'         => 'required|date|after_or_equal:start_date',
            'is_half_day'      => 'boolean',
            'half_day_part'    => 'nullable|in:morning,afternoon',
            'is_short_leave'   => 'boolean',
            'short_leave_from' => 'nullable|date_format:H:i',
            'short_leave_to'   => 'nullable|date_format:H:i|after:short_leave_from',
            'short_leave_part' => 'nullable|in:morning,afternoon',
            'reason'           => 'nullable|string|max:500',
            'admin_override'   => 'boolean',
        ]);

        $isHalfDay    = $request->boolean('is_half_day');
        $isShortLeave = $request->boolean('is_short_leave');

        if ($isHalfDay) {
            if ($validated['start_date'] !== $validated['end_date']) {
                return back()->withErrors(['start_date' => 'Half-day leave must be a single day.'])->withInput();
            }
            if (empty($validated['half_day_part'])) {
                return back()->withErrors(['start_date' => 'Please select morning or afternoon.'])->withInput();
            }
        }

        if ($isShortLeave) {
            if (empty($validated['short_leave_from']) || empty($validated['short_leave_to'])) {
                return back()->withErrors(['start_date' => 'Please enter start and end time for short leave.'])->withInput();
            }
        }

        // Only managers can book for others
        if (!$user->isManager() && $validated['employee_id'] != $user->id) {
            abort(403);
        }

        $bankHolidayDates = BankHoliday::pluck('date')
            ->map(fn($d) => $d->toDateString())
            ->toArray();

        if ($isShortLeave) {
            $days = 0; // no deduction
        } elseif ($isHalfDay) {
            $fullDays = $this->countWorkingDays($validated['start_date'], $validated['start_date'], $bankHolidayDates);
            if ($fullDays <= 0) {
                return back()->withErrors(['start_date' => 'The selected date is not a working day.'])->withInput();
            }
            $days = 0.5;
        } else {
            $days = $this->countWorkingDays($validated['start_date'], $validated['end_date'], $bankHolidayDates);
            if ($days <= 0) {
                return back()->withErrors(['start_date' => 'No working days in the selected range.'])->withInput();
            }
        }

        $employee = User::findOrFail($validated['employee_id']);

        // Short leave never counts toward allowance
        if (!$isShortLeave && $employee->hasHolidayAllowance()) {
            $leaveType = $validated['leave_type_id']
                ? LeaveType::find($validated['leave_type_id'])
                : null;

            $countsTowardAllowance = !$leaveType || $leaveType->counts_toward_allowance;

            if ($countsTowardAllowance) {
                $usedDays = $employee->usedDays();
                if ($usedDays + $days > $employee->days_allowed) {
                    $remaining = $employee->days_allowed - $usedDays;
                    return back()->withErrors(['start_date' => "Only {$remaining} days remaining."])->withInput();
                }
            }
        }

        // Department concurrency check (skip if admin_override or WFH/location leave type)
        $adminOverride = $user->isAdmin() && $request->boolean('admin_override');

        $leaveTypeForCheck = LeaveType::find($validated['leave_type_id']);
        $leaveTypeName     = strtolower($leaveTypeForCheck?->name ?? '');
        $isWfhLeaveType    = str_contains($leaveTypeName, 'wfh')
                          || str_contains($leaveTypeName, 'work from home')
                          || str_contains($leaveTypeName, 'working from home');

        if (!$adminOverride && !$isWfhLeaveType) {
            $conflicts = $this->checkDepartmentConflicts($employee, $validated['start_date'], $validated['end_date']);
            if ($conflicts) {
                return back()->withErrors(['start_date' => $conflicts])->withInput();
            }
        }

        $isManager = $user->isManager();

        $leave = LeaveRequest::create([
            'employee_id'      => $validated['employee_id'],
            'leave_type_id'    => $validated['leave_type_id'] ?? null,
            'start_date'       => $validated['start_date'],
            'end_date'         => ($isHalfDay || $isShortLeave) ? $validated['start_date'] : $validated['end_date'],
            'days'             => $days,
            'is_half_day'      => $isHalfDay,
            'half_day_part'    => $isHalfDay ? $validated['half_day_part'] : null,
            'is_short_leave'   => $isShortLeave,
            'short_leave_from' => $isShortLeave ? $validated['short_leave_from'] : null,
            'short_leave_to'   => $isShortLeave ? $validated['short_leave_to'] : null,
            'short_leave_part' => $isShortLeave ? ($validated['short_leave_part'] ?? null) : null,
            'reason'           => $validated['reason'] ?? '',
            'status'           => $isManager ? 'approved' : 'pending',
            'approved_by_id'   => $isManager ? $user->id : null,
            'approved_at'      => $isManager ? now() : null,
            'admin_override'   => $adminOverride,
        ]);

        $leave->load(['employee', 'leaveType', 'approvedBy']);

        if ($isManager) {
            // Manager booked directly — notify the employee
            Mail::to($leave->employee->email)->send(new LeaveStatusUpdated($leave));
        } else {
            // Employee requested — notify all managers & admins
            $managers = User::whereIn('role_type', [User::ROLE_ADMIN, User::ROLE_MANAGER])->get();
            foreach ($managers as $manager) {
                Mail::to($manager->email)->send(new LeaveRequested($leave));
            }
        }

        return redirect()->route('leave.index')
            ->with('success', 'Leave ' . ($isManager ? 'booked' : 'requested') . ' successfully.');
    }

    public function update(Request $request, LeaveRequest $leave)
    {
        $user = Auth::user();

        if (!$user->isManager()) abort(403);

        $validated = $request->validate([
            'status'         => 'required|in:approved,rejected',
            'admin_override' => 'boolean',
        ]);

        // Department concurrency check when approving (skip if admin_override or WFH/location leave type)
        $adminOverride  = $user->isAdmin() && $request->boolean('admin_override');
        $leaveTypeName  = strtolower($leave->leaveType?->name ?? '');
        $isWfhLeaveType = str_contains($leaveTypeName, 'wfh')
                       || str_contains($leaveTypeName, 'work from home')
                       || str_contains($leaveTypeName, 'working from home');

        if ($validated['status'] === 'approved' && !$adminOverride && !$isWfhLeaveType) {
            $conflicts = $this->checkDepartmentConflicts(
                $leave->employee,
                $leave->start_date->toDateString(),
                $leave->end_date->toDateString(),
                $leave->id
            );
            if ($conflicts) {
                return back()->withErrors(['department' => $conflicts]);
            }
        }

        $leave->update([
            'status'         => $validated['status'],
            'approved_by_id' => $user->id,
            'approved_at'    => now(),
            'admin_override' => $adminOverride,
        ]);

        $leave->load(['employee', 'leaveType', 'approvedBy']);
        Mail::to($leave->employee->email)->send(new LeaveStatusUpdated($leave));

        return back()->with('success', 'Leave ' . $validated['status'] . '.');
    }

    public function destroy(LeaveRequest $leave)
    {
        $user = Auth::user();

        if (!$user->isManager() && $leave->employee_id !== $user->id) {
            abort(403);
        }

        $leave->delete();

        return back()->with('success', 'Leave request removed.');
    }

    // ── Helpers ───────────────────────────────────────────────────────────────

    /**
     * Check if approving leave for an employee would breach any department concurrency limits.
     * Returns an error message string, or null if all is fine.
     */
    private function checkDepartmentConflicts(User $employee, string $startDate, string $endDate, ?int $excludeLeaveId = null): ?string
    {
        $departments = $employee->departments;

        foreach ($departments as $dept) {
            $concurrent = $dept->concurrentAbsences($startDate, $endDate, $excludeLeaveId);
            if ($concurrent >= $dept->max_concurrent) {
                return "Cannot approve: {$dept->name} already has {$concurrent} of {$dept->max_concurrent} allowed concurrent absence(s) during this period. An Admin can override this.";
            }
        }

        return null;
    }

    private function countWorkingDays(string $start, string $end, array $bankHolidays): int
    {
        $count   = 0;
        $current = Carbon::parse($start);
        $endDate = Carbon::parse($end);

        while ($current->lte($endDate)) {
            if (!$current->isWeekend() && !in_array($current->toDateString(), $bankHolidays)) {
                $count++;
            }
            $current->addDay();
        }

        return $count;
    }

}
