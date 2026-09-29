<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Session;

class SessionTimeout
{
    protected $timeout = 1800; // 30 minutes

    public function handle(Request $request, Closure $next)
    {
        if (Auth::check()) {
            $last = Session::get('lastActivityTime');
            $now = time();

            if ($last && ($now - $last) > $this->timeout) {
                Auth::logout();
                $request->session()->invalidate();
                $request->session()->regenerateToken();

                $message = 'Sesi Anda telah berakhir. Silakan login kembali.';

                if ($request->expectsJson()) {
                    return response()->json([
                        'message' => $message,
                        'error'   => 'session_expired',
                    ], 401);
                }

                return redirect()->route('login')
                    ->with('error', $message)
                    ->withErrors(['message' => $message]);
            }

            Session::put('lastActivityTime', $now);
        }

        return $next($request);
    }
}
