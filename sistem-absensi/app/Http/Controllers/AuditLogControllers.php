<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use App\Models\Pegawai;
use App\Models\Attendance;
use App\Helpers\OrganizationHelper;

class AuditLogControllers extends Controller
{
    /**
     * Menampilkan riwayat aktivitas (Audit Log) - API Endpoint
     */
    public function index(Request $request)
    {
        // Ambil ID dari URL (misal: /api/audit-log?akun_id=1)
        $akun_id = $request->query('akun_id');

        if (!$akun_id) {
            return response()->json(['message' => 'Akun ID tidak disertakan.'], 400);
        }
        
        $user = Auth::user();
        if ($user && $user->akun_id != $akun_id) {
            return response()->json(['message' => 'Anda tidak memiliki akses ke log audit akun ini.'], 403);
        }

        // Langsung tarik data milik akun tersebut
        $logs = \Illuminate\Support\Facades\DB::table('audit_log')
            ->select('log_id', 'aktivitas', 'waktu_log')
            ->where('akun_id', $akun_id)
            ->orderBy('waktu_log', 'desc')
            ->get();

        return response()->json([
            'status'  => 'success',
            'data'    => $logs
        ], 200);
    }

    public function webIndex(Request $request)
    {
        $user = Auth::user();
        $isSuperAdmin = $user && $user->isSuperAdmin();
        $orgId = OrganizationHelper::getActiveOrganizationId();

        // If not super admin and no org context, require valid organization
        if (! $isSuperAdmin && ! $orgId) {
            abort(403, 'Anda tidak memiliki akses organisasi yang valid. Silakan hubungi administrator.');
        }

        $query = DB::table('audit_log')
            ->join('akun', 'audit_log.akun_id', '=', 'akun.id')
            ->leftJoin('pegawai', 'akun.pegawai_id', '=', 'pegawai.pegawai_id')
            ->leftJoin('organizations', 'pegawai.organization_id', '=', 'organizations.organization_id')
            ->leftJoin('role as access_role', 'akun.role_id', '=', 'access_role.role_id')
            ->select(
                'audit_log.log_id',
                'audit_log.aktivitas',
                'audit_log.waktu_log',
                'akun.username',
                'akun.role',
                'access_role.nama_role as access_role',
                'pegawai.nama_pegawai',
                'organizations.nama_organisasi',
                'pegawai.organization_id'
            );

        if (! $isSuperAdmin) {
            $query->where('pegawai.organization_id', $orgId);
        } else {
            // Filter by organization if specified
            if ($request->filled('organization_id') && $request->organization_id !== 'all') {
                $query->where('pegawai.organization_id', $request->organization_id);
            }
        }

        // Filter by tanggal
        if ($request->filled('tanggal')) {
            $query->whereDate('audit_log.waktu_log', $request->tanggal);
        }

        // Filter by user / pegawai
        if ($request->filled('user')) {
            $userSearch = '%' . strtolower(trim((string) $request->user)) . '%';
            $query->where(function ($q) use ($userSearch) {
                $q->whereRaw('LOWER(pegawai.nama_pegawai) LIKE ?', [$userSearch])
                  ->orWhereRaw('LOWER(akun.username) LIKE ?', [$userSearch]);
            });
        }

        // Filter by aktivitas keyword
        if ($request->filled('aktivitas')) {
            $aktivitasSearch = '%' . strtolower(trim((string) $request->aktivitas)) . '%';
            $query->whereRaw('LOWER(audit_log.aktivitas) LIKE ?', [$aktivitasSearch]);
        }

        $logs = $query->orderBy('audit_log.waktu_log', 'desc')->paginate(15)->withQueryString();
        $logs->getCollection()->transform(function ($log) {
            $actorName = trim((string) ($log->nama_pegawai ?? '')) ?: $log->username;
            $activity = $log->aktivitas;

            foreach ([
                ' berhasil login ke dalam sistem',
                ' melakukan logout dari sistem',
            ] as $suffix) {
                if (str_ends_with($activity, $suffix)) {
                    $activity = $actorName . $suffix;
                    break;
                }
            }

            $log->aktivitas_display = $activity;
            $log->access_role_display = $log->access_role ?: ($log->role ?: 'Tanpa Role');
            $log->organization_name_display = $log->nama_organisasi ?: 'Sistem / Super Admin';

            return $log;
        });

        // Hitung statistik terfilter atau global
        $filterOrgId = (! $isSuperAdmin) ? $orgId : ($request->filled('organization_id') && $request->organization_id !== 'all' ? $request->organization_id : null);

        $totalPegawaiQuery = Pegawai::whereDoesntHave('akun', function ($query) {
            $query->whereRaw('LOWER(role) = ?', ['admin']);
        })->where('status', 'Aktif');

        if ($filterOrgId) {
            $totalPegawaiQuery->where('organization_id', $filterOrgId);
        }
        $totalPegawai = $totalPegawaiQuery->count();

        $hadirQuery = Attendance::where('tanggal_absensi', today());
        if ($filterOrgId) {
            $hadirQuery->whereHas('pegawai', function($q) use ($filterOrgId) {
                $q->where('organization_id', $filterOrgId);
            });
        }

        $hadirHariIni = (clone $hadirQuery)->count();

        $wfoCount = (clone $hadirQuery)
            ->where(function($query) {
                $query->whereNull('skema_kerja')
                      ->orWhere('skema_kerja', 'WFO');
            })
            ->count();

        $wfhWfcCount = (clone $hadirQuery)
            ->whereIn('skema_kerja', ['WFH', 'WFC'])
            ->count();

        $allOrganizations = $isSuperAdmin
            ? \App\Models\Organization::orderBy('nama_organisasi')->get(['organization_id', 'nama_organisasi'])
            : collect();

        return view('admin.log-aktivitas', compact(
            'logs',
            'totalPegawai',
            'hadirHariIni',
            'wfoCount',
            'wfhWfcCount',
            'isSuperAdmin',
            'allOrganizations'
        ));
    }
}