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
use App\Models\Setting;
use App\Models\WorkLocation;
use App\Helpers\OrganizationHelper;
use Database\Seeders\OrganizationFeatureSystemSeeder;

class SettingsFeatureTest extends TestCase
{
    use RefreshDatabase;

    protected Akun $superAdmin;
    protected Role $superAdminRole;
    protected Organization $orgA;
    protected Organization $orgB;

    protected function setUp(): void
    {
        parent::setUp();

        OrganizationHelper::clearActiveOrganizationCache();

        $this->seed(OrganizationFeatureSystemSeeder::class);

        $this->superAdminRole = Role::firstOrCreate(['nama_role' => 'Super Admin', 'organization_id' => null]);
        $this->superAdmin = Akun::create([
            'username'   => 'superadmin_settings',
            'password'   => bcrypt('password'),
            'role_id'    => $this->superAdminRole->role_id,
            'role'       => 'Super Admin',
            'pegawai_id' => null,
        ]);

        $this->orgA = Organization::create([
            'nama_organisasi' => 'Organisasi Alfa',
            'kode_organisasi' => 'ALFA01',
            'status'          => 'active',
            'display_token'   => 'alfa-token',
        ]);

        $this->orgB = Organization::create([
            'nama_organisasi' => 'Organisasi Beta',
            'kode_organisasi' => 'BETA01',
            'status'          => 'active',
            'display_token'   => 'beta-token',
        ]);

        // Enable default features for orgA
        $this->enableFeature($this->orgA, 'schedule');
        $this->enableFeature($this->orgA, 'location');
        $this->enableFeature($this->orgA, 'gps');
        $this->enableFeature($this->orgA, 'employee');

        // Enable default features for orgB
        $this->disableFeature($this->orgB, 'schedule');
        $this->disableFeature($this->orgB, 'location');
        $this->disableFeature($this->orgB, 'gps');
        $this->enableFeature($this->orgB, 'employee');
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

    public function test_1_organization_isolation_in_settings()
    {
        $this->actingAs($this->superAdmin);

        // Set setting for Org A
        Setting::create([
            'organization_id' => $this->orgA->organization_id,
            'key'             => 'primary_color',
            'value'           => '#FF0000',
        ]);

        // Set setting for Org B
        Setting::create([
            'organization_id' => $this->orgB->organization_id,
            'key'             => 'primary_color',
            'value'           => '#00FF00',
        ]);

        // In context of Org A, get primary_color
        session(['active_organization_id' => $this->orgA->organization_id]);
        $this->assertEquals('#FF0000', Setting::get('primary_color'));

        // In context of Org B, get primary_color
        session(['active_organization_id' => $this->orgB->organization_id]);
        $this->assertEquals('#00FF00', Setting::get('primary_color'));
    }

    public function test_2_branding_uses_active_organization()
    {
        Setting::create([
            'organization_id' => $this->orgA->organization_id,
            'key'             => 'primary_color',
            'value'           => '#123456',
        ]);

        $response = $this->actingAs($this->superAdmin)
            ->withSession(['active_organization_id' => $this->orgA->organization_id])
            ->get(route('admin.tampilan-branding'));

        $response->assertStatus(200);
        $response->assertSee('Organisasi Alfa');
        $response->assertSee('#123456');
    }

    public function test_3_schedule_on_allows_simpan_jam_kerja()
    {
        $response = $this->actingAs($this->superAdmin)
            ->withSession(['active_organization_id' => $this->orgA->organization_id])
            ->post(route('admin.jam-kerja.simpan'), [
                'jam_masuk'  => '08:00',
                'jam_pulang' => '17:00',
            ]);

        // Should succeed (redirect back with success)
        $response->assertStatus(302);
        $response->assertSessionHas('success');
    }

    public function test_4_schedule_off_blocks_simpan_jam_kerja()
    {
        $response = $this->actingAs($this->superAdmin)
            ->withSession(['active_organization_id' => $this->orgB->organization_id])
            ->post(route('admin.jam-kerja.simpan'), [
                'jam_masuk'  => '08:00',
                'jam_pulang' => '17:00',
            ]);

        $response->assertStatus(403);
    }

    public function test_5_location_gps_on_allows_managing_location()
    {
        $response = $this->actingAs($this->superAdmin)
            ->withSession(['active_organization_id' => $this->orgA->organization_id])
            ->post(route('admin.settings.lokasi.simpan'), [
                'nama_kantor'  => 'Kantor Cabang Baru',
                'latitude'     => -6.2088,
                'longitude'    => 106.8456,
                'radius_meter' => 100,
            ]);

        $response->assertRedirect(route('admin.tampilan-branding', ['tab' => 'lokasi']));
        $this->assertDatabaseHas('lokasi_kantor', [
            'organization_id' => $this->orgA->organization_id,
            'nama_kantor'     => 'Kantor Cabang Baru',
        ]);
    }

    public function test_5b_location_off_blocks_managing_location()
    {
        $response = $this->actingAs($this->superAdmin)
            ->withSession(['active_organization_id' => $this->orgB->organization_id])
            ->post(route('admin.settings.lokasi.simpan'), [
                'nama_kantor'  => 'Kantor Cabang Terlarang',
                'latitude'     => -6.2088,
                'longitude'    => 106.8456,
                'radius_meter' => 100,
            ]);

        $response->assertStatus(403);
    }

    public function test_6_location_off_redirects_when_accessing_lokasi_tab()
    {
        $response = $this->actingAs($this->superAdmin)
            ->withSession(['active_organization_id' => $this->orgB->organization_id])
            ->get(route('admin.tampilan-branding', ['tab' => 'lokasi']));

        $response->assertRedirect(route('admin.tampilan-branding', ['tab' => 'branding']));
    }

    public function test_10_custom_terminology_rendered_in_settings()
    {
        Setting::create([
            'organization_id' => $this->orgA->organization_id,
            'key'             => 'term_member',
            'value'           => 'Peserta',
        ]);

        $response = $this->actingAs($this->superAdmin)
            ->withSession(['active_organization_id' => $this->orgA->organization_id])
            ->get(route('admin.tampilan-branding', ['tab' => 'lokasi']));

        $response->assertStatus(200);
        $response->assertSee('peserta');
    }

    public function test_11_organization_a_does_not_see_organization_b_locations()
    {
        WorkLocation::create([
            'organization_id' => $this->orgA->organization_id,
            'nama_kantor'     => 'Kantor Unik Alfa',
            'latitude'        => -6.2000,
            'longitude'       => 106.8000,
            'radius_meter'    => 50,
        ]);

        $locB = WorkLocation::create([
            'organization_id' => $this->orgB->organization_id,
            'nama_kantor'     => 'Kantor Rahasia Beta',
            'latitude'        => -6.3000,
            'longitude'       => 106.9000,
            'radius_meter'    => 50,
        ]);

        $response = $this->actingAs($this->superAdmin)
            ->withSession(['active_organization_id' => $this->orgA->organization_id])
            ->get(route('admin.tampilan-branding', ['tab' => 'lokasi']));

        $response->assertStatus(200);
        $response->assertSee('Kantor Unik Alfa');
        $response->assertDontSee('Kantor Rahasia Beta');

        // Cannot delete Org B location from Org A context
        $deleteResponse = $this->actingAs($this->superAdmin)
            ->withSession(['active_organization_id' => $this->orgA->organization_id])
            ->delete(route('admin.settings.lokasi.hapus', $locB->lokasi_id));

        $this->assertDatabaseHas('lokasi_kantor', [
            'lokasi_id' => $locB->lokasi_id,
        ]);
    }
}
