<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class CheckNotArchived
{
    public function handle(Request $request, Closure $next)
    {
        if (Auth::check() && Auth::user()->isArchived()) {
            Auth::logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();
            return redirect()->route('login')
                ->withErrors(['email' => 'Your account has been deactivated. Please contact your manager.']);
        }

        return $next($request);
    }
}
