<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class AdminAccessMiddleware
{
    public function handle(Request $request, Closure $next)
    {
        $role = Auth::user()?->roleAkses;

        if (! $role || ! $role->hasAnyPrivilege()) {
            abort(403, 'Akses ditolak: role Anda tidak memiliki hak akses admin.');
        }

        return $next($request);
    }
}