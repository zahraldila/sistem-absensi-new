<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use App\Helpers\OrganizationHelper;
use Symfony\Component\HttpFoundation\Response;

class EnsureOrganizationContext
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $user = Auth::user();

        // 1. Must be authenticated
        if (!$user) {
            return redirect()->route('login');
        }

        // 2. Check Organization Context
        $orgId = OrganizationHelper::getActiveOrganizationId();

        // If no organization ID is found
        if (!$orgId) {
            if ($user->isSuperAdmin()) {
                // If they are on the selection or system management routes, let them pass
                if (
                    $request->routeIs('admin.organization.*') || 
                    $request->routeIs('admin.system.*') ||
                    $request->routeIs('admin.log-aktivitas*')
                ) {
                    return $next($request);
                }
                
                // Otherwise, force Super Admin to select an organization
                return redirect()->route('admin.organization.select');
            } else {
                // For regular users, they cannot select orgs. They just don't have one.
                abort(403, 'Akun Anda tidak terhubung dengan organisasi yang valid. Silakan hubungi Administrator.');
            }
        }

        // If orgId exists but they are a Super Admin trying to access the select page again,
        // we can let them pass (so they can switch orgs).
        // The controller will handle the rest.

        // Note: We DO NOT take organization_id from the $request payload to determine context.
        // It is strictly determined by OrganizationHelper.

        return $next($request);
    }
}
