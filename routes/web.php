<?php

use App\Http\Controllers\CheckinController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\LeaveController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\ReportsController;
use App\Http\Controllers\SettingsController;
use App\Http\Controllers\TeamController;
use Illuminate\Support\Facades\Route;

Route::get('/', fn() => redirect()->route('dashboard'));

Route::middleware(['auth', 'verified', 'not_archived'])->group(function () {
    // Dashboard
    Route::get('/dashboard', [DashboardController::class, 'index'])->name('dashboard');

    // Daily attendance
    Route::post('/checkin',  [CheckinController::class, 'store'])->name('checkin.store');
    Route::post('/checkout', [CheckinController::class, 'checkout'])->name('checkin.checkout');
    Route::patch('/checkin/{checkin}', [CheckinController::class, 'update'])->name('checkin.update');

    // Leave management
    Route::get('/leave', [LeaveController::class, 'index'])->name('leave.index');
    Route::post('/leave', [LeaveController::class, 'store'])->name('leave.store');
    Route::patch('/leave/{leave}', [LeaveController::class, 'update'])->name('leave.update');
    Route::delete('/leave/{leave}', [LeaveController::class, 'destroy'])->name('leave.destroy');

    // Team (managers only)
    Route::get('/team', [TeamController::class, 'index'])->name('team.index');
    Route::get('/team/custom', [TeamController::class, 'custom'])->name('team.custom');
    Route::post('/team/notices', [TeamController::class, 'storeNotice'])->name('team.notices.store');
    Route::delete('/team/notices/{notice}', [TeamController::class, 'destroyNotice'])->name('team.notices.destroy');

    // Reports (managers only)
    Route::get('/reports', [ReportsController::class, 'index'])->name('reports.index');
    Route::get('/reports/export', [ReportsController::class, 'export'])->name('reports.export');

    // Settings (managers only)
    Route::get('/settings', [SettingsController::class, 'index'])->name('settings.index');

    // Employees
    Route::post('/settings/employees', [SettingsController::class, 'addEmployee'])->name('settings.employees.add');
    Route::patch('/settings/employees/{user}', [SettingsController::class, 'updateEmployee'])->name('settings.employees.update');
    Route::delete('/settings/employees/{user}', [SettingsController::class, 'removeEmployee'])->name('settings.employees.remove');
    Route::patch('/settings/employees/{user}/days', [SettingsController::class, 'updateDays'])->name('settings.employees.days');
    Route::patch('/settings/employees/{user}/password', [SettingsController::class, 'changePassword'])->name('settings.employees.password');
    Route::post('/settings/employees/{user}/archive', [SettingsController::class, 'archiveEmployee'])->name('settings.employees.archive');
    Route::post('/settings/employees/{user}/unarchive', [SettingsController::class, 'unarchiveEmployee'])->name('settings.employees.unarchive');

    // Bank holidays
    Route::post('/settings/bank-holidays', [SettingsController::class, 'addBankHoliday'])->name('settings.bank-holidays.add');
    Route::delete('/settings/bank-holidays/{bankHoliday}', [SettingsController::class, 'removeBankHoliday'])->name('settings.bank-holidays.remove');

    // Leave types
    Route::post('/settings/leave-types', [SettingsController::class, 'addLeaveType'])->name('settings.leave-types.add');
    Route::patch('/settings/leave-types/{leaveType}', [SettingsController::class, 'updateLeaveType'])->name('settings.leave-types.update');
    Route::delete('/settings/leave-types/{leaveType}', [SettingsController::class, 'removeLeaveType'])->name('settings.leave-types.remove');

    // Departments
    Route::post('/settings/departments', [SettingsController::class, 'addDepartment'])->name('settings.departments.add');
    Route::patch('/settings/departments/{department}', [SettingsController::class, 'updateDepartment'])->name('settings.departments.update');
    Route::delete('/settings/departments/{department}', [SettingsController::class, 'removeDepartment'])->name('settings.departments.remove');
    Route::patch('/settings/departments/{department}/members', [SettingsController::class, 'updateDepartmentMembers'])->name('settings.departments.members');

    // Profile
    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::post('/profile/photo', [ProfileController::class, 'uploadPhoto'])->name('profile.photo');
    Route::delete('/profile/photo', [ProfileController::class, 'removePhoto'])->name('profile.photo.remove');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');
});

require __DIR__.'/auth.php';
