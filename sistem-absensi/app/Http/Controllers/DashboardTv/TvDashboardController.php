<?php

namespace App\Http\Controllers\DashboardTv;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;
use App\Models\Organization;

class TvDashboardController extends Controller
{
    public function index(Request $request, $displayToken)
    {
        $org = Organization::where('display_token', $displayToken)->where('status', 'active')->firstOrFail();
        
        $date = $request->query('date', now()->toDateString());
        $data = $this->fetchStats($date, $org->organization_id);
        
        // Resolve branding without session
        $logo = DB::table('settings')->where('organization_id', $org->organization_id)->where('key', 'company_logo')->value('value');
        $logoUrl = null;
        if ($logo && trim($logo) !== '') {
            $logo = trim($logo);
            if (preg_match('/^https?:\/\//i', $logo)) {
                $logoUrl = $logo;
            } elseif (str_starts_with($logo, 'images/') || str_starts_with($logo, 'assets/') || str_starts_with($logo, 'storage/')) {
                $logoUrl = asset($logo);
            } else {
                $bucket = config('supabase.assets_bucket', 'company-assets');
                $supabaseUrl = rtrim(config('supabase.url'), '/');
                $logo = ltrim($logo, '/');
                $logoUrl = "{$supabaseUrl}/storage/v1/object/public/{$bucket}/{$logo}";
            }
        }

        return view('dashboard-tv.index', array_merge($data, [
            'selectedDate' => $date,
            'isDemo' => $request->has('date'),
            'organizationName' => $org->nama_organisasi,
            'logoUrl' => $logoUrl,
            'organizationInitials' => getInitials($org->nama_organisasi),
            'displayToken' => $displayToken
        ]));
    }

    public function getStats(Request $request, $displayToken)
    {
        $org = Organization::where('display_token', $displayToken)->where('status', 'active')->firstOrFail();
        $date = $request->query('date', now()->toDateString());
        $data = $this->fetchStats($date, $org->organization_id);
        
        return response()->json($data);
    }

    private function fetchStats($date, $organizationId)
    {
        // 1. Fetch dynamic list of branches from database ordered by ID
        $branches = DB::table('lokasi_kantor')
            ->where('organization_id', $organizationId)
            ->orderBy('lokasi_id', 'asc')
            ->get(['lokasi_id', 'nama_kantor', 'latitude', 'longitude', 'radius_meter']);


        // 2. Total Pegawai (Aktif)
        $totalPegawai = DB::table('pegawai')
            ->where('organization_id', $organizationId)
            ->where(function ($query) {
                $query->where('status', 'Aktif')
                      ->orWhereNull('status')
                      ->orWhere('status', '');
            })
            ->count();

        // 3. Fetch all raw attendance records for the date
        $allAttendances = DB::table('absensi')
            ->join('pegawai', 'absensi.pegawai_id', '=', 'pegawai.pegawai_id')
            ->leftJoin('master_divisi', 'pegawai.divisi_id', '=', 'master_divisi.divisi_id')
            ->leftJoin('master_jabatan', 'pegawai.jabatan_id', '=', 'master_jabatan.jabatan_id')
            ->leftJoin('jadwal_kerja', 'absensi.jadwal_id', '=', 'jadwal_kerja.jadwal_id')
            ->where('pegawai.organization_id', $organizationId)
            ->whereDate('absensi.tanggal_absensi', $date)
            ->whereNotNull('absensi.jam_checkin')
            ->whereIn(DB::raw('LOWER(TRIM(absensi.status_kehadiran))'), ['hadir', 'terlambat', 'tepat waktu'])
            ->select(
                'absensi.absensi_id',
                'absensi.pegawai_id',
                'absensi.jam_checkin',
                'absensi.jam_checkout',
                'absensi.skema_kerja',
                'absensi.status_kehadiran',
                'absensi.latitude',
                'absensi.longitude',
                'absensi.catatan',
                'absensi.lokasi_id',
                'pegawai.nama_pegawai',
                'pegawai.foto_profile',
                'master_divisi.nama_divisi',
                'master_jabatan.nama_jabatan',
                'jadwal_kerja.jam_masuk',
                'jadwal_kerja.jam_pulang'
            )
            ->orderBy('absensi.absensi_id', 'desc')
            ->get();

        // 4. Group by pegawai_id to handle Multi-Session Check-in/Check-out
        $groupedByPegawai = $allAttendances->groupBy('pegawai_id');

        $latestAttendances = $groupedByPegawai->map(function ($employeeAttendances) {
            $activeSession = $employeeAttendances->first(function ($a) {
                return empty($a->jam_checkout);
            });

            if ($activeSession) {
                $primary = $activeSession;
            } else {
                $primary = $employeeAttendances->sortByDesc(function ($a) {
                    return $a->jam_checkout ?? $a->jam_checkin ?? $a->absensi_id;
                })->first();
            }

            $totalMinutes = 0;
            foreach ($employeeAttendances as $att) {
                if ($att->jam_checkin && $att->jam_checkout) {
                    $totalMinutes += Carbon::parse($att->jam_checkin)->diffInMinutes(Carbon::parse($att->jam_checkout));
                } elseif ($att->jam_checkin && empty($att->jam_checkout)) {
                    $totalMinutes += Carbon::parse($att->jam_checkin)->diffInMinutes(now());
                }
            }

            $primary->total_duration_minutes = $totalMinutes;
            $primary->sessions_count = $employeeAttendances->count();

            return $primary;
        })->values();

        // 5. Map records with branch determination
        $mappedAttendances = $latestAttendances->map(function ($item) use ($branches) {
            $catatan = strtolower($item->catatan ?? '');
            
            // Primary location from absensi
            $matchedLocationId = $item->lokasi_id;
            $matchedLocationName = 'Remote';

            if (!$matchedLocationId) {
                // A. Check branch name in catatan first
                foreach ($branches as $branch) {
                    $bName = strtolower($branch->nama_kantor);
                    $bShort = trim(str_replace('kantor', '', $bName));
                    if (str_contains($catatan, $bName) || (!empty($bShort) && strlen($bShort) >= 3 && str_contains($catatan, $bShort))) {
                        $matchedLocationId = $branch->lokasi_id;
                        break;
                    }
                }


                // C. Check Geo-Location
                if (!$matchedLocationId && !empty($item->latitude) && !empty($item->longitude)) {
                    $lat = (float) $item->latitude;
                    $long = (float) $item->longitude;

                    $closestDist = PHP_FLOAT_MAX;
                    $closestBranch = null;

                    foreach ($branches as $branch) {
                        if ($branch->latitude && $branch->longitude) {
                            $bLat = (float) $branch->latitude;
                            $bLong = (float) $branch->longitude;
                            $dist = sqrt(pow($lat - $bLat, 2) + pow($long - $bLong, 2));
                            if ($dist < $closestDist) {
                                $closestDist = $dist;
                                $closestBranch = $branch;
                            }
                        }
                    }

                    if ($closestBranch && $closestDist < 0.05) {
                        $matchedLocationId = $closestBranch->lokasi_id;
                    }
                }
            }

            // D. Fallback for WFO / WFH
            if (!$matchedLocationId) {
                if (in_array(strtoupper($item->skema_kerja ?? ''), ['WFH', 'WFC'])) {
                    $matchedLocationId = 'remote';
                    $matchedLocationName = 'Remote (WFH/WFC)';
                } else {
                    $firstBranch = $branches->first();
                    $matchedLocationId = $firstBranch?->lokasi_id;
                }
            }

            // Resolve name
            if ($matchedLocationId && $matchedLocationId !== 'remote') {
                $found = $branches->firstWhere('lokasi_id', (int)$matchedLocationId);
                $matchedLocationName = $found ? $found->nama_kantor : 'Unresolved Location';
            }

            $hasCheckOut = !empty($item->jam_checkout);
            $checkinTime = $item->jam_checkin ? Carbon::parse($item->jam_checkin)->format('H:i:s') : '-';
            $checkoutTime = $item->jam_checkout ? Carbon::parse($item->jam_checkout)->format('H:i:s') : '-';
            $tipeAktivitas = $hasCheckOut ? 'checkout' : 'checkin';
            $waktuTerbaru = $hasCheckOut ? $checkoutTime : $checkinTime;

            $durasiKerja = '-';
            if (isset($item->total_duration_minutes) && $item->total_duration_minutes > 0) {
                $hours = intdiv($item->total_duration_minutes, 60);
                $mins = $item->total_duration_minutes % 60;
                $durasiKerja = "{$hours} Jam {$mins} Menit";
            } elseif ($item->jam_checkin && $item->jam_checkout) {
                $diffMins = Carbon::parse($item->jam_checkin)->diffInMinutes(Carbon::parse($item->jam_checkout));
                $hours = intdiv($diffMins, 60);
                $mins = $diffMins % 60;
                $durasiKerja = "{$hours} Jam {$mins} Menit";
            }

            $skema = strtoupper($item->skema_kerja ?? 'WFO');
            if ($skema === 'WFO') {
                $skemaLabel = 'Work From Office';
                $lokasi = $matchedLocationName;
            } elseif ($skema === 'WFH') {
                $skemaLabel = 'Work From Home';
                $lokasi = 'Rumah';
            } elseif ($skema === 'WFC') {
                $skemaLabel = 'Work From Cafe';
                $lokasi = 'Cafe';
            } else {
                $skemaLabel = $item->skema_kerja ?? 'Remote';
                $lokasi = 'Remote';
            }

            $jamKerja = '08:30 - 17:30';
            if ($item->jam_masuk && $item->jam_pulang) {
                $masuk = Carbon::parse($item->jam_masuk)->format('H:i');
                $pulang = Carbon::parse($item->jam_pulang)->format('H:i');
                $jamKerja = "{$masuk} - {$pulang}";
            }

            $statusKehadiran = 'Tepat Waktu';
            if (strtolower(trim($item->status_kehadiran ?? '')) === 'terlambat') {
                $statusKehadiran = 'Terlambat';
            } elseif ($item->jam_checkin && $item->jam_masuk) {
                $checkInTimeParsed = Carbon::parse($item->jam_checkin)->format('H:i:s');
                $jamMasukTimeParsed = Carbon::parse($item->jam_masuk)->format('H:i:s');
                if ($checkInTimeParsed > $jamMasukTimeParsed) {
                    $statusKehadiran = 'Terlambat';
                }
            }

            return [
                'id' => $item->absensi_id,
                'pegawai_id' => $item->pegawai_id,
                'nama' => $item->nama_pegawai,
                'tipe' => $tipeAktivitas,
                'waktu' => $waktuTerbaru,
                'jam_checkin' => $checkinTime,
                'jam_checkout' => $checkoutTime,
                'durasi' => $durasiKerja,
                'has_checkout' => $hasCheckOut,
                'skema' => $skema,
                'skema_label' => $skemaLabel,
                'cabang_id' => (string)$matchedLocationId,
                'cabang_label' => $matchedLocationName,
                'lokasi' => $lokasi,
                'status_kehadiran' => $statusKehadiran,
                'status_kerja' => $hasCheckOut ? 'Sudah Pulang' : 'Sedang Bekerja',
                'divisi' => $item->nama_divisi ?? 'IT',
                'jabatan' => $item->nama_jabatan ?? 'Staff',
                'jam_kerja' => $jamKerja,
                'foto_profile' => (function() use ($item) {
                    if (!$item->foto_profile) {
                        return null;
                    }
                    if (str_starts_with($item->foto_profile, 'http://') || str_starts_with($item->foto_profile, 'https://')) {
                        return $item->foto_profile;
                    }
                    $projectRef = 'fxovkmcrdeezrotwqjhb';
                    $dbUser = env('DB_USERNAME', '');
                    if (str_contains($dbUser, '.')) {
                        $parts = explode('.', $dbUser);
                        $projectRef = end($parts);
                    }
                    return "https://{$projectRef}.supabase.co/storage/v1/object/public/" . ltrim($item->foto_profile, '/');
                })(),
            ];
        });

        // 6. Global Summary counts
        $totalHadir = $mappedAttendances->count();
        $sedangBekerja = $mappedAttendances->where('has_checkout', false)->count();
        $sudahCheckOut = $mappedAttendances->where('has_checkout', true)->count();
        $wfoCount = $mappedAttendances->where('skema', 'WFO')->count();
        $wfhCount = $mappedAttendances->whereIn('skema', ['WFH', 'WFC'])->count();

        // Sakit & Izin scoped by organization
        $sakitCount = DB::table('pengajuan')
            ->join('pegawai', 'pengajuan.pegawai_id', '=', 'pegawai.pegawai_id')
            ->where('pegawai.organization_id', $organizationId)
            ->whereDate('pengajuan.tanggal_pengajuan', $date)
            ->where('pengajuan.jenis_pengajuan', 'Sakit')
            ->where('pengajuan.status_pengajuan', 'Disetujui')
            ->distinct('pengajuan.pegawai_id')
            ->count('pengajuan.pegawai_id');

        $izinCount = DB::table('pengajuan')
            ->join('pegawai', 'pengajuan.pegawai_id', '=', 'pegawai.pegawai_id')
            ->where('pegawai.organization_id', $organizationId)
            ->whereDate('pengajuan.tanggal_pengajuan', $date)
            ->where('pengajuan.jenis_pengajuan', 'Izin')
            ->where('pengajuan.status_pengajuan', 'Disetujui')
            ->distinct('pengajuan.pegawai_id')
            ->count('pengajuan.pegawai_id');

        $belumHadir = max(0, $totalPegawai - $totalHadir - $sakitCount - $izinCount);

        // 7. Group attendances for EVERY branch
        $branchCards = [];
        $firstBranch = $branches->first();
        foreach ($branches as $branch) {
            $branchId = (string)$branch->lokasi_id;
            $branchName = $branch->nama_kantor;
            $isHq = $firstBranch && $branch->lokasi_id === $firstBranch->lokasi_id; // Set first branch as HQ

            $list = $mappedAttendances->filter(function ($i) use ($branchId, $branchName) {
                return (string)$i['cabang_id'] === $branchId;
            })->values();

            $working = $list->where('has_checkout', false)->count();
            $checkout = $list->where('has_checkout', true)->count();

            $branchCards[] = [
                'lokasi_id' => $branch->lokasi_id,
                'nama_kantor' => $branchName,
                'is_hq' => $isHq,
                'total_hadir' => $list->count(),
                'working_count' => $working,
                'checkout_count' => $checkout,
                'attendances' => $list,
            ];
        }

        return [
            'branches' => $branches,
            'branchCards' => $branchCards,
            'totalPegawai' => $totalPegawai,
            'totalHadir' => $totalHadir,
            'sedangBekerja' => $sedangBekerja,
            'sudahCheckOut' => $sudahCheckOut,
            'wfoCount' => $wfoCount,
            'wfhCount' => $wfhCount,
            'sakitCount' => $sakitCount,
            'izinCount' => $izinCount,
            'belumHadir' => $belumHadir,
            'liveCheckIns' => $mappedAttendances,
        ];
    }
}
