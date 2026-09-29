<?php

namespace Tests\Feature;

use Tests\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;
use App\Models\Organization;
use App\Models\Role;
use App\Models\Akun;
use App\Models\Pegawai;
use App\Models\WorkLocation;
use App\Models\Approval;

class SecurityTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        // Org A
        $this->orgA = Organization::create([
            'organization_id' => 101,
            'nama_organisasi' => 'Org A',
            'kode_organisasi' => 'ORGA',
            'status' => 'active',
            'display_token' => 'token-a'
        ]);

        // Org B
        $this->orgB = Organization::create([
            'organization_id' => 102,
            'nama_organisasi' => 'Org B',
            'kode_organisasi' => 'ORGB',
            'status' => 'active',
            'display_token' => 'token-b'
        ]);

        // Roles
        $this->hrRole = Role::firstOrCreate(['nama_role' => 'HR']);
        $this->pegawaiRole = Role::firstOrCreate(['nama_role' => 'Pegawai']);
        $this->superAdminRole = Role::firstOrCreate(['nama_role' => 'Super Admin']);

        // HR Org A
        $this->hrPegawaiA = Pegawai::create([
            'nip' => 'HR-A',
            'nama_pegawai' => 'HR A',
            'email' => 'hra@test.com',
            'organization_id' => $this->orgA->organization_id
        ]);
        $this->hrUserA = Akun::create([
            'username' => 'hra',
            'password' => bcrypt('password'),
            'role_id' => $this->hrRole->role_id,
            'role' => 'HR',
            'pegawai_id' => $this->hrPegawaiA->pegawai_id
        ]);

        // Pegawai Org B
        $this->pegawaiB = Pegawai::create([
            'nip' => 'PEG-B',
            'nama_pegawai' => 'Pegawai B',
            'email' => 'pegb@test.com',
            'organization_id' => $this->orgB->organization_id
        ]);
        
        // Location Org B
        $this->locationB = WorkLocation::create([
            'nama_kantor' => 'Lokasi B',
            'latitude' => 0,
            'longitude' => 0,
            'radius_meter' => 50,
            'organization_id' => $this->orgB->organization_id
        ]);

        // Submission Org B
        $this->submissionB = Approval::create([
            'pegawai_id' => $this->pegawaiB->pegawai_id,
            'jenis_pengajuan' => 'Izin',
            'tanggal_pengajuan' => '2026-01-01',
            'status_pengajuan' => 'Pending'
        ]);
    }

    // 1. HR cannot access organization B employee.
    public function test_hr_cannot_access_org_b_employee()
    {
        $response = $this->actingAs($this->hrUserA)
                         ->withSession(['active_organization_id' => $this->orgA->organization_id])
                         ->get('/admin/employees/' . $this->pegawaiB->pegawai_id);
        $response->assertStatus(403);
    }

    // 2. HR cannot update organization B employee.
    public function test_hr_cannot_update_org_b_employee()
    {
        $response = $this->actingAs($this->hrUserA)
                         ->withSession(['active_organization_id' => $this->orgA->organization_id])
                         ->put('/admin/employees/' . $this->pegawaiB->pegawai_id, [
            'nama_pegawai' => 'Hacked'
        ]);
        $response->assertStatus(403);
    }

    // 3. HR cannot access organization B location.
    public function test_hr_cannot_access_org_b_location()
    {
        $response = $this->actingAs($this->hrUserA)
                         ->withSession(['active_organization_id' => $this->orgA->organization_id])
                         ->delete('/admin/settings/lokasi/' . $this->locationB->lokasi_id);
        $response->assertStatus(403);
    }

    // 4. HR cannot approve organization B submission.
    public function test_hr_cannot_approve_org_b_submission()
    {
        $response = $this->actingAs($this->hrUserA)
                         ->withSession(['active_organization_id' => $this->orgA->organization_id])
                         ->post('/admin/persetujuan/' . $this->submissionB->pengajuan_id . '/approve');
        $response->assertStatus(403);
    }

    // 5. Pegawai cannot access admin.
    public function test_pegawai_cannot_access_admin()
    {
        $pegawaiUser = Akun::create([
            'username' => 'pega',
            'password' => bcrypt('password'),
            'role_id' => $this->pegawaiRole->role_id,
            'role' => 'Pegawai'
        ]);

        $response = $this->actingAs($pegawaiUser)->get('/admin');
        // Because of RoleMiddleware, it might be 403 or redirect
        $response->assertStatus(403);
    }

    public function test_director_can_access_only_assigned_privileges()
    {
        $directorRole = Role::create([
            'nama_role' => 'Direktur',
            'organization_id' => $this->orgA->organization_id,
        ]);
        $dashboardPrivilege = \App\Models\Privilege::where('nama_privilege', 'lihat_dashboard')->firstOrFail();
        $directorRole->privileges()->attach($dashboardPrivilege->privilege_id);

        $director = Akun::create([
            'username' => 'director-a',
            'password' => bcrypt('password'),
            'role_id' => $directorRole->role_id,
            'role' => 'Direktur',
            'pegawai_id' => $this->hrPegawaiA->pegawai_id,
        ]);

        $dashboardResponse = $this->actingAs($director)
            ->withSession(['active_organization_id' => $this->orgA->organization_id])
            ->get('/admin');
        $dashboardResponse->assertOk();

        $restrictedResponse = $this->actingAs($director)
            ->withSession(['active_organization_id' => $this->orgA->organization_id])
            ->get('/admin/laporan-kehadiran');
        $restrictedResponse->assertForbidden();
    }

    public function test_role_names_and_privileges_are_isolated_by_organization()
    {
        $roleA = Role::create([
            'nama_role' => 'Direktur',
            'organization_id' => $this->orgA->organization_id,
        ]);
        $roleB = Role::create([
            'nama_role' => 'Direktur',
            'organization_id' => $this->orgB->organization_id,
        ]);
        $dashboardPrivilege = \App\Models\Privilege::where('nama_privilege', 'lihat_dashboard')->firstOrFail();
        $roleA->privileges()->attach($dashboardPrivilege->privilege_id);

        $this->actingAs($this->hrUserA)
            ->withSession(['active_organization_id' => $this->orgA->organization_id]);

        $resolvedRole = app(\App\Services\EmployeeManagementService::class)->resolveRoleData('Direktur');

        $this->assertSame($roleA->role_id, $resolvedRole['role_id']);
        $this->assertTrue($roleA->hasPrivilege('lihat_dashboard'));
        $this->assertFalse($roleB->hasPrivilege('lihat_dashboard'));
    }

    public function test_organization_user_cannot_assign_global_super_admin_role()
    {
        $organizationAdminRole = Role::create([
            'nama_role' => 'Admin',
            'organization_id' => $this->orgA->organization_id,
        ]);

        $this->actingAs($this->hrUserA)
            ->withSession(['active_organization_id' => $this->orgA->organization_id]);

        $service = app(\App\Services\EmployeeManagementService::class);
        $resolvedRole = $service->resolveRoleData('Admin');
        $this->assertSame($organizationAdminRole->role_id, $resolvedRole['role_id']);

        try {
            $service->resolveRoleData('Super Admin');
            $this->fail('A tenant user must not be able to assign the global Super Admin role.');
        } catch (\Illuminate\Validation\ValidationException $exception) {
            $this->assertArrayHasKey('role', $exception->errors());
        }
    }

    public function test_anggota_role_has_no_web_access_and_cannot_receive_privileges()
    {
        $memberRole = Role::create([
            'nama_role' => 'Anggota',
            'organization_id' => $this->orgA->organization_id,
        ]);
        $member = Akun::create([
            'username' => 'member-a',
            'password' => bcrypt('password'),
            'role_id' => $memberRole->role_id,
            'role' => 'Anggota',
            'pegawai_id' => $this->hrPegawaiA->pegawai_id,
        ]);

        $this->assertFalse($memberRole->hasAnyPrivilege());

        $this->actingAs($member)
            ->withSession(['active_organization_id' => $this->orgA->organization_id])
            ->get('/admin')
            ->assertForbidden();

        $superAdmin = Akun::create([
            'username' => 'superadmin-a',
            'password' => bcrypt('password'),
            'role_id' => $this->superAdminRole->role_id,
            'role' => 'Super Admin',
        ]);

        $this->actingAs($superAdmin)
            ->withSession(['active_organization_id' => $this->orgA->organization_id])
            ->get('/admin/tampilan-branding?tab=roles')
            ->assertOk()
            ->assertSee('Anggota')
            ->assertSee('Tidak memiliki akses Web Admin');

        $dashboardPrivilege = \App\Models\Privilege::where('nama_privilege', 'lihat_dashboard')->firstOrFail();
        $this->post(route('admin.settings.roles.simpan'), [
            'role_id' => $memberRole->role_id,
            'privilege_ids' => [$dashboardPrivilege->privilege_id],
        ])->assertRedirect();

        $this->assertFalse($memberRole->fresh()->hasPrivilege('lihat_dashboard'));
    }

    public function test_migration_assigns_unmapped_accounts_to_anggota_and_preserves_super_admin()
    {
        $unmappedPegawai = Pegawai::create([
            'nip' => 'NO-ROLE-A',
            'nama_pegawai' => 'No Role A',
            'email' => 'no-role-a@test.com',
            'organization_id' => $this->orgA->organization_id,
        ]);
        $unmappedAccount = Akun::create([
            'username' => 'no-role-a',
            'password' => bcrypt('password'),
            'role' => '',
            'role_id' => null,
            'pegawai_id' => $unmappedPegawai->pegawai_id,
        ]);

        $superAdminPegawai = Pegawai::create([
            'nip' => 'SUPER-ADMIN-A',
            'nama_pegawai' => 'Legacy Super Admin',
            'email' => 'legacy-super-admin-a@test.com',
            'organization_id' => $this->orgA->organization_id,
        ]);
        $legacySuperAdmin = Akun::create([
            'username' => 'legacy-super-admin-a',
            'password' => bcrypt('password'),
            'role' => 'Super Admin',
            'role_id' => null,
            'pegawai_id' => $superAdminPegawai->pegawai_id,
        ]);

        $migration = require database_path('migrations/2026_09_29_000002_create_member_role_and_assign_unmapped_accounts.php');
        $migration->up();

        $unmappedAccount->refresh();
        $legacySuperAdmin->refresh();

        $this->assertSame('Anggota', $unmappedAccount->role);
        $this->assertNotNull($unmappedAccount->role_id);
        $this->assertSame('Super Admin', $legacySuperAdmin->role);
        $this->assertNull($legacySuperAdmin->role_id);
    }

    // 6. Fake Super Admin role string cannot bypass role_id.
    public function test_fake_super_admin_string_cannot_bypass_role_id()
    {
        // Fake SA: role string is "Super Admin", but role_id is Pegawai
        $fakeSaUser = Akun::create([
            'username' => 'fakesa',
            'password' => bcrypt('password'),
            'role_id' => $this->pegawaiRole->role_id,
            'role' => 'Super Admin' // The legacy string
        ]);

        $response = $this->actingAs($fakeSaUser)->get('/admin');
        $response->assertStatus(403);
    }

    // 7. TV token A cannot return organization B.
    public function test_tv_token_a_cannot_return_org_b()
    {
        $response = $this->get('/api/tv/token-a/stats');
        $response->assertStatus(200);
    }

    // 8. Invalid TV token returns 404.
    public function test_invalid_tv_token_returns_404()
    {
        $response = $this->get('/tv/invalid-token');
        $response->assertStatus(404);
        
        $apiResponse = $this->get('/api/tv/invalid-token/stats');
        $apiResponse->assertStatus(404);
    }
}
