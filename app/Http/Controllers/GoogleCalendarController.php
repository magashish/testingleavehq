<?php

namespace App\Http\Controllers;

use App\Services\GoogleCalendarService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class GoogleCalendarController extends Controller
{
    public function redirect(GoogleCalendarService $google)
    {
        return redirect($google->getAuthUrl());
    }

    public function callback(Request $request, GoogleCalendarService $google)
    {
        $user = Auth::user();

        if ($request->has('error')) {
            return redirect()->route('profile.edit')
                ->with('status', 'google-denied');
        }

        $code = $request->get('code');
        $data = $google->exchangeCode($code);

        if (isset($data['error'])) {
            return redirect()->route('profile.edit')
                ->with('status', 'google-error');
        }

        $user->update([
            'google_access_token'    => encrypt($data['access_token']),
            'google_refresh_token'   => isset($data['refresh_token'])
                ? encrypt($data['refresh_token'])
                : $user->google_refresh_token,
            'google_token_expires_at' => now()->addSeconds(($data['expires_in'] ?? 3600) - 60),
            'google_calendar_id'     => 'primary',
        ]);

        return redirect()->route('profile.edit')
            ->with('status', 'google-connected');
    }

    public function disconnect()
    {
        Auth::user()->update([
            'google_access_token'    => null,
            'google_refresh_token'   => null,
            'google_token_expires_at' => null,
            'google_calendar_id'     => null,
        ]);

        return redirect()->route('profile.edit')
            ->with('status', 'google-disconnected');
    }
}
