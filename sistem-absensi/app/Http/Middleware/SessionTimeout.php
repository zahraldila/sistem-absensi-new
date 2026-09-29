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
            $akun = Auth::user();

            // Populate session metadata jika user kembali melalui remember-me cookie
            if (! Session::has('role')) {
                $pegawai = $akun->pegawai;
                Session::put([
                    'akun_id' => $akun->akun_id,
                    'pegawai_id' => $akun->pegawai_id,
                    'role' => $akun->role,
                    'nama_pegawai' => $pegawai ? $pegawai->nama_pegawai : null,
                    'email_pegawai' => $pegawai ? $pegawai->email : null,
                ]);
            }

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

            if ($last) {
                Session::put('lastActivityTime', $now);
            }
        }

        return $next($request);
    }
}
