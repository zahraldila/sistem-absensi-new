<?php

namespace App\Http\Controllers;

use App\Helpers\logHelpers;
use App\Models\Approval;
use App\Models\Attendance;
use App\Models\AuditLog;
use App\Models\Pegawai;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use App\Helpers\OrganizationHelper;

class DashboardControllers extends Controller
{
    public function admin(Request $request)
    {
        $orgId = OrganizationHelper::requireActiveOrganization();
        $org   = OrganizationHelper::getActiveOrganization();
        $today = now()->toDateString();

        // Feature flags for active organization:
        // Corporate work mode (WFO/WFH/WFC) is part of the corporate employee feature domain
        $hasWfoWfh   = $org ? ($org->hasFeature('wfo_wfh') || $org->hasFeature('employee')) : false;
        $hasApproval = $org ? $org->hasFeature('approval') : false;
        $hasSchedule = $org ? $org->hasFeature('schedule') : false;

        $totalPegawai = Pegawai::where('organization_id', $orgId)
            ->whereDoesntHave('akun', function ($query) {
                $query->whereRaw('LOWER(role) = ?', ['admin']);
            })->where('status', 'Aktif')->count();

        $hadirHariIni = Attendance::whereHas('pegawai', function ($q) use ($orgId) {
                $q->where('organization_id', $orgId);
            })
            ->whereDate('tanggal_absensi', $today)
            ->distinct('pegawai_id')
            ->count('pegawai_id');

        // Conditional Query: Work Mode statistics (WFO / WFH / WFC) only queried if wfo_wfh feature is enabled
        $wfoCount = 0;
        $wfhWfcCount = 0;
        if ($hasWfoWfh) {
            $wfoCount = Attendance::whereHas('pegawai', function ($q) use ($orgId) {
                    $q->where('organization_id', $orgId);
                })
                ->whereDate('tanggal_absensi', $today)
                ->where('skema_kerja', 'WFO')
                ->distinct('pegawai_id')
                ->count('pegawai_id');

            $wfhWfcCount = Attendance::whereHas('pegawai', function ($q) use ($orgId) {
                    $q->where('organization_id', $orgId);
                })
                ->whereDate('tanggal_absensi', $today)
                ->whereIn('skema_kerja', ['WFH', 'WFC'])
                ->count();
        }

        $liveCheckIns = Attendance::whereHas('pegawai', function ($q) use ($orgId) {
                $q->where('organization_id', $orgId);
            })
            ->whereDate('tanggal_absensi', $today)
            ->whereNotNull('jam_checkin')
            ->with('pegawai')
            ->orderByDesc('jam_checkin')
            ->limit(5)
            ->get()
            ->map(function ($attendance) use ($hasWfoWfh) {
                return [
                    'nama'   => $attendance->pegawai?->nama_pegawai ?? 'Unknown',
                    'foto'   => $attendance->pegawai?->foto_profile
                        ? supabase_public_url($attendance->pegawai->foto_profile)
                        : null,
                    'status' => $hasWfoWfh ? ($attendance->skema_kerja ?? 'WFO') : 'Hadir',
                    'jam'    => $attendance->jam_checkin
                        ? Carbon::parse($attendance->jam_checkin)->format('H:i')
                        : '-',
                ];
            });

        // Conditional Query: Pending Approvals queried ONLY if approval feature is enabled
        $pendingApprovals = collect();
        $pendingApprovalsCount = 0;
        $approvalBreakdown = [
            'Cuti'  => 0,
            'Izin'  => 0,
            'Sakit' => 0,
            'WFH'   => 0,
        ];

        if ($hasApproval) {
            $pendingApprovals = Approval::whereHas('pegawai', function ($q) use ($orgId) {
                    $q->where('organization_id', $orgId);
                })
                ->where('status_pengajuan', 'Pending')
                ->with('pegawai')
                ->orderByDesc('tanggal_pengajuan')
                ->limit(4)
                ->get()
                ->map(function ($approval) {
                    return [
                        'nama'     => $approval->pegawai?->nama_pegawai ?? 'Unknown',
                        'jenis'    => $approval->jenis_pengajuan ?? '-',
                        'tanggal'  => $approval->tanggal_pengajuan
                            ? Carbon::parse($approval->tanggal_pengajuan)->translatedFormat('d F Y')
                            : '-',
                        'status'   => $approval->status_pengajuan ?? 'Pending',
                    ];
                });

            // Summary breakdown for approval widget
            $counts = Approval::whereHas('pegawai', function ($q) use ($orgId) {
                    $q->where('organization_id', $orgId);
                })
                ->where('status_pengajuan', 'Pending')
                ->selectRaw('jenis_pengajuan, count(*) as total')
                ->groupBy('jenis_pengajuan')
                ->pluck('total', 'jenis_pengajuan')
                ->toArray();

            $approvalBreakdown['Cuti']  = $counts['Cuti'] ?? 0;
            $approvalBreakdown['Izin']  = $counts['Izin'] ?? 0;
            $approvalBreakdown['Sakit'] = $counts['Sakit'] ?? 0;
            $approvalBreakdown['WFH']   = $counts['WFH'] ?? ($counts['WFH/WFC'] ?? 0);
            $pendingApprovalsCount = array_sum($counts);
        }

        $activities = AuditLog::query()
            ->whereHas('akun.pegawai', function ($q) use ($orgId) {
                $q->where('organization_id', $orgId);
            })
            ->with('akun.pegawai')
            ->orderByDesc('waktu_log')
            ->limit(4)
            ->get()
            ->map(function ($log) {
                $namaPegawai = $log->akun?->pegawai?->nama_pegawai;
                $aktivitas   = $log->aktivitas ?? 'Aktivitas terbaru';

                return [
                    'title' => $namaPegawai ? $namaPegawai . ' - ' . $aktivitas : $aktivitas,
                    'time'  => $log->waktu_log ? Carbon::parse($log->waktu_log)->diffForHumans() : '-',
                    'color' => 'green',
                ];
            });

        if ($activities->isEmpty()) {
            $activities = collect([[
                'title' => 'Belum ada aktivitas',
                'time'  => '-',
                'color' => 'blue',
            ]]);
        }

        // Conditional Query: Schedule times
        $jamMasuk  = '08:00';
        $jamPulang = '17:00';
        if ($hasSchedule) {
            $jadwal = DB::table('jadwal_kerja')
                ->where('organization_id', $orgId)
                ->orderByDesc('jadwal_id')
                ->first();
            if ($jadwal) {
                $jamMasuk  = Carbon::parse($jadwal->jam_masuk)->format('H:i');
                $jamPulang = Carbon::parse($jadwal->jam_pulang)->format('H:i');
            }
        }

        return view('admin.index', compact(
            'totalPegawai',
            'hadirHariIni',
            'wfoCount',
            'wfhWfcCount',
            'liveCheckIns',
            'pendingApprovals',
            'pendingApprovalsCount',
            'approvalBreakdown',
            'activities',
            'jamMasuk',
            'jamPulang',
            'hasWfoWfh',
            'hasApproval',
            'hasSchedule'
        ));
    }

    /**
     * Return attendance chart data as JSON for the given filter.
     * filter: 'minggu' (default) | 'bulan' | 'tahun'
     */
    public function chartStatistik(Request $request)
    {
        $orgId = OrganizationHelper::requireActiveOrganization();
        $org   = OrganizationHelper::getActiveOrganization();
        $hasWfoWfh = $org ? ($org->hasFeature('wfo_wfh') || $org->hasFeature('employee')) : false;

        $filter = $request->query('filter', 'minggu');

        // Dynamic categories depending on wfo_wfh capability
        $skemas = $hasWfoWfh
            ? ['WFO', 'WFH/WFC', 'Izin', 'Alfa', 'Dinas']
            : ['Hadir', 'Izin', 'Alfa'];

        $tipeExpr = $hasWfoWfh
            ? DB::raw("CASE
                WHEN skema_kerja IN ('WFH','WFC') THEN 'WFH/WFC'
                WHEN status_kehadiran = 'Izin'   THEN 'Izin'
                WHEN status_kehadiran = 'Alfa'   THEN 'Alfa'
                WHEN skema_kerja = 'Dinas'       THEN 'Dinas'
                ELSE COALESCE(skema_kerja, 'WFO')
            END AS tipe")
            : DB::raw("CASE
                WHEN status_kehadiran = 'Izin' THEN 'Izin'
                WHEN status_kehadiran = 'Alfa' THEN 'Alfa'
                ELSE 'Hadir'
            END AS tipe");

        if ($filter === 'minggu') {
            $start    = Carbon::now()->startOfWeek(Carbon::MONDAY);
            $end      = Carbon::now()->endOfWeek(Carbon::SUNDAY);
            $dayNames = ['Sen', 'Sel', 'Rab', 'Kam', 'Jum', 'Sab', 'Min'];

            $labels = [];
            $period = [];
            for ($d = $start->copy(); $d->lte($end); $d->addDay()) {
                $labels[] = $dayNames[$d->dayOfWeek === 0 ? 6 : $d->dayOfWeek - 1];
                $period[] = $d->toDateString();
            }

            $rows = Attendance::select(
                    DB::raw('DATE(tanggal_absensi) as tgl'),
                    $tipeExpr,
                    DB::raw("CASE
                        WHEN (CASE
                            WHEN skema_kerja IN ('WFH','WFC') THEN 'WFH/WFC'
                            WHEN status_kehadiran = 'Izin'   THEN 'Izin'
                            WHEN status_kehadiran = 'Alfa'   THEN 'Alfa'
                            WHEN skema_kerja = 'Dinas'       THEN 'Dinas'
                            ELSE skema_kerja
                        END) = 'WFO' THEN COUNT(DISTINCT pegawai_id)
                        ELSE COUNT(*)
                    END as total")
                )
                ->whereHas('pegawai', function ($q) use ($orgId) {
                    $q->where('organization_id', $orgId);
                })
                ->whereBetween('tanggal_absensi', [$start->toDateString(), $end->toDateString()])
                ->groupBy('tgl', 'tipe')
                ->get()
                ->groupBy('tgl');

            $datasets = [];
            foreach ($skemas as $skema) {
                $data = [];
                foreach ($period as $date) {
                    $found = $rows->get($date, collect())->firstWhere('tipe', $skema);
                    $data[] = $found ? (int) $found->total : 0;
                }
                $datasets[] = ['label' => $skema, 'data' => $data];
            }

            return response()->json([
                'labels'   => $labels,
                'datasets' => $datasets,
                'subtitle' => '7 hari terakhir',
            ]);

        } elseif ($filter === 'bulan') {
            $start = Carbon::now()->startOfMonth();
            $end   = Carbon::now()->endOfMonth();

            $rows = Attendance::select(
                    $tipeExpr,
                    DB::raw('EXTRACT(WEEK FROM tanggal_absensi) as minggu_ke'),
                    DB::raw("CASE
                        WHEN (CASE
                            WHEN skema_kerja IN ('WFH','WFC') THEN 'WFH/WFC'
                            WHEN status_kehadiran = 'Izin'   THEN 'Izin'
                            WHEN status_kehadiran = 'Alfa'   THEN 'Alfa'
                            WHEN skema_kerja = 'Dinas'       THEN 'Dinas'
                            ELSE skema_kerja
                        END) = 'WFO' THEN COUNT(DISTINCT CONCAT(pegawai_id, '_', DATE(tanggal_absensi)))
                        ELSE COUNT(*)
                    END as total")
                )
                ->whereHas('pegawai', function ($q) use ($orgId) {
                    $q->where('organization_id', $orgId);
                })
                ->whereBetween('tanggal_absensi', [$start->toDateString(), $end->toDateString()])
                ->groupBy('tipe', 'minggu_ke')
                ->orderBy('minggu_ke')
                ->get()
                ->groupBy('minggu_ke');

            $weekNos = $rows->keys()->sort()->values();
            $labels  = $weekNos->map(fn ($w, $i) => 'Minggu ' . ($i + 1))->toArray();

            $datasets = [];
            foreach ($skemas as $skema) {
                $data = [];
                foreach ($weekNos as $wk) {
                    $found = $rows->get($wk, collect())->firstWhere('tipe', $skema);
                    $data[] = $found ? (int) $found->total : 0;
                }
                $datasets[] = ['label' => $skema, 'data' => $data];
            }

            return response()->json([
                'labels'   => $labels,
                'datasets' => $datasets,
                'subtitle' => Carbon::now()->translatedFormat('F Y'),
            ]);

        } else {
            // tahun – grouped by month
            $year       = Carbon::now()->year;
            $monthNames = ['Jan', 'Feb', 'Mar', 'Apr', 'Mei', 'Jun',
                           'Jul', 'Agu', 'Sep', 'Okt', 'Nov', 'Des'];

            $rows = Attendance::select(
                    $tipeExpr,
                    DB::raw('EXTRACT(MONTH FROM tanggal_absensi) as bulan_ke'),
                    DB::raw("CASE
                        WHEN (CASE
                            WHEN skema_kerja IN ('WFH','WFC') THEN 'WFH/WFC'
                            WHEN status_kehadiran = 'Izin'   THEN 'Izin'
                            WHEN status_kehadiran = 'Alfa'   THEN 'Alfa'
                            WHEN skema_kerja = 'Dinas'       THEN 'Dinas'
                            ELSE skema_kerja
                        END) = 'WFO' THEN COUNT(DISTINCT CONCAT(pegawai_id, '_', DATE(tanggal_absensi)))
                        ELSE COUNT(*)
                    END as total")
                )
                ->whereHas('pegawai', function ($q) use ($orgId) {
                    $q->where('organization_id', $orgId);
                })
                ->whereYear('tanggal_absensi', $year)
                ->groupBy('tipe', 'bulan_ke')
                ->orderBy('bulan_ke')
                ->get()
                ->groupBy('bulan_ke');

            $datasets = [];
            foreach ($skemas as $skema) {
                $data = [];
                foreach (range(1, 12) as $m) {
                    $found = $rows->get($m, collect())->firstWhere('tipe', $skema);
                    $data[] = $found ? (int) $found->total : 0;
                }
                $datasets[] = ['label' => $skema, 'data' => $data];
            }

            return response()->json([
                'labels'   => $monthNames,
                'datasets' => $datasets,
                'subtitle' => 'Tahun ' . $year,
            ]);
        }
    }

    /**
     * Handle saving Jam Masuk & Jam Pulang from the modal form.
     */
    public function simpanJamKerja(Request $request)
    {
        $orgId = OrganizationHelper::requireActiveOrganization();
        
        $request->validate([
            'jam_masuk'  => ['required', 'date_format:H:i'],
            'jam_pulang' => ['required', 'date_format:H:i', 'after:jam_masuk'],
        ], [
            'jam_masuk.required'       => 'Jam masuk wajib diisi.',
            'jam_masuk.date_format'    => 'Format jam masuk tidak valid (HH:MM).',
            'jam_pulang.required'      => 'Jam pulang wajib diisi.',
            'jam_pulang.date_format'   => 'Format jam pulang tidak valid (HH:MM).',
            'jam_pulang.after'         => 'Jam pulang harus setelah jam masuk.',
        ]);
    
        // Update atau buat jadwal_kerja baru
        $jadwalAktif = DB::table('jadwal_kerja')
            ->where('organization_id', $orgId)
            ->orderByDesc('jadwal_id')
            ->first();
        
        if ($jadwalAktif) {
            DB::table('jadwal_kerja')
                ->where('organization_id', $orgId)
                ->where('jadwal_id', $jadwalAktif->jadwal_id)
                ->update([
                    'jam_masuk'  => $request->jam_masuk,
                    'jam_pulang' => $request->jam_pulang,
                    'tanggal_berlaku' => now()->toDateString(),
                ]);
        } else {
            DB::table('jadwal_kerja')->insert([
                'organization_id' => $orgId,
                'jam_masuk'  => $request->jam_masuk,
                'jam_pulang' => $request->jam_pulang,
                'tanggal_berlaku' => now()->toDateString(),
            ]);
        }

        // Catat aktivitas admin
        $user = \Illuminate\Support\Facades\Auth::user();
    
        if ($user && $user->akun_id) {
            logHelpers::record(
                $user->akun_id,
                "Mengubah jam kerja: {$request->jam_masuk} - {$request->jam_pulang}"
            );
        }
    
        return redirect()
            ->route('admin.dashboard')
            ->with(
                'success',
                'Jam kerja berhasil diperbarui: Masuk ' .
                $request->jam_masuk .
                ' – Pulang ' .
                $request->jam_pulang
            );
    }
}
