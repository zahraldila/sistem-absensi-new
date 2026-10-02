<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use App\Helpers\OrganizationHelper;
use Symfony\Component\HttpFoundation\Response;

class EnsureFeatureEnabled
{
    /**
     * Handle an incoming request.
     * Enforces that the active organization possesses the required capability/feature.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     * @param  string  $featureKey
     */
    public function handle(Request $request, Closure $next, string $featureKey): Response
    {
        $org = OrganizationHelper::getActiveOrganization();

        if (!$org || !$org->hasFeature($featureKey)) {
            if ($request->expectsJson() || $request->is('api/*')) {
                return response()->json([
                    'status'  => 'error',
                    'message' => "Fitur [{$featureKey}] tidak diaktifkan pada organisasi Anda.",
                ], 403);
            }

            abort(403, "Akses ditolak: Fitur [{$featureKey}] tidak diaktifkan pada organisasi Anda.");
        }

        return $next($request);
    }
}
