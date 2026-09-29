<?php

namespace App\Helpers;

use Illuminate\Support\Facades\Auth;

class OrganizationHelper
{
    /**
     * Get the active organization ID based on the logged-in user.
     * Super Admin uses session 'active_organization_id'.
     * Other users use their 'pegawai->organization_id'.
     */
    public static function getActiveOrganizationId(): ?int
    {
        $user = Auth::user();

        if (!$user) {
            return null;
        }

        if ($user->isSuperAdmin()) {
            return session('active_organization_id');
        }

        return $user->pegawai->organization_id ?? null;
    }

    /**
     * Requires an active organization ID to exist.
     * If not available, aborts with 403.
     */
    public static function requireActiveOrganization(): int
    {
        $orgId = self::getActiveOrganizationId();
        
        if (!$orgId) {
            abort(403, 'Anda tidak memiliki akses organisasi yang valid. Silakan hubungi administrator.');
        }

        return $orgId;
    }
}
