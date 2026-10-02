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
        $data = $this->fetchStats($date, $org);
        
        // Resolve branding without session
        $logo = DB::table('settings')->where('organization_id', $org->organization_id)->where('key', 'company_logo')->value('value');
        $logoUrl = company_logo_url($logo);

        return view('dashboard-tv.index', array_merge($data, [
            'selectedDate' => $date,
            'isDemo' => $request->has('date'),
            'organizationName' => $org->nama_organisasi,
            'logoUrl' => $logoUrl,
            'organizationInitials' => getInitials($org->nama_organisasi),
            'displayToken' => $displayToken,
            'hasAttendance' => $org->hasFeature('attendance'),
            'hasEmployee' => $org->hasFeature('employee'),
            'hasDivision' => $org->hasFeature('division'),
            'hasPosition' => $org->hasFeature('position'),
            'hasWfoWfh' => $org->hasFeature('wfo_wfh'),
            'hasSchedule' => $org->hasFeature('schedule'),
            'hasLocation' => $org->hasFeature('location') || $org->hasFeature('gps'),
            'memberTerm' => $org->getTerminology('member', 'Anggota'),
            'divisionTerm' => $org->getTerminology('division', 'Divisi'),
            'positionTerm' => $org->getTerminology('position', 'Jabatan'),
        ]));
    }

    public function getStats(Request $request, $displayToken)
    {
        $org = Organization::where('display_token', $displayToken)->where('status', 'active')->firstOrFail();
        $date = $request->query('date', now()->toDateString());
        $data = $this->fetchStats($date, $org);
        
        return response()->json($data);
    }

    private function fetchStats($date, Organization $org)
    {
        $organizationId = $org->organization_id;
        $hasAttendance = $org->hasFeature('attendance');
        $hasEmployee = $org->hasFeature('employee');
        $hasDivision = $org->hasFeature('division');
        $hasPosition = $org->hasFeature('position');
        $hasWfoWfh = $org->hasFeature('wfo_wfh');
        $hasSchedule = $org->hasFeature('schedule');

        // 1. Fetch dynamic list of branches from database ordered by ID
        $branches = DB::table('lokasi_kantor')
            ->where('organization_id', $organizationId)
            ->orderBy('lokasi_id', 'asc')
            ->get(['lokasi_id', 'nama_kantor', 'latitude', 'longitude', 'radius_meter']);

        // 2. Total Pegawai (Aktif) - only query if employee capability is enabled
        $totalPegawai = 0;
        if ($hasEmployee) {
            $totalPegawai = DB::table('pegawai')
                ->where('organization_id', $organizationId)
                ->where(function ($query) {
                    $query->where('status', 'Aktif')
                          ->orWhereNull('status')
                          ->orWhere('status', '');
                })
                ->count();
        }

        // 3. If attendance feature is disabled, return early without querying attendance
        if (!$hasAttendance) {
            return [
                'branches' => $branches,
                'branchCards' => [],
                'totalPegawai' => $totalPegawai,
                'totalHadir' => 0,
                'sedangBekerja' => 0,
                'sudahCheckOut' => 0,
                'wfoCount' => 0,
                'wfhCount' => 0,
                'sakitCount' => 0,
                'izinCount' => 0,
                'belumHadir' => 0,
                'liveCheckIns' => collect([]),
                'hasAttendance' => false,
                'hasEmployee' => $hasEmployee,
                'hasDivision' => $hasDivision,
                'hasPosition' => $hasPosition,
                'hasWfoWfh' => $hasWfoWfh,
                'hasSchedule' => $hasSchedule,
            ];
        }

        // 4. Fetch all raw attendance records for the date
        $query = DB::table('absensi')
            ->join('pegawai', 'absensi.pegawai_id', '=', 'pegawai.pegawai_id');

        if ($hasDivision) {
            $query->leftJoin('master_divisi', 'pegawai.divisi_id', '=', 'master_divisi.divisi_id');
        }
        if ($hasPosition) {
            $query->leftJoin('master_jabatan', 'pegawai.jabatan_id', '=', 'master_jabatan.jabatan_id');
        }
        if ($hasSchedule) {
            $query->leftJoin('jadwal_kerja', 'absensi.jadwal_id', '=', 'jadwal_kerja.jadwal_id');
        }

        $select = [
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
        ];
        if ($hasDivision) {
            $select[] = 'master_divisi.nama_divisi';
        }
        if ($hasPosition) {
            $select[] = 'master_jabatan.nama_jabatan';
        }
        if ($hasSchedule) {
            $select[] = 'jadwal_kerja.jam_masuk';
            $select[] = 'jadwal_kerja.jam_pulang';
        }

        $allAttendances = $query
            ->where('pegawai.organization_id', $organizationId)
            ->whereDate('absensi.tanggal_absensi', $date)
            ->whereNotNull('absensi.jam_checkin')
            ->whereIn(DB::raw('LOWER(TRIM(absensi.status_kehadiran))'), ['hadir', 'terlambat', 'tepat waktu'])
            ->select($select)
            ->orderBy('absensi.absensi_id', 'desc')
            ->get();

        // 5. Group by pegawai_id to handle Multi-Session Check-in/Check-out
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

        // 6. Map records with branch determination
        $mappedAttendances = $latestAttendances->map(function ($item) use ($branches, $hasWfoWfh, $hasDivision, $hasPosition, $hasSchedule) {
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

                // B. Check Geo-Location
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

            // Fallback for location
            if (!$matchedLocationId) {
                if ($hasWfoWfh && in_array(strtoupper($item->skema_kerja ?? ''), ['WFH', 'WFC'])) {
                    $matchedLocationId = 'remote';
                    $matchedLocationName = 'Remote (WFH/WFC)';
                } else {
                    $firstBranch = $branches->first();
                    $matchedLocationId = $firstBranch?->lokasi_id ?? 'main';
                }
            }

            // Resolve name
            if ($matchedLocationId && $matchedLocationId !== 'remote' && $matchedLocationId !== 'main') {
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

            $skema = null;
            $skemaLabel = null;
            $lokasi = $matchedLocationName;
            if ($hasWfoWfh) {
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
            }

            $jamKerja = null;
            if ($hasSchedule) {
                $jamKerja = '08:30 - 17:30';
                if (!empty($item->jam_masuk) && !empty($item->jam_pulang)) {
                    $masuk = Carbon::parse($item->jam_masuk)->format('H:i');
                    $pulang = Carbon::parse($item->jam_pulang)->format('H:i');
                    $jamKerja = "{$masuk} - {$pulang}";
                }
            }

            $statusKehadiran = 'Tepat Waktu';
            if (strtolower(trim($item->status_kehadiran ?? '')) === 'terlambat') {
                $statusKehadiran = 'Terlambat';
            } elseif ($hasSchedule && $item->jam_checkin && !empty($item->jam_masuk)) {
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
                'divisi' => $hasDivision ? ($item->nama_divisi ?? null) : null,
                'jabatan' => $hasPosition ? ($item->nama_jabatan ?? null) : null,
                'jam_kerja' => $jamKerja,
                'foto_profile' => supabase_public_url($item->foto_profile),
            ];
        });

        // 7. Global Summary counts
        $totalHadir = $mappedAttendances->count();
        $sedangBekerja = $mappedAttendances->where('has_checkout', false)->count();
        $sudahCheckOut = $mappedAttendances->where('has_checkout', true)->count();
        $wfoCount = $hasWfoWfh ? $mappedAttendances->where('skema', 'WFO')->count() : 0;
        $wfhCount = $hasWfoWfh ? $mappedAttendances->whereIn('skema', ['WFH', 'WFC'])->count() : 0;

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

        // 8. Group attendances for EVERY branch
        $branchCards = [];
        $firstBranch = $branches->first();

        if ($branches->isNotEmpty()) {
            foreach ($branches as $branch) {
                $branchId = (string)$branch->lokasi_id;
                $branchName = $branch->nama_kantor;
                $isHq = $firstBranch && $branch->lokasi_id === $firstBranch->lokasi_id;

                $list = $mappedAttendances->filter(function ($i) use ($branchId) {
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
        } else {
            // Default virtual branch card when no specific locations are configured
            $working = $mappedAttendances->where('has_checkout', false)->count();
            $checkout = $mappedAttendances->where('has_checkout', true)->count();

            $branchCards[] = [
                'lokasi_id' => 0,
                'nama_kantor' => $org->nama_organisasi,
                'is_hq' => true,
                'total_hadir' => $mappedAttendances->count(),
                'working_count' => $working,
                'checkout_count' => $checkout,
                'attendances' => $mappedAttendances,
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
            'hasAttendance' => true,
            'hasEmployee' => $hasEmployee,
            'hasDivision' => $hasDivision,
            'hasPosition' => $hasPosition,
            'hasWfoWfh' => $hasWfoWfh,
            'hasSchedule' => $hasSchedule,
        ];
    }
}
