<?php

namespace Tests\Feature;

use Tests\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;
use App\Models\Organization;
use App\Models\OrganizationCategory;
use App\Models\Feature;
use App\Models\CategoryTemplate;
use App\Models\CategoryTemplateFeature;
use App\Models\OrganizationFeature;
use App\Models\Role;
use App\Models\Privilege;
use App\Models\Akun;
use App\Models\Pegawai;
use App\Services\OrganizationProvisioningService;
use Database\Seeders\OrganizationFeatureSystemSeeder;

class DynamicFeatureSystemTest extends TestCase
{
    use RefreshDatabase;

    protected Akun $superAdmin;
    protected Role $superAdminRole;

    protected function setUp(): void
    {
        parent::setUp();

        // Clear static memoization cache
        \App\Helpers\OrganizationHelper::clearActiveOrganizationCache();

        // Run feature system seeder in sqlite memory
        $this->seed(OrganizationFeatureSystemSeeder::class);

        // Seed basic privileges needed for testing
        $privileges = [
            'lihat_dashboard',
            'lihat_laporan_kehadiran',
            'lihat_manajemen_akun',
            'lihat_persetujuan',
            'lihat_log_aktivitas',
        ];
        foreach ($privileges as $priv) {
            Privilege::firstOrCreate(['nama_privilege' => $priv], [
                'label_privilege' => ucfirst(str_replace('_', ' ', $priv)),
                'kategori'        => 'Web Admin',
            ]);
        }

        // Create Super Admin
        $this->superAdminRole = Role::firstOrCreate(['nama_role' => 'Super Admin', 'organization_id' => null]);
        $this->superAdmin = Akun::create([
            'username'   => 'superadmin',
            'password'   => bcrypt('password'),
            'role_id'    => $this->superAdminRole->role_id,
            'role'       => 'Super Admin',
            'pegawai_id' => null,
        ]);
    }

    protected function tearDown(): void
    {
        \App\Helpers\OrganizationHelper::clearActiveOrganizationCache();
        parent::tearDown();
    }

    // =========================================================================
    // 1. CATEGORY TESTS
    // =========================================================================

    public function test_super_admin_can_create_category()
    {
        $response = $this->actingAs($this->superAdmin)
            ->post(route('admin.system.categories.store'), [
                'name'        => 'Sekolah Menengah',
                'slug'        => 'sekolah-menengah',
                'description' => 'Institusi pendidikan formal',
                'status'      => 'active',
            ]);

        $response->assertRedirect(route('admin.system.categories'));
        $this->assertDatabaseHas('organization_categories', [
            'slug'   => 'sekolah-menengah',
            'name'   => 'Sekolah Menengah',
            'status' => 'active',
        ]);

        // Automatically creates a default template for the category
        $category = OrganizationCategory::where('slug', 'sekolah-menengah')->first();
        $this->assertNotNull($category);
        $this->assertDatabaseHas('category_templates', [
            'category_id' => $category->id,
            'is_default'  => true,
        ]);
    }

    public function test_duplicate_category_slug_is_rejected()
    {
        $response = $this->actingAs($this->superAdmin)
            ->post(route('admin.system.categories.store'), [
                'name'        => 'Duplikat Perusahaan',
                'slug'        => 'perusahaan', // already seeded
                'description' => 'Desc',
                'status'      => 'active',
            ]);

        $response->assertSessionHasErrors('slug');
    }

    public function test_super_admin_can_edit_category()
    {
        $category = OrganizationCategory::where('slug', 'perusahaan')->first();

        $response = $this->actingAs($this->superAdmin)
            ->put(route('admin.system.categories.update', $category->id), [
                'name'        => 'Korporasi Modern',
                'slug'        => 'perusahaan',
                'description' => 'Diperbarui',
                'status'      => 'active',
            ]);

        $response->assertRedirect(route('admin.system.categories'));
        $this->assertDatabaseHas('organization_categories', [
            'id'   => $category->id,
            'name' => 'Korporasi Modern',
        ]);
    }

    public function test_super_admin_can_toggle_category_status()
    {
        $category = OrganizationCategory::where('slug', 'bimbel')->first();

        $this->actingAs($this->superAdmin)
            ->post(route('admin.system.categories.toggle', $category->id));

        $this->assertEquals('inactive', $category->fresh()->status);

        $this->actingAs($this->superAdmin)
            ->post(route('admin.system.categories.toggle', $category->id));

        $this->assertEquals('active', $category->fresh()->status);
    }

    // =========================================================================
    // 2. FEATURE CATALOG TESTS
    // =========================================================================

    public function test_super_admin_can_create_feature()
    {
        $response = $this->actingAs($this->superAdmin)
            ->post(route('admin.system.features.store'), [
                'key'            => 'payroll',
                'name'           => 'Manajemen Penggajian',
                'description'    => 'Kalkulasi gaji pegawai bulanan',
                'category_group' => 'corporate',
                'status'         => 'active',
            ]);

        $response->assertRedirect(route('admin.system.features'));
        $this->assertDatabaseHas('features', [
            'key'            => 'payroll',
            'name'           => 'Manajemen Penggajian',
            'category_group' => 'corporate',
        ]);
    }

    public function test_duplicate_feature_key_is_rejected()
    {
        $response = $this->actingAs($this->superAdmin)
            ->post(route('admin.system.features.store'), [
                'key'            => 'attendance', // already seeded
                'name'           => 'Attendance Duplikat',
                'category_group' => 'core',
                'status'         => 'active',
            ]);

        $response->assertSessionHasErrors('key');
    }

    public function test_super_admin_can_edit_and_toggle_feature()
    {
        $feature = Feature::where('key', 'gps')->first();

        $this->actingAs($this->superAdmin)
            ->put(route('admin.system.features.update', $feature->id), [
                'key'            => 'gps',
                'name'           => 'Validasi Titik GPS Akurat',
                'category_group' => 'method',
                'status'         => 'active',
            ]);

        $this->assertEquals('Validasi Titik GPS Akurat', $feature->fresh()->name);

        $this->actingAs($this->superAdmin)
            ->post(route('admin.system.features.toggle', $feature->id));

        $this->assertEquals('inactive', $feature->fresh()->status);
    }

    // =========================================================================
    // 3. TEMPLATE MANAGEMENT TESTS
    // =========================================================================

    public function test_super_admin_can_create_and_set_default_template()
    {
        $category = OrganizationCategory::where('slug', 'bimbel')->first();
        $featAttendance = Feature::where('key', 'attendance')->first();
        $featStudent = Feature::where('key', 'student')->first();

        $response = $this->actingAs($this->superAdmin)
            ->post(route('admin.system.templates.store'), [
                'category_id' => $category->id,
                'name'        => 'Bimbel Intensif Premium',
                'description' => 'Template khusus kelas privat',
                'is_default'  => true,
                'features'    => [
                    $featAttendance->id => '1',
                    $featStudent->id    => '1',
                ],
            ]);

        $response->assertRedirect(route('admin.system.templates'));
        
        $newTemplate = CategoryTemplate::where('name', 'Bimbel Intensif Premium')->first();
        $this->assertNotNull($newTemplate);
        $this->assertTrue($newTemplate->is_default);

        // Previous default template for bimbel is no longer default
        $oldDefault = CategoryTemplate::where('category_id', $category->id)
            ->where('id', '!=', $newTemplate->id)
            ->where('is_default', true)
            ->first();
        $this->assertNull($oldDefault);
    }

    // =========================================================================
    // 4. ORGANIZATION PROVISIONING TESTS
    // =========================================================================

    public function test_create_organization_with_perusahaan_category_provisions_correct_features()
    {
        $categoryPerusahaan = OrganizationCategory::where('slug', 'perusahaan')->first();

        $response = $this->actingAs($this->superAdmin)
            ->post(route('admin.organization.storeNew'), [
                'nama_organisasi' => 'PT Sukses Mandiri',
                'kode_organisasi' => 'SUKSES',
                'alamat'          => 'Jl. Sudirman No 1',
                'category_id'     => $categoryPerusahaan->id,
            ]);

        $response->assertRedirect(route('admin.organization.select'));

        $org = Organization::where('kode_organisasi', 'SUKSES')->first();
        $this->assertNotNull($org);
        $this->assertEquals($categoryPerusahaan->id, $org->category_id);

        // Check features according to Perusahaan template
        $this->assertTrue($org->hasFeature('attendance'));
        $this->assertTrue($org->hasFeature('employee'));
        $this->assertTrue($org->hasFeature('approval'));
        $this->assertFalse($org->hasFeature('student')); // Academic is disabled in Perusahaan
        $this->assertFalse($org->hasFeature('tutor'));

        // Member role Anggota created
        $this->assertDatabaseHas('role', [
            'nama_role'       => 'Anggota',
            'organization_id' => $org->organization_id,
        ]);
    }

    public function test_create_organization_with_bimbel_category_provisions_correct_features()
    {
        $categoryBimbel = OrganizationCategory::where('slug', 'bimbel')->first();

        $response = $this->actingAs($this->superAdmin)
            ->post(route('admin.organization.storeNew'), [
                'nama_organisasi' => 'Bimbel Cerdas Prestasi',
                'kode_organisasi' => 'BCP01',
                'alamat'          => 'Jl. Pendidikan No 12',
                'category_id'     => $categoryBimbel->id,
            ]);

        $response->assertRedirect(route('admin.organization.select'));

        $org = Organization::where('kode_organisasi', 'BCP01')->first();
        $this->assertNotNull($org);

        // Check features according to Bimbel template
        $this->assertTrue($org->hasFeature('attendance'));
        $this->assertTrue($org->hasFeature('student'));  // Enabled for Bimbel
        $this->assertTrue($org->hasFeature('tutor'));    // Enabled for Bimbel
        $this->assertFalse($org->hasFeature('employee')); // Corporate disabled in Bimbel
        $this->assertFalse($org->hasFeature('approval')); // Corporate disabled in Bimbel
    }

    // =========================================================================
    // 5. ORGANIZATION FEATURE OVERRIDE TESTS
    // =========================================================================

    public function test_super_admin_can_override_features_for_specific_organization()
    {
        $categoryBimbel = OrganizationCategory::where('slug', 'bimbel')->first();
        $provisioner = app(OrganizationProvisioningService::class);
        $org = $provisioner->provision([
            'nama_organisasi' => 'Bimbel Override Test',
            'kode_organisasi' => 'BOVR01',
            'category_id'     => $categoryBimbel->id,
        ]);

        // Initially approval is false for Bimbel
        $this->assertFalse($org->hasFeature('approval'));

        // Super Admin overrides features: turn ON approval
        $featApproval = Feature::where('key', 'approval')->first();
        $featAttendance = Feature::where('key', 'attendance')->first();

        $this->actingAs($this->superAdmin)
            ->put(route('admin.organization.features.update', $org->organization_id), [
                'features' => [
                    $featApproval->id   => '1',
                    $featAttendance->id => '1',
                ],
            ]);

        // Cache must be cleared and immediately reflect updated capability
        $freshOrg = $org->fresh();
        $this->assertTrue($freshOrg->hasFeature('approval'));
        $this->assertTrue($freshOrg->hasFeature('attendance'));
    }

    // =========================================================================
    // 6. TENANT ISOLATION TESTS
    // =========================================================================

    public function test_tenant_isolation_organization_a_feature_override_does_not_affect_organization_b()
    {
        $provisioner = app(OrganizationProvisioningService::class);
        $cat = OrganizationCategory::where('slug', 'perusahaan')->first();

        $orgA = $provisioner->provision([
            'nama_organisasi' => 'Org A Tenant',
            'kode_organisasi' => 'ORGA_T',
            'category_id'     => $cat->id,
        ]);

        $orgB = $provisioner->provision([
            'nama_organisasi' => 'Org B Tenant',
            'kode_organisasi' => 'ORGB_T',
            'category_id'     => $cat->id,
        ]);

        // Both initially have nfc = true
        $this->assertTrue($orgA->hasFeature('nfc'));
        $this->assertTrue($orgB->hasFeature('nfc'));

        // Turn OFF nfc only on Org A
        $featNfc = Feature::where('key', 'nfc')->first();
        OrganizationFeature::where('organization_id', $orgA->organization_id)
            ->where('feature_id', $featNfc->id)
            ->update(['is_enabled' => false]);
        $orgA->clearFeaturesCache();

        $this->assertFalse($orgA->fresh()->hasFeature('nfc'));
        // Org B must remain true!
        $this->assertTrue($orgB->fresh()->hasFeature('nfc'));
    }

    // =========================================================================
    // 7. PILOT MIDDLEWARE INTEGRATION (EnsureFeatureEnabled)
    // =========================================================================

    public function test_middleware_blocks_access_when_feature_is_disabled()
    {
        $provisioner = app(OrganizationProvisioningService::class);
        $catBimbel = OrganizationCategory::where('slug', 'bimbel')->first();

        // In Bimbel, 'approval' feature is false by default
        $org = $provisioner->provision([
            'nama_organisasi' => 'Bimbel No Approval',
            'kode_organisasi' => 'BNA01',
            'category_id'     => $catBimbel->id,
        ]);

        // Super Admin selects this organization into session
        // Route /admin/persetujuan has middleware: ['privilege:lihat_persetujuan', 'feature:approval']
        $response = $this->actingAs($this->superAdmin)
            ->withSession(['active_organization_id' => $org->organization_id])
            ->get(route('admin.persetujuan'));

        // Must be aborted with 403 because feature 'approval' is disabled for this organization
        $response->assertStatus(403);
    }

    public function test_middleware_allows_access_when_feature_is_enabled()
    {
        $provisioner = app(OrganizationProvisioningService::class);
        $catPerusahaan = OrganizationCategory::where('slug', 'perusahaan')->first();

        // In Perusahaan, 'approval' feature is true
        $org = $provisioner->provision([
            'nama_organisasi' => 'PT Yes Approval',
            'kode_organisasi' => 'PYA01',
            'category_id'     => $catPerusahaan->id,
        ]);

        $response = $this->actingAs($this->superAdmin)
            ->withSession(['active_organization_id' => $org->organization_id])
            ->get(route('admin.persetujuan'));

        // Passes feature gate (returns 200 or whatever controller renders)
        $this->assertNotEquals(403, $response->getStatusCode());
    }
}
