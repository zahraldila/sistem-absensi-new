<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Organization extends Model
{
    protected $table = 'organizations';
    protected $primaryKey = 'organization_id';
    public $incrementing = true;
    protected $keyType = 'int';
    public $timestamps = true;

    protected $fillable = [
        'nama_organisasi',
        'kode_organisasi',
        'alamat',
        'status',
        'display_token',
        'category_id',
    ];

    /**
     * Instance-level cache for organization features to prevent duplicate DB queries during request.
     * Strictly scoped to this instance to maintain tenant isolation.
     */
    protected ?array $cachedFeatures = null;

    public function category(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(OrganizationCategory::class, 'category_id', 'id');
    }

    public function organizationFeatures(): HasMany
    {
        return $this->hasMany(OrganizationFeature::class, 'organization_id', 'organization_id');
    }

    public function features(): \Illuminate\Database\Eloquent\Relations\BelongsToMany
    {
        return $this->belongsToMany(Feature::class, 'organization_features', 'organization_id', 'feature_id')
            ->withPivot('is_enabled', 'configuration')
            ->withTimestamps();
    }

    /**
     * Check if a specific feature is enabled for this organization.
     * Returns true ONLY if the feature exists, the organization has a feature record, and is_enabled === true.
     * Never fallbacks to true.
     */
    public function hasFeature(string $featureKey): bool
    {
        if ($this->cachedFeatures === null) {
            $this->loadFeaturesCache();
        }

        if (array_key_exists($featureKey, $this->cachedFeatures)) {
            return (bool) $this->cachedFeatures[$featureKey];
        }

        // Compatibility fallback for corporate work mode if not explicitly mapped as distinct feature
        if ($featureKey === 'wfo_wfh') {
            return $this->cachedFeatures['employee'] ?? false;
        }

        return false;
    }

    /**
     * Load features into instance cache.
     */
    public function loadFeaturesCache(): void
    {
        $this->cachedFeatures = $this->organizationFeatures()
            ->join('features', 'features.id', '=', 'organization_features.feature_id')
            ->pluck('organization_features.is_enabled', 'features.key')
            ->map(fn ($val) => (bool) $val)
            ->toArray();
    }

    /**
     * Clear instance-level feature cache (e.g., when features are updated).
     */
    public function clearFeaturesCache(): void
    {
        $this->cachedFeatures = null;
    }

    public function pegawais(): HasMany
    {
        return $this->hasMany(Pegawai::class, 'organization_id', 'organization_id');
    }

    public function masterDivisis(): HasMany
    {
        return $this->hasMany(MasterDivisi::class, 'organization_id', 'organization_id');
    }

    public function masterJabatans(): HasMany
    {
        return $this->hasMany(MasterJabatan::class, 'organization_id', 'organization_id');
    }

    public function jadwalKerjas(): HasMany
    {
        return $this->hasMany(WorkSchedule::class, 'organization_id', 'organization_id');
    }

    public function settings(): HasMany
    {
        return $this->hasMany(Setting::class, 'organization_id', 'organization_id');
    }

    public function roles(): HasMany
    {
        return $this->hasMany(Role::class, 'organization_id', 'organization_id');
    }

    /**
     * Get customized or default terminology for this organization.
     */
    public function getTerminology(string $key, ?string $default = null): string
    {
        try {
            $custom = Setting::where('key', 'term_' . $key)
                ->where('organization_id', $this->organization_id)
                ->value('value');
            if ($custom !== null && trim($custom) !== '') {
                return $custom;
            }
        } catch (\Throwable $e) {
        }

        $configDefault = config('organization_terminology.defaults.' . $key);
        if ($configDefault !== null) {
            return $configDefault;
        }

        return $default ?? ucfirst(str_replace('_', ' ', $key));
    }
}
