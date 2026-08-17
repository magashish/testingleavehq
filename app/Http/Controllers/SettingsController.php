<?php

namespace App\Http\Controllers;

use App\Models\BankHoliday;
use App\Models\DailyCheckin;
use App\Models\Department;
use App\Models\LeaveType;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;

class SettingsController extends Controller
{
    private function requireManager(): void
    {
        if (!Auth::user()->isManager()) abort(403);
    }

    private function requireAdmin(): void
    {
        if (!Auth::user()->isAdmin()) abort(403);
    }

    public function index()
    {
        $this->requireManager();

        $usedDaysQuery = fn($q) => $q
            ->where('status', 'approved')
            ->where(function ($sq) {
                $sq->whereNull('leave_type_id')
                   ->orWhereHas('leaveType', fn($ltq) => $ltq->where('counts_toward_allowance', true));
            })
            ->select(\DB::raw('sum(days)'));

        $employees = User::active()->orderBy('name')
            ->withCount(['leaveRequests as used_days' => $usedDaysQuery])
            ->with('departments')
            ->get();

        $archivedEmployees = User::whereNotNull('archived_at')->orderBy('name')
            ->withCount(['leaveRequests as used_days' => $usedDaysQuery])
            ->get();

        $bankHolidays  = BankHoliday::orderBy('date')->get();
        $leaveTypes    = LeaveType::orderBy('name')->get();
        $departments   = Department::with('users')->orderBy('name')->get();

        $attendanceDate = request('attendance_date', today()->toDateString());
        $checkins = DailyCheckin::with('user')
            ->where('date', $attendanceDate)
            ->get()
            ->keyBy('user_id');

        return view('settings.index', compact('employees', 'archivedEmployees', 'bankHolidays', 'leaveTypes', 'departments', 'attendanceDate', 'checkins'));
    }

    // ── Employees ─────────────────────────────────────────────────────────────

    public function addEmployee(Request $request)
    {
        $this->requireManager();

        $validated = $request->validate([
            'name'          => 'required|string|max:255',
            'email'         => 'required|email|unique:users',
            'role'          => 'required|string|max:100',
            'role_type'     => 'required|in:admin,manager,employee,contractor,intern',
            'days_allowed'  => 'required|integer|min:0|max:60',
            'color'         => 'required|string|max:10',
            'work_location' => 'required|in:office,remote,wfh',
        ]);

        // Restrict creating admins to admins only
        if ($validated['role_type'] === 'admin' && !Auth::user()->isAdmin()) {
            abort(403, 'Only admins can create admin accounts.');
        }

        $user = User::create([
            'name'          => $validated['name'],
            'email'         => $validated['email'],
            'password'      => Hash::make('password123'),
            'role'          => $validated['role'],
            'role_type'     => $validated['role_type'],
            'is_manager'    => in_array($validated['role_type'], ['admin', 'manager']),
            'days_allowed'  => $validated['days_allowed'],
            'color'         => $validated['color'],
            'work_location' => $validated['work_location'],
        ]);

        return back()->with('success', "{$user->name} added. Default password: password123");
    }

    public function updateEmployee(Request $request, User $user)
    {
        $this->requireManager();

        // Only admin can promote/demote to admin
        $rules = [
            'name'          => 'required|string|max:255',
            'email'         => 'required|email|unique:users,email,' . $user->id,
            'role'          => 'required|string|max:100',
            'role_type'     => 'required|in:admin,manager,employee,contractor,intern',
            'days_allowed'  => 'required|integer|min:0|max:60',
            'color'         => 'required|string|max:10',
            'work_location' => 'required|in:office,remote,wfh',
        ];

        $validated = $request->validate($rules);

        if ($validated['role_type'] === 'admin' && !Auth::user()->isAdmin()) {
            abort(403, 'Only admins can grant admin access.');
        }

        $user->update([
            'name'          => $validated['name'],
            'email'         => $validated['email'],
            'role'          => $validated['role'],
            'role_type'     => $validated['role_type'],
            'is_manager'    => in_array($validated['role_type'], ['admin', 'manager']),
            'days_allowed'  => $validated['days_allowed'],
            'color'         => $validated['color'],
            'work_location' => $validated['work_location'],
        ]);

        return back()->with('success', "{$user->name} updated.");
    }

    public function removeEmployee(User $user)
    {
        $this->requireManager();

        if ($user->id === Auth::id()) {
            return back()->withErrors(['general' => 'You cannot remove your own account here.']);
        }

        $name = $user->name;
        $user->delete();

        return back()->with('success', "{$name} removed.");
    }

    public function changePassword(Request $request, User $user)
    {
        $this->requireManager();

        $validated = $request->validate([
            'password'              => 'required|string|min:8|confirmed',
            'password_confirmation' => 'required',
        ]);

        $user->update(['password' => Hash::make($validated['password'])]);

        return back()->with('success', "{$user->name}'s password has been updated.");
    }

    public function archiveEmployee(Request $request, User $user)
    {
        $this->requireManager();

        if ($user->id === Auth::id()) {
            return back()->withErrors(['general' => 'You cannot archive your own account.']);
        }

        $validated = $request->validate([
            'finish_date' => 'nullable|date',
        ]);

        $user->update([
            'archived_at' => now(),
            'finish_date' => $validated['finish_date'] ?? null,
        ]);

        return back()->with('success', "{$user->name} has been archived.");
    }

    public function unarchiveEmployee(User $user)
    {
        $this->requireManager();

        $user->update([
            'archived_at' => null,
            'finish_date' => null,
        ]);

        return back()->with('success', "{$user->name} has been restored.");
    }

    public function updateDays(Request $request, User $user)
    {
        $this->requireManager();

        $validated = $request->validate([
            'days_allowed' => 'required|integer|min:0|max:60',
        ]);

        $user->update(['days_allowed' => $validated['days_allowed']]);

        return back()->with('success', 'Allowance updated.');
    }

    // ── Bank Holidays ─────────────────────────────────────────────────────────

    public function addBankHoliday(Request $request)
    {
        $this->requireManager();

        $validated = $request->validate([
            'name' => 'required|string|max:100',
            'date' => 'required|date|unique:bank_holidays,date',
        ]);

        BankHoliday::create($validated);

        return back()->with('success', 'Bank holiday added.');
    }

    public function removeBankHoliday(BankHoliday $bankHoliday)
    {
        $this->requireManager();

        $bankHoliday->delete();

        return back()->with('success', "{$bankHoliday->name} removed.");
    }

    // ── Leave Types ───────────────────────────────────────────────────────────

    public function addLeaveType(Request $request)
    {
        $this->requireManager();

        $validated = $request->validate([
            'name'                    => 'required|string|max:100|unique:leave_types,name',
            'color'                   => 'required|string|max:10',
            'counts_toward_allowance' => 'boolean',
        ]);

        LeaveType::create([
            'name'                    => $validated['name'],
            'color'                   => $validated['color'],
            'counts_toward_allowance' => $request->boolean('counts_toward_allowance', true),
            'is_active'               => true,
        ]);

        return back()->with('success', 'Leave type added.');
    }

    public function updateLeaveType(Request $request, LeaveType $leaveType)
    {
        $this->requireManager();

        $validated = $request->validate([
            'name'                    => 'required|string|max:100|unique:leave_types,name,' . $leaveType->id,
            'color'                   => 'required|string|max:10',
            'counts_toward_allowance' => 'boolean',
            'is_active'               => 'boolean',
        ]);

        $leaveType->update([
            'name'                    => $validated['name'],
            'color'                   => $validated['color'],
            'counts_toward_allowance' => $request->boolean('counts_toward_allowance'),
            'is_active'               => $request->boolean('is_active'),
        ]);

        return back()->with('success', 'Leave type updated.');
    }

    public function removeLeaveType(LeaveType $leaveType)
    {
        $this->requireManager();

        $leaveType->delete();

        return back()->with('success', "{$leaveType->name} removed.");
    }

    // ── Departments ───────────────────────────────────────────────────────────

    public function addDepartment(Request $request)
    {
        $this->requireManager();

        $validated = $request->validate([
            'name'           => 'required|string|max:100|unique:departments,name',
            'max_concurrent' => 'required|integer|min:1|max:20',
        ]);

        Department::create($validated);

        return back()->with('success', 'Department added.');
    }

    public function updateDepartment(Request $request, Department $department)
    {
        $this->requireManager();

        $validated = $request->validate([
            'name'           => 'required|string|max:100|unique:departments,name,' . $department->id,
            'max_concurrent' => 'required|integer|min:1|max:20',
        ]);

        $department->update($validated);

        return back()->with('success', 'Department updated.');
    }

    public function removeDepartment(Department $department)
    {
        $this->requireManager();

        $department->delete();

        return back()->with('success', "{$department->name} removed.");
    }

    public function updateDepartmentMembers(Request $request, Department $department)
    {
        $this->requireManager();

        $validated = $request->validate([
            'user_ids'   => 'nullable|array',
            'user_ids.*' => 'exists:users,id',
        ]);

        $department->users()->sync($validated['user_ids'] ?? []);

        return back()->with('success', "{$department->name} members updated.");
    }
}
