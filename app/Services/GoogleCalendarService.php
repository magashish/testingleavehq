<?php

namespace App\Services;

use App\Models\LeaveRequest;
use App\Models\User;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class GoogleCalendarService
{
    private string $clientId;
    private string $clientSecret;
    private string $redirectUri;

    public function __construct()
    {
        $this->clientId     = config('services.google.client_id');
        $this->clientSecret = config('services.google.client_secret');
        $this->redirectUri  = config('services.google.redirect');
    }

    public function getAuthUrl(): string
    {
        $params = http_build_query([
            'client_id'     => $this->clientId,
            'redirect_uri'  => $this->redirectUri,
            'response_type' => 'code',
            'scope'         => 'https://www.googleapis.com/auth/calendar.events',
            'access_type'   => 'offline',
            'prompt'        => 'consent',
        ]);

        return 'https://accounts.google.com/o/oauth2/auth?' . $params;
    }

    public function exchangeCode(string $code): array
    {
        $response = Http::post('https://oauth2.googleapis.com/token', [
            'code'          => $code,
            'client_id'     => $this->clientId,
            'client_secret' => $this->clientSecret,
            'redirect_uri'  => $this->redirectUri,
            'grant_type'    => 'authorization_code',
        ]);

        return $response->json();
    }

    public function getValidToken(User $user): ?string
    {
        if (!$user->google_refresh_token) return null;

        // Token still valid
        if ($user->google_token_expires_at && $user->google_token_expires_at->isFuture()) {
            return decrypt($user->google_access_token);
        }

        // Refresh the token
        $response = Http::post('https://oauth2.googleapis.com/token', [
            'client_id'     => $this->clientId,
            'client_secret' => $this->clientSecret,
            'refresh_token' => decrypt($user->google_refresh_token),
            'grant_type'    => 'refresh_token',
        ]);

        if ($response->failed()) {
            Log::warning("Google token refresh failed for user {$user->id}");
            return null;
        }

        $data = $response->json();

        $user->update([
            'google_access_token'    => encrypt($data['access_token']),
            'google_token_expires_at' => now()->addSeconds(($data['expires_in'] ?? 3600) - 60),
        ]);

        return $data['access_token'];
    }

    public function createEvent(User $employee, LeaveRequest $leave): ?string
    {
        if (!$employee->google_refresh_token) return null;

        $token = $this->getValidToken($employee);
        if (!$token) return null;

        $typeName = $leave->leaveType?->name ?? 'Annual Leave';
        $summary  = "{$employee->name} — {$typeName}";
        if ($leave->is_half_day)    $summary .= ' (half day)';
        if ($leave->is_short_leave) $summary .= ' (short leave)';

        $description = "Leave type: {$typeName}";
        if ($leave->reason) $description .= "\nReason: {$leave->reason}";
        $description .= "\n\nBooked via Pinnacle HR";

        // Google all-day end date is exclusive — add 1 day
        $endDate = $leave->end_date->copy()->addDay()->toDateString();

        $body = [
            'summary'      => $summary,
            'description'  => $description,
            'start'        => ['date' => $leave->start_date->toDateString()],
            'end'          => ['date' => $endDate],
            'transparency' => 'transparent',
        ];

        $calendarId = $employee->google_calendar_id ?? 'primary';

        $response = Http::withToken($token)
            ->post("https://www.googleapis.com/calendar/v3/calendars/" . urlencode($calendarId) . "/events", $body);

        if ($response->successful()) {
            return $response->json('id');
        }

        Log::warning("Google Calendar event creation failed for leave {$leave->id}: " . $response->body());
        return null;
    }

    public function deleteEvent(User $employee, string $eventId): void
    {
        if (!$employee->google_refresh_token) return;

        $token = $this->getValidToken($employee);
        if (!$token) return;

        $calendarId = $employee->google_calendar_id ?? 'primary';

        Http::withToken($token)
            ->delete("https://www.googleapis.com/calendar/v3/calendars/" . urlencode($calendarId) . "/events/{$eventId}");
    }
}
