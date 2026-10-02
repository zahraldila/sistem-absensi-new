<?php

namespace App\Services;

use App\Models\CategoryTemplate;
use App\Models\Organization;
use App\Models\OrganizationCategory;
use App\Models\OrganizationFeature;
use App\Models\Role;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class OrganizationProvisioningService
{
    /**
     * Provision a new organization with a category and its template features.
     * Everything is wrapped in DB::transaction to ensure atomicity.
     *
     * @param array $organizationData [nama_organisasi, kode_organisasi, alamat, category_id, status, display_token]
     * @param array|null $featureOverrides [feature_key => bool] optional custom overrides
     * @return Organization
     */
    public function provision(array $organizationData, ?array $featureOverrides = null): Organization
    {
        return DB::transaction(function () use ($organizationData, $featureOverrides) {
            // 1. Resolve Category
            $categoryId = $organizationData['category_id'] ?? null;
            if (!$categoryId && isset($organizationData['category_slug'])) {
                $category = OrganizationCategory::where('slug', $organizationData['category_slug'])->first();
                $categoryId = $category?->id;
            }

            if (!$categoryId) {
                // Default to Perusahaan if not specified
                $perusahaan = OrganizationCategory::firstOrCreate(
                    ['slug' => 'perusahaan'],
                    ['name' => 'Perusahaan', 'description' => 'Default Organization', 'status' => 'active']
                );
                $categoryId = $perusahaan->id;
            }

            $category = OrganizationCategory::findOrFail($categoryId);

            // 2. Create Organization record
            $organization = Organization::create([
                'nama_organisasi' => $organizationData['nama_organisasi'],
                'kode_organisasi' => $organizationData['kode_organisasi'],
                'alamat'          => $organizationData['alamat'] ?? null,
                'status'          => $organizationData['status'] ?? 'active',
                'display_token'   => $organizationData['display_token'] ?? (string) Str::uuid(),
                'category_id'     => $category->id,
            ]);

            // 3. Resolve Template (use default template for this category)
            $template = CategoryTemplate::where('category_id', $category->id)
                ->where('is_default', true)
                ->first();

            // Fallback to first available template for this category if none marked is_default
            if (!$template) {
                $template = CategoryTemplate::where('category_id', $category->id)->first();
            }

            // 4. Attach Features from Template
            if ($template) {
                $templateFeatures = $template->templateFeatures()->with('feature')->get();

                foreach ($templateFeatures as $tmplFeat) {
                    $featKey = $tmplFeat->feature?->key;

                    // If an explicit override was provided for this feature key, apply it
                    $isEnabled = ($featKey && $featureOverrides !== null && array_key_exists($featKey, $featureOverrides))
                        ? (bool) $featureOverrides[$featKey]
                        : (bool) $tmplFeat->is_enabled;

                    OrganizationFeature::create([
                        'organization_id' => $organization->organization_id,
                        'feature_id'      => $tmplFeat->feature_id,
                        'is_enabled'      => $isEnabled,
                        'configuration'   => $tmplFeat->default_config,
                    ]);
                }
            }

            // 5. Seed default Member / Anggota Role for mobile apps
            $memberRole = Role::firstOrCreate(
                [
                    'nama_role'       => 'Anggota',
                    'organization_id' => $organization->organization_id,
                ],
                ['deskripsi' => 'Akun anggota untuk aplikasi mobile dan presensi.']
            );
            $memberRole->privileges()->detach();

            return $organization;
        });
    }

    /**
     * Apply or re-apply a template to an existing organization.
     */
    public function applyTemplate(Organization $organization, CategoryTemplate $template, bool $overwriteCustom = false): void
    {
        DB::transaction(function () use ($organization, $template, $overwriteCustom) {
            $templateFeatures = $template->templateFeatures()->get();

            foreach ($templateFeatures as $tmplFeat) {
                if ($overwriteCustom) {
                    OrganizationFeature::updateOrCreate(
                        [
                            'organization_id' => $organization->organization_id,
                            'feature_id'      => $tmplFeat->feature_id,
                        ],
                        [
                            'is_enabled'    => $tmplFeat->is_enabled,
                            'configuration' => $tmplFeat->default_config,
                        ]
                    );
                } else {
                    OrganizationFeature::firstOrCreate(
                        [
                            'organization_id' => $organization->organization_id,
                            'feature_id'      => $tmplFeat->feature_id,
                        ],
                        [
                            'is_enabled'    => $tmplFeat->is_enabled,
                            'configuration' => $tmplFeat->default_config,
                        ]
                    );
                }
            }

            $organization->clearFeaturesCache();
        });
    }
}
