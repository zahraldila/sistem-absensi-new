<?php

namespace Tests\Feature;

use Tests\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use App\Models\Organization;
use App\Models\Feature;
use App\Models\OrganizationFeature;
use App\Models\Role;
use App\Models\Akun;
use App\Models\Pegawai;
use App\Models\Approval;
use App\Models\Setting;
use App\Helpers\OrganizationHelper;
use Database\Seeders\OrganizationFeatureSystemSeeder;

class ApprovalFeatureTest extends TestCase
{
    use RefreshDatabase;

    protected Akun $superAdmin;
    protected Role $superAdminRole;
    protected Organization $orgCorp;
    protected Organization $orgBimbel;
    protected Pegawai $pegawaiCorp;
    protected Pegawai $pegawaiBimbel;

    protected function setUp(): void
    {
        parent::setUp();

        OrganizationHelper::clearActiveOrganizationCache();

        $this->seed(OrganizationFeatureSystemSeeder::class);

        // Super Admin setup
        $this->superAdminRole = Role::firstOrCreate(['nama_role' => 'Super Admin', 'organization_id' => null]);
        $this->superAdmin = Akun::create([
            'username'   => 'superadmin_approval',
            'password'   => bcrypt('password'),
            'role_id'    => $this->superAdminRole->role_id,
            'role'       => 'Super Admin',
            'pegawai_id' => null,
        ]);

        // Create Org 1: Corporate (has approval, wfo_wfh, employee, division)
        $this->orgCorp = Organization::create([
            'nama_organisasi' => 'PT Selada Corporate',
            'kode_organisasi' => 'SELADA01',
            'status'          => 'active',
            'display_token'   => 'selada-token',
        ]);

        $this->enableFeature($this->orgCorp, 'approval');
        $this->enableFeature($this->orgCorp, 'wfo_wfh');
        $this->enableFeature($this->orgCorp, 'employee');
        $this->enableFeature($this->orgCorp, 'division');

        $this->pegawaiCorp = Pegawai::create([
            'nama_pegawai'    => 'Karyawan Selada',
            'nip'             => 'EMP-SELADA-01',
            'email'           => 'selada@corp.com',
            'organization_id' => $this->orgCorp->organization_id,
            'status'          => 'Aktif',
        ]);

        // Create Org 2: Bimbel (has approval, but wfo_wfh=false, custom terminology)
        $this->orgBimbel = Organization::create([
            'nama_organisasi' => 'Bimbel Prestasi',
            'kode_organisasi' => 'BIMBEL01',
            'status'          => 'active',
            'display_token'   => 'bimbel-token',
        ]);

        $this->enableFeature($this->orgBimbel, 'approval');
        $this->disableFeature($this->orgBimbel, 'wfo_wfh');
        $this->enableFeature($this->orgBimbel, 'employee');
        $this->disableFeature($this->orgBimbel, 'division');

        Setting::create([
            'organization_id' => $this->orgBimbel->organization_id,
            'key'             => 'term_member',
            'value'           => 'Siswa',
        ]);

        $this->pegawaiBimbel = Pegawai::create([
            'nama_pegawai'    => 'Siswa Bintang',
            'nip'             => 'SISWA-01',
            'email'           => 'siswa@bimbel.com',
            'organization_id' => $this->orgBimbel->organization_id,
            'status'          => 'Aktif',
        ]);
    }

    protected function tearDown(): void
    {
        OrganizationHelper::clearActiveOrganizationCache();
        parent::tearDown();
    }

    protected function enableFeature(Organization $org, string $featureKey): void
    {
        $feature = Feature::firstOrCreate(
            ['key' => $featureKey],
            ['name' => $featureKey, 'category_group' => 'corporate', 'status' => 'active']
        );
        OrganizationFeature::updateOrCreate(
            [
                'organization_id' => $org->organization_id,
                'feature_id'      => $feature->id,
            ],
            [
                'is_enabled' => true,
            ]
        );
        $org->clearFeaturesCache();
    }

    protected function disableFeature(Organization $org, string $featureKey): void
    {
        $feature = Feature::firstOrCreate(
            ['key' => $featureKey],
            ['name' => $featureKey, 'category_group' => 'corporate', 'status' => 'active']
        );
        OrganizationFeature::updateOrCreate(
            [
                'organization_id' => $org->organization_id,
                'feature_id'      => $feature->id,
            ],
            [
                'is_enabled' => false,
            ]
        );
        $org->clearFeaturesCache();
    }

    // =========================================================================
    // A — APPROVAL DISABLED
    // =========================================================================

    public function test_a1_approval_index_returns_403_when_approval_disabled()
    {
        $this->disableFeature($this->orgCorp, 'approval');

        $response = $this->actingAs($this->superAdmin)
            ->withSession(['active_organization_id' => $this->orgCorp->organization_id])
            ->get(route('admin.persetujuan'));

        $response->assertStatus(403);
    }

    public function test_a2_approval_detail_returns_403_when_approval_disabled()
    {
        $approval = Approval::create([
            'pegawai_id'        => $this->pegawaiCorp->pegawai_id,
            'jenis_pengajuan'   => 'Izin',
            'tanggal_pengajuan' => '2026-10-01',
            'status_pengajuan'  => 'Pending',
        ]);

        $this->disableFeature($this->orgCorp, 'approval');

        $response = $this->actingAs($this->superAdmin)
            ->withSession(['active_organization_id' => $this->orgCorp->organization_id])
            ->get(route('admin.persetujuan.detail', $approval));

        $response->assertStatus(403);
    }

    public function test_a3_approval_approve_returns_403_when_approval_disabled()
    {
        $approval = Approval::create([
            'pegawai_id'        => $this->pegawaiCorp->pegawai_id,
            'jenis_pengajuan'   => 'Izin',
            'tanggal_pengajuan' => '2026-10-01',
            'status_pengajuan'  => 'Pending',
        ]);

        $this->disableFeature($this->orgCorp, 'approval');

        $response = $this->actingAs($this->superAdmin)
            ->withSession(['active_organization_id' => $this->orgCorp->organization_id])
            ->post(route('admin.persetujuan.approve', $approval->pengajuan_id));

        $response->assertStatus(403);
    }

    public function test_a4_approval_reject_returns_403_when_approval_disabled()
    {
        $approval = Approval::create([
            'pegawai_id'        => $this->pegawaiCorp->pegawai_id,
            'jenis_pengajuan'   => 'Izin',
            'tanggal_pengajuan' => '2026-10-01',
            'status_pengajuan'  => 'Pending',
        ]);

        $this->disableFeature($this->orgCorp, 'approval');

        $response = $this->actingAs($this->superAdmin)
            ->withSession(['active_organization_id' => $this->orgCorp->organization_id])
            ->post(route('admin.persetujuan.reject', $approval->pengajuan_id), [
                'catatan_admin' => 'Alasan penolakan',
            ]);

        $response->assertStatus(403);
    }

    public function test_a5_approval_store_returns_403_when_approval_disabled()
    {
        $this->disableFeature($this->orgCorp, 'approval');

        $response = $this->actingAs($this->superAdmin)
            ->withSession(['active_organization_id' => $this->orgCorp->organization_id])
            ->post(route('admin.persetujuan.store'), [
                'pegawai_id' => $this->pegawaiCorp->pegawai_id,
                'tanggal'    => '2026-10-01',
                'jenis'      => 'Izin',
                'keterangan' => 'Urusan keluarga',
            ]);

        $response->assertStatus(403);
    }

    // =========================================================================
    // B — APPROVAL ENABLED
    // =========================================================================

    public function test_b6_approval_index_returns_200_when_approval_enabled()
    {
        $response = $this->actingAs($this->superAdmin)
            ->withSession(['active_organization_id' => $this->orgCorp->organization_id])
            ->get(route('admin.persetujuan'));

        $response->assertStatus(200);
    }

    public function test_b7_valid_approval_data_appears_on_index()
    {
        Approval::create([
            'pegawai_id'        => $this->pegawaiCorp->pegawai_id,
            'jenis_pengajuan'   => 'Sakit',
            'tanggal_pengajuan' => '2026-10-01',
            'status_pengajuan'  => 'Pending',
            'keterangan'        => 'Flu berat',
        ]);

        $response = $this->actingAs($this->superAdmin)
            ->withSession(['active_organization_id' => $this->orgCorp->organization_id])
            ->get(route('admin.persetujuan'));

        $response->assertStatus(200);
        $response->assertSee('Karyawan Selada');
        $response->assertSee('Sakit');
    }

    public function test_b8_approve_works_when_approval_enabled()
    {
        $approval = Approval::create([
            'pegawai_id'        => $this->pegawaiCorp->pegawai_id,
            'jenis_pengajuan'   => 'Izin',
            'tanggal_pengajuan' => '2026-10-01',
            'status_pengajuan'  => 'Pending',
        ]);

        $response = $this->actingAs($this->superAdmin)
            ->withSession(['active_organization_id' => $this->orgCorp->organization_id])
            ->post(route('admin.persetujuan.approve', $approval->pengajuan_id));

        $response->assertStatus(200);
        $response->assertJson([
            'status' => 'success',
        ]);

        $this->assertDatabaseHas('pengajuan', [
            'pengajuan_id'     => $approval->pengajuan_id,
            'status_pengajuan' => 'Disetujui',
        ]);
    }

    public function test_b9_reject_works_when_approval_enabled()
    {
        $approval = Approval::create([
            'pegawai_id'        => $this->pegawaiCorp->pegawai_id,
            'jenis_pengajuan'   => 'Izin',
            'tanggal_pengajuan' => '2026-10-01',
            'status_pengajuan'  => 'Pending',
        ]);

        $response = $this->actingAs($this->superAdmin)
            ->withSession(['active_organization_id' => $this->orgCorp->organization_id])
            ->post(route('admin.persetujuan.reject', $approval->pengajuan_id), [
                'catatan_admin' => 'Tidak memenuhi syarat pengajuan',
            ]);

        $response->assertStatus(200);
        $response->assertJson([
            'status' => 'success',
        ]);

        $this->assertDatabaseHas('pengajuan', [
            'pengajuan_id'     => $approval->pengajuan_id,
            'status_pengajuan' => 'Ditolak',
        ]);
    }

    public function test_b10_store_manual_approval_note_works()
    {
        $response = $this->actingAs($this->superAdmin)
            ->withSession(['active_organization_id' => $this->orgCorp->organization_id])
            ->post(route('admin.persetujuan.store'), [
                'pegawai_id' => $this->pegawaiCorp->pegawai_id,
                'tanggal'    => '2026-10-02',
                'jenis'      => 'Izin',
                'keterangan' => 'Keperluan mendesak',
            ]);

        $response->assertRedirect(route('admin.persetujuan'));

        $this->assertDatabaseHas('pengajuan', [
            'pegawai_id'        => $this->pegawaiCorp->pegawai_id,
            'jenis_pengajuan'   => 'Izin',
            'status_pengajuan'  => 'Disetujui',
            'source'            => 'MANUAL',
        ]);
    }

    // =========================================================================
    // C — WFO/WFH DISABLED
    // =========================================================================

    public function test_c11_wfh_does_not_appear_when_wfo_wfh_disabled()
    {
        // orgBimbel has wfo_wfh = false
        // Insert a WFH submission and an Izin submission for orgBimbel
        Approval::create([
            'pegawai_id'        => $this->pegawaiBimbel->pegawai_id,
            'jenis_pengajuan'   => 'WFH',
            'tanggal_pengajuan' => '2026-10-01',
            'status_pengajuan'  => 'Pending',
        ]);

        Approval::create([
            'pegawai_id'        => $this->pegawaiBimbel->pegawai_id,
            'jenis_pengajuan'   => 'Izin',
            'tanggal_pengajuan' => '2026-10-02',
            'status_pengajuan'  => 'Pending',
        ]);

        $response = $this->actingAs($this->superAdmin)
            ->withSession(['active_organization_id' => $this->orgBimbel->organization_id])
            ->get(route('admin.persetujuan'));

        $response->assertStatus(200);
        $response->assertSee('Izin');
        $response->assertDontSee('data-jenis="WFH"');
    }

    public function test_c14_filter_and_dropdown_wfo_wfh_dinas_not_rendered_when_disabled()
    {
        $response = $this->actingAs($this->superAdmin)
            ->withSession(['active_organization_id' => $this->orgBimbel->organization_id])
            ->get(route('admin.persetujuan'));

        $response->assertStatus(200);
        $content = $response->getContent();

        // In filter select modal, WFH, WFC, Dinas should not be in the options
        $this->assertStringNotContainsString('<option value="WFH">WFH</option>', $content);
        $this->assertStringNotContainsString('<option value="WFC">WFC</option>', $content);
        $this->assertStringNotContainsString('<option value="Dinas">Dinas</option>', $content);
    }

    public function test_c15_backend_excludes_wfh_wfc_dinas_when_wfo_wfh_disabled()
    {
        Approval::create([
            'pegawai_id'        => $this->pegawaiBimbel->pegawai_id,
            'jenis_pengajuan'   => 'WFH',
            'tanggal_pengajuan' => '2026-10-01',
            'status_pengajuan'  => 'Pending',
        ]);

        Approval::create([
            'pegawai_id'        => $this->pegawaiBimbel->pegawai_id,
            'jenis_pengajuan'   => 'Dinas',
            'tanggal_pengajuan' => '2026-10-01',
            'status_pengajuan'  => 'Pending',
        ]);

        Approval::create([
            'pegawai_id'        => $this->pegawaiBimbel->pegawai_id,
            'jenis_pengajuan'   => 'Sakit',
            'tanggal_pengajuan' => '2026-10-01',
            'status_pengajuan'  => 'Pending',
        ]);

        // AJAX request to fetch approvals table
        $response = $this->actingAs($this->superAdmin)
            ->withSession(['active_organization_id' => $this->orgBimbel->organization_id])
            ->getJson(route('admin.persetujuan'));

        $response->assertStatus(200);
        $data = $response->json();

        // Pending count should only be 1 (Sakit), WFH & Dinas excluded
        $this->assertEquals(1, $data['counts']['pending']);
        $this->assertStringNotContainsString('WFH', $data['html']);
        $this->assertStringNotContainsString('Dinas', $data['html']);
        $this->assertStringContainsString('Sakit', $data['html']);
    }

    // =========================================================================
    // D — WFO/WFH ENABLED
    // =========================================================================

    public function test_d16_wfh_wfc_dinas_available_when_wfo_wfh_enabled()
    {
        Approval::create([
            'pegawai_id'        => $this->pegawaiCorp->pegawai_id,
            'jenis_pengajuan'   => 'WFH',
            'tanggal_pengajuan' => '2026-10-01',
            'status_pengajuan'  => 'Pending',
        ]);

        $response = $this->actingAs($this->superAdmin)
            ->withSession(['active_organization_id' => $this->orgCorp->organization_id])
            ->get(route('admin.persetujuan'));

        $response->assertStatus(200);
        $content = $response->getContent();

        $this->assertStringContainsString('WFH', $content);
        $this->assertStringContainsString('<option value="WFH">WFH</option>', $content);
        $this->assertStringContainsString('<option value="WFC">WFC</option>', $content);
        $this->assertStringContainsString('<option value="Dinas">Dinas</option>', $content);
    }

    // =========================================================================
    // E — ORGANIZATION ISOLATION
    // =========================================================================

    public function test_e17_organization_a_does_not_see_approval_of_organization_b()
    {
        Approval::create([
            'pegawai_id'        => $this->pegawaiCorp->pegawai_id,
            'jenis_pengajuan'   => 'Izin',
            'tanggal_pengajuan' => '2026-10-01',
            'status_pengajuan'  => 'Pending',
            'keterangan'        => 'Keterangan Khusus Corp',
        ]);

        Approval::create([
            'pegawai_id'        => $this->pegawaiBimbel->pegawai_id,
            'jenis_pengajuan'   => 'Sakit',
            'tanggal_pengajuan' => '2026-10-01',
            'status_pengajuan'  => 'Pending',
            'keterangan'        => 'Keterangan Khusus Bimbel',
        ]);

        // Accessing as orgBimbel
        $response = $this->actingAs($this->superAdmin)
            ->withSession(['active_organization_id' => $this->orgBimbel->organization_id])
            ->get(route('admin.persetujuan'));

        $response->assertStatus(200);
        $response->assertSee('Siswa Bintang');
        $response->assertDontSee('Karyawan Selada');
        $response->assertDontSee('Keterangan Khusus Corp');
    }

    public function test_e18_cannot_access_or_action_approval_belonging_to_another_org()
    {
        $corpApproval = Approval::create([
            'pegawai_id'        => $this->pegawaiCorp->pegawai_id,
            'jenis_pengajuan'   => 'Izin',
            'tanggal_pengajuan' => '2026-10-01',
            'status_pengajuan'  => 'Pending',
        ]);

        // Attempt to show Corp approval while in Bimbel org context
        $response = $this->actingAs($this->superAdmin)
            ->withSession(['active_organization_id' => $this->orgBimbel->organization_id])
            ->get(route('admin.persetujuan.detail', $corpApproval));

        $response->assertStatus(403);

        // Attempt to approve Corp approval while in Bimbel org context
        $responseApprove = $this->actingAs($this->superAdmin)
            ->withSession(['active_organization_id' => $this->orgBimbel->organization_id])
            ->post(route('admin.persetujuan.approve', $corpApproval->pengajuan_id));

        $responseApprove->assertStatus(404);
    }

    // =========================================================================
    // F — TERMINOLOGY
    // =========================================================================

    public function test_f19_custom_terminology_used_in_approval_ui()
    {
        // orgBimbel has term_member = 'Siswa'
        $response = $this->actingAs($this->superAdmin)
            ->withSession(['active_organization_id' => $this->orgBimbel->organization_id])
            ->get(route('admin.persetujuan'));

        $response->assertStatus(200);
        $content = $response->getContent();

        // Check header, table header, or modal uses 'Siswa'
        $this->assertStringContainsString('Siswa', $content);
    }
}
