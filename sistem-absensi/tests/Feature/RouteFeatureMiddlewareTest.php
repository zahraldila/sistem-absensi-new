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
use App\Helpers\OrganizationHelper;
use Database\Seeders\OrganizationFeatureSystemSeeder;

class RouteFeatureMiddlewareTest extends TestCase
{
    use RefreshDatabase;

    protected Akun $superAdmin;
    protected Role $superAdminRole;
    protected Organization $orgA;
    protected Organization $orgB;
    protected Pegawai $pegawaiA;
    protected Pegawai $pegawaiB;

    protected function setUp(): void
    {
        parent::setUp();

        OrganizationHelper::clearActiveOrganizationCache();

        $this->seed(OrganizationFeatureSystemSeeder::class);

        $this->superAdminRole = Role::firstOrCreate(['nama_role' => 'Super Admin', 'organization_id' => null]);
        $this->superAdmin = Akun::create([
            'username'   => 'superadmin_route_test',
            'password'   => bcrypt('password'),
            'role_id'    => $this->superAdminRole->role_id,
            'role'       => 'Super Admin',
            'pegawai_id' => null,
        ]);

        $this->orgA = Organization::create([
            'nama_organisasi' => 'Organisasi Alfa Complete',
            'kode_organisasi' => 'ALFA01',
            'status'          => 'active',
            'display_token'   => 'alfa-token',
        ]);

        $this->orgB = Organization::create([
            'nama_organisasi' => 'Organisasi Beta Minimal',
            'kode_organisasi' => 'BETA01',
            'status'          => 'active',
            'display_token'   => 'beta-token',
        ]);

        // Enable full features for orgA
        $allFeatures = ['attendance', 'schedule', 'location', 'gps', 'employee', 'division', 'position', 'approval', 'wfo_wfh'];
        foreach ($allFeatures as $feat) {
            $this->enableFeature($this->orgA, $feat);
        }

        // orgB has everything disabled except employee
        foreach ($allFeatures as $feat) {
            $this->disableFeature($this->orgB, $feat);
        }
        $this->enableFeature($this->orgB, 'employee');

        // Create Pegawai for both
        $this->pegawaiA = Pegawai::create([
            'nama_pegawai'    => 'Pegawai A',
            'email'           => 'pegawaiA@alfa.com',
            'status'          => 'Aktif',
            'organization_id' => $this->orgA->organization_id,
        ]);

        $this->pegawaiB = Pegawai::create([
            'nama_pegawai'    => 'Pegawai B',
            'email'           => 'pegawaiB@beta.com',
            'status'          => 'Aktif',
            'organization_id' => $this->orgB->organization_id,
        ]);
    }

    protected function enableFeature(Organization $org, string $featureKey): void
    {
        $feature = Feature::where('key', $featureKey)->first();
        if ($feature) {
            OrganizationFeature::updateOrCreate(
                ['organization_id' => $org->organization_id, 'feature_id' => $feature->id],
                ['is_enabled' => true]
            );
            $org->clearFeaturesCache();
        }
    }

    protected function disableFeature(Organization $org, string $featureKey): void
    {
        $feature = Feature::where('key', $featureKey)->first();
        if ($feature) {
            OrganizationFeature::updateOrCreate(
                ['organization_id' => $org->organization_id, 'feature_id' => $feature->id],
                ['is_enabled' => false]
            );
            $org->clearFeaturesCache();
        }
    }

    // ── 1. Employee endpoint 403 when feature OFF ────────────────────────────
    public function test_01_employee_endpoint_403_when_feature_disabled()
    {
        $this->disableFeature($this->orgB, 'employee');
        $this->actingAs($this->superAdmin);
        session(['active_organization_id' => $this->orgB->organization_id]);

        $response = $this->get(route('admin.employee-management.index'));
        $response->assertStatus(403);
    }

    // ── 2. Division endpoint 403 when feature OFF ────────────────────────────
    public function test_02_division_endpoint_403_when_feature_disabled()
    {
        $this->actingAs($this->superAdmin);
        session(['active_organization_id' => $this->orgB->organization_id]);

        $response = $this->post(route('admin.employee-management.storeDivision'), [
            'nama_divisi' => 'IT Research',
        ]);
        $response->assertStatus(403);
    }

    // ── 3. Position endpoint 403 when feature OFF ────────────────────────────
    public function test_03_position_endpoint_403_when_feature_disabled()
    {
        $this->actingAs($this->superAdmin);
        session(['active_organization_id' => $this->orgB->organization_id]);

        $response = $this->post(route('admin.employee-management.storeRole'), [
            'nama_role' => 'Senior Lead',
        ]);
        $response->assertStatus(403);
    }

    // ── 4. Approval endpoint 403 when feature OFF ────────────────────────────
    public function test_04_approval_endpoint_403_when_feature_disabled()
    {
        $this->actingAs($this->superAdmin);
        session(['active_organization_id' => $this->orgB->organization_id]);

        $response = $this->get(route('admin.persetujuan'));
        $response->assertStatus(403);
    }

    // ── 5. Schedule endpoint 403 when feature OFF ────────────────────────────
    public function test_05_schedule_endpoint_403_when_feature_disabled()
    {
        $this->actingAs($this->superAdmin);
        session(['active_organization_id' => $this->orgB->organization_id]);

        $response = $this->post(route('admin.jam-kerja.simpan'), [
            'nama_jadwal' => 'Normal Shift',
            'jam_masuk'   => '08:00',
            'jam_pulang'  => '17:00',
            'hari_kerja'  => ['Senin', 'Selasa'],
        ]);
        $response->assertStatus(403);
    }

    // ── 6. Location endpoint 403 when feature OFF ────────────────────────────
    public function test_06_location_endpoint_403_when_feature_disabled()
    {
        $this->actingAs($this->superAdmin);
        session(['active_organization_id' => $this->orgB->organization_id]);

        $response = $this->get(route('admin.settings.lokasi'));
        $response->assertStatus(403);
    }

    // ── 7. Location simpan endpoint 403 when feature OFF ─────────────────────
    public function test_07_location_simpan_endpoint_403_when_feature_disabled()
    {
        $this->actingAs($this->superAdmin);
        session(['active_organization_id' => $this->orgB->organization_id]);

        $response = $this->post(route('admin.settings.lokasi.simpan'), [
            'nama_lokasi' => 'Cabang Baru',
            'latitude'    => -6.2,
            'longitude'   => 106.8,
            'radius'      => 100,
        ]);
        $response->assertStatus(403);
    }

    // ── 8. Attendance report 403 when feature OFF ────────────────────────────
    public function test_08_attendance_report_403_when_feature_disabled()
    {
        $this->actingAs($this->superAdmin);
        session(['active_organization_id' => $this->orgB->organization_id]);

        $response = $this->get(route('admin.laporan-kehadiran'));
        $response->assertStatus(403);
    }

    // ── 9. Attendance export 403 when feature OFF ────────────────────────────
    public function test_09_attendance_export_403_when_feature_disabled()
    {
        $this->actingAs($this->superAdmin);
        session(['active_organization_id' => $this->orgB->organization_id]);

        $response = $this->get(route('admin.laporan-kehadiran.export.excel'));
        $response->assertStatus(403);

        $responsePdf = $this->get(route('admin.laporan-kehadiran.export.pdf'));
        $responsePdf->assertStatus(403);

        $responseCsv = $this->get(route('admin.laporan-kehadiran.export.csv'));
        $responseCsv->assertStatus(403);
    }

    // ── 10. Pegawai check-in 403 when attendance feature OFF ─────────────────
    public function test_10_pegawai_checkin_403_when_attendance_disabled()
    {
        $akunPegawai = Akun::create([
            'username'   => 'user_beta_pegawai',
            'password'   => bcrypt('password'),
            'role_id'    => $this->superAdminRole->role_id,
            'role'       => 'Pegawai',
            'pegawai_id' => $this->pegawaiB->pegawai_id,
        ]);

        $this->actingAs($akunPegawai);

        $response = $this->post('/pegawai/attendance/checkin', [
            'latitude'  => -6.2,
            'longitude' => 106.8,
        ]);
        $response->assertStatus(403);
    }

    // ── 11. Feature ON + Privilege ON -> Access Granted ──────────────────────
    public function test_11_feature_on_and_privilege_on_allows_access()
    {
        $this->actingAs($this->superAdmin);
        session(['active_organization_id' => $this->orgA->organization_id]);

        $response = $this->get(route('admin.employee-management.index'));
        $response->assertStatus(200);

        $responseAppr = $this->get(route('admin.persetujuan'));
        $responseAppr->assertStatus(200);

        $responseAtt = $this->get(route('admin.laporan-kehadiran'));
        $responseAtt->assertStatus(200);
    }

    // ── 12. Feature ON + Privilege OFF -> Access Denied ──────────────────────
    public function test_12_feature_on_and_privilege_off_denies_access()
    {
        // Create custom role without 'lihat_persetujuan' or 'lihat_manajemen_akun'
        $restrictedRole = Role::create([
            'nama_role'       => 'Staff Terbatas',
            'organization_id' => $this->orgA->organization_id,
        ]);

        $restrictedUser = Akun::create([
            'username'   => 'staff_terbatas',
            'password'   => bcrypt('password'),
            'role_id'    => $restrictedRole->role_id,
            'role'       => 'Staff Terbatas',
            'pegawai_id' => $this->pegawaiA->pegawai_id,
        ]);

        $this->actingAs($restrictedUser);

        // Feature is ON for orgA, but user lacks privilege
        $response = $this->get(route('admin.employee-management.index'));
        $response->assertStatus(403);

        $responseAppr = $this->get(route('admin.persetujuan'));
        $responseAppr->assertStatus(403);
    }

    // ── 13. Tenant Isolation: Org A cannot access data of Org B ─────────────
    public function test_13_tenant_isolation_prevents_cross_org_access()
    {
        $this->actingAs($this->superAdmin);
        session(['active_organization_id' => $this->orgA->organization_id]);

        // Attempt to edit Pegawai B (belongs to Org B) while active in Org A
        $response = $this->get(route('admin.employee-management.edit', ['pegawai' => $this->pegawaiB->pegawai_id]));
        $response->assertStatus(403);
    }

    // ── 14. Sub-action: approve without feature -> 403 ────────────────────────
    public function test_14_approve_without_feature_approval_returns_403()
    {
        $this->actingAs($this->superAdmin);
        session(['active_organization_id' => $this->orgB->organization_id]);

        $response = $this->post(route('admin.persetujuan.approve', ['pengajuan' => 9999]));
        $response->assertStatus(403);
    }

    // ── 15. Sub-action: reject without feature -> 403 ─────────────────────────
    public function test_15_reject_without_feature_approval_returns_403()
    {
        $this->actingAs($this->superAdmin);
        session(['active_organization_id' => $this->orgB->organization_id]);

        $response = $this->post(route('admin.persetujuan.reject', ['pengajuan' => 9999]), [
            'catatan_admin' => 'Alasan penolakan',
        ]);
        $response->assertStatus(403);
    }

    // ── 16. Sub-action: employee update without feature -> 403 ────────────────
    public function test_16_employee_update_without_feature_returns_403()
    {
        $this->disableFeature($this->orgB, 'employee');
        $this->actingAs($this->superAdmin);
        session(['active_organization_id' => $this->orgB->organization_id]);

        $response = $this->put(route('admin.employee-management.update', ['pegawai' => $this->pegawaiB->pegawai_id]), [
            'nama_pegawai' => 'Updated Name',
        ]);
        $response->assertStatus(403);
    }

    // ── 17. Sub-action: division store without feature -> 403 ─────────────────
    public function test_17_division_store_without_feature_returns_403()
    {
        $this->actingAs($this->superAdmin);
        session(['active_organization_id' => $this->orgB->organization_id]);

        $response = $this->post(route('admin.employee-management.storeDivision'), [
            'nama_divisi' => 'Divisi Keuangan',
        ]);
        $response->assertStatus(403);
    }

    // ── 18. Sub-action: position store without feature -> 403 ─────────────────
    public function test_18_position_store_without_feature_returns_403()
    {
        $this->actingAs($this->superAdmin);
        session(['active_organization_id' => $this->orgB->organization_id]);

        $response = $this->post(route('admin.employee-management.storeRole'), [
            'nama_role' => 'Manager HR',
        ]);
        $response->assertStatus(403);
    }
}
