<?php

namespace Tests\Feature;

use Tests\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use App\Models\Organization;
use App\Models\Feature;
use App\Models\OrganizationFeature;
use App\Models\Pegawai;
use App\Models\MasterDivisi;
use App\Models\MasterJabatan;
use App\Models\Setting;
use App\Helpers\OrganizationHelper;
use Database\Seeders\OrganizationFeatureSystemSeeder;

class TvDashboardFeatureTest extends TestCase
{
    use RefreshDatabase;

    protected Organization $orgCorp;
    protected Organization $orgBimbel;
    protected Pegawai $pegawaiCorp;
    protected Pegawai $pegawaiBimbel;

    protected function setUp(): void
    {
        parent::setUp();

        OrganizationHelper::clearActiveOrganizationCache();

        $this->seed(OrganizationFeatureSystemSeeder::class);

        // Org 1: Corporate (attendance=true, employee=true, division=true, position=true, wfo_wfh=true, schedule=true)
        $this->orgCorp = Organization::create([
            'nama_organisasi' => 'Corp TV Indo',
            'kode_organisasi' => 'CORPTV01',
            'status'          => 'active',
            'display_token'   => 'corp-tv-token',
        ]);

        $this->enableFeature($this->orgCorp, 'attendance');
        $this->enableFeature($this->orgCorp, 'employee');
        $this->enableFeature($this->orgCorp, 'division');
        $this->enableFeature($this->orgCorp, 'position');
        $this->enableFeature($this->orgCorp, 'wfo_wfh');
        $this->enableFeature($this->orgCorp, 'schedule');

        $divisi = MasterDivisi::create([
            'nama_divisi'     => 'Teknologi Informasi',
            'organization_id' => $this->orgCorp->organization_id,
        ]);
        $jabatan = MasterJabatan::create([
            'nama_jabatan'    => 'Senior Developer',
            'organization_id' => $this->orgCorp->organization_id,
        ]);

        $this->pegawaiCorp = Pegawai::create([
            'nama_pegawai'    => 'Pegawai Corporate TV',
            'nip'             => 'CORP-01',
            'email'           => 'corp@tv.com',
            'organization_id' => $this->orgCorp->organization_id,
            'divisi_id'       => $divisi->divisi_id,
            'jabatan_id'      => $jabatan->jabatan_id,
            'status'          => 'Aktif',
        ]);

        // Insert attendance for Corp
        DB::table('absensi')->insert([
            'pegawai_id'       => $this->pegawaiCorp->pegawai_id,
            'tanggal_absensi'  => now()->toDateString(),
            'jam_checkin'      => '08:00:00',
            'jam_checkout'     => null,
            'status_kehadiran' => 'Hadir',
            'skema_kerja'      => 'WFO',
            'created_at'       => now(),
            'updated_at'       => now(),
        ]);

        // Org 2: Bimbel (attendance=true, employee=true, division=false, position=false, wfo_wfh=false, schedule=false, custom term)
        $this->orgBimbel = Organization::create([
            'nama_organisasi' => 'Bimbel Edukasi TV',
            'kode_organisasi' => 'BIMBELTV01',
            'status'          => 'active',
            'display_token'   => 'bimbel-tv-token',
        ]);

        $this->enableFeature($this->orgBimbel, 'attendance');
        $this->enableFeature($this->orgBimbel, 'employee');
        $this->disableFeature($this->orgBimbel, 'division');
        $this->disableFeature($this->orgBimbel, 'position');
        $this->disableFeature($this->orgBimbel, 'wfo_wfh');
        $this->disableFeature($this->orgBimbel, 'schedule');

        Setting::create([
            'organization_id' => $this->orgBimbel->organization_id,
            'key'             => 'term_member',
            'value'           => 'Siswa',
        ]);

        $this->pegawaiBimbel = Pegawai::create([
            'nama_pegawai'    => 'Siswa Cerdas TV',
            'nip'             => 'SISWA-TV-01',
            'email'           => 'siswa@tv.com',
            'organization_id' => $this->orgBimbel->organization_id,
            'status'          => 'Aktif',
        ]);

        // Insert attendance for Bimbel
        DB::table('absensi')->insert([
            'pegawai_id'       => $this->pegawaiBimbel->pegawai_id,
            'tanggal_absensi'  => now()->toDateString(),
            'jam_checkin'      => '08:30:00',
            'jam_checkout'     => null,
            'status_kehadiran' => 'Hadir',
            'skema_kerja'      => 'WFO',
            'created_at'       => now(),
            'updated_at'       => now(),
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

    public function test_12_attendance_on_renders_tv_dashboard()
    {
        $response = $this->get(route('tv.dashboard', ['display_token' => $this->orgCorp->display_token]));

        $response->assertStatus(200);
        $response->assertSee('Corp TV Indo');
        $response->assertSee('Pegawai Corporate TV');
    }

    public function test_13_attendance_off_shows_disabled_state()
    {
        $this->disableFeature($this->orgCorp, 'attendance');

        $response = $this->get(route('tv.dashboard', ['display_token' => $this->orgCorp->display_token]));

        $response->assertStatus(200);
        $response->assertSee('Presensi Kehadiran Nonaktif');
        $response->assertDontSee('Pegawai Corporate TV');

        // Stats API also reflects hasAttendance = false
        $apiResponse = $this->get(route('tv.dashboard.stats', ['display_token' => $this->orgCorp->display_token]));
        $apiResponse->assertStatus(200);
        $this->assertFalse($apiResponse->json('hasAttendance'));
        $this->assertEquals(0, $apiResponse->json('totalHadir'));
    }

    public function test_14_division_on_renders_division_information()
    {
        $response = $this->get(route('tv.dashboard', ['display_token' => $this->orgCorp->display_token]));

        $response->assertStatus(200);
        $response->assertSee('Divisi');

        $apiResponse = $this->get(route('tv.dashboard.stats', ['display_token' => $this->orgCorp->display_token]));
        $apiResponse->assertStatus(200);
        $attendances = $apiResponse->json('liveCheckIns');
        $this->assertEquals('Teknologi Informasi', $attendances[0]['divisi']);
    }

    public function test_15_division_off_hides_division_information()
    {
        $response = $this->get(route('tv.dashboard', ['display_token' => $this->orgBimbel->display_token]));

        $response->assertStatus(200);
        $content = $response->getContent();
        // The modal details should not contain Divisi row
        $this->assertStringNotContainsString('{{ $divisionTerm ?? \'Divisi\' }}', $content);

        $apiResponse = $this->get(route('tv.dashboard.stats', ['display_token' => $this->orgBimbel->display_token]));
        $apiResponse->assertStatus(200);
        $attendances = $apiResponse->json('liveCheckIns');
        $this->assertNull($attendances[0]['divisi']);
    }

    public function test_16_position_off_hides_position_information()
    {
        $apiResponse = $this->get(route('tv.dashboard.stats', ['display_token' => $this->orgBimbel->display_token]));
        $apiResponse->assertStatus(200);
        $attendances = $apiResponse->json('liveCheckIns');
        $this->assertNull($attendances[0]['jabatan']);
    }

    public function test_17_employee_on_counts_members_and_uses_term()
    {
        $apiResponse = $this->get(route('tv.dashboard.stats', ['display_token' => $this->orgCorp->display_token]));
        $apiResponse->assertStatus(200);
        $this->assertEquals(1, $apiResponse->json('totalPegawai'));

        $this->disableFeature($this->orgCorp, 'employee');
        $apiResponseOff = $this->get(route('tv.dashboard.stats', ['display_token' => $this->orgCorp->display_token]));
        $apiResponseOff->assertStatus(200);
        $this->assertEquals(0, $apiResponseOff->json('totalPegawai'));
    }

    public function test_18_wfo_wfh_on_renders_wfo_widget()
    {
        $response = $this->get(route('tv.dashboard', ['display_token' => $this->orgCorp->display_token]));

        $response->assertStatus(200);
        $response->assertSee('WFO');

        $apiResponse = $this->get(route('tv.dashboard.stats', ['display_token' => $this->orgCorp->display_token]));
        $apiResponse->assertStatus(200);
        $this->assertEquals(1, $apiResponse->json('wfoCount'));
    }

    public function test_19_wfo_wfh_off_omits_wfo_widget_and_counts()
    {
        $response = $this->get(route('tv.dashboard', ['display_token' => $this->orgBimbel->display_token]));

        $response->assertStatus(200);
        $content = $response->getContent();
        // Should not render WFO badge in mini stats bar
        $this->assertStringNotContainsString('<span>WFO</span>', $content);

        $apiResponse = $this->get(route('tv.dashboard.stats', ['display_token' => $this->orgBimbel->display_token]));
        $apiResponse->assertStatus(200);
        $this->assertEquals(0, $apiResponse->json('wfoCount'));
        $this->assertEquals(0, $apiResponse->json('wfhCount'));
        $attendances = $apiResponse->json('liveCheckIns');
        $this->assertNull($attendances[0]['skema']);
    }

    public function test_20_organization_isolation_in_tv_dashboard()
    {
        $responseCorp = $this->get(route('tv.dashboard', ['display_token' => $this->orgCorp->display_token]));
        $responseCorp->assertStatus(200);
        $responseCorp->assertSee('Pegawai Corporate TV');
        $responseCorp->assertDontSee('Siswa Cerdas TV');

        $responseBimbel = $this->get(route('tv.dashboard', ['display_token' => $this->orgBimbel->display_token]));
        $responseBimbel->assertStatus(200);
        $responseBimbel->assertSee('Siswa Cerdas TV');
        $responseBimbel->assertDontSee('Pegawai Corporate TV');
    }

    public function test_21_custom_terminology_rendered_in_tv_dashboard()
    {
        $response = $this->get(route('tv.dashboard', ['display_token' => $this->orgBimbel->display_token]));

        $response->assertStatus(200);
        // Custom term 'Siswa' should be rendered in header badge
        $response->assertSee('Siswa Hadir');
    }
}
