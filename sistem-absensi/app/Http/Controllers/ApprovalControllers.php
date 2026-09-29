<?php

namespace App\Http\Controllers;

use App\Exports\ApprovalExport;
use App\Models\Approval;
use App\Models\Pegawai; 
use Illuminate\Http\Request;
use Maatwebsite\Excel\Facades\Excel;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use App\Helpers\logHelpers;
use Carbon\Carbon;

class ApprovalControllers extends Controller
{
    private function normalizeStatus(?string $status): ?string
    {
        if (!$status) {
            return null;
        }

        $s = strtolower(trim($status));

        if ($s === 'pending' || $s === 'menunggu') {
            return 'Pending';
        }
        if ($s === 'disetujui') {
            return 'Disetujui';
        }
        if ($s === 'ditolak') {
            return 'Ditolak';
        }

        return null;
    }

    public function index(Request $request)
    {
        $orgId = \App\Helpers\OrganizationHelper::requireActiveOrganization();
        
        $pending = Approval::whereHas('pegawai', function($q) use ($orgId) {
            $q->where('organization_id', $orgId);
        })->whereIn('status_pengajuan', ['Pending', 'Menunggu'])->count();
    
        $disetujui = Approval::whereHas('pegawai', function($q) use ($orgId) {
            $q->where('organization_id', $orgId);
        })->where('status_pengajuan', 'Disetujui')->count();
    
        $ditolak = Approval::whereHas('pegawai', function($q) use ($orgId) {
            $q->where('organization_id', $orgId);
        })->where('status_pengajuan', 'Ditolak')->count();
    
        $query = Approval::with('pegawai.masterDivisi')->whereHas('pegawai', function($q) use ($orgId) {
            $q->where('organization_id', $orgId);
        });
    
        /*
        |--------------------------------------------------------------------------
        | Filter Tab Status
        |--------------------------------------------------------------------------
        */
    
        $status = $this->normalizeStatus($request->query('status'));

        if ($status === 'Pending') {
            $query->whereIn('status_pengajuan', ['Pending', 'Menunggu']);
        } elseif ($status === 'Disetujui') {
            $query->where('status_pengajuan', 'Disetujui');
        } elseif ($status === 'Ditolak') {
            $query->where('status_pengajuan', 'Ditolak');
        }

        /*
        |--------------------------------------------------------------------------
        | Filter Modal
        |--------------------------------------------------------------------------
        */

        $jenis = $request->query('jenis_pengajuan');
        $tanggalAwal = $request->query('tanggal_awal');
        $tanggalAkhir = $request->query('tanggal_akhir');
        $pegawaiId = $request->query('pegawai_id');

        if ($tanggalAwal && $tanggalAkhir && $tanggalAwal > $tanggalAkhir) {
            if ($request->ajax() || $request->wantsJson()) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Tanggal awal tidak boleh lebih besar dari tanggal akhir.',
                ], 422);
            }
            return redirect()->back()->with('error', 'Tanggal awal tidak boleh lebih besar dari tanggal akhir.');
        }

        if ($jenis) {
            $query->where('jenis_pengajuan', $jenis);
        }

        if ($tanggalAwal) {
            $query->whereDate('tanggal_pengajuan', '>=', $tanggalAwal);
        }

        if ($tanggalAkhir) {
            $query->whereDate('tanggal_pengajuan', '<=', $tanggalAkhir);
        }

        if ($pegawaiId) {
            $query->where('pegawai_id', $pegawaiId);
        }
    
        /*
        |--------------------------------------------------------------------------
        | Data Pengajuan
        |--------------------------------------------------------------------------
        */
    
        $approvals = $query
            ->orderByDesc('tanggal_pengajuan')
            ->paginate(5)
            ->withQueryString();

        $jenisPengajuan = Approval::query()
            ->whereHas('pegawai', function($q) use ($orgId) {
                $q->where('organization_id', $orgId);
            })
            ->whereNotNull('jenis_pengajuan')
            ->where('jenis_pengajuan', '!=', '')
            ->select('jenis_pengajuan')
            ->distinct()
            ->orderBy('jenis_pengajuan')
            ->pluck('jenis_pengajuan');
        
        $pegawai = Pegawai::query()
            ->where('organization_id', $orgId)
            ->where('status', 'Aktif')
            ->whereDoesntHave('akun', function ($q) {
                $q->where('role', 'admin');
            })
            ->orderBy('nama_pegawai')
            ->get([
                'pegawai_id',
                'nama_pegawai',
            ]);
    
        /*
        |--------------------------------------------------------------------------
        | AJAX Request
        |--------------------------------------------------------------------------
        */
    
        if ($request->ajax() || $request->wantsJson()) {
            return response()->json([
                'html' => view(
                    'admin.persetujuan.partials.table',
                    compact('approvals')
                )->render(),
                'counts' => [
                    'pending' => $pending,
                    'disetujui' => $disetujui,
                    'ditolak' => $ditolak,
                ],
            ]);
        }
    
        $pegawaiOptions = Pegawai::query()
            ->where('organization_id', $orgId)
            ->where('status', 'Aktif')
            ->orderBy('nama_pegawai')
            ->get(['pegawai_id', 'nama_pegawai']);

        $jenisOptions = [
            'WFH', 'WFC', 'Sakit', 'Izin', 'Cuti', 'Dinas',
            'Tidak Masuk', 'Lainnya',
        ];

        return view(
            'admin.persetujuan.index',
            compact(
                'approvals',
                'pending',
                'disetujui',
                'ditolak',
                'status',
                'jenisPengajuan',
                'pegawai',
                'pegawaiOptions',
                'jenisOptions'
            )
        );
    }

    public function exportExcel(Request $request)
    {
        $orgId = \App\Helpers\OrganizationHelper::requireActiveOrganization();

        if ($request->filled('tanggal_awal') && $request->filled('tanggal_akhir')) {
            if ($request->tanggal_awal > $request->tanggal_akhir) {
                if ($request->ajax() || $request->wantsJson()) {
                    return response()->json([
                        'status' => 'error',
                        'message' => 'Tanggal awal tidak boleh lebih besar dari tanggal akhir.',
                    ], 422);
                }
                return redirect()->back()->with('error', 'Tanggal awal tidak boleh lebih besar dari tanggal akhir.');
            }
        }

        $status = $this->normalizeStatus($request->query('status') ?? $request->input('status'));

        $filters = [
            'organization_id' => $orgId,
            'tanggal_awal' => $request->query('tanggal_awal') ?? $request->input('tanggal_awal'),
            'tanggal_akhir' => $request->query('tanggal_akhir') ?? $request->input('tanggal_akhir'),
            'status' => $status,
            'pegawai_id' => $request->query('pegawai_id') ?? $request->input('pegawai_id'),
            'jenis_pengajuan' => $request->query('jenis_pengajuan') ?? $request->input('jenis_pengajuan'),
        ];

        $export = new ApprovalExport($filters);
        if (!$export->query()->exists()) {
            if ($request->ajax() || $request->wantsJson()) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Tidak ada data persetujuan yang sesuai dengan filter untuk diekspor.',
                ], 404);
            }
            return redirect()->back()->with('error', 'Tidak ada data persetujuan yang sesuai dengan filter untuk diekspor.');
        }

        $user = Auth::user();
        if ($user && $user->akun_id) {
            logHelpers::record(
                $user->akun_id,
                'Mengekspor data persetujuan ke Excel'
            );
        }

        return Excel::download(
            $export,
            'persetujuan-' . now()->format('Ymd_His') . '.xlsx'
        );
    }

    public function exportCsv(Request $request)
    {
        $orgId = \App\Helpers\OrganizationHelper::requireActiveOrganization();

        if ($request->filled('tanggal_awal') && $request->filled('tanggal_akhir')) {
            if ($request->tanggal_awal > $request->tanggal_akhir) {
                if ($request->ajax() || $request->wantsJson()) {
                    return response()->json([
                        'status' => 'error',
                        'message' => 'Tanggal awal tidak boleh lebih besar dari tanggal akhir.',
                    ], 422);
                }
                return redirect()->back()->with('error', 'Tanggal awal tidak boleh lebih besar dari tanggal akhir.');
            }
        }

        $status = $this->normalizeStatus($request->query('status') ?? $request->input('status'));

        $filters = [
            'organization_id' => $orgId,
            'tanggal_awal' => $request->query('tanggal_awal') ?? $request->input('tanggal_awal'),
            'tanggal_akhir' => $request->query('tanggal_akhir') ?? $request->input('tanggal_akhir'),
            'status' => $status,
            'pegawai_id' => $request->query('pegawai_id') ?? $request->input('pegawai_id'),
            'jenis_pengajuan' => $request->query('jenis_pengajuan') ?? $request->input('jenis_pengajuan'),
        ];

        $export = new ApprovalExport($filters);
        if (!$export->query()->exists()) {
            if ($request->ajax() || $request->wantsJson()) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Tidak ada data persetujuan yang sesuai dengan filter untuk diekspor.',
                ], 404);
            }
            return redirect()->back()->with('error', 'Tidak ada data persetujuan yang sesuai dengan filter untuk diekspor.');
        }

        $approvals = $export->query()->get();

        $user = Auth::user();
        if ($user && $user->akun_id) {
            logHelpers::record(
                $user->akun_id,
                'Mengekspor data persetujuan ke CSV'
            );
        }

        $filename = 'persetujuan-' . date('Ymd_His') . '.csv';

        $callback = function () use ($approvals, $export) {
            $out = fopen('php://output', 'w');
            fwrite($out, "\xEF\xBB\xBF");
            fwrite($out, "sep=;\r\n");
            fputcsv($out, $export->headings(), ';');

            foreach ($approvals as $approval) {
                fputcsv($out, $export->map($approval), ';');
            }

            fclose($out);
        };

        return new \Symfony\Component\HttpFoundation\StreamedResponse($callback, 200, [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'Content-Disposition' => 'attachment; filename="' . $filename . '"',
            'Pragma' => 'public',
            'Cache-Control' => 'must-revalidate, post-check=0, pre-check=0',
        ]);
    }

    public function exportPdf(Request $request)
    {
        $orgId = \App\Helpers\OrganizationHelper::requireActiveOrganization();

        if ($request->filled('tanggal_awal') && $request->filled('tanggal_akhir')) {
            if ($request->tanggal_awal > $request->tanggal_akhir) {
                if ($request->ajax() || $request->wantsJson()) {
                    return response()->json([
                        'status' => 'error',
                        'message' => 'Tanggal awal tidak boleh lebih besar dari tanggal akhir.',
                    ], 422);
                }
                return redirect()->back()->with('error', 'Tanggal awal tidak boleh lebih besar dari tanggal akhir.');
            }
        }

        Carbon::setLocale('id');

        $status = $this->normalizeStatus($request->query('status') ?? $request->input('status'));

        $filters = [
            'organization_id' => $orgId,
            'tanggal_awal' => $request->query('tanggal_awal') ?? $request->input('tanggal_awal'),
            'tanggal_akhir' => $request->query('tanggal_akhir') ?? $request->input('tanggal_akhir'),
            'status' => $status,
            'pegawai_id' => $request->query('pegawai_id') ?? $request->input('pegawai_id'),
            'jenis_pengajuan' => $request->query('jenis_pengajuan') ?? $request->input('jenis_pengajuan'),
        ];

        $export = new ApprovalExport($filters);
        if (!$export->query()->exists()) {
            if ($request->ajax() || $request->wantsJson()) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Tidak ada data persetujuan yang sesuai dengan filter untuk diekspor.',
                ], 404);
            }
            return redirect()->back()->with('error', 'Tidak ada data persetujuan yang sesuai dengan filter untuk diekspor.');
        }

        $approvals = $export->query()->get();

        $user = Auth::user();
        if ($user && $user->akun_id) {
            logHelpers::record(
                $user->akun_id,
                'Mengekspor data persetujuan ke PDF'
            );
        }

        $rows = $approvals->values()->map(function ($approval, $index) {
            $statusDisplay = in_array($approval->status_pengajuan, ['Pending', 'Menunggu'], true)
                ? 'Pending'
                : ($approval->status_pengajuan ?? '-');

            return [
                'no' => $index + 1,
                'nama' => $approval->pegawai?->nama_pegawai ?? '-',
                'divisi' => $approval->pegawai?->masterDivisi?->nama_divisi ?? $approval->pegawai?->jabatan ?? '-',
                'jenis_pengajuan' => $approval->jenis_pengajuan ?? '-',
                'tanggal_pengajuan' => $approval->tanggal_pengajuan ? Carbon::parse($approval->tanggal_pengajuan)->translatedFormat('d F Y') : '-',
                'status' => $statusDisplay,
                'keterangan' => $approval->keterangan ?? '-',
            ];
        });

        $filterLabels = [
            'status' => (!empty($filters['status']) && $filters['status'] !== 'Semua') ? $filters['status'] : 'Semua',
            'jenis' => (!empty($filters['jenis_pengajuan']) && $filters['jenis_pengajuan'] !== 'Semua') ? $filters['jenis_pengajuan'] : 'Semua',
            'pegawai' => (!empty($filters['pegawai_id']) && $filters['pegawai_id'] !== 'Semua') ? (Pegawai::find($filters['pegawai_id'])->nama_pegawai ?? $filters['pegawai_id']) : 'Semua',
            'tanggal_awal' => !empty($filters['tanggal_awal']) ? Carbon::parse($filters['tanggal_awal'])->translatedFormat('d F Y') : 'Semua',
            'tanggal_akhir' => !empty($filters['tanggal_akhir']) ? Carbon::parse($filters['tanggal_akhir'])->translatedFormat('d F Y') : 'Semua',
        ];

        $org = \App\Models\Organization::find($orgId);
        $organizationName = $org->name ?? $org->nama ?? 'ORGANISASI';

        $pdf = app('dompdf.wrapper')->loadView('admin.persetujuan.export-pdf', [
            'rows' => $rows,
            'filters' => $filterLabels,
            'generatedAt' => now()->translatedFormat('d F Y H:i'),
            'organizationName' => $organizationName,
        ])->setPaper('a4', 'landscape');

        $filename = 'persetujuan-' . now()->format('Ymd_His') . '.pdf';

        return $pdf->download($filename);
    }

    public function show(Approval $approval)
    {
        return view(
            'admin.persetujuan.detail',
            compact('approval')
        );
    }

    /**
     * Setujui pengajuan oleh Admin
     */
    public function approve(Request $request, $pengajuanId)
    {
        $user = Auth::user();
        if (!$user) {
            return response()->json([
                'status' => 'error',
                'message' => 'Sesi login telah berakhir. Silakan login kembali.',
            ], 401);
        }

        $orgId = \App\Helpers\OrganizationHelper::requireActiveOrganization();
        
        $pengajuan = DB::table('pengajuan')
            ->join('pegawai', 'pegawai.pegawai_id', '=', 'pengajuan.pegawai_id')
            ->where('pegawai.organization_id', $orgId)
            ->where('pengajuan.pengajuan_id', $pengajuanId)
            ->select('pengajuan.*')
            ->first();
        if (!$pengajuan) {
            return response()->json([
                'status' => 'error',
                'message' => 'Data pengajuan tidak ditemukan.',
            ], 404);
        }

        // Pastikan hanya pengajuan berstatus 'Pending' atau 'Menunggu' yang bisa disetujui
        if (!in_array($pengajuan->status_pengajuan, ['Pending', 'Menunggu'], true)) {
            return response()->json([
                'status' => 'error',
                'message' => "Pengajuan ini sudah berstatus {$pengajuan->status_pengajuan} dan tidak dapat diproses lagi.",
            ], 422);
        }

        $now = Carbon::now();
        $jenisPengajuan = $pengajuan->jenis_pengajuan ?? 'Pengajuan';
        
        $pegawai = $pengajuan->pegawai_id
            ? Pegawai::find($pengajuan->pegawai_id)
            : null;
        
        $namaPegawai = $pegawai?->nama_pegawai ?? 'Pegawai';

        DB::beginTransaction();
        try {
            // 1. Simpan ke tabel 'approval'
            DB::table('approval')->insert([
                'pengajuan_id'     => $pengajuanId,
                'akun_id'          => $user->akun_id,
                'status_approval'  => 'Disetujui',
                'catatan_admin'    => $request->catatan_admin ?? null,
                'tanggal_approval' => $now,
            ]);

            // 2. Update status pada tabel 'pengajuan'
            DB::table('pengajuan')
                ->where('pengajuan_id', $pengajuanId)
                ->update([
                    'status_pengajuan' => 'Disetujui',
                ]);

            // Ensure notifikasi id sequence is set correctly
            // Sequence sync handled by migration; removed direct setval.

            // 3. Simpan record notifikasi untuk pegawai pemilik pengajuan
            if ($pengajuan->pegawai_id) {
                DB::table('notifikasi')->insert([
                    'pegawai_id'    => $pengajuan->pegawai_id,
                    'judul'         => "Pengajuan {$jenisPengajuan} Disetujui",
                    'isi_pesan'     => "Pengajuan {$jenisPengajuan} Anda telah disetujui oleh admin.",
                    'tanggal_kirim' => $now,
                ]);
            }


            DB::commit();

            // Catat log setelah transaksi berhasil
            logHelpers::record(
                $user->akun_id,
                "Menyetujui pengajuan {$jenisPengajuan} untuk {$namaPegawai}"
            );

            // Ambil data statistik counter terkini
            $counts = [
                'pending'   => DB::table('pengajuan')->join('pegawai', 'pegawai.pegawai_id', '=', 'pengajuan.pegawai_id')->where('pegawai.organization_id', $orgId)->whereIn('status_pengajuan', ['Pending', 'Menunggu'])->count(),
                'disetujui' => DB::table('pengajuan')->join('pegawai', 'pegawai.pegawai_id', '=', 'pengajuan.pegawai_id')->where('pegawai.organization_id', $orgId)->where('status_pengajuan', 'Disetujui')->count(),
                'ditolak'   => DB::table('pengajuan')->join('pegawai', 'pegawai.pegawai_id', '=', 'pengajuan.pegawai_id')->where('pegawai.organization_id', $orgId)->where('status_pengajuan', 'Ditolak')->count(),
            ];

            return response()->json([
                'status'  => 'success',
                'message' => "Pengajuan {$jenisPengajuan} berhasil disetujui.",
                'counts'  => $counts,
            ], 200);

        } catch (\Exception $e) {
            DB::rollBack();

            return response()->json([
                'status'  => 'error',
                'message' => 'Terjadi kesalahan sistem saat menyetujui pengajuan.',
                'error'   => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Tolak pengajuan oleh Admin (wajib menyertakan alasan)
     */
    public function reject(Request $request, $pengajuanId)
    {
        $user = Auth::user();
        if (!$user) {
            return response()->json([
                'status' => 'error',
                'message' => 'Sesi login telah berakhir. Silakan login kembali.',
            ], 401);
        }

        // Validasi alasan penolakan wajib diisi
        $request->validate([
            'catatan_admin' => 'required|string|min:3|max:1000',
        ], [
            'catatan_admin.required' => 'Alasan penolakan wajib diisi.',
            'catatan_admin.min'      => 'Alasan penolakan minimal harus berisi 3 karakter.',
            'catatan_admin.max'      => 'Alasan penolakan maksimal 1000 karakter.',
        ]);

        $orgId = \App\Helpers\OrganizationHelper::requireActiveOrganization();
        
        $pengajuan = DB::table('pengajuan')
            ->join('pegawai', 'pegawai.pegawai_id', '=', 'pengajuan.pegawai_id')
            ->where('pegawai.organization_id', $orgId)
            ->where('pengajuan.pengajuan_id', $pengajuanId)
            ->select('pengajuan.*')
            ->first();
        if (!$pengajuan) {
            return response()->json([
                'status' => 'error',
                'message' => 'Data pengajuan tidak ditemukan.',
            ], 404);
        }

        // Pastikan hanya pengajuan berstatus 'Pending' atau 'Menunggu' yang bisa ditolak
        if (!in_array($pengajuan->status_pengajuan, ['Pending', 'Menunggu'], true)) {
            return response()->json([
                'status' => 'error',
                'message' => "Pengajuan ini sudah berstatus {$pengajuan->status_pengajuan} dan tidak dapat diproses lagi.",
            ], 422);
        }

        $now = Carbon::now();
        $jenisPengajuan = $pengajuan->jenis_pengajuan ?? 'Pengajuan';
        $alasanPenolakan = trim($request->catatan_admin);
        
        $pegawai = $pengajuan->pegawai_id
            ? Pegawai::find($pengajuan->pegawai_id)
            : null;
        
        $namaPegawai = $pegawai?->nama_pegawai ?? 'Pegawai';

        DB::beginTransaction();
        try {
            // 1. Simpan ke tabel 'approval'
            DB::table('approval')->insert([
                'pengajuan_id'     => $pengajuanId,
                'akun_id'          => $user->akun_id,
                'status_approval'  => 'Ditolak',
                'catatan_admin'    => $alasanPenolakan,
                'tanggal_approval' => $now,
            ]);

            // 2. Update status pada tabel 'pengajuan'
            DB::table('pengajuan')
                ->where('pengajuan_id', $pengajuanId)
                ->update([
                    'status_pengajuan' => 'Ditolak',
                ]);

            // Ensure notifikasi id sequence is set correctly
            // Sequence sync handled by migration; removed direct setval.

            // 3. Simpan record notifikasi untuk pegawai pemilik pengajuan
            if ($pengajuan->pegawai_id) {
                DB::table('notifikasi')->insert([
                    'pegawai_id'    => $pengajuan->pegawai_id,
                    'judul'         => "Pengajuan {$jenisPengajuan} Ditolak",
                    'isi_pesan'     => "Pengajuan {$jenisPengajuan} Anda ditolak. Alasan: {$alasanPenolakan}",
                    'tanggal_kirim' => $now,
                ]);
            }

            DB::commit();

            // Catat log setelah transaksi berhasil
            logHelpers::record(
                $user->akun_id,
                "Menolak pengajuan {$jenisPengajuan} untuk {$namaPegawai}. Alasan: {$alasanPenolakan}"
            );

            // Ambil data statistik counter terkini
            $counts = [
                'pending'   => DB::table('pengajuan')->join('pegawai', 'pegawai.pegawai_id', '=', 'pengajuan.pegawai_id')->where('pegawai.organization_id', $orgId)->whereIn('status_pengajuan', ['Pending', 'Menunggu'])->count(),
                'disetujui' => DB::table('pengajuan')->join('pegawai', 'pegawai.pegawai_id', '=', 'pengajuan.pegawai_id')->where('pegawai.organization_id', $orgId)->where('status_pengajuan', 'Disetujui')->count(),
                'ditolak'   => DB::table('pengajuan')->join('pegawai', 'pegawai.pegawai_id', '=', 'pengajuan.pegawai_id')->where('pegawai.organization_id', $orgId)->where('status_pengajuan', 'Ditolak')->count(),
            ];

            return response()->json([
                'status'  => 'success',
                'message' => "Pengajuan {$jenisPengajuan} berhasil ditolak.",
                'counts'  => $counts,
            ], 200);

        } catch (\Exception $e) {
            DB::rollBack();

            return response()->json([
                'status'  => 'error',
                'message' => 'Terjadi kesalahan sistem saat menolak pengajuan.',
                'error'   => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Endpoint terpadu untuk pemrosesan status approval
     */
    public function process(Request $request, $pengajuanId)
    {
        $status = $request->input('status_approval');
        if ($status === 'Disetujui') {
            return $this->approve($request, $pengajuanId);
        } elseif ($status === 'Ditolak') {
            return $this->reject($request, $pengajuanId);
        }

        return response()->json([
            'status'  => 'error',
            'message' => 'Status approval tidak valid. Pilih Disetujui atau Ditolak.',
        ], 422);
    }

    /**
     * Simpan catatan absensi yang dibuat oleh admin/role berwenang.
     *
     * WFO/WFH/WFC → insert ke tabel absensi
     * Sakit/Izin/Cuti/Dinas/Tidak Masuk/Lainnya → insert ke tabel pengajuan
     */
    public function store(Request $request)
    {
        $user = Auth::user();
        if (! $user) {
            return redirect('/login');
        }

        $orgId = \App\Helpers\OrganizationHelper::requireActiveOrganization();

        // Validasi input
        $request->validate([
            'pegawai_id' => 'required|integer',
            'tanggal'    => 'required|date',
            'jenis'      => 'required|string|max:50',
            'keterangan' => 'nullable|string|max:2000',
        ], [
            'pegawai_id.required' => 'Pegawai wajib dipilih.',
            'tanggal.required'    => 'Tanggal wajib diisi.',
            'jenis.required'      => 'Jenis catatan wajib dipilih.',
        ]);

        $pegawaiId  = $request->input('pegawai_id');
        $tanggal    = $request->input('tanggal');
        $jenis      = $request->input('jenis');
        $keterangan = $request->input('keterangan');

        // Validasi pegawai milik organization aktif
        $pegawai = Pegawai::where('pegawai_id', $pegawaiId)
            ->where('organization_id', $orgId)
            ->where('status', 'Aktif')
            ->first();

        if (! $pegawai) {
            return back()
                ->withErrors(['pegawai_id' => 'Pegawai tidak ditemukan atau bukan milik organisasi Anda.'])
                ->withInput();
        }

        // Tolak secara eksplisit jika jenis WFO dikirimkan
        if (strtoupper(trim($jenis)) === 'WFO') {
            return back()
                ->withErrors(['jenis' => 'Jenis WFO tidak diizinkan pada form Tambah Catatan Absensi.'])
                ->withInput();
        }

        $allowedJenis = ['WFH', 'WFC', 'Sakit', 'Izin', 'Cuti', 'Dinas', 'Tidak Masuk', 'Lainnya'];
        if (!in_array($jenis, $allowedJenis, true)) {
            return back()
                ->withErrors(['jenis' => 'Jenis catatan tidak valid. Pilihan yang diizinkan: ' . implode(', ', $allowedJenis)])
                ->withInput();
        }

        // Cek duplicate pengajuan pada tanggal yang sama untuk pegawai ini
        $existingPengajuan = DB::table('pengajuan')
            ->where('pegawai_id', $pegawaiId)
            ->whereDate('tanggal_pengajuan', $tanggal)
            ->first();

        if ($existingPengajuan) {
            return back()
                ->withErrors(['tanggal' => "Sudah ada catatan pengajuan ({$existingPengajuan->jenis_pengajuan}) untuk {$pegawai->nama_pegawai} pada tanggal tersebut."])
                ->withInput();
        }

        $adminId = $user->akun_id ?? $user->id;

        DB::beginTransaction();
        try {
            // 1. Simpan ke tabel 'pengajuan'
            $pengajuanId = DB::table('pengajuan')->insertGetId([
                'pegawai_id'        => $pegawaiId,
                'jenis_pengajuan'   => $jenis,
                'tanggal_pengajuan' => $tanggal,
                'keterangan'        => $keterangan,
                'status_pengajuan'  => 'Disetujui',
                'created_by'        => $adminId,
                'source'            => 'MANUAL',
                'created_at'        => now(),
                'updated_at'        => now(),
            ], 'pengajuan_id');

            // 2. Simpan juga ke tabel 'approval' sebagai riwayat persetujuan resmi
            DB::table('approval')->insert([
                'pengajuan_id'     => $pengajuanId,
                'akun_id'          => $adminId,
                'status_approval'  => 'Disetujui',
                'catatan_admin'    => $keterangan ?: 'Dicatat langsung oleh Admin.',
                'tanggal_approval' => now(),
                'created_at'       => now(),
                'updated_at'       => now(),
            ]);

            // 3. Jika sudah ada data di tabel 'absensi' pada tanggal yang sama, sinkronisasikan status_kehadiran
            $existingAbsensi = DB::table('absensi')
                ->where('pegawai_id', $pegawaiId)
                ->whereDate('tanggal_absensi', $tanggal)
                ->first();

            if ($existingAbsensi) {
                DB::table('absensi')
                    ->where('absensi_id', $existingAbsensi->absensi_id)
                    ->update([
                        'status_kehadiran' => $jenis,
                        'catatan'          => $keterangan ?: $existingAbsensi->catatan,
                        'updated_at'       => now(),
                    ]);
            }

            DB::commit();
        } catch (\Exception $e) {
            DB::rollBack();
            return back()
                ->withErrors(['error' => 'Gagal menyimpan catatan absensi: ' . $e->getMessage()])
                ->withInput();
        }

        logHelpers::record(
            $adminId,
            "Mencatat pengajuan {$jenis} untuk {$pegawai->nama_pegawai} pada {$tanggal}"
        );

        return redirect()
            ->route('admin.persetujuan')
            ->with('success', "Catatan {$jenis} untuk {$pegawai->nama_pegawai} berhasil disimpan.");
    }
}