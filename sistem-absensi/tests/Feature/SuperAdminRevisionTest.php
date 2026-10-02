<?php

namespace Tests\Feature;

use Tests\TestCase;
use App\Models\Akun;
use App\Models\Feature;
use App\Models\Organization;
use App\Models\OrganizationCategory;
use App\Models\OrganizationFeature;
use App\Models\Role;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;

class SuperAdminRevisionTest extends TestCase
{
    use RefreshDatabase;

    protected Akun $superAdmin;
    protected Organization $orgA;
    protected Organization $orgB;
    protected Feature $featureAttendance;
    protected Feature $featureGps;

    protected function setUp(): void
    {
        parent::setUp();

        $role = Role::firstOrCreate(
            ['nama_role' => 'Super Admin'],
            ['deskripsi' => 'Super Admin Role', 'is_active' => true]
        );

        $this->superAdmin = Akun::create([
            'username' => 'test-sa-' . uniqid(),
            'password' => bcrypt('secret'),
            'role_id'  => $role->role_id,
            'role'     => 'Super Admin',
        ]);

        $category = OrganizationCategory::firstOrCreate(
            ['slug' => 'test-cat'],
            ['name' => 'Perusahaan', 'status' => 'active']
        );

        $this->orgA = Organization::create([
            'nama_organisasi' => 'Organisasi Test A',
            'kode_organisasi' => 'TESTA',
            'alamat'          => 'Jl. Test No. 1',
            'status'          => 'active',
            'category_id'     => $category->id,
            'display_token'   => 'token-test-a',
        ]);

        $this->orgB = Organization::create([
            'nama_organisasi' => 'Organisasi Test B',
            'kode_organisasi' => 'TESTB',
            'alamat'          => 'Jl. Test No. 2',
            'status'          => 'active',
            'category_id'     => $category->id,
            'display_token'   => 'token-test-b',
        ]);

        $this->featureAttendance = Feature::firstOrCreate(
            ['key' => 'attendance'],
            ['name' => 'Presensi Kehadiran', 'category_group' => 'core', 'status' => 'active']
        );

        $this->featureGps = Feature::firstOrCreate(
            ['key' => 'gps'],
            ['name' => 'Validasi GPS', 'category_group' => 'location', 'status' => 'active']
        );
    }

    public function test_super_admin_organizations_page_renders_table_and_no_cards_or_summary_metrics()
    {
        $response = $this->actingAs($this->superAdmin)->get(route('admin.organization.select'));

        $response->assertOk();
        // Header
        $response->assertSee('Organisasi');
        $response->assertSee('Kelola seluruh organisasi yang terdaftar dalam sistem.');
        $response->assertSee('Tambah Organisasi');

        // Table headers
        $response->assertSee('Nama Organisasi');
        $response->assertSee('Kategori');
        $response->assertSee('Alamat');
        $response->assertSee('Status');
        $response->assertSee('Aksi');

        // Rows
        $response->assertSee('Organisasi Test A');
        $response->assertSee('TESTA');
        $response->assertSee('Organisasi Test B');
        $response->assertSee('TESTB');

        // Actions
        $response->assertSee('Masuk');
        $response->assertSee('Kelola Fitur');

        // Modal title
        $response->assertSee('Kelola Fitur Organisasi');

        // Assert 3 summary cards are NOT shown as requested
        $response->assertDontSee('Total Organisasi');
        $response->assertDontSee('Organisasi Aktif');
        $response->assertDontSee('Aktivitas Hari Ini');
    }

    public function test_super_admin_can_manage_features_via_modal_endpoint()
    {
        $response = $this->actingAs($this->superAdmin)->put(
            route('admin.organization.features.update', $this->orgA->organization_id),
            [
                'features' => [
                    $this->featureAttendance->id => '1',
                ],
                'redirect_to' => route('admin.organization.select'),
            ]
        );

        $response->assertRedirect(route('admin.organization.select'));
        $response->assertSessionHas('success');

        $this->assertTrue(
            OrganizationFeature::where('organization_id', $this->orgA->organization_id)
                ->where('feature_id', $this->featureAttendance->id)
                ->value('is_enabled')
        );
    }

    public function test_super_admin_masuk_enters_organization_dashboard()
    {
        $response = $this->actingAs($this->superAdmin)->post(
            route('admin.organization.store'),
            ['organization_id' => $this->orgA->organization_id]
        );

        $response->assertRedirect(route('admin.dashboard'));
        $this->assertEquals($this->orgA->organization_id, session('active_organization_id'));
    }

    public function test_super_admin_can_access_global_log_aktivitas_without_active_organization()
    {
        // No active_organization_id in session
        session()->forget('active_organization_id');

        DB::table('audit_log')->insert([
            'akun_id'   => $this->superAdmin->id,
            'aktivitas' => 'Super admin melakukan aksi audit global',
            'waktu_log' => now(),
        ]);

        $response = $this->actingAs($this->superAdmin)->get(route('admin.log-aktivitas'));

        $response->assertOk();
        $response->assertSee('Log Aktivitas');
        $response->assertSee('Organisasi'); // Filter and column
        $response->assertSee('Super admin melakukan aksi audit global');
    }

    public function test_super_admin_global_sidebar_renders_expected_menus_and_hides_settings()
    {
        session()->forget('active_organization_id');

        $view = $this->actingAs($this->superAdmin)->blade('<x-layout.sidebar />');

        $this->assertStringContainsString('Organisasi', $view);
        $this->assertStringContainsString('Log Aktivitas', $view);
        $this->assertStringContainsString('Sistem & Fitur', $view);
        $this->assertStringContainsString('Logout', $view);

        // In global mode, settings should not be shown
        $this->assertStringNotContainsString('route(\'admin.tampilan-branding\')', $view);
        $this->assertStringNotContainsString('tampilan-branding', $view);
    }
}
