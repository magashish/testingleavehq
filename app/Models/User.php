<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Str;

class User extends Authenticatable
{
    use HasFactory, Notifiable;

    // Valid role types
    const ROLE_ADMIN      = 'admin';
    const ROLE_MANAGER    = 'manager';
    const ROLE_EMPLOYEE   = 'employee';
    const ROLE_CONTRACTOR = 'contractor';
    const ROLE_INTERN     = 'intern';

    const ROLES = [
        self::ROLE_ADMIN      => 'Admin',
        self::ROLE_MANAGER    => 'Manager',
        self::ROLE_EMPLOYEE   => 'Employee',
        self::ROLE_CONTRACTOR => 'Contractor',
        self::ROLE_INTERN     => 'Intern',
    ];

    protected $fillable = [
        'name',
        'email',
        'password',
        'role',
        'role_type',
        'is_manager',
        'days_allowed',
        'color',
        'profile_photo',
        'work_location',
        'archived_at',
        'finish_date',
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected $casts = [
        'email_verified_at' => 'datetime',
        'password'          => 'hashed',
        'is_manager'        => 'boolean',
        'archived_at'       => 'datetime',
        'finish_date'       => 'date',
    ];

    // ── Scopes ───────────────────────────────────────────────────────────────

    public function scopeActive($query)
    {
        return $query->whereNull('archived_at');
    }

    // ── Role helpers ─────────────────────────────────────────────────────────

    public function isAdmin(): bool
    {
        return $this->role_type === self::ROLE_ADMIN;
    }

    public function isManager(): bool
    {
        return in_array($this->role_type, [self::ROLE_ADMIN, self::ROLE_MANAGER]);
    }

    public function isContractor(): bool
    {
        return $this->role_type === self::ROLE_CONTRACTOR;
    }

    public function isIntern(): bool
    {
        return $this->role_type === self::ROLE_INTERN;
    }

    public function isArchived(): bool
    {
        return $this->archived_at !== null;
    }

    /** Contractors and Interns have no holiday allowance */
    public function hasHolidayAllowance(): bool
    {
        return !in_array($this->role_type, [self::ROLE_CONTRACTOR, self::ROLE_INTERN]);
    }

    public function roleBadgeLabel(): string
    {
        return self::ROLES[$this->role_type] ?? ucfirst($this->role_type);
    }

    // ── Relationships ─────────────────────────────────────────────────────────

    public function leaveRequests()
    {
        return $this->hasMany(LeaveRequest::class, 'employee_id');
    }

    public function approvedLeave()
    {
        return $this->hasMany(LeaveRequest::class, 'approved_by_id');
    }

    public function departments()
    {
        return $this->belongsToMany(Department::class, 'department_user');
    }

    public function checkins()
    {
        return $this->hasMany(DailyCheckin::class);
    }

    // ── Computed ──────────────────────────────────────────────────────────────

    public function usedDays(): int
    {
        // Count approved days where leave type counts toward allowance (or no type set)
        return (int) $this->leaveRequests()
            ->where('status', 'approved')
            ->where(function ($q) {
                $q->whereNull('leave_type_id')
                  ->orWhereHas('leaveType', fn($sq) => $sq->where('counts_toward_allowance', true));
            })
            ->sum('days');
    }

    public function remainingDays(): int
    {
        return max(0, $this->days_allowed - $this->usedDays());
    }

    public function initials(): string
    {
        return collect(explode(' ', $this->name))
            ->map(fn($part) => strtoupper($part[0]))
            ->implode('');
    }

    public function photoUrl(): ?string
    {
        return $this->profile_photo
            ? asset('storage/' . $this->profile_photo)
            : null;
    }
}
