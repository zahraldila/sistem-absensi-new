<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class OrganizationFeatureSystemSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        DB::beginTransaction();

        try {
            $now = now();

            // 1. SEED KATEGORI AWAL (HANYA Perusahaan & Bimbel)
            $categories = [
                [
                    'name'        => 'Perusahaan',
                    'slug'        => 'perusahaan',
                    'description' => 'Organisasi korporat / bisnis dengan struktur pegawai, divisi, jabatan, dan workflow persetujuan.',
                    'status'      => 'active',
                ],
                [
                    'name'        => 'Bimbel',
                    'slug'        => 'bimbel',
                    'description' => 'Lembaga bimbingan belajar / kursus yang berfokus pada kegiatan belajar mengajar tutor dan siswa.',
                    'status'      => 'active',
                ],
            ];

            $categoryMap = [];
            foreach ($categories as $cat) {
                DB::table('organization_categories')->updateOrInsert(
                    ['slug' => $cat['slug']],
                    [
                        'name'        => $cat['name'],
                        'description' => $cat['description'],
                        'status'      => $cat['status'],
                        'updated_at'  => $now,
                    ]
                );

                $catRecord = DB::table('organization_categories')->where('slug', $cat['slug'])->first();
                $categoryMap[$cat['slug']] = $catRecord->id;
            }

            // 2. SEED FEATURE CATALOG (14 Features)
            $features = [
                // Core
                [
                    'key'            => 'attendance',
                    'name'           => 'Presensi Kehadiran',
                    'description'    => 'Pencatatan log check-in dan check-out.',
                    'category_group' => 'core',
                    'status'         => 'active',
                ],
                [
                    'key'            => 'schedule',
                    'name'           => 'Jam Operasional & Jadwal',
                    'description'    => 'Pengaturan jam kerja, jam masuk, dan jam pulang.',
                    'category_group' => 'core',
                    'status'         => 'active',
                ],
                [
                    'key'            => 'location',
                    'name'           => 'Lokasi & Geofence',
                    'description'    => 'Titik koordinat fisik dan radius presensi.',
                    'category_group' => 'core',
                    'status'         => 'active',
                ],
                // Method
                [
                    'key'            => 'gps',
                    'name'           => 'Validasi GPS',
                    'description'    => 'Validasi posisi perangkat pengguna saat melakukan absensi.',
                    'category_group' => 'method',
                    'status'         => 'active',
                ],
                [
                    'key'            => 'nfc',
                    'name'           => 'Kartu / Badge NFC',
                    'description'    => 'Presensi fisik menggunakan tap kartu RFID / NFC.',
                    'category_group' => 'method',
                    'status'         => 'active',
                ],
                [
                    'key'            => 'wifi',
                    'name'           => 'Pembatasan WiFi Kantor',
                    'description'    => 'Validasi presensi berdasarkan jaringan IP / WiFi yang diizinkan.',
                    'category_group' => 'method',
                    'status'         => 'active',
                ],
                [
                    'key'            => 'manual_entry',
                    'name'           => 'Catatan Absensi Manual',
                    'description'    => 'Input presensi langsung oleh admin atas nama anggota.',
                    'category_group' => 'method',
                    'status'         => 'active',
                ],
                // Corporate Domain
                [
                    'key'            => 'employee',
                    'name'           => 'Manajemen Pegawai',
                    'description'    => 'Pengelolaan data dan akun pegawai perusahaan.',
                    'category_group' => 'corporate',
                    'status'         => 'active',
                ],
                [
                    'key'            => 'division',
                    'name'           => 'Master Divisi',
                    'description'    => 'Pengelompokan departemen / divisi kerja.',
                    'category_group' => 'corporate',
                    'status'         => 'active',
                ],
                [
                    'key'            => 'position',
                    'name'           => 'Master Jabatan',
                    'description'    => 'Struktur jenjang jabatan pegawai.',
                    'category_group' => 'corporate',
                    'status'         => 'active',
                ],
                [
                    'key'            => 'approval',
                    'name'           => 'Pengajuan & Persetujuan',
                    'description'    => 'Alur birokrasi pengajuan cuti, izin, sakit, dinas, dan approval admin.',
                    'category_group' => 'corporate',
                    'status'         => 'active',
                ],
                // Academic Domain (Capability only)
                [
                    'key'            => 'student',
                    'name'           => 'Manajemen Siswa',
                    'description'    => 'Definisi kapabilitas siswa / peserta didik (capability definition).',
                    'category_group' => 'academic',
                    'status'         => 'active',
                ],
                [
                    'key'            => 'tutor',
                    'name'           => 'Manajemen Tutor / Pengajar',
                    'description'    => 'Definisi kapabilitas pengajar / tutor (capability definition).',
                    'category_group' => 'academic',
                    'status'         => 'active',
                ],
                [
                    'key'            => 'groups',
                    'name'           => 'Pengelompokan Kelas / Rombel',
                    'description'    => 'Definisi kapabilitas kelas / rombongan belajar (capability definition).',
                    'category_group' => 'academic',
                    'status'         => 'active',
                ],
            ];

            $featureMap = [];
            foreach ($features as $feat) {
                DB::table('features')->updateOrInsert(
                    ['key' => $feat['key']],
                    [
                        'name'           => $feat['name'],
                        'description'    => $feat['description'],
                        'category_group' => $feat['category_group'],
                        'status'         => $feat['status'],
                        'updated_at'     => $now,
                    ]
                );

                $featRecord = DB::table('features')->where('key', $feat['key'])->first();
                $featureMap[$feat['key']] = $featRecord->id;
            }

            // 3. SEED TEMPLATE KATEGORI
            // A. Template Perusahaan (Default)
            $perusahaanCatId = $categoryMap['perusahaan'];
            DB::table('category_templates')->updateOrInsert(
                [
                    'category_id' => $perusahaanCatId,
                    'name'        => 'Perusahaan (Default)',
                ],
                [
                    'description' => 'Template standar untuk organisasi korporat / bisnis dengan fitur absensi dan manajemen kepegawaian lengkap.',
                    'is_default'  => true,
                    'updated_at'  => $now,
                ]
            );
            $perusahaanTemplate = DB::table('category_templates')
                ->where('category_id', $perusahaanCatId)
                ->where('name', 'Perusahaan (Default)')
                ->first();

            $perusahaanFeatureStatus = [
                'attendance'   => true,
                'schedule'     => true,
                'location'     => true,
                'gps'          => true,
                'nfc'          => true,
                'wifi'         => true,
                'manual_entry' => true,
                'employee'     => true,
                'division'     => true,
                'position'     => true,
                'approval'     => true,
                'student'      => false,
                'tutor'        => false,
                'groups'       => false,
            ];

            foreach ($perusahaanFeatureStatus as $key => $isEnabled) {
                if (isset($featureMap[$key])) {
                    DB::table('category_template_features')->updateOrInsert(
                        [
                            'template_id' => $perusahaanTemplate->id,
                            'feature_id'  => $featureMap[$key],
                        ],
                        [
                            'is_enabled'     => $isEnabled,
                            'default_config' => null,
                            'updated_at'     => $now,
                        ]
                    );
                }
            }

            // B. Template Bimbel (Default)
            $bimbelCatId = $categoryMap['bimbel'];
            DB::table('category_templates')->updateOrInsert(
                [
                    'category_id' => $bimbelCatId,
                    'name'        => 'Bimbel (Default)',
                ],
                [
                    'description' => 'Template standar untuk bimbingan belajar dengan presensi kelas, tutor, dan siswa.',
                    'is_default'  => true,
                    'updated_at'  => $now,
                ]
            );
            $bimbelTemplate = DB::table('category_templates')
                ->where('category_id', $bimbelCatId)
                ->where('name', 'Bimbel (Default)')
                ->first();

            $bimbelFeatureStatus = [
                'attendance'   => true,
                'schedule'     => true,
                'location'     => true,
                'gps'          => true,
                'nfc'          => false,
                'wifi'         => false,
                'manual_entry' => true,
                'employee'     => false,
                'division'     => false,
                'position'     => false,
                'approval'     => false,
                'student'      => true,
                'tutor'        => true,
                'groups'       => true,
            ];

            foreach ($bimbelFeatureStatus as $key => $isEnabled) {
                if (isset($featureMap[$key])) {
                    DB::table('category_template_features')->updateOrInsert(
                        [
                            'template_id' => $bimbelTemplate->id,
                            'feature_id'  => $featureMap[$key],
                        ],
                        [
                            'is_enabled'     => $isEnabled,
                            'default_config' => null,
                            'updated_at'     => $now,
                        ]
                    );
                }
            }

            // 4. PROVISIONING EXISTING ORGANIZATIONS
            // Semua organisasi existing dikaitkan ke Perusahaan dan di-seed seluruh feature corporate = true
            $existingOrgs = DB::table('organizations')->get();
            foreach ($existingOrgs as $org) {
                // Update category_id ke Perusahaan
                DB::table('organizations')
                    ->where('organization_id', $org->organization_id)
                    ->update([
                        'category_id' => $perusahaanCatId,
                        'updated_at'  => $now,
                    ]);

                // Seed organization_features secara eksplisit sesuai template Perusahaan
                foreach ($perusahaanFeatureStatus as $key => $isEnabled) {
                    if (isset($featureMap[$key])) {
                        DB::table('organization_features')->updateOrInsert(
                            [
                                'organization_id' => $org->organization_id,
                                'feature_id'      => $featureMap[$key],
                            ],
                            [
                                'is_enabled'    => $isEnabled,
                                'configuration' => null,
                                'updated_at'    => $now,
                            ]
                        );
                    }
                }
            }

            DB::commit();
        } catch (\Throwable $e) {
            DB::rollBack();
            throw $e;
        }
    }
}
