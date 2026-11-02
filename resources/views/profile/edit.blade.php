<x-app-layout>
<div class="page">
    <div class="page-header">
        <h2>Profile</h2>
        <p>Manage your account details and photo</p>
    </div>

    @if(session('status') === 'profile-updated')
        <div class="alert alert-success" style="margin-bottom:16px;">Profile updated.</div>
    @endif
    @if(session('status') === 'photo-updated')
        <div class="alert alert-success" style="margin-bottom:16px;">Photo updated.</div>
    @endif
    @if(session('status') === 'photo-removed')
        <div class="alert alert-success" style="margin-bottom:16px;">Photo removed.</div>
    @endif

    {{-- ── Photo ── --}}
    <div class="card" style="margin-bottom:20px;">
        <div class="card-title">Profile Photo</div>
        <div style="display:flex;align-items:center;gap:20px;">
            @if($user->profile_photo)
                <img src="{{ $user->photoUrl() }}" alt="{{ $user->name }}"
                    style="width:72px;height:72px;border-radius:50%;object-fit:cover;border:2px solid #e5e2dc;">
            @else
                <div class="avatar" style="width:72px;height:72px;font-size:22px;background:{{ $user->color }}33;color:{{ $user->color }}">
                    {{ $user->initials() }}
                </div>
            @endif
            <div style="flex:1;">
                <form method="POST" action="{{ route('profile.photo') }}" enctype="multipart/form-data" style="display:flex;gap:10px;align-items:center;flex-wrap:wrap;">
                    @csrf
                    <input type="file" name="profile_photo" accept="image/*" class="form-input" style="flex:1;min-width:200px;" required>
                    <button type="submit" class="btn btn-primary btn-sm">Upload photo</button>
                </form>
                @error('profile_photo')
                    <div class="error-msg" style="margin-top:6px;">{{ $message }}</div>
                @enderror
                @if($user->profile_photo)
                    <form method="POST" action="{{ route('profile.photo.remove') }}" style="margin-top:8px;">
                        @csrf @method('DELETE')
                        <button type="submit" class="btn btn-outline btn-sm" style="color:#999;"
                            onclick="return confirm('Remove your photo?')">Remove photo</button>
                    </form>
                @endif
                <p style="font-size:11px;color:#aaa;margin-top:6px;">Max 2MB. JPEG, PNG, GIF, or WebP.</p>
            </div>
        </div>
    </div>

    {{-- ── Profile info ── --}}
    <div class="card" style="margin-bottom:20px;">
        <div class="card-title">Account Details</div>
        <form method="POST" action="{{ route('profile.update') }}">
            @csrf @method('PATCH')
            <div class="form-row">
                <div class="form-group">
                    <label class="form-label">Name</label>
                    <input type="text" name="name" class="form-input" value="{{ old('name', $user->name) }}" required>
                    @error('name') <div class="error-msg">{{ $message }}</div> @enderror
                </div>
                <div class="form-group">
                    <label class="form-label">Email</label>
                    <input type="email" name="email" class="form-input" value="{{ old('email', $user->email) }}" required>
                    @error('email') <div class="error-msg">{{ $message }}</div> @enderror
                </div>
            </div>
            <div style="display:grid;grid-template-columns:1fr 1fr 1fr;gap:12px;margin-bottom:16px;">
                <div>
                    <div style="font-size:12px;color:#888;margin-bottom:2px;">Access level</div>
                    <div style="font-size:13px;font-weight:500;">{{ $user->roleBadgeLabel() }}</div>
                </div>
                <div>
                    <div style="font-size:12px;color:#888;margin-bottom:2px;">Job title</div>
                    <div style="font-size:13px;">{{ $user->role }}</div>
                </div>
                <div>
                    <div style="font-size:12px;color:#888;margin-bottom:2px;">Annual allowance</div>
                    <div style="font-size:13px;">
                        @if($user->hasHolidayAllowance())
                            {{ $user->remainingDays() }} remaining of {{ $user->days_allowed }}
                        @else
                            N/A ({{ $user->roleBadgeLabel() }})
                        @endif
                    </div>
                </div>
            </div>
            <div class="modal-footer" style="padding:0;">
                <button type="submit" class="btn btn-primary">Save changes</button>
            </div>
        </form>
    </div>

    {{-- ── Change password ── --}}
    <div class="card" style="margin-bottom:20px;">
        <div class="card-title">Change Password</div>
        @include('profile.partials.update-password-form')
    </div>

    {{-- ── Google Calendar ── --}}
    <div class="card" style="margin-bottom:20px;">
        <div class="card-title">Google Calendar</div>
        @if(session('status') === 'google-connected')
            <div class="alert alert-success" style="margin-bottom:12px;">Google Calendar connected successfully.</div>
        @elseif(session('status') === 'google-disconnected')
            <div class="alert alert-success" style="margin-bottom:12px;">Google Calendar disconnected.</div>
        @elseif(session('status') === 'google-denied')
            <div class="alert alert-error" style="margin-bottom:12px;">Google Calendar access was denied.</div>
        @elseif(session('status') === 'google-error')
            <div class="alert alert-error" style="margin-bottom:12px;">Something went wrong connecting to Google. Please try again.</div>
        @endif
        @if($user->google_refresh_token)
            <div style="display:flex;align-items:center;gap:12px;flex-wrap:wrap;">
                <div style="display:flex;align-items:center;gap:8px;">
                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none"><circle cx="12" cy="12" r="10" fill="#22c55e"/><path d="M8 12l3 3 5-5" stroke="white" stroke-width="2" stroke-linecap="round"/></svg>
                    <span style="font-size:13px;color:#059669;font-weight:500;">Connected</span>
                </div>
                <span style="font-size:12px;color:#aaa;">Your approved leaves will sync to your Google Calendar.</span>
                <form method="POST" action="{{ route('google.disconnect') }}" style="margin-left:auto;">
                    @csrf
                    <button type="submit" class="btn btn-outline btn-sm" onclick="return confirm('Disconnect Google Calendar?')">Disconnect</button>
                </form>
            </div>
        @else
            <div style="display:flex;align-items:center;gap:12px;flex-wrap:wrap;">
                <p style="font-size:13px;color:#666;margin:0;">Connect your Google Calendar so approved leave is automatically added as events.</p>
                <a href="{{ route('google.redirect') }}" class="btn btn-primary btn-sm" style="margin-left:auto;white-space:nowrap;">Connect Google Calendar</a>
            </div>
        @endif
    </div>

    {{-- ── Delete account ── --}}
    <div class="card">
        <div class="card-title" style="color:#ef4444;">Danger Zone</div>
        @include('profile.partials.delete-user-form')
    </div>
</div>
</x-app-layout>
