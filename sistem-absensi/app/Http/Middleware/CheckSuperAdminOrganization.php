<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class CheckSuperAdminOrganization
{
    /**
     * Handle an incoming request.
     */
    public function handle(Request $request, Closure $next)
    {
        $user = Auth::user();

        // If not logged in, pass it to auth middleware to handle
        if (!$user) {
            return $next($request);
        }

        // Only enforce this for Super Admin
        if ($user->isSuperAdmin()) {
            // Check if they have an active organization in session
            if (!session()->has('active_organization_id')) {
                // Biarkan lolos jika sedang berada di rute pemilihan organisasi, agar tidak terjadi redirect loop
                if (
                    !$request->routeIs('admin.organization.select') && 
                    !$request->routeIs('admin.organization.store') &&
                    !$request->routeIs('admin.organization.create') &&
                    !$request->routeIs('admin.organization.storeNew')
                ) {
                    return redirect()->route('admin.organization.select');
                }
            } else {
                // If they already have an active organization but try to go to the selection page,
                // we can let them, because they might want to switch. But we have a separate route for that if needed.
                // Actually, letting them access it is fine.
            }
        }

        return $next($request);
    }
}
