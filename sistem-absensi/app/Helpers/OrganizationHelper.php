<?php

namespace App\Helpers;

use Illuminate\Support\Facades\Auth;

class OrganizationHelper
{
    protected static ?\App\Models\Organization $activeOrgInstance = null;

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
     * Get the active Organization model instance (memoized per-request).
     */
    public static function getActiveOrganization(): ?\App\Models\Organization
    {
        $orgId = self::getActiveOrganizationId();
        if (!$orgId) {
            self::$activeOrgInstance = null;
            return null;
        }

        if (self::$activeOrgInstance && self::$activeOrgInstance->organization_id === $orgId) {
            return self::$activeOrgInstance;
        }

        self::$activeOrgInstance = \App\Models\Organization::find($orgId);
        return self::$activeOrgInstance;
    }

    /**
     * Shorthand alias to get active Organization model instance.
     */
    public static function active(): ?\App\Models\Organization
    {
        return self::getActiveOrganization();
    }

    /**
     * Check if a feature is enabled for the active organization AND the user has the required privilege.
     * Core Principle: ACCESS = FEATURE && PRIVILEGE
     *
     * @param string $featureKey
     * @param string|null $privilegeKey
     * @return bool
     */
    public static function canAccessFeature(string $featureKey, ?string $privilegeKey = null): bool
    {
        $org = self::getActiveOrganization();
        if (!$org || !$org->hasFeature($featureKey)) {
            return false;
        }

        if ($privilegeKey !== null) {
            $user = Auth::user();
            if (!$user) {
                return false;
            }

            if ($user->isSuperAdmin()) {
                return true;
            }

            return $user->roleAkses?->hasPrivilege($privilegeKey) ?? false;
        }

        return true;
    }

    /**
     * Clear memoized organization instance (useful during testing or tenant switching).
     */
    public static function clearActiveOrganizationCache(): void
    {
        self::$activeOrgInstance = null;
    }

    /**
     * Resolve terminology dynamically for the active organization or fall back to defaults.
     * Hierarchy:
     * 1. Setting::get("term_{$key}") if set for active organization
     * 2. config("organization_terminology.defaults.{$key}")
     * 3. $default parameter passed by caller
     *
     * @param string $key
     * @param string|null $default
     * @return string
     */
    public static function term(string $key, ?string $default = null, ?\App\Models\Organization $org = null): string
    {
        try {
            $targetOrg = $org ?? self::getActiveOrganization();
            if ($targetOrg) {
                return $targetOrg->getTerminology($key, $default);
            }
        } catch (\Throwable $e) {
            // Fail safely without throwing errors
        }

        // Check fallback config
        $configDefault = config('organization_terminology.defaults.' . $key);
        if ($configDefault !== null) {
            return $configDefault;
        }

        // Return caller default or humanized key as ultimate fallback
        return $default ?? ucfirst(str_replace('_', ' ', $key));
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

