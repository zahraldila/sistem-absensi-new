<?php

namespace App\Http\Controllers;

use App\Exports\AttendanceReportExport;
use App\Helpers\logHelpers;
use App\Models\Attendance;
use App\Models\Pegawai;
use Barryvdh\DomPDF\Facade\Pdf;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Collection;
use Maatwebsite\Excel\Facades\Excel;

class AttendanceReportController extends Controller
{
    private function attendanceQuery(Request $request)
    {
        $orgId = \App\Helpers\OrganizationHelper::requireActiveOrganization();
        
        $query = Attendance::with('pegawai.masterDivisi')
            ->whereHas('pegawai', function ($q) use ($orgId) {
                $q->where('organization_id', $orgId)
                  ->where('status', 'Aktif')
                  ->whereDoesntHave('akun', function ($q2) {
                      $q2->where('role', 'admin');
                  });
            });

        if ($search = trim((string) $request->query('search', ''))) {
            $searchTerm = "%{$search}%";

            $query->where(function ($query) use ($search, $searchTerm) {
                $query->whereHas('pegawai', function ($query) use ($searchTerm) {
                    $query->where('nama_pegawai', 'ilike', $searchTerm);
                })
                ->orWhere('skema_kerja', 'ilike', $searchTerm)
                ->orWhere('status_kehadiran', 'ilike', $searchTerm)
                ->orWhereRaw("to_char(tanggal_absensi, 'FMDD TMMonth YYYY') ILIKE ?", [$searchTerm])
                ->orWhereRaw("CAST(tanggal_absensi AS TEXT) ILIKE ?", [$searchTerm])
                ->orWhereRaw("COALESCE(to_char(jam_checkin, 'HH24:MI'), '') ILIKE ?", [$searchTerm])
                ->orWhereRaw("COALESCE(to_char(jam_checkout, 'HH24:MI'), '') ILIKE ?", [$searchTerm])
                ->orWhereRaw("(
                    CASE
                        WHEN jam_checkin IS NULL OR jam_checkout IS NULL OR jam_checkout < jam_checkin THEN ''
                        ELSE (
                            CASE WHEN FLOOR(EXTRACT(EPOCH FROM (jam_checkout - jam_checkin)) / 3600) > 0
                                THEN FLOOR(EXTRACT(EPOCH FROM (jam_checkout - jam_checkin)) / 3600)::TEXT || ' Jam'
                                ELSE ''
                            END
                            || CASE WHEN MOD(FLOOR(EXTRACT(EPOCH FROM (jam_checkout - jam_checkin)) / 60), 60) > 0
                                THEN CASE WHEN FLOOR(EXTRACT(EPOCH FROM (jam_checkout - jam_checkin)) / 3600) > 0 THEN ' ' ELSE '' END
                                     || MOD(FLOOR(EXTRACT(EPOCH FROM (jam_checkout - jam_checkin)) / 60), 60)::TEXT || ' Menit'
                                ELSE ''
                            END
                            || CASE WHEN FLOOR(EXTRACT(EPOCH FROM (jam_checkout - jam_checkin)) / 60) = 0 THEN '0 Menit' ELSE '' END
                        )
                    END
                ) ILIKE ?", [$searchTerm])
                ->orWhereRaw("CAST(latitude AS TEXT) ILIKE ? OR CAST(longitude AS TEXT) ILIKE ? OR (CAST(latitude AS TEXT) || ', ' || CAST(longitude AS TEXT)) ILIKE ?", [$searchTerm, $searchTerm, $searchTerm]);

                if ($date = $this->parseSearchDate($search)) {
                    $query->orWhereDate('tanggal_absensi', '=', $date);
                }
            });
        }

        if ($status = $request->query('status')) {
            if ($status !== 'Semua') {
                if ($status === 'Tepat Waktu') {
                    $query->whereRaw("CAST(jam_checkin AS TIME) <= (
                        SELECT jam_masuk FROM jadwal_kerja WHERE jadwal_kerja.jadwal_id = absensi.jadwal_id LIMIT 1
                    )");
                } elseif ($status === 'Terlambat') {
                    $query->whereRaw("CAST(jam_checkin AS TIME) > (
                        SELECT jam_masuk FROM jadwal_kerja WHERE jadwal_kerja.jadwal_id = absensi.jadwal_id LIMIT 1
                    )");
                } elseif ($status === 'Hadir') {
                    $query->whereNotNull('jam_checkin');
                } else {
                    $query->where('status_kehadiran', $status);
                }
            }
        }

        if ($startDate = $request->query('start_date')) {
            $query->whereDate('tanggal_absensi', '>=', $startDate);
        }

        if ($endDate = $request->query('end_date')) {
            $query->whereDate('tanggal_absensi', '<=', $endDate);
        }

        if ($divisiId = $request->query('divisi_id')) {
            if ($divisiId !== 'Semua') {
                $query->whereHas('pegawai', function ($q) use ($divisiId) {
                    $q->where('divisi_id', $divisiId);
                });
            }
        }

        if ($modeKerja = $request->query('mode_kerja')) {
            if ($modeKerja !== 'Semua') {
                $query->where('skema_kerja', $modeKerja);
            }
        }

        if ($pegawaiId = $request->query('pegawai_id')) {
            if ($pegawaiId !== 'Semua') {
                $query->where('pegawai_id', $pegawaiId);
            }
        }

        return $query;
    }

    private function getAbsentRecords(Request $request): Collection
    {
        $modeKerja = $request->query('mode_kerja');
        if ($modeKerja && $modeKerja !== 'Semua') {
            return collect();
        }

        $startDate = $request->query('start_date');
        $endDate = $request->query('end_date');
        
        $dates = [];
        if ($startDate && $endDate) {
            try {
                $start = Carbon::parse($startDate);
                $end = Carbon::parse($endDate);
                if ($start->gt($end)) {
                    $dates[] = $startDate;
                } else {
                    for ($d = $start->copy(); $d->lte($end); $d->addDay()) {
                        $dates[] = $d->toDateString();
                    }
                }
            } catch (\Throwable $e) {
                $dates[] = Carbon::today()->toDateString();
            }
        } elseif ($startDate) {
            $dates[] = $startDate;
        } elseif ($endDate) {
            $dates[] = $endDate;
        } else {
            $dates[] = Carbon::today()->toDateString();
        }

        $records = collect();
        $divisiId = $request->query('divisi_id');
        $pegawaiId = $request->query('pegawai_id');
        $search = trim((string) $request->query('search', ''));
        $searchTerm = "%{$search}%";

        $orgId = \App\Helpers\OrganizationHelper::requireActiveOrganization();

        foreach ($dates as $date) {
            $query = Pegawai::with('masterDivisi')
                ->where('organization_id', $orgId)
                ->whereDoesntHave('absensi', function ($q) use ($date) {
                    $q->whereDate('tanggal_absensi', $date);
                });

            if ($divisiId && $divisiId !== 'Semua') {
                $query->where('divisi_id', $divisiId);
            }

            if ($pegawaiId && $pegawaiId !== 'Semua') {
                $query->where('pegawai_id', $pegawaiId);
            }

            if ($search !== '') {
                $query->where('nama_pegawai', 'ilike', $searchTerm);
            }

            $pegawais = $query->orderBy('nama_pegawai')->get();

            foreach ($pegawais as $pegawai) {
                if ($pegawai->status !== 'Aktif') {
                    continue;
                }

                // Cek apakah pegawai memiliki pengajuan yang disetujui pada tanggal ini
                $pengajuanDisetujui = \DB::table('pengajuan')
                    ->where('pegawai_id', $pegawai->pegawai_id)
                    ->whereDate('tanggal_pengajuan', $date)
                    ->where('status_pengajuan', 'Disetujui')
                    ->first();

                $statusKehadiran = $pengajuanDisetujui
                    ? $pengajuanDisetujui->jenis_pengajuan   // "Sakit", "Izin", "Cuti", dll
                    : 'Tidak Hadir';

                $attendance = new Attendance();
                $attendance->pegawai = $pegawai;
                $attendance->tanggal_absensi = $date;
                $attendance->status_kehadiran = $statusKehadiran;
                $attendance->jam_checkin = null;
                $attendance->jam_checkout = null;
                $attendance->skema_kerja = '-';
                $attendance->latitude = null;
                $attendance->longitude = null;
                
                $records->push($attendance);
            }
        }

        return $records->sortByDesc('tanggal_absensi')->values();
    }

    private function formatDuration(?string $checkin, ?string $checkout): string
    {
        if (empty($checkin) || empty($checkout)) {
            return '-';
        }

        $start = Carbon::parse($checkin);
        $end = Carbon::parse($checkout);

        if ($end->lt($start)) {
            return '-';
        }

        $minutes = $start->diffInMinutes($end);
        $hours = intdiv($minutes, 60);
        $remainingMinutes = $minutes % 60;

        $parts = [];

        if ($hours > 0) {
            $parts[] = "{$hours} Jam";
        }

        if ($remainingMinutes > 0) {
            $parts[] = "{$remainingMinutes} Menit";
        }

        return $parts ? implode(' ', $parts) : '0 Menit';
    }

    private function formatTime(?string $value): string
    {
        if (empty($value)) {
            return '-';
        }

        return Carbon::parse($value)->format('H:i');
    }

    private function formatDate(?string $value): string
    {
        if (empty($value)) {
            return '-';
        }

        return Carbon::parse($value)->translatedFormat('d F Y');
    }

    private function formatLocation($latitude, $longitude): string
    {
        if ($latitude !== null && $longitude !== null) {
            return sprintf('%s, %s', trim((string) $latitude), trim((string) $longitude));
        }

        return '-';
    }

    private function parseSearchDate(string $search): ?string
    {
        $search = trim(strtolower($search));

        if ($search === '') {
            return null;
        }

        $months = [
            'januari' => 'January',
            'februari' => 'February',
            'maret' => 'March',
            'april' => 'April',
            'mei' => 'May',
            'juni' => 'June',
            'juli' => 'July',
            'agustus' => 'August',
            'september' => 'September',
            'oktober' => 'October',
            'november' => 'November',
            'desember' => 'December',
        ];

        $normalized = preg_replace_callback('/\b(' . implode('|', array_keys($months)) . ')\b/i', function ($matches) use ($months) {
            return $months[strtolower($matches[1])];
        }, $search);

        $formats = ['d F Y', 'd M Y', 'Y-m-d', 'd-m-Y', 'd/m/Y'];

        foreach ($formats as $format) {
            try {
                $date = Carbon::createFromFormat($format, $normalized);
                if ($date !== false) {
                    return $date->toDateString();
                }
            } catch (\Exception $e) {
                continue;
            }
        }

        return null;
    }

    public function index(Request $request)
    {
        Carbon::setLocale('id');

        $startDate = $request->query('start_date');
        $endDate = $request->query('end_date');
        if ($startDate && $endDate && $startDate > $endDate) {
            if ($request->ajax() || $request->wantsJson()) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Tanggal awal tidak boleh lebih besar dari tanggal akhir.',
                ], 422);
            }
            return redirect()->back()->with('error', 'Tanggal awal tidak boleh lebih besar dari tanggal akhir.');
        }

        if ($request->query('status') === 'Tidak Hadir') {
            $absentRecords = $this->getAbsentRecords($request);
            $page = \Illuminate\Pagination\LengthAwarePaginator::resolveCurrentPage();
            $perPage = 5;
            $currentPageItems = $absentRecords->slice(($page - 1) * $perPage, $perPage)->values();
            
            $attendances = new \Illuminate\Pagination\LengthAwarePaginator(
                $currentPageItems,
                $absentRecords->count(),
                $perPage,
                $page,
                ['path' => \Illuminate\Pagination\LengthAwarePaginator::resolveCurrentPath()]
            );
            $attendances->withQueryString();
        } else {
            $attendances = $this->attendanceQuery($request)
                ->orderByDesc('tanggal_absensi')
                ->orderBy('jam_checkin')
                ->paginate(5)
                ->withQueryString();

            $attendances->getCollection()->transform(function ($attendance) {
                if ($attendance->jam_checkin) {
                    $schedule = \DB::table('jadwal_kerja')
                        ->where('jadwal_id', $attendance->jadwal_id)
                        ->first();
                    if ($schedule && $schedule->jam_masuk) {
                        $checkInTime = Carbon::parse($attendance->jam_checkin)->format('H:i:s');
                        $jamMasukTime = Carbon::parse($schedule->jam_masuk)->format('H:i:s');
                        $attendance->status_kehadiran = ($checkInTime > $jamMasukTime) ? 'Terlambat' : 'Hadir';
                    }
                }
                return $attendance;
            });
        }

        $pegawaiList = Pegawai::where('status', 'Aktif')
            ->whereDoesntHave('akun', function ($q) {
                $q->where('role', 'admin');
            })
            ->orderBy('nama_pegawai')
            ->get(['pegawai_id', 'nama_pegawai']);
        $statusOptions = ['Semua', 'Hadir', 'Tepat Waktu', 'Terlambat', 'Tidak Hadir'];
        $divisions = \App\Models\MasterDivisi::orderBy('nama_divisi')->get(['divisi_id', 'nama_divisi']);

        return view('admin.laporan-kehadiran', [
            'attendances' => $attendances,
            'pegawaiList' => $pegawaiList,
            'statusOptions' => $statusOptions,
            'divisions' => $divisions,
        ]);
    }

    public function exportExcel(Request $request)
    {
        if ($request->filled('start_date') && $request->filled('end_date') && $request->start_date > $request->end_date) {
            return redirect()->back()->with('error', 'Tanggal awal tidak boleh lebih besar dari tanggal akhir.');
        }

        Carbon::setLocale('id');

        if ($request->query('status') === 'Tidak Hadir') {
            $rows = $this->getAbsentRecords($request)->map(function (Attendance $attendance) {
                return [
                    'Nama Anggota' => $attendance->pegawai?->nama_pegawai ?? '-',
                    'Divisi' => $attendance->pegawai?->masterDivisi?->nama_divisi ?? '-',
                    'Tanggal' => $this->formatDate($attendance->tanggal_absensi),
                    'Jam Masuk' => '-',
                    'Jam Keluar' => '-',
                    'Durasi Kehadiran' => '-',
                    'Mode Kerja' => '-',
                    'Lokasi' => '-',
                    'Status' => 'Tidak Hadir',
                ];
            });
        } else {
            $rows = $this->attendanceQuery($request)
                ->get()
                ->map(function (Attendance $attendance) {
                    $status = $attendance->status_kehadiran ?? 'Hadir';
                    if ($attendance->jam_checkin) {
                        $schedule = \DB::table('jadwal_kerja')
                            ->where('jadwal_id', $attendance->jadwal_id)
                            ->first();
                        if ($schedule && $schedule->jam_masuk) {
                            $checkInTime = Carbon::parse($attendance->jam_checkin)->format('H:i:s');
                            $jamMasukTime = Carbon::parse($schedule->jam_masuk)->format('H:i:s');
                            $status = ($checkInTime > $jamMasukTime) ? 'Terlambat' : 'Hadir';
                        }
                    }
                    return [
                        'Nama Anggota' => $attendance->pegawai?->nama_pegawai ?? '-',
                        'Divisi' => $attendance->pegawai?->masterDivisi?->nama_divisi ?? '-',
                        'Tanggal' => $this->formatDate($attendance->tanggal_absensi),
                        'Jam Masuk' => $this->formatTime($attendance->jam_checkin),
                        'Jam Keluar' => $this->formatTime($attendance->jam_checkout),
                        'Durasi Kehadiran' => $this->formatDuration($attendance->jam_checkin, $attendance->jam_checkout),
                        'Mode Kerja' => $attendance->skema_kerja ?? '-',
                        'Lokasi' => $this->formatLocation($attendance->latitude, $attendance->longitude),
                        'Status' => $status,
                    ];
                });
        }

        if ($rows->isEmpty()) {
            return redirect()->back()->with('error', 'Tidak ada data untuk diexport.');
        }
        
        $user = Auth::user();
        
        if ($user && $user->akun_id) {
            logHelpers::record(
                $user->akun_id,
                'Mengekspor laporan kehadiran ke Excel'
            );
        }
        
        $filename = 'laporan-kehadiran-' . date('Ymd_His') . '.xlsx';

        $callback = function () use ($rows) {
            $spreadsheet = new \PhpOffice\PhpSpreadsheet\Spreadsheet();
            $sheet = $spreadsheet->getActiveSheet();
            $sheet->fromArray(array_keys($rows->first()), null, 'A1');

            $rowIndex = 2;
            foreach ($rows as $row) {
                $sheet->fromArray(array_values($row), null, 'A' . $rowIndex++);
            }

            $writer = new \PhpOffice\PhpSpreadsheet\Writer\Xlsx($spreadsheet);
            $writer->save('php://output');
        };

        return new \Symfony\Component\HttpFoundation\StreamedResponse($callback, 200, [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            'Content-Disposition' => 'attachment; filename="' . $filename . '"',
            'Pragma' => 'public',
            'Cache-Control' => 'must-revalidate, post-check=0, pre-check=0',
        ]);
    }

    public function exportCsv(Request $request)
    {
        if ($request->filled('start_date') && $request->filled('end_date') && $request->start_date > $request->end_date) {
            return redirect()->back()->with('error', 'Tanggal awal tidak boleh lebih besar dari tanggal akhir.');
        }

        Carbon::setLocale('id');

        if ($request->query('status') === 'Tidak Hadir') {
            $rows = $this->getAbsentRecords($request)->map(function (Attendance $attendance) {
                return [
                    'Nama Anggota' => $attendance->pegawai?->nama_pegawai ?? '-',
                    'Divisi' => $attendance->pegawai?->masterDivisi?->nama_divisi ?? '-',
                    'Tanggal' => $this->formatDate($attendance->tanggal_absensi),
                    'Jam Masuk' => '-',
                    'Jam Keluar' => '-',
                    'Durasi Kehadiran' => '-',
                    'Mode Kerja' => '-',
                    'Lokasi' => '-',
                    'Status' => 'Tidak Hadir',
                ];
            });
        } else {
            $rows = $this->attendanceQuery($request)
                ->get()
                ->map(function (Attendance $attendance) {
                    $status = $attendance->status_kehadiran ?? 'Hadir';
                    if ($attendance->jam_checkin) {
                        $schedule = \DB::table('jadwal_kerja')
                            ->where('jadwal_id', $attendance->jadwal_id)
                            ->first();
                        if ($schedule && $schedule->jam_masuk) {
                            $checkInTime = Carbon::parse($attendance->jam_checkin)->format('H:i:s');
                            $jamMasukTime = Carbon::parse($schedule->jam_masuk)->format('H:i:s');
                            $status = ($checkInTime > $jamMasukTime) ? 'Terlambat' : 'Hadir';
                        }
                    }
                    return [
                        'Nama Anggota' => $attendance->pegawai?->nama_pegawai ?? '-',
                        'Divisi' => $attendance->pegawai?->masterDivisi?->nama_divisi ?? '-',
                        'Tanggal' => $this->formatDate($attendance->tanggal_absensi),
                        'Jam Masuk' => $this->formatTime($attendance->jam_checkin),
                        'Jam Keluar' => $this->formatTime($attendance->jam_checkout),
                        'Durasi Kehadiran' => $this->formatDuration($attendance->jam_checkin, $attendance->jam_checkout),
                        'Mode Kerja' => $attendance->skema_kerja ?? '-',
                        'Lokasi' => $this->formatLocation($attendance->latitude, $attendance->longitude),
                        'Status' => $status,
                    ];
                });
        }

        if ($rows->isEmpty()) {
            return redirect()->back()->with('error', 'Tidak ada data untuk diexport.');
        }

        $user = Auth::user();

        if ($user && $user->akun_id) {
            logHelpers::record(
                $user->akun_id,
                'Mengekspor laporan kehadiran ke CSV'
            );
        }

        $filename = 'laporan-kehadiran-' . date('Ymd_His') . '.csv';

        $callback = function () use ($rows) {
            $out = fopen('php://output', 'w');
            fwrite($out, "\xEF\xBB\xBF");
            fwrite($out, "sep=;\r\n");
            fputcsv($out, array_keys($rows->first()), ';');

            foreach ($rows as $row) {
                fputcsv($out, array_values($row), ';');
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
        if ($request->filled('start_date') && $request->filled('end_date') && $request->start_date > $request->end_date) {
            return redirect()->back()->with('error', 'Tanggal awal tidak boleh lebih besar dari tanggal akhir.');
        }

        Carbon::setLocale('id');

        if ($request->query('status') === 'Tidak Hadir') {
            $rows = $this->getAbsentRecords($request)->map(function (Attendance $attendance) {
                return [
                    'nama' => $attendance->pegawai?->nama_pegawai ?? '-',
                    'divisi' => $attendance->pegawai?->masterDivisi?->nama_divisi ?? '-',
                    'tanggal' => $this->formatDate($attendance->tanggal_absensi),
                    'jam_masuk' => '-',
                    'jam_keluar' => '-',
                    'durasi' => '-',
                    'mode' => '-',
                    'lokasi' => '-',
                    'status' => 'Tidak Hadir',
                ];
            });
        } else {
            $rows = $this->attendanceQuery($request)
                ->get()
                ->map(function (Attendance $attendance) {
                    $status = $attendance->status_kehadiran ?? 'Hadir';
                    if ($attendance->jam_checkin) {
                        $schedule = \DB::table('jadwal_kerja')
                            ->where('jadwal_id', $attendance->jadwal_id)
                            ->first();
                        if ($schedule && $schedule->jam_masuk) {
                            $checkInTime = Carbon::parse($attendance->jam_checkin)->format('H:i:s');
                            $jamMasukTime = Carbon::parse($schedule->jam_masuk)->format('H:i:s');
                            $status = ($checkInTime > $jamMasukTime) ? 'Terlambat' : 'Hadir';
                        }
                    }
                    return [
                        'nama' => $attendance->pegawai?->nama_pegawai ?? '-',
                        'divisi' => $attendance->pegawai?->masterDivisi?->nama_divisi ?? '-',
                        'tanggal' => $this->formatDate($attendance->tanggal_absensi),
                        'jam_masuk' => $this->formatTime($attendance->jam_checkin),
                        'jam_keluar' => $this->formatTime($attendance->jam_checkout),
                        'durasi' => $this->formatDuration($attendance->jam_checkin, $attendance->jam_checkout),
                        'mode' => $attendance->skema_kerja ?? '-',
                        'lokasi' => $this->formatLocation($attendance->latitude, $attendance->longitude),
                        'status' => $status,
                    ];
                });
        }

        $user = Auth::user();

        if ($user && $user->akun_id) {
            logHelpers::record(
                $user->akun_id,
                'Mengekspor laporan kehadiran ke PDF'
            );
        }

        $pdf = Pdf::loadView('admin.laporan-kehadiran-pdf', [
            'rows' => $rows,
            'generatedAt' => now()->translatedFormat('d F Y H:i'),
        ]);

        $filename = 'laporan-kehadiran-' . now()->format('Ymd-His') . '.pdf';

        return $pdf->download($filename);
    }
}
