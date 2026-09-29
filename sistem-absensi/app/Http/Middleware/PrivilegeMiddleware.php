<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class PrivilegeMiddleware
{
    /**
     * Handle an incoming request.
     */
    public function handle(Request $request, Closure $next, $privilege)
    {
        $user = Auth::user();
        if (! $user) {
            if ($request->expectsJson() || $request->is('api/*')) {
                return response()->json(['message' => 'Unauthenticated.'], 401);
            }
            return redirect('/login');
        }

        // A mapped role is authoritative; never fall back to the legacy string
        // when a role_id exists but no corresponding role can be loaded.
        if (!empty($user->role_id)) {
            $role = $user->roleAkses;
            if (! $role || ! $role->hasPrivilege($privilege)) {
                abort(403, "Akses ditolak: Anda tidak memiliki privilege [{$privilege}].");
            }
        } else {
            if ($user->isSuperAdmin()) {
                // Legacy Super Admin bypass
            } else {
                abort(403, "Akses ditolak: Akun Anda belum dipetakan ke role yang memiliki privilege [{$privilege}].");
            }
        }

        return $next($request);
    }
}
