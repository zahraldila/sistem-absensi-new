<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\CategoryTemplate;
use App\Models\CategoryTemplateFeature;
use App\Models\Feature;
use App\Models\Organization;
use App\Models\OrganizationCategory;
use App\Models\OrganizationFeature;
use App\Services\OrganizationProvisioningService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class FeatureSystemController extends Controller
{
    /**
     * Enforce Super Admin only for all feature system management.
     */
    public function __construct()
    {
        // Checked in methods or middleware
    }

    protected function authorizeSuperAdmin(): void
    {
        if (! Auth::user()?->isSuperAdmin()) {
            abort(403, 'Akses ditolak: Hanya Super Admin yang berhak mengelola sistem fitur & kategori.');
        }
    }

    // =========================================================================
    // BAGIAN B: CATEGORY MANAGEMENT
    // =========================================================================

    public function categoriesIndex()
    {
        $this->authorizeSuperAdmin();

        $categories = OrganizationCategory::withCount(['organizations', 'templates'])
            ->orderBy('name')
            ->get();

        return view('admin.system.categories', compact('categories'));
    }

    public function categoryStore(Request $request)
    {
        $this->authorizeSuperAdmin();

        $validated = $request->validate([
            'name'        => 'required|string|max:100',
            'slug'        => 'required|string|max:100|alpha_dash|unique:organization_categories,slug',
            'description' => 'nullable|string',
            'status'      => 'required|in:active,inactive',
        ], [
            'name.required' => 'Nama kategori wajib diisi.',
            'slug.required' => 'Slug kategori wajib diisi.',
            'slug.unique'   => 'Slug kategori sudah digunakan, silakan gunakan slug lain.',
            'slug.alpha_dash' => 'Slug hanya boleh berisi huruf, angka, strip, dan garis bawah.',
        ]);

        \Illuminate\Support\Facades\Log::info('[FeatureSystem] categoryStore: validated input received', [
            'slug' => $validated['slug'],
            'name' => $validated['name'],
        ]);

        DB::transaction(function () use ($validated) {
            \Illuminate\Support\Facades\Log::info('[FeatureSystem] categoryStore: creating category...');
            $category = OrganizationCategory::create($validated);
            \Illuminate\Support\Facades\Log::info('[FeatureSystem] categoryStore: category created', ['id' => $category->id]);

            \Illuminate\Support\Facades\Log::info('[FeatureSystem] categoryStore: creating default template...');
            $template = CategoryTemplate::create([
                'category_id' => $category->id,
                'name'        => $category->name . ' (Default)',
                'description' => 'Template bawaan untuk kategori ' . $category->name . '.',
                'is_default'  => true,
            ]);
            \Illuminate\Support\Facades\Log::info('[FeatureSystem] categoryStore: default template created', ['template_id' => $template->id]);
        });

        \Illuminate\Support\Facades\Log::info('[FeatureSystem] categoryStore: transaction completed successfully, redirecting.');

        return redirect()->route('admin.system.categories')->with('success', 'Kategori baru berhasil dibuat dengan template default.');
    }

    public function categoryUpdate(Request $request, OrganizationCategory $category)
    {
        $this->authorizeSuperAdmin();

        $validated = $request->validate([
            'name'        => 'required|string|max:100',
            'slug'        => 'required|string|max:100|alpha_dash|unique:organization_categories,slug,' . $category->id,
            'description' => 'nullable|string',
            'status'      => 'required|in:active,inactive',
        ], [
            'name.required' => 'Nama kategori wajib diisi.',
            'slug.required' => 'Slug kategori wajib diisi.',
            'slug.unique'   => 'Slug kategori sudah digunakan.',
        ]);

        $category->update($validated);

        return redirect()->route('admin.system.categories')->with('success', 'Kategori ' . $category->name . ' berhasil diperbarui.');
    }

    public function categoryToggle(OrganizationCategory $category)
    {
        $this->authorizeSuperAdmin();

        $newStatus = $category->status === 'active' ? 'inactive' : 'active';
        $category->update(['status' => $newStatus]);

        return redirect()->route('admin.system.categories')->with('success', 'Status kategori ' . $category->name . ' diubah menjadi ' . $newStatus . '.');
    }

    // =========================================================================
    // BAGIAN C: FEATURE CATALOG MANAGEMENT
    // =========================================================================

    public function featuresIndex()
    {
        $this->authorizeSuperAdmin();

        $features = Feature::orderBy('category_group')
            ->orderBy('name')
            ->get()
            ->groupBy('category_group');

        return view('admin.system.features', compact('features'));
    }

    public function featureStore(Request $request)
    {
        $this->authorizeSuperAdmin();

        $validated = $request->validate([
            'key'            => 'required|string|max:50|alpha_dash|unique:features,key',
            'name'           => 'required|string|max:100',
            'description'    => 'nullable|string',
            'category_group' => 'required|string|max:50',
            'status'         => 'required|in:active,inactive',
        ], [
            'key.required'       => 'Key fitur wajib diisi.',
            'key.unique'         => 'Key fitur sudah terdaftar di sistem.',
            'key.alpha_dash'     => 'Key fitur hanya boleh huruf, angka, strip, dan garis bawah.',
            'name.required'      => 'Nama fitur wajib diisi.',
            'category_group.required' => 'Grup kategori fitur wajib diisi.',
        ]);

        DB::transaction(function () use ($validated) {
            $feature = Feature::create($validated);

            // Automatically attach this feature to all existing templates (disabled by default)
            $templates = CategoryTemplate::all();
            if ($templates->isNotEmpty()) {
                $insertData = [];
                $now = now();
                foreach ($templates as $template) {
                    $insertData[] = [
                        'template_id'    => $template->id,
                        'feature_id'     => $feature->id,
                        'is_enabled'     => false,
                        'default_config' => null,
                        'created_at'     => $now,
                        'updated_at'     => $now,
                    ];
                }
                CategoryTemplateFeature::insert($insertData);
            }
        });

        return redirect()->route('admin.system.features')->with('success', 'Fitur global [' . $validated['key'] . '] berhasil didaftarkan ke katalog.');
    }

    public function featureUpdate(Request $request, Feature $feature)
    {
        $this->authorizeSuperAdmin();

        $validated = $request->validate([
            'key'            => 'required|string|max:50|alpha_dash|unique:features,key,' . $feature->id,
            'name'           => 'required|string|max:100',
            'description'    => 'nullable|string',
            'category_group' => 'required|string|max:50',
            'status'         => 'required|in:active,inactive',
        ], [
            'key.required'   => 'Key fitur wajib diisi.',
            'key.unique'     => 'Key fitur sudah terdaftar di sistem.',
            'name.required'  => 'Nama fitur wajib diisi.',
        ]);

        $feature->update($validated);

        return redirect()->route('admin.system.features')->with('success', 'Fitur [' . $feature->key . '] berhasil diperbarui.');
    }

    public function featureToggle(Feature $feature)
    {
        $this->authorizeSuperAdmin();

        $newStatus = $feature->status === 'active' ? 'inactive' : 'active';
        $feature->update(['status' => $newStatus]);

        return redirect()->route('admin.system.features')->with('success', 'Status fitur [' . $feature->key . '] diubah menjadi ' . $newStatus . '.');
    }

    // =========================================================================
    // BAGIAN D: TEMPLATE MANAGEMENT
    // =========================================================================

    public function templatesIndex()
    {
        $this->authorizeSuperAdmin();

        $templates = CategoryTemplate::with(['category', 'templateFeatures.feature'])
            ->orderBy('category_id')
            ->orderByDesc('is_default')
            ->get();

        $categories = OrganizationCategory::where('status', 'active')->orderBy('name')->get();
        $features = Feature::where('status', 'active')->orderBy('category_group')->orderBy('name')->get();

        return view('admin.system.templates', compact('templates', 'categories', 'features'));
    }

    public function templateCreate()
    {
        $this->authorizeSuperAdmin();

        $categories = OrganizationCategory::where('status', 'active')->orderBy('name')->get();
        $features = Feature::where('status', 'active')->orderBy('category_group')->orderBy('name')->get();

        return view('admin.system.template-form', [
            'template'   => new CategoryTemplate(),
            'categories' => $categories,
            'features'   => $features,
            'isEdit'     => false,
        ]);
    }

    public function templateStore(Request $request)
    {
        $this->authorizeSuperAdmin();

        $validated = $request->validate([
            'category_id' => 'required|exists:organization_categories,id',
            'name'        => 'required|string|max:100',
            'description' => 'nullable|string',
            'is_default'  => 'nullable|boolean',
            'features'    => 'nullable|array',
        ], [
            'category_id.required' => 'Kategori template wajib dipilih.',
            'name.required'        => 'Nama template wajib diisi.',
        ]);

        $isDefault = $request->boolean('is_default');

        DB::transaction(function () use ($validated, $isDefault, $request) {
            if ($isDefault) {
                // Unset existing defaults for this category
                CategoryTemplate::where('category_id', $validated['category_id'])->update(['is_default' => false]);
            }

            $template = CategoryTemplate::create([
                'category_id' => $validated['category_id'],
                'name'        => $validated['name'],
                'description' => $validated['description'] ?? null,
                'is_default'  => $isDefault,
            ]);

            $selectedFeatureIds = array_keys($request->input('features', []));
            $allFeatures = Feature::all();

            $insertData = [];
            $now = now();
            foreach ($allFeatures as $feat) {
                $insertData[] = [
                    'template_id'    => $template->id,
                    'feature_id'     => $feat->id,
                    'is_enabled'     => in_array($feat->id, $selectedFeatureIds),
                    'default_config' => null,
                    'created_at'     => $now,
                    'updated_at'     => $now,
                ];
            }
            if (!empty($insertData)) {
                CategoryTemplateFeature::insert($insertData);
            }
        });

        return redirect()->route('admin.system.templates')->with('success', 'Template kategori baru berhasil dibuat.');
    }

    public function templateEdit(CategoryTemplate $template)
    {
        $this->authorizeSuperAdmin();

        $categories = OrganizationCategory::where('status', 'active')->orderBy('name')->get();
        $features = Feature::orderBy('category_group')->orderBy('name')->get();
        $templateFeaturesMap = $template->templateFeatures->pluck('is_enabled', 'feature_id')->toArray();

        return view('admin.system.template-form', [
            'template'            => $template,
            'categories'          => $categories,
            'features'            => $features,
            'templateFeaturesMap' => $templateFeaturesMap,
            'isEdit'              => true,
        ]);
    }

    public function templateUpdate(Request $request, CategoryTemplate $template)
    {
        $this->authorizeSuperAdmin();

        $validated = $request->validate([
            'category_id' => 'required|exists:organization_categories,id',
            'name'        => 'required|string|max:100',
            'description' => 'nullable|string',
            'is_default'  => 'nullable|boolean',
            'features'    => 'nullable|array',
        ], [
            'category_id.required' => 'Kategori template wajib dipilih.',
            'name.required'        => 'Nama template wajib diisi.',
        ]);

        $isDefault = $request->boolean('is_default');

        DB::transaction(function () use ($template, $validated, $isDefault, $request) {
            if ($isDefault && ! $template->is_default) {
                CategoryTemplate::where('category_id', $validated['category_id'])
                    ->where('id', '!=', $template->id)
                    ->update(['is_default' => false]);
            }

            $template->update([
                'category_id' => $validated['category_id'],
                'name'        => $validated['name'],
                'description' => $validated['description'] ?? null,
                'is_default'  => $isDefault,
            ]);

            $selectedFeatureIds = array_keys($request->input('features', []));
            $allFeatures = Feature::all();

            foreach ($allFeatures as $feat) {
                CategoryTemplateFeature::updateOrCreate(
                    [
                        'template_id' => $template->id,
                        'feature_id'  => $feat->id,
                    ],
                    [
                        'is_enabled' => in_array($feat->id, $selectedFeatureIds),
                    ]
                );
            }
        });

        return redirect()->route('admin.system.templates')->with('success', 'Template ' . $template->name . ' berhasil diperbarui.');
    }

    public function templateSetDefault(CategoryTemplate $template)
    {
        $this->authorizeSuperAdmin();

        DB::transaction(function () use ($template) {
            CategoryTemplate::where('category_id', $template->category_id)->update(['is_default' => false]);
            $template->update(['is_default' => true]);
        });

        return redirect()->route('admin.system.templates')->with('success', 'Template ' . $template->name . ' telah ditetapkan sebagai template default.');
    }

    // =========================================================================
    // BAGIAN E: ORGANIZATION FEATURE MANAGEMENT (OVERRIDE PER ORGANISASI)
    // =========================================================================

    public function orgFeaturesIndex(Organization $organization)
    {
        $this->authorizeSuperAdmin();

        $organization->load(['category.defaultTemplate', 'organizationFeatures.feature']);

        $features = Feature::orderBy('category_group')
            ->orderBy('name')
            ->get()
            ->groupBy('category_group');

        // Key-value map of feature_id => is_enabled for this organization
        $currentFeaturesMap = $organization->organizationFeatures->pluck('is_enabled', 'feature_id')->toArray();

        return view('admin.system.organization-features', compact('organization', 'features', 'currentFeaturesMap'));
    }

    public function orgFeaturesUpdate(Request $request, Organization $organization)
    {
        $this->authorizeSuperAdmin();

        $selectedFeatureIds = array_keys($request->input('features', []));
        $allFeatures = Feature::all();

        DB::transaction(function () use ($organization, $allFeatures, $selectedFeatureIds) {
            foreach ($allFeatures as $feature) {
                $isEnabled = in_array($feature->id, $selectedFeatureIds);

                OrganizationFeature::updateOrCreate(
                    [
                        'organization_id' => $organization->organization_id,
                        'feature_id'      => $feature->id,
                    ],
                    [
                        'is_enabled'    => $isEnabled,
                        'configuration' => null,
                    ]
                );
            }

            // Immediately clear in-memory and instance feature cache
            $organization->clearFeaturesCache();
            \App\Helpers\OrganizationHelper::clearActiveOrganizationCache();
        });

        if ($request->wantsJson()) {
            return response()->json([
                'success' => true,
                'message' => 'Fitur organisasi ' . $organization->nama_organisasi . ' berhasil diperbarui.',
            ]);
        }

        if ($request->filled('redirect_to')) {
            return redirect($request->input('redirect_to'))
                ->with('success', 'Fitur organisasi ' . $organization->nama_organisasi . ' berhasil diperbarui.');
        }

        return redirect()->back()
            ->with('success', 'Fitur organisasi ' . $organization->nama_organisasi . ' berhasil diperbarui.');
    }
}
