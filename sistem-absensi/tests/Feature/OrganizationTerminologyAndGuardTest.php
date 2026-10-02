<?php

namespace Tests\Feature;

use Tests\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Blade;
use App\Models\Organization;
use App\Models\OrganizationCategory;
use App\Models\Feature;
use App\Models\OrganizationFeature;
use App\Models\Akun;
use App\Models\Pegawai;
use App\Models\Role;
use App\Models\Setting;
use App\Helpers\OrganizationHelper;
use Database\Seeders\OrganizationFeatureSystemSeeder;

class OrganizationTerminologyAndGuardTest extends TestCase
{
    use RefreshDatabase;

    protected Organization $orgA;
    protected Organization $orgB;
    protected Akun $userA;
    protected Akun $userB;

    protected function setUp(): void
    {
        parent::setUp();

        OrganizationHelper::clearActiveOrganizationCache();

        $this->seed(OrganizationFeatureSystemSeeder::class);

        $category = OrganizationCategory::firstOrCreate(
            ['slug' => 'test-category'],
            ['name' => 'Test Category', 'is_active' => true]
        );

        // Org A
        $this->orgA = Organization::create([
            'nama_organisasi' => 'Organisasi Alfa',
            'kode_organisasi' => 'ALFA',
            'category_id'     => $category->id,
            'status'          => 'active',
            'display_token'   => 'token_alfa_123',
        ]);

        $roleA = Role::create([
            'nama_role'       => 'Admin Alfa',
            'organization_id' => $this->orgA->organization_id,
        ]);

        $pegawaiA = Pegawai::create([
            'nama_pegawai'    => 'User Alfa',
            'nip'             => 'ALFA001',
            'email'           => 'alfa@example.com',
            'organization_id' => $this->orgA->organization_id,
        ]);

        $this->userA = Akun::create([
            'username'   => 'alfa_user',
            'password'   => bcrypt('password'),
            'role_id'    => $roleA->role_id,
            'role'       => 'Admin',
            'pegawai_id' => $pegawaiA->pegawai_id,
        ]);

        // Org B
        $this->orgB = Organization::create([
            'nama_organisasi' => 'Organisasi Beta',
            'kode_organisasi' => 'BETA',
            'category_id'     => $category->id,
            'status'          => 'active',
            'display_token'   => 'token_beta_456',
        ]);

        $roleB = Role::create([
            'nama_role'       => 'Admin Beta',
            'organization_id' => $this->orgB->organization_id,
        ]);

        $pegawaiB = Pegawai::create([
            'nama_pegawai'    => 'User Beta',
            'nip'             => 'BETA001',
            'email'           => 'beta@example.com',
            'organization_id' => $this->orgB->organization_id,
        ]);

        $this->userB = Akun::create([
            'username'   => 'beta_user',
            'password'   => bcrypt('password'),
            'role_id'    => $roleB->role_id,
            'role'       => 'Admin',
            'pegawai_id' => $pegawaiB->pegawai_id,
        ]);
    }

    /**
     * A. term() fallback: existing/default context menghasilkan fallback yang benar.
     */
    public function test_term_fallback_returns_configured_defaults(): void
    {
        $this->actingAs($this->userA);
        OrganizationHelper::clearActiveOrganizationCache();

        $this->assertEquals('Anggota', OrganizationHelper::term('member'));
        $this->assertEquals('ID Anggota', OrganizationHelper::term('member_id'));
        $this->assertEquals('Data Anggota', OrganizationHelper::term('member_management'));
        $this->assertEquals('Divisi', OrganizationHelper::term('division'));
        $this->assertEquals('Jabatan', OrganizationHelper::term('position'));
        $this->assertEquals('Lokasi', OrganizationHelper::term('location'));
    }

    /**
     * B. missing key: tidak error dan menggunakan $default param atau humanized fallback.
     */
    public function test_term_missing_key_does_not_throw_and_returns_default(): void
    {
        $this->actingAs($this->userA);
        OrganizationHelper::clearActiveOrganizationCache();

        $resultWithDefault = OrganizationHelper::term('non_existent_key', 'Custom Fallback');
        $this->assertEquals('Custom Fallback', $resultWithDefault);

        $resultWithoutDefault = OrganizationHelper::term('some_arbitrary_key');
        $this->assertEquals('Some arbitrary key', $resultWithoutDefault);
    }

    /**
     * C. no active organization: tidak error dan menggunakan config fallback.
     */
    public function test_term_without_active_organization_does_not_throw(): void
    {
        // No user authenticated
        OrganizationHelper::clearActiveOrganizationCache();

        $this->assertNull(OrganizationHelper::getActiveOrganizationId());
        $this->assertEquals('Anggota', OrganizationHelper::term('member'));
        $this->assertEquals('Custom Value', OrganizationHelper::term('unknown_key', 'Custom Value'));
    }

    /**
     * Custom organization setting overrides default term.
     */
    public function test_term_uses_organization_custom_setting_if_present(): void
    {
        $this->actingAs($this->userA);
        OrganizationHelper::clearActiveOrganizationCache();

        // Save custom setting for Org A
        Setting::create([
            'organization_id' => $this->orgA->organization_id,
            'key'             => 'term_member',
            'value'           => 'Peserta Kursus',
        ]);

        $this->assertEquals('Peserta Kursus', OrganizationHelper::term('member'));

        // Org B should still see default (tenant isolation of terminology)
        $this->actingAs($this->userB);
        OrganizationHelper::clearActiveOrganizationCache();
        $this->assertEquals('Anggota', OrganizationHelper::term('member'));
    }

    /**
     * D. hasFeature(): enabled => true, disabled => false, unknown => false.
     */
    public function test_has_feature_evaluation(): void
    {
        $employeeFeature = Feature::where('key', 'employee')->firstOrFail();
        $approvalFeature = Feature::where('key', 'approval')->firstOrFail();

        // Enable employee, disable approval on Org A
        OrganizationFeature::updateOrCreate(
            ['organization_id' => $this->orgA->organization_id, 'feature_id' => $employeeFeature->id],
            ['is_enabled' => true]
        );
        OrganizationFeature::updateOrCreate(
            ['organization_id' => $this->orgA->organization_id, 'feature_id' => $approvalFeature->id],
            ['is_enabled' => false]
        );

        $this->orgA->clearFeaturesCache();

        $this->assertTrue($this->orgA->hasFeature('employee'));
        $this->assertFalse($this->orgA->hasFeature('approval'));
        $this->assertFalse($this->orgA->hasFeature('completely_unknown_feature'));
    }

    /**
     * E. @hasfeature Blade directive: feature enabled => content rendered, disabled => content hidden.
     */
    public function test_blade_hasfeature_directive(): void
    {
        $employeeFeature = Feature::where('key', 'employee')->firstOrFail();
        $approvalFeature = Feature::where('key', 'approval')->firstOrFail();

        OrganizationFeature::updateOrCreate(
            ['organization_id' => $this->orgA->organization_id, 'feature_id' => $employeeFeature->id],
            ['is_enabled' => true]
        );
        OrganizationFeature::updateOrCreate(
            ['organization_id' => $this->orgA->organization_id, 'feature_id' => $approvalFeature->id],
            ['is_enabled' => false]
        );

        $this->actingAs($this->userA);
        OrganizationHelper::clearActiveOrganizationCache();

        $bladeTemplate = '
            @hasfeature(\'employee\')
                <span>EMPLOYEE_VISIBLE</span>
            @endhasfeature
            @hasfeature(\'approval\')
                <span>APPROVAL_VISIBLE</span>
            @endhasfeature
        ';

        $rendered = Blade::render($bladeTemplate);

        $this->assertStringContainsString('EMPLOYEE_VISIBLE', $rendered);
        $this->assertStringNotContainsString('APPROVAL_VISIBLE', $rendered);
    }

    /**
     * Blade @hasanyfeature directive test.
     */
    public function test_blade_hasanyfeature_directive(): void
    {
        $employeeFeature = Feature::where('key', 'employee')->firstOrFail();

        OrganizationFeature::updateOrCreate(
            ['organization_id' => $this->orgA->organization_id, 'feature_id' => $employeeFeature->id],
            ['is_enabled' => true]
        );

        $this->actingAs($this->userA);
        OrganizationHelper::clearActiveOrganizationCache();

        $bladeAnyMatch = '
            @hasanyfeature([\'approval\', \'employee\'])
                <span>ANY_MATCH</span>
            @endhasanyfeature
        ';
        $bladeNoMatch = '
            @hasanyfeature([\'approval\', \'reports\'])
                <span>NO_MATCH</span>
            @endhasanyfeature
        ';

        $this->assertStringContainsString('ANY_MATCH', Blade::render($bladeAnyMatch));
        $this->assertStringNotContainsString('NO_MATCH', Blade::render($bladeNoMatch));
    }

    /**
     * F. Tenant isolation: feature organization A tidak mempengaruhi organization B.
     */
    public function test_feature_tenant_isolation(): void
    {
        $employeeFeature = Feature::where('key', 'employee')->firstOrFail();

        // Enable for Org A, disable for Org B
        OrganizationFeature::updateOrCreate(
            ['organization_id' => $this->orgA->organization_id, 'feature_id' => $employeeFeature->id],
            ['is_enabled' => true]
        );
        OrganizationFeature::updateOrCreate(
            ['organization_id' => $this->orgB->organization_id, 'feature_id' => $employeeFeature->id],
            ['is_enabled' => false]
        );

        // Org A context
        $this->actingAs($this->userA);
        OrganizationHelper::clearActiveOrganizationCache();
        $this->assertTrue(OrganizationHelper::canAccessFeature('employee'));
        $this->assertStringContainsString('IS_ACTIVE', Blade::render('@hasfeature(\'employee\') IS_ACTIVE @endhasfeature'));

        // Org B context
        $this->actingAs($this->userB);
        OrganizationHelper::clearActiveOrganizationCache();
        $this->assertFalse(OrganizationHelper::canAccessFeature('employee'));
        $this->assertStringNotContainsString('IS_ACTIVE', Blade::render('@hasfeature(\'employee\') IS_ACTIVE @endhasfeature'));
    }

    /**
     * G. Sidebar: Menu employee rendered only when feature employee is ON and privilege is ON.
     * When feature is OFF, menu is completely hidden (not disabled/grayed out).
     */
    public function test_sidebar_employee_menu_hidden_when_feature_disabled(): void
    {
        $employeeFeature = Feature::where('key', 'employee')->firstOrFail();

        // Org A: employee feature = true, grant privilege
        OrganizationFeature::updateOrCreate(
            ['organization_id' => $this->orgA->organization_id, 'feature_id' => $employeeFeature->id],
            ['is_enabled' => true]
        );
        $roleA = Role::find($this->userA->role_id);
        $privManajemenAkun = \App\Models\Privilege::firstOrCreate(
            ['nama_privilege' => 'lihat_manajemen_akun'],
            ['label_privilege' => 'Lihat Manajemen Akun', 'kategori' => 'Web Admin']
        );
        $roleA->privileges()->syncWithoutDetaching([$privManajemenAkun->privilege_id]);

        // Org B: employee feature = false, grant privilege
        OrganizationFeature::updateOrCreate(
            ['organization_id' => $this->orgB->organization_id, 'feature_id' => $employeeFeature->id],
            ['is_enabled' => false]
        );
        $roleB = Role::find($this->userB->role_id);
        $roleB->privileges()->syncWithoutDetaching([$privManajemenAkun->privilege_id]);

        // Render sidebar for Org A (feature ON, privilege ON)
        $this->actingAs($this->userA);
        OrganizationHelper::clearActiveOrganizationCache();
        $viewA = $this->blade('<x-layout.sidebar />');
        $this->assertStringContainsString('admin/manajemen-akun', $viewA);
        $this->assertStringContainsString('Data Anggota', $viewA);

        // Render sidebar for Org B (feature OFF, privilege ON) => must NOT contain route or text
        $this->actingAs($this->userB);
        OrganizationHelper::clearActiveOrganizationCache();
        $viewB = $this->blade('<x-layout.sidebar />');
        $this->assertStringNotContainsString('admin/manajemen-akun', $viewB);
        $this->assertStringNotContainsString('Anda tidak memiliki akses ke Manajemen Akun', $viewB);
    }

    /**
     * H. Sidebar: Menu approval rendered only when feature approval is ON.
     * When feature approval is OFF, menu is completely hidden.
     */
    public function test_sidebar_approval_menu_hidden_when_feature_disabled(): void
    {
        $approvalFeature = Feature::where('key', 'approval')->firstOrFail();

        // Org A: approval = true
        OrganizationFeature::updateOrCreate(
            ['organization_id' => $this->orgA->organization_id, 'feature_id' => $approvalFeature->id],
            ['is_enabled' => true]
        );
        $privApproval = \App\Models\Privilege::firstOrCreate(
            ['nama_privilege' => 'lihat_persetujuan'],
            ['label_privilege' => 'Lihat Persetujuan', 'kategori' => 'Web Admin']
        );
        $roleA = Role::find($this->userA->role_id);
        $roleA->privileges()->syncWithoutDetaching([$privApproval->privilege_id]);

        // Org B: approval = false
        OrganizationFeature::updateOrCreate(
            ['organization_id' => $this->orgB->organization_id, 'feature_id' => $approvalFeature->id],
            ['is_enabled' => false]
        );
        $roleB = Role::find($this->userB->role_id);
        $roleB->privileges()->syncWithoutDetaching([$privApproval->privilege_id]);

        // Context Org A
        $this->actingAs($this->userA);
        OrganizationHelper::clearActiveOrganizationCache();
        $viewA = $this->blade('<x-layout.sidebar />');
        $this->assertStringContainsString('Persetujuan', $viewA);

        // Context Org B
        $this->actingAs($this->userB);
        OrganizationHelper::clearActiveOrganizationCache();
        $viewB = $this->blade('<x-layout.sidebar />');
        $this->assertStringNotContainsString('Persetujuan', $viewB);
        $this->assertStringNotContainsString('admin/persetujuan', $viewB);
        $this->assertStringNotContainsString('Anda tidak memiliki akses ke Persetujuan', $viewB);
    }

    /**
     * I. Sidebar: Context switch from Org A to Org B immediately updates sidebar menus.
     */
    public function test_sidebar_reflects_active_organization_switch(): void
    {
        $employeeFeature = Feature::where('key', 'employee')->firstOrFail();
        $approvalFeature = Feature::where('key', 'approval')->firstOrFail();

        // Org A: has employee, has approval
        OrganizationFeature::updateOrCreate(
            ['organization_id' => $this->orgA->organization_id, 'feature_id' => $employeeFeature->id],
            ['is_enabled' => true]
        );
        OrganizationFeature::updateOrCreate(
            ['organization_id' => $this->orgA->organization_id, 'feature_id' => $approvalFeature->id],
            ['is_enabled' => true]
        );

        // Org B: NO employee, NO approval
        OrganizationFeature::updateOrCreate(
            ['organization_id' => $this->orgB->organization_id, 'feature_id' => $employeeFeature->id],
            ['is_enabled' => false]
        );
        OrganizationFeature::updateOrCreate(
            ['organization_id' => $this->orgB->organization_id, 'feature_id' => $approvalFeature->id],
            ['is_enabled' => false]
        );

        $superAdminRole = Role::firstOrCreate(['nama_role' => 'Super Admin', 'organization_id' => null]);
        $superAdminUser = Akun::create([
            'username'   => 'sa_sidebar',
            'password'   => bcrypt('password'),
            'role_id'    => $superAdminRole->role_id,
            'role'       => 'Super Admin',
            'pegawai_id' => null,
        ]);

        $this->actingAs($superAdminUser);

        // Switch to Org A
        session(['active_organization_id' => $this->orgA->organization_id]);
        OrganizationHelper::clearActiveOrganizationCache();
        $viewA = $this->blade('<x-layout.sidebar />');
        $this->assertStringContainsString('Organisasi Alfa', $viewA);
        $this->assertStringContainsString('Persetujuan', $viewA);
        $this->assertStringContainsString('Sistem & Fitur', $viewA); // Super admin sees this

        // Switch to Org B
        session(['active_organization_id' => $this->orgB->organization_id]);
        OrganizationHelper::clearActiveOrganizationCache();
        $viewB = $this->blade('<x-layout.sidebar />');
        $this->assertStringContainsString('Organisasi Beta', $viewB);
        $this->assertStringNotContainsString('Persetujuan', $viewB);
    }

    /**
     * J. Dashboard: Corporate capability (wfo_wfh, approval, schedule) displays full widgets and cards.
     */
    public function test_corporate_dashboard_renders_all_corporate_widgets(): void
    {
        $features = Feature::whereIn('key', ['employee', 'attendance', 'approval', 'schedule'])->get();
        foreach ($features as $f) {
            OrganizationFeature::updateOrCreate(
                ['organization_id' => $this->orgA->organization_id, 'feature_id' => $f->id],
                ['is_enabled' => true]
            );
        }

        // Grant privilege lihat_dashboard
        $privDashboard = \App\Models\Privilege::firstOrCreate(
            ['nama_privilege' => 'lihat_dashboard'],
            ['label_privilege' => 'Lihat Dashboard', 'kategori' => 'Web Admin']
        );
        $roleA = Role::find($this->userA->role_id);
        $roleA->privileges()->syncWithoutDetaching([$privDashboard->privilege_id]);

        $this->actingAs($this->userA);
        OrganizationHelper::clearActiveOrganizationCache();

        $response = $this->get(route('admin.dashboard'));
        $response->assertStatus(200);

        // Corporate widgets present
        $response->assertSee('WFO');
        $response->assertSee('WFH / WFC');
        $response->assertSee('Menunggu Persetujuan');
        $response->assertSee('Edit Jam Masuk');

        // Dynamic chart JSON contains WFO/WFH
        $chartResp = $this->getJson(route('admin.chart-statistik', ['filter' => 'minggu']));
        $chartResp->assertStatus(200);
        $chartResp->assertJsonFragment(['label' => 'WFO']);
        $chartResp->assertJsonFragment(['label' => 'WFH/WFC']);
    }

    /**
     * K. Dashboard: Non-corporate capability (employee = false, approval = false, schedule = false)
     * hides WFO, WFH/WFC, approval widget, and edit jam masuk button/modal.
     */
    public function test_non_corporate_dashboard_hides_wfo_approval_and_schedule(): void
    {
        $featAttendance = Feature::where('key', 'attendance')->firstOrFail();
        $featEmployee   = Feature::where('key', 'employee')->firstOrFail();
        $featApproval   = Feature::where('key', 'approval')->firstOrFail();
        $featSchedule   = Feature::where('key', 'schedule')->firstOrFail();

        OrganizationFeature::updateOrCreate(
            ['organization_id' => $this->orgB->organization_id, 'feature_id' => $featAttendance->id],
            ['is_enabled' => true]
        );
        OrganizationFeature::updateOrCreate(
            ['organization_id' => $this->orgB->organization_id, 'feature_id' => $featEmployee->id],
            ['is_enabled' => false]
        );
        OrganizationFeature::updateOrCreate(
            ['organization_id' => $this->orgB->organization_id, 'feature_id' => $featApproval->id],
            ['is_enabled' => false]
        );
        OrganizationFeature::updateOrCreate(
            ['organization_id' => $this->orgB->organization_id, 'feature_id' => $featSchedule->id],
            ['is_enabled' => false]
        );

        $privDashboard = \App\Models\Privilege::firstOrCreate(
            ['nama_privilege' => 'lihat_dashboard'],
            ['label_privilege' => 'Lihat Dashboard', 'kategori' => 'Web Admin']
        );
        $roleB = Role::find($this->userB->role_id);
        $roleB->privileges()->syncWithoutDetaching([$privDashboard->privilege_id]);

        $this->actingAs($this->userB);
        OrganizationHelper::clearActiveOrganizationCache();

        $response = $this->get(route('admin.dashboard'));
        $response->assertStatus(200);

        // Corporate widgets NOT present
        $response->assertDontSee('Work From Office');
        $response->assertDontSee('Remote Working');
        $response->assertDontSee('Menunggu Persetujuan');
        $response->assertDontSee('Edit Jam Masuk');

        // Core attendance summary STILL present
        $response->assertSee('Total Anggota');
        $response->assertSee('Hadir Hari Ini');

        // Dynamic chart JSON does NOT contain WFO/WFH, instead contains general Hadir
        $chartResp = $this->getJson(route('admin.chart-statistik', ['filter' => 'minggu']));
        $chartResp->assertStatus(200);
        $chartResp->assertJsonFragment(['label' => 'Hadir']);
        $chartResp->assertJsonMissing(['label' => 'WFO']);
        $chartResp->assertJsonMissing(['label' => 'WFH/WFC']);
    }

    /**
     * L. Dashboard: Terminology override dynamically reflects in dashboard headers and labels.
     */
    public function test_dashboard_uses_custom_terminology(): void
    {
        $featAttendance = Feature::where('key', 'attendance')->firstOrFail();
        OrganizationFeature::updateOrCreate(
            ['organization_id' => $this->orgA->organization_id, 'feature_id' => $featAttendance->id],
            ['is_enabled' => true]
        );

        Setting::create([
            'organization_id' => $this->orgA->organization_id,
            'key'             => 'term_member',
            'value'           => 'Karyawan PT',
        ]);

        $privDashboard = \App\Models\Privilege::firstOrCreate(
            ['nama_privilege' => 'lihat_dashboard'],
            ['label_privilege' => 'Lihat Dashboard', 'kategori' => 'Web Admin']
        );
        $roleA = Role::find($this->userA->role_id);
        $roleA->privileges()->syncWithoutDetaching([$privDashboard->privilege_id]);

        $this->actingAs($this->userA);
        OrganizationHelper::clearActiveOrganizationCache();

        $response = $this->get(route('admin.dashboard'));
        $response->assertStatus(200);
        $response->assertSee('Dashboard Kehadiran Karyawan PT');
        $response->assertSee('Total Karyawan PT');
    }

    /**
     * M. Attendance Report - Scenario A: Corporate (division = true, wfo_wfh = true).
     * Filter & column Divisi and Mode Kerja are rendered. Export includes both.
     */
    public function test_attendance_report_corporate_renders_division_and_work_mode(): void
    {
        $featAttendance = Feature::where('key', 'attendance')->firstOrFail();
        $featDivision   = Feature::where('key', 'division')->firstOrFail();
        $featWfo        = Feature::firstOrCreate(
            ['key' => 'wfo_wfh'],
            ['name' => 'Mode Kerja WFO/WFH', 'category_group' => 'corporate', 'status' => 'active']
        );

        OrganizationFeature::updateOrCreate(
            ['organization_id' => $this->orgA->organization_id, 'feature_id' => $featAttendance->id],
            ['is_enabled' => true]
        );
        OrganizationFeature::updateOrCreate(
            ['organization_id' => $this->orgA->organization_id, 'feature_id' => $featDivision->id],
            ['is_enabled' => true]
        );
        OrganizationFeature::updateOrCreate(
            ['organization_id' => $this->orgA->organization_id, 'feature_id' => $featWfo->id],
            ['is_enabled' => true]
        );

        $privReport = \App\Models\Privilege::firstOrCreate(
            ['nama_privilege' => 'lihat_laporan_kehadiran'],
            ['label_privilege' => 'Lihat Laporan Kehadiran', 'kategori' => 'Web Admin']
        );
        $privExport = \App\Models\Privilege::firstOrCreate(
            ['nama_privilege' => 'export_laporan_kehadiran'],
            ['label_privilege' => 'Export Laporan Kehadiran', 'kategori' => 'Web Admin']
        );
        $roleA = Role::find($this->userA->role_id);
        $roleA->privileges()->syncWithoutDetaching([$privReport->privilege_id, $privExport->privilege_id]);

        $divisi = \App\Models\MasterDivisi::create([
            'nama_divisi'     => 'IT Development',
            'organization_id' => $this->orgA->organization_id,
        ]);
        $member = Pegawai::create([
            'nama_pegawai'    => 'Anggota Alfa 1',
            'nip'             => 'MBRA001',
            'email'           => 'mbra001@example.com',
            'status'          => 'Aktif',
            'divisi_id'       => $divisi->divisi_id,
            'organization_id' => $this->orgA->organization_id,
        ]);

        \App\Models\Attendance::create([
            'pegawai_id'       => $member->pegawai_id,
            'tanggal_absensi'  => now()->toDateString(),
            'jam_checkin'      => '08:00:00',
            'jam_checkout'     => '17:00:00',
            'skema_kerja'      => 'WFO',
            'status_kehadiran' => 'Hadir',
        ]);

        $this->actingAs($this->userA);
        OrganizationHelper::clearActiveOrganizationCache();

        $response = $this->get(route('admin.laporan-kehadiran'));
        $response->assertStatus(200);

        // Division & Work Mode filters and columns visible
        $response->assertSee('name="divisi_id"', false);
        $response->assertSee('name="mode_kerja"', false);
        $response->assertSee('IT Development');
        $response->assertSee('WFO');

        // Export CSV includes Division and Mode Kerja columns
        $csvResp = $this->get(route('admin.laporan-kehadiran.export.csv'));
        $csvResp->assertStatus(200);
        $streamOutput = $csvResp->streamedContent();
        $this->assertStringContainsString('Divisi', $streamOutput);
        $this->assertStringContainsString('Mode Kerja', $streamOutput);
    }

    /**
     * N. Attendance Report - Scenario B: No Division (division = false, wfo_wfh = true).
     * Filter & column Divisi are hidden. divisi_id request parameter is safely ignored.
     */
    public function test_attendance_report_no_division(): void
    {
        $featAttendance = Feature::where('key', 'attendance')->firstOrFail();
        $featDivision   = Feature::where('key', 'division')->firstOrFail();
        $featWfo        = Feature::firstOrCreate(
            ['key' => 'wfo_wfh'],
            ['name' => 'Mode Kerja WFO/WFH', 'category_group' => 'corporate', 'status' => 'active']
        );

        OrganizationFeature::updateOrCreate(
            ['organization_id' => $this->orgA->organization_id, 'feature_id' => $featAttendance->id],
            ['is_enabled' => true]
        );
        OrganizationFeature::updateOrCreate(
            ['organization_id' => $this->orgA->organization_id, 'feature_id' => $featDivision->id],
            ['is_enabled' => false]
        );
        OrganizationFeature::updateOrCreate(
            ['organization_id' => $this->orgA->organization_id, 'feature_id' => $featWfo->id],
            ['is_enabled' => true]
        );

        $privReport = \App\Models\Privilege::firstOrCreate(
            ['nama_privilege' => 'lihat_laporan_kehadiran'],
            ['label_privilege' => 'Lihat Laporan Kehadiran', 'kategori' => 'Web Admin']
        );
        $roleA = Role::find($this->userA->role_id);
        $roleA->privileges()->syncWithoutDetaching([$privReport->privilege_id]);

        $member = Pegawai::create([
            'nama_pegawai'    => 'Anggota Alfa 2',
            'nip'             => 'MBRA002',
            'email'           => 'mbra002@example.com',
            'status'          => 'Aktif',
            'organization_id' => $this->orgA->organization_id,
        ]);
        \App\Models\Attendance::create([
            'pegawai_id'       => $member->pegawai_id,
            'tanggal_absensi'  => now()->toDateString(),
            'jam_checkin'      => '08:00:00',
            'jam_checkout'     => '17:00:00',
            'skema_kerja'      => 'WFO',
            'status_kehadiran' => 'Hadir',
        ]);

        $this->actingAs($this->userA);
        OrganizationHelper::clearActiveOrganizationCache();

        // Sending a dummy divisi_id=999 must NOT filter out the attendance because division is disabled
        $response = $this->get(route('admin.laporan-kehadiran', ['divisi_id' => 999]));
        $response->assertStatus(200);

        // Division filter & table column hidden
        $response->assertDontSee('name="divisi_id"', false);
        $response->assertSee('name="mode_kerja"', false);
        $response->assertSee($member->nama_pegawai);
        $response->assertSee('WFO');
    }

    /**
     * O. Attendance Report - Scenario C: No Work Mode (division = true, wfo_wfh = false).
     * Filter & column Mode Kerja are hidden. work_mode / mode_kerja parameter is safely ignored.
     */
    public function test_attendance_report_no_work_mode(): void
    {
        $featAttendance = Feature::where('key', 'attendance')->firstOrFail();
        $featDivision   = Feature::where('key', 'division')->firstOrFail();
        $featWfo        = Feature::firstOrCreate(
            ['key' => 'wfo_wfh'],
            ['name' => 'Mode Kerja WFO/WFH', 'category_group' => 'corporate', 'status' => 'active']
        );

        OrganizationFeature::updateOrCreate(
            ['organization_id' => $this->orgA->organization_id, 'feature_id' => $featAttendance->id],
            ['is_enabled' => true]
        );
        OrganizationFeature::updateOrCreate(
            ['organization_id' => $this->orgA->organization_id, 'feature_id' => $featDivision->id],
            ['is_enabled' => true]
        );
        OrganizationFeature::updateOrCreate(
            ['organization_id' => $this->orgA->organization_id, 'feature_id' => $featWfo->id],
            ['is_enabled' => false]
        );

        $privReport = \App\Models\Privilege::firstOrCreate(
            ['nama_privilege' => 'lihat_laporan_kehadiran'],
            ['label_privilege' => 'Lihat Laporan Kehadiran', 'kategori' => 'Web Admin']
        );
        $roleA = Role::find($this->userA->role_id);
        $roleA->privileges()->syncWithoutDetaching([$privReport->privilege_id]);

        $divisi = \App\Models\MasterDivisi::create([
            'nama_divisi'     => 'Finance Division',
            'organization_id' => $this->orgA->organization_id,
        ]);
        $member = Pegawai::create([
            'nama_pegawai'    => 'Anggota Alfa 3',
            'nip'             => 'MBRA003',
            'email'           => 'mbra003@example.com',
            'status'          => 'Aktif',
            'divisi_id'       => $divisi->divisi_id,
            'organization_id' => $this->orgA->organization_id,
        ]);

        \App\Models\Attendance::create([
            'pegawai_id'       => $member->pegawai_id,
            'tanggal_absensi'  => now()->toDateString(),
            'jam_checkin'      => '08:00:00',
            'jam_checkout'     => '17:00:00',
            'skema_kerja'      => 'WFO',
            'status_kehadiran' => 'Hadir',
        ]);

        $this->actingAs($this->userA);
        OrganizationHelper::clearActiveOrganizationCache();

        // Sending an invalid mode_kerja must NOT filter out the record because wfo_wfh is disabled
        $response = $this->get(route('admin.laporan-kehadiran', ['mode_kerja' => 'NON_EXISTENT_MODE']));
        $response->assertStatus(200);

        $response->assertSee('name="divisi_id"', false);
        $response->assertDontSee('name="mode_kerja"', false);
        $response->assertSee('Finance Division');
        $response->assertSee($member->nama_pegawai);
    }

    /**
     * P. Attendance Report - Scenario D: Neither Division nor Work Mode (division = false, wfo_wfh = false).
     * Both filters and columns are hidden. Both parameters are ignored. Report works seamlessly.
     */
    public function test_attendance_report_neither_division_nor_work_mode(): void
    {
        $featAttendance = Feature::where('key', 'attendance')->firstOrFail();
        $featDivision   = Feature::where('key', 'division')->firstOrFail();
        $featWfo        = Feature::firstOrCreate(
            ['key' => 'wfo_wfh'],
            ['name' => 'Mode Kerja WFO/WFH', 'category_group' => 'corporate', 'status' => 'active']
        );

        OrganizationFeature::updateOrCreate(
            ['organization_id' => $this->orgA->organization_id, 'feature_id' => $featAttendance->id],
            ['is_enabled' => true]
        );
        OrganizationFeature::updateOrCreate(
            ['organization_id' => $this->orgA->organization_id, 'feature_id' => $featDivision->id],
            ['is_enabled' => false]
        );
        OrganizationFeature::updateOrCreate(
            ['organization_id' => $this->orgA->organization_id, 'feature_id' => $featWfo->id],
            ['is_enabled' => false]
        );

        $privReport = \App\Models\Privilege::firstOrCreate(
            ['nama_privilege' => 'lihat_laporan_kehadiran'],
            ['label_privilege' => 'Lihat Laporan Kehadiran', 'kategori' => 'Web Admin']
        );
        $roleA = Role::find($this->userA->role_id);
        $roleA->privileges()->syncWithoutDetaching([$privReport->privilege_id]);

        $member = Pegawai::create([
            'nama_pegawai'    => 'Anggota Alfa 4',
            'nip'             => 'MBRA004',
            'email'           => 'mbra004@example.com',
            'status'          => 'Aktif',
            'organization_id' => $this->orgA->organization_id,
        ]);
        \App\Models\Attendance::create([
            'pegawai_id'       => $member->pegawai_id,
            'tanggal_absensi'  => now()->toDateString(),
            'jam_checkin'      => '08:00:00',
            'jam_checkout'     => '17:00:00',
            'skema_kerja'      => 'WFO',
            'status_kehadiran' => 'Hadir',
        ]);

        $this->actingAs($this->userA);
        OrganizationHelper::clearActiveOrganizationCache();

        $response = $this->get(route('admin.laporan-kehadiran', ['divisi_id' => 123, 'mode_kerja' => 'WFH']));
        $response->assertStatus(200);

        // Neither filter is rendered
        $response->assertDontSee('name="divisi_id"', false);
        $response->assertDontSee('name="mode_kerja"', false);
        $response->assertSee($member->nama_pegawai);
    }

    /**
     * Q. Attendance Report - Scenario E & F: Active Organization Switch and Export Feature-Aware.
     * When organization has features disabled, exported CSV contains zero corporate columns.
     */
    public function test_attendance_report_export_omits_disabled_features(): void
    {
        $featAttendance = Feature::where('key', 'attendance')->firstOrFail();
        $featDivision   = Feature::where('key', 'division')->firstOrFail();
        $featWfo        = Feature::firstOrCreate(
            ['key' => 'wfo_wfh'],
            ['name' => 'Mode Kerja WFO/WFH', 'category_group' => 'corporate', 'status' => 'active']
        );

        // Org B: Academic/Non-corporate with division=false, wfo_wfh=false
        OrganizationFeature::updateOrCreate(
            ['organization_id' => $this->orgB->organization_id, 'feature_id' => $featAttendance->id],
            ['is_enabled' => true]
        );
        OrganizationFeature::updateOrCreate(
            ['organization_id' => $this->orgB->organization_id, 'feature_id' => $featDivision->id],
            ['is_enabled' => false]
        );
        OrganizationFeature::updateOrCreate(
            ['organization_id' => $this->orgB->organization_id, 'feature_id' => $featWfo->id],
            ['is_enabled' => false]
        );

        $privReport = \App\Models\Privilege::firstOrCreate(
            ['nama_privilege' => 'lihat_laporan_kehadiran'],
            ['label_privilege' => 'Lihat Laporan Kehadiran', 'kategori' => 'Web Admin']
        );
        $privExport = \App\Models\Privilege::firstOrCreate(
            ['nama_privilege' => 'export_laporan_kehadiran'],
            ['label_privilege' => 'Export Laporan Kehadiran', 'kategori' => 'Web Admin']
        );
        $roleB = Role::find($this->userB->role_id);
        $roleB->privileges()->syncWithoutDetaching([$privReport->privilege_id, $privExport->privilege_id]);

        $memberB = Pegawai::create([
            'nama_pegawai'    => 'Anggota Beta 1',
            'nip'             => 'MBRB001',
            'email'           => 'mbrb001@example.com',
            'status'          => 'Aktif',
            'organization_id' => $this->orgB->organization_id,
        ]);
        \App\Models\Attendance::create([
            'pegawai_id'       => $memberB->pegawai_id,
            'tanggal_absensi'  => now()->toDateString(),
            'jam_checkin'      => '08:30:00',
            'jam_checkout'     => '16:30:00',
            'skema_kerja'      => 'WFO',
            'status_kehadiran' => 'Hadir',
        ]);

        $this->actingAs($this->userB);
        OrganizationHelper::clearActiveOrganizationCache();

        $csvResp = $this->get(route('admin.laporan-kehadiran.export.csv'));
        $csvResp->assertStatus(200);
        $streamOutput = $csvResp->streamedContent();

        // Must NOT contain disabled corporate columns
        $this->assertStringNotContainsString('Divisi', $streamOutput);
        $this->assertStringNotContainsString('Mode Kerja', $streamOutput);

        // Core attendance columns remain present
        $this->assertStringContainsString('Nama Anggota', $streamOutput);
        $this->assertStringContainsString('Tanggal', $streamOutput);
        $this->assertStringContainsString('Status', $streamOutput);
    }

    /**
     * R. Attendance Report - Scenario G: PT Selada Regression.
     * PT Selada retains corporate capability for division and work mode.
     */
    public function test_pt_selada_regression_features(): void
    {
        $selada = Organization::where('nama_organisasi', 'like', '%Selada%')->first();
        if (!$selada) {
            $perusahaanCat = OrganizationCategory::where('slug', 'perusahaan')->first();
            if ($perusahaanCat) {
                $selada = app(\App\Services\OrganizationProvisioningService::class)->provision([
                    'nama_organisasi' => 'PT Selada',
                    'kode_organisasi' => 'SELADA',
                    'category_id'     => $perusahaanCat->id,
                    'status'          => 'active',
                ]);
            }
        }

        if (!$selada) {
            $this->markTestSkipped('PT Selada organization not found and could not be provisioned.');
        }

        $this->assertTrue($selada->hasFeature('division'), 'PT Selada must have division feature enabled.');
        $this->assertTrue($selada->hasFeature('wfo_wfh'), 'PT Selada must have wfo_wfh capability enabled.');
        $this->assertTrue($selada->hasFeature('attendance'), 'PT Selada must have attendance feature enabled.');
    }
}

