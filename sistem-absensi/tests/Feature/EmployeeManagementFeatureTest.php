<?php

namespace Tests\Feature;

use Tests\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;
use App\Models\Organization;
use App\Models\Feature;
use App\Models\OrganizationFeature;
use App\Models\Role;
use App\Models\Akun;
use App\Models\Pegawai;
use App\Models\MasterDivisi;
use App\Models\MasterJabatan;
use App\Models\Setting;
use App\Helpers\OrganizationHelper;
use Database\Seeders\OrganizationFeatureSystemSeeder;

class EmployeeManagementFeatureTest extends TestCase
{
    use RefreshDatabase;

    protected Akun $superAdmin;
    protected Role $superAdminRole;
    protected Organization $orgCorp;
    protected Organization $orgBimbel;
    protected Role $orgCorpRole;
    protected Role $orgBimbelRole;

    protected function setUp(): void
    {
        parent::setUp();

        OrganizationHelper::clearActiveOrganizationCache();

        $this->seed(OrganizationFeatureSystemSeeder::class);

        // Super Admin setup
        $this->superAdminRole = Role::firstOrCreate(['nama_role' => 'Super Admin', 'organization_id' => null]);
        $this->superAdmin = Akun::create([
            'username'   => 'superadmin_emp',
            'password'   => bcrypt('password'),
            'role_id'    => $this->superAdminRole->role_id,
            'role'       => 'Super Admin',
            'pegawai_id' => null,
        ]);

        // Create Org 1: Corporate (has employee, division, position)
        $this->orgCorp = Organization::create([
            'nama_organisasi' => 'Corp Inc',
            'kode_organisasi' => 'CORP01',
            'status'          => 'active',
            'display_token'   => 'corp-token',
        ]);
        $this->orgCorpRole = Role::create([
            'nama_role'       => 'Anggota',
            'organization_id' => $this->orgCorp->organization_id,
        ]);

        // Enable employee, division, position for orgCorp
        $this->enableFeature($this->orgCorp, 'employee');
        $this->enableFeature($this->orgCorp, 'division');
        $this->enableFeature($this->orgCorp, 'position');

        // Create Org 2: Bimbel (has employee, but division=false, position=false, custom terminology)
        $this->orgBimbel = Organization::create([
            'nama_organisasi' => 'Bimbel Hebat',
            'kode_organisasi' => 'BIMBEL01',
            'status'          => 'active',
            'display_token'   => 'bimbel-token',
        ]);
        $this->orgBimbelRole = Role::create([
            'nama_role'       => 'Anggota',
            'organization_id' => $this->orgBimbel->organization_id,
        ]);

        Setting::create([
            'organization_id' => $this->orgBimbel->organization_id,
            'key'             => 'term_member',
            'value'           => 'Siswa',
        ]);
        Setting::create([
            'organization_id' => $this->orgBimbel->organization_id,
            'key'             => 'term_member_id',
            'value'           => 'NIS',
        ]);
        Setting::create([
            'organization_id' => $this->orgBimbel->organization_id,
            'key'             => 'term_member_management',
            'value'           => 'Data Siswa',
        ]);

        // Enable employee only for orgBimbel
        $this->enableFeature($this->orgBimbel, 'employee');
        $this->disableFeature($this->orgBimbel, 'division');
        $this->disableFeature($this->orgBimbel, 'position');
    }

    protected function tearDown(): void
    {
        OrganizationHelper::clearActiveOrganizationCache();
        parent::tearDown();
    }

    protected function enableFeature(Organization $org, string $featureKey): void
    {
        $feature = Feature::where('key', $featureKey)->first();
        if ($feature) {
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
    }

    protected function disableFeature(Organization $org, string $featureKey): void
    {
        $feature = Feature::where('key', $featureKey)->first();
        if ($feature) {
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
    }

    public function test_a_employee_management_index_returns_403_when_employee_feature_disabled()
    {
        $this->disableFeature($this->orgCorp, 'employee');

        $response = $this->actingAs($this->superAdmin)
            ->withSession(['active_organization_id' => $this->orgCorp->organization_id])
            ->get(route('admin.employee-management.index'));

        $response->assertStatus(403);
    }

    public function test_b_employee_management_index_returns_200_when_employee_feature_enabled()
    {
        $response = $this->actingAs($this->superAdmin)
            ->withSession(['active_organization_id' => $this->orgCorp->organization_id])
            ->get(route('admin.employee-management.index'));

        $response->assertStatus(200);
    }

    public function test_c_employee_management_create_returns_403_when_employee_feature_disabled()
    {
        $this->disableFeature($this->orgCorp, 'employee');

        $response = $this->actingAs($this->superAdmin)
            ->withSession(['active_organization_id' => $this->orgCorp->organization_id])
            ->get(route('admin.employee-management.create'));

        $response->assertStatus(403);
    }

    public function test_d_employee_management_store_returns_403_when_employee_feature_disabled()
    {
        $this->disableFeature($this->orgCorp, 'employee');

        $response = $this->actingAs($this->superAdmin)
            ->withSession(['active_organization_id' => $this->orgCorp->organization_id])
            ->post(route('admin.employee-management.store'), [
                'nama_pegawai' => 'Budi Santoso',
                'nip'          => 'EMP001',
                'email'        => 'budi@corp.com',
                'role'         => 'Anggota',
                'password'     => 'password123',
                'password_confirmation' => 'password123',
            ]);

        $response->assertStatus(403);
    }

    public function test_e_employee_management_edit_returns_403_when_employee_feature_disabled()
    {
        $pegawai = Pegawai::create([
            'nama_pegawai'    => 'Pegawai Edit Test',
            'nip'             => 'EDIT001',
            'email'           => 'edit@corp.com',
            'organization_id' => $this->orgCorp->organization_id,
        ]);

        $this->disableFeature($this->orgCorp, 'employee');

        $response = $this->actingAs($this->superAdmin)
            ->withSession(['active_organization_id' => $this->orgCorp->organization_id])
            ->get(route('admin.employee-management.edit', $pegawai->pegawai_id));

        $response->assertStatus(403);
    }

    public function test_f_employee_management_update_returns_403_when_employee_feature_disabled()
    {
        $pegawai = Pegawai::create([
            'nama_pegawai'    => 'Pegawai Update Test',
            'nip'             => 'UPD001',
            'email'           => 'upd@corp.com',
            'organization_id' => $this->orgCorp->organization_id,
        ]);

        $this->disableFeature($this->orgCorp, 'employee');

        $response = $this->actingAs($this->superAdmin)
            ->withSession(['active_organization_id' => $this->orgCorp->organization_id])
            ->put(route('admin.employee-management.update', $pegawai->pegawai_id), [
                'nama_pegawai' => 'Pegawai Updated',
                'nip'          => 'UPD001',
                'email'        => 'upd@corp.com',
                'role'         => 'Anggota',
            ]);

        $response->assertStatus(403);
    }

    public function test_g_employee_management_export_returns_403_when_employee_feature_disabled()
    {
        $this->disableFeature($this->orgCorp, 'employee');

        $response = $this->actingAs($this->superAdmin)
            ->withSession(['active_organization_id' => $this->orgCorp->organization_id])
            ->post(route('admin.employee-management.export'), ['type' => 'csv']);

        $response->assertStatus(403);
    }

    public function test_h_employee_management_index_omits_division_when_division_feature_disabled()
    {
        // Bimbel has division = false
        $responseBimbel = $this->actingAs($this->superAdmin)
            ->withSession(['active_organization_id' => $this->orgBimbel->organization_id])
            ->get(route('admin.employee-management.index'));

        $responseBimbel->assertStatus(200);
        $contentBimbel = $responseBimbel->getContent();
        // Desktop table th for division should not be present
        $this->assertStringNotContainsString('id="col-header-division"', $contentBimbel);

        // Corp has division = true
        $responseCorp = $this->actingAs($this->superAdmin)
            ->withSession(['active_organization_id' => $this->orgCorp->organization_id])
            ->get(route('admin.employee-management.index'));

        $responseCorp->assertStatus(200);
        $contentCorp = $responseCorp->getContent();
        $this->assertStringContainsString('id="col-header-division"', $contentCorp);
    }

    public function test_i_employee_management_index_omits_position_when_position_feature_disabled()
    {
        // Bimbel has position = false
        $responseBimbel = $this->actingAs($this->superAdmin)
            ->withSession(['active_organization_id' => $this->orgBimbel->organization_id])
            ->get(route('admin.employee-management.index'));

        $responseBimbel->assertStatus(200);
        $contentBimbel = $responseBimbel->getContent();
        // Desktop table th for position should not be present
        $this->assertStringNotContainsString('id="col-header-position"', $contentBimbel);

        // Corp has position = true
        $responseCorp = $this->actingAs($this->superAdmin)
            ->withSession(['active_organization_id' => $this->orgCorp->organization_id])
            ->get(route('admin.employee-management.index'));

        $responseCorp->assertStatus(200);
        $contentCorp = $responseCorp->getContent();
        $this->assertStringContainsString('id="col-header-position"', $contentCorp);
    }

    public function test_j_store_division_returns_403_when_division_feature_disabled()
    {
        // Bimbel has division = false
        $response = $this->actingAs($this->superAdmin)
            ->withSession(['active_organization_id' => $this->orgBimbel->organization_id])
            ->post(route('admin.employee-management.storeDivision'), [
                'nama_divisi' => 'Divisi Baru',
            ]);

        $response->assertStatus(403);
    }

    public function test_k_store_role_returns_403_when_position_feature_disabled()
    {
        // Bimbel has position = false
        $response = $this->actingAs($this->superAdmin)
            ->withSession(['active_organization_id' => $this->orgBimbel->organization_id])
            ->post(route('admin.employee-management.storeRole'), [
                'nama_jabatan' => 'Jabatan Baru',
            ]);

        $response->assertStatus(403);
    }

    public function test_l_store_nullifies_divisi_id_when_division_feature_disabled()
    {
        // Bimbel has division = false. Sending divisi_id should be ignored and set to null.
        $response = $this->actingAs($this->superAdmin)
            ->withSession(['active_organization_id' => $this->orgBimbel->organization_id])
            ->post(route('admin.employee-management.store'), [
                'nama_pegawai'          => 'Siswa Budi',
                'nip'                   => 'BIMBEL-001',
                'email'                 => 'budi@bimbel.com',
                'role'                  => 'Anggota',
                'password'              => 'password123',
                'password_confirmation' => 'password123',
                'divisi_id'             => 9999, // Should be ignored
            ]);

        $response->assertRedirect(route('admin.employee-management.index'));

        $pegawai = Pegawai::where('nip', 'BIMBEL-001')->first();
        $this->assertNotNull($pegawai);
        $this->assertNull($pegawai->divisi_id);
    }

    public function test_m_store_nullifies_jabatan_id_when_position_feature_disabled()
    {
        // Bimbel has position = false. Sending jabatan_id should be ignored and set to null.
        $response = $this->actingAs($this->superAdmin)
            ->withSession(['active_organization_id' => $this->orgBimbel->organization_id])
            ->post(route('admin.employee-management.store'), [
                'nama_pegawai'          => 'Siswa Ani',
                'nip'                   => 'BIMBEL-002',
                'email'                 => 'ani@bimbel.com',
                'role'                  => 'Anggota',
                'password'              => 'password123',
                'password_confirmation' => 'password123',
                'jabatan_id'            => 8888, // Should be ignored
            ]);

        $response->assertRedirect(route('admin.employee-management.index'));

        $pegawai = Pegawai::where('nip', 'BIMBEL-002')->first();
        $this->assertNotNull($pegawai);
        $this->assertNull($pegawai->jabatan_id);
    }

    public function test_n_store_fails_validation_for_cross_organization_divisi_id()
    {
        // Create division belonging to another org
        $otherDivision = MasterDivisi::create([
            'nama_divisi'     => 'Divisi Asing',
            'organization_id' => $this->orgBimbel->organization_id,
        ]);

        $response = $this->actingAs($this->superAdmin)
            ->withSession(['active_organization_id' => $this->orgCorp->organization_id])
            ->post(route('admin.employee-management.store'), [
                'nama_pegawai'          => 'Pegawai Cross Org',
                'nip'                   => 'CORP-CROSS-1',
                'email'                 => 'cross@corp.com',
                'role'                  => 'Anggota',
                'password'              => 'password123',
                'password_confirmation' => 'password123',
                'divisi_id'             => $otherDivision->divisi_id,
            ]);

        $response->assertSessionHasErrors(['divisi_id']);
    }

    public function test_o_store_fails_validation_for_cross_organization_jabatan_id()
    {
        // Create position belonging to another org
        $otherPosition = MasterJabatan::create([
            'nama_jabatan'    => 'Jabatan Asing',
            'organization_id' => $this->orgBimbel->organization_id,
        ]);

        $response = $this->actingAs($this->superAdmin)
            ->withSession(['active_organization_id' => $this->orgCorp->organization_id])
            ->post(route('admin.employee-management.store'), [
                'nama_pegawai'          => 'Pegawai Cross Pos',
                'nip'                   => 'CORP-CROSS-2',
                'email'                 => 'crosspos@corp.com',
                'role'                  => 'Anggota',
                'password'              => 'password123',
                'password_confirmation' => 'password123',
                'jabatan_id'            => $otherPosition->jabatan_id,
            ]);

        $response->assertSessionHasErrors(['jabatan_id']);
    }

    public function test_p_export_csv_and_excel_omit_division_and_position_when_disabled()
    {
        // Create an employee in Bimbel
        Pegawai::create([
            'nama_pegawai'    => 'Export Bimbel Siswa',
            'nip'             => 'EXP-BIMBEL-01',
            'email'           => 'expbimbel@test.com',
            'organization_id' => $this->orgBimbel->organization_id,
        ]);

        // Bimbel has division=false, position=false
        $responseBimbel = $this->actingAs($this->superAdmin)
            ->withSession(['active_organization_id' => $this->orgBimbel->organization_id])
            ->post(route('admin.employee-management.export'), ['format' => 'csv']);

        $responseBimbel->assertStatus(200);
        $csvBimbel = $responseBimbel->streamedContent();
        $this->assertStringNotContainsString(',Divisi,', $csvBimbel);
        $this->assertStringNotContainsString(',Jabatan,', $csvBimbel);

        // Create an employee in Corp
        $div = MasterDivisi::create(['nama_divisi' => 'IT Corp', 'organization_id' => $this->orgCorp->organization_id]);
        $jab = MasterJabatan::create(['nama_jabatan' => 'Developer', 'organization_id' => $this->orgCorp->organization_id]);
        Pegawai::create([
            'nama_pegawai'    => 'Export Corp Pegawai',
            'nip'             => 'EXP-CORP-01',
            'email'           => 'expcorp@test.com',
            'divisi_id'       => $div->divisi_id,
            'jabatan_id'      => $jab->jabatan_id,
            'organization_id' => $this->orgCorp->organization_id,
        ]);

        // Corp has division=true, position=true
        $responseCorp = $this->actingAs($this->superAdmin)
            ->withSession(['active_organization_id' => $this->orgCorp->organization_id])
            ->post(route('admin.employee-management.export'), ['format' => 'csv']);

        $responseCorp->assertStatus(200);
        $csvCorp = $responseCorp->streamedContent();
        $this->assertStringContainsString('Divisi', $csvCorp);
        $this->assertStringContainsString('Jabatan', $csvCorp);
    }

    public function test_q_custom_terminology_rendered_in_employee_views()
    {
        // Bimbel has terminology: member => Siswa, member_id => NIS, member_management => Data Siswa
        $response = $this->actingAs($this->superAdmin)
            ->withSession(['active_organization_id' => $this->orgBimbel->organization_id])
            ->get(route('admin.employee-management.index'));

        $response->assertStatus(200);
        $content = $response->getContent();
        $this->assertStringContainsString('Data Siswa', $content);
        $this->assertStringContainsString('Tambah Siswa', $content);
        $this->assertStringContainsString('NIS', $content);
    }

    public function test_r_store_division_and_role_scoped_to_active_organization_when_enabled()
    {
        // Fast create division
        $respDiv = $this->actingAs($this->superAdmin)
            ->withSession(['active_organization_id' => $this->orgCorp->organization_id])
            ->post(route('admin.employee-management.storeDivision'), [
                'nama_divisi' => 'Divisi Riset Baru',
            ]);

        $respDiv->assertStatus(201);
        $divData = $respDiv->json();
        $this->assertArrayHasKey('division', $divData);
        $this->assertDatabaseHas('master_divisi', [
            'divisi_id'       => $divData['division']['divisi_id'],
            'nama_divisi'     => 'Divisi Riset Baru',
            'organization_id' => $this->orgCorp->organization_id,
        ]);

        // Fast create position
        $respJab = $this->actingAs($this->superAdmin)
            ->withSession(['active_organization_id' => $this->orgCorp->organization_id])
            ->post(route('admin.employee-management.storeRole'), [
                'nama_jabatan' => 'Lead Engineer Baru',
            ]);

        $respJab->assertStatus(201);
        $jabData = $respJab->json();
        $this->assertArrayHasKey('role', $jabData);
        $this->assertDatabaseHas('master_jabatan', [
            'jabatan_id'      => $jabData['role']['jabatan_id'],
            'nama_jabatan'    => 'Lead Engineer Baru',
            'organization_id' => $this->orgCorp->organization_id,
        ]);
    }
}
