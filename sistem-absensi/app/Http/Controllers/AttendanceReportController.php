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
        $org   = \App\Helpers\OrganizationHelper::getActiveOrganization();

        $hasDivision = $org ? $org->hasFeature('division') : false;
        $hasWfoWfh   = $org ? $org->hasFeature('wfo_wfh') : false;
        
        $query = Attendance::with($hasDivision ? 'pegawai.masterDivisi' : 'pegawai')
            ->whereHas('pegawai', function ($q) use ($orgId) {
                $q->where('organization_id', $orgId)
                  ->where('status', 'Aktif')
                  ->whereDoesntHave('akun', function ($q2) {
                      $q2->where('role', 'admin');
                  });
            });

        if ($search = trim((string) $request->query('search', ''))) {
            $searchTerm = "%{$search}%";

            $query->where(function ($query) use ($search, $searchTerm, $hasDivision, $hasWfoWfh) {
                $query->whereHas('pegawai', function ($query) use ($searchTerm) {
                    $query->where('nama_pegawai', 'ilike', $searchTerm);
                });

                if ($hasWfoWfh) {
                    $query->orWhere('skema_kerja', 'ilike', $searchTerm);
                }

                $query->orWhere('status_kehadiran', 'ilike', $searchTerm)
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

        // Division filter only applies if division feature is enabled
        if ($hasDivision && ($divisiId = $request->query('divisi_id'))) {
            if ($divisiId !== 'Semua') {
                $query->whereHas('pegawai', function ($q) use ($divisiId) {
                    $q->where('divisi_id', $divisiId);
                });
            }
        }

        // Work mode filter only applies if wfo_wfh feature is enabled
        $modeKerja = $request->query('mode_kerja') ?? $request->query('work_mode');
        if ($hasWfoWfh && $modeKerja) {
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
        $orgId = \App\Helpers\OrganizationHelper::requireActiveOrganization();
        $org   = \App\Helpers\OrganizationHelper::getActiveOrganization();

        $hasDivision = $org ? $org->hasFeature('division') : false;
        $hasWfoWfh   = $org ? $org->hasFeature('wfo_wfh') : false;

        $modeKerja = $request->query('mode_kerja') ?? $request->query('work_mode');
        if ($hasWfoWfh && $modeKerja && $modeKerja !== 'Semua') {
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
        $divisiId = $hasDivision ? $request->query('divisi_id') : null;
        $pegawaiId = $request->query('pegawai_id');
        $search = trim((string) $request->query('search', ''));
        $searchTerm = "%{$search}%";

        foreach ($dates as $date) {
            $query = Pegawai::with($hasDivision ? 'masterDivisi' : [])
                ->where('organization_id', $orgId)
                ->whereDoesntHave('absensi', function ($q) use ($date) {
                    $q->whereDate('tanggal_absensi', $date);
                });

            if ($hasDivision && $divisiId && $divisiId !== 'Semua') {
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
        $org = \App\Helpers\OrganizationHelper::getActiveOrganization();
        $hasDivision = $org ? $org->hasFeature('division') : false;
        $hasWfoWfh = $org ? $org->hasFeature('wfo_wfh') : false;

        $divisions = $hasDivision
            ? \App\Models\MasterDivisi::orderBy('nama_divisi')->get(['divisi_id', 'nama_divisi'])
            : collect();

        return view('admin.laporan-kehadiran', [
            'attendances' => $attendances,
            'pegawaiList' => $pegawaiList,
            'statusOptions' => $statusOptions,
            'divisions' => $divisions,
            'hasDivision' => $hasDivision,
            'hasWfoWfh' => $hasWfoWfh,
        ]);
    }

    public function exportExcel(Request $request)
    {
        if ($request->filled('start_date') && $request->filled('end_date') && $request->start_date > $request->end_date) {
            return redirect()->back()->with('error', 'Tanggal awal tidak boleh lebih besar dari tanggal akhir.');
        }

        Carbon::setLocale('id');

        $org         = \App\Helpers\OrganizationHelper::getActiveOrganization();
        $hasDivision = $org ? $org->hasFeature('division') : false;
        $hasWfoWfh   = $org ? $org->hasFeature('wfo_wfh') : false;

        $mapRow = function (Attendance $attendance, string $status, ?string $checkin, ?string $checkout) use ($hasDivision, $hasWfoWfh) {
            $row = [
                'Nama Anggota' => $attendance->pegawai?->nama_pegawai ?? '-',
            ];

            if ($hasDivision) {
                $row['Divisi'] = $attendance->pegawai?->masterDivisi?->nama_divisi ?? '-';
            }

            $row['Tanggal'] = $this->formatDate($attendance->tanggal_absensi);
            $row['Jam Masuk'] = $checkin ? $this->formatTime($checkin) : '-';
            $row['Jam Keluar'] = $checkout ? $this->formatTime($checkout) : '-';
            $row['Durasi Kehadiran'] = ($checkin && $checkout) ? $this->formatDuration($checkin, $checkout) : '-';

            if ($hasWfoWfh) {
                $row['Mode Kerja'] = $attendance->skema_kerja ?? '-';
            }

            $row['Lokasi'] = $this->formatLocation($attendance->latitude, $attendance->longitude);
            $row['Status'] = $status;

            return $row;
        };

        if ($request->query('status') === 'Tidak Hadir') {
            $rows = $this->getAbsentRecords($request)->map(function (Attendance $attendance) use ($mapRow) {
                return $mapRow($attendance, 'Tidak Hadir', null, null);
            });
        } else {
            $rows = $this->attendanceQuery($request)
                ->get()
                ->map(function (Attendance $attendance) use ($mapRow) {
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
                    return $mapRow($attendance, $status, $attendance->jam_checkin, $attendance->jam_checkout);
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

        $org         = \App\Helpers\OrganizationHelper::getActiveOrganization();
        $hasDivision = $org ? $org->hasFeature('division') : false;
        $hasWfoWfh   = $org ? $org->hasFeature('wfo_wfh') : false;

        $mapRow = function (Attendance $attendance, string $status, ?string $checkin, ?string $checkout) use ($hasDivision, $hasWfoWfh) {
            $row = [
                'Nama Anggota' => $attendance->pegawai?->nama_pegawai ?? '-',
            ];

            if ($hasDivision) {
                $row['Divisi'] = $attendance->pegawai?->masterDivisi?->nama_divisi ?? '-';
            }

            $row['Tanggal'] = $this->formatDate($attendance->tanggal_absensi);
            $row['Jam Masuk'] = $checkin ? $this->formatTime($checkin) : '-';
            $row['Jam Keluar'] = $checkout ? $this->formatTime($checkout) : '-';
            $row['Durasi Kehadiran'] = ($checkin && $checkout) ? $this->formatDuration($checkin, $checkout) : '-';

            if ($hasWfoWfh) {
                $row['Mode Kerja'] = $attendance->skema_kerja ?? '-';
            }

            $row['Lokasi'] = $this->formatLocation($attendance->latitude, $attendance->longitude);
            $row['Status'] = $status;

            return $row;
        };

        if ($request->query('status') === 'Tidak Hadir') {
            $rows = $this->getAbsentRecords($request)->map(function (Attendance $attendance) use ($mapRow) {
                return $mapRow($attendance, 'Tidak Hadir', null, null);
            });
        } else {
            $rows = $this->attendanceQuery($request)
                ->get()
                ->map(function (Attendance $attendance) use ($mapRow) {
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
                    return $mapRow($attendance, $status, $attendance->jam_checkin, $attendance->jam_checkout);
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

        $org         = \App\Helpers\OrganizationHelper::getActiveOrganization();
        $hasDivision = $org ? $org->hasFeature('division') : false;
        $hasWfoWfh   = $org ? $org->hasFeature('wfo_wfh') : false;

        $mapPdfRow = function (Attendance $attendance, string $status, ?string $checkin, ?string $checkout) use ($hasDivision, $hasWfoWfh) {
            $row = [
                'nama' => $attendance->pegawai?->nama_pegawai ?? '-',
            ];

            if ($hasDivision) {
                $row['divisi'] = $attendance->pegawai?->masterDivisi?->nama_divisi ?? '-';
            }

            $row['tanggal'] = $this->formatDate($attendance->tanggal_absensi);
            $row['jam_masuk'] = $checkin ? $this->formatTime($checkin) : '-';
            $row['jam_keluar'] = $checkout ? $this->formatTime($checkout) : '-';
            $row['durasi'] = ($checkin && $checkout) ? $this->formatDuration($checkin, $checkout) : '-';

            if ($hasWfoWfh) {
                $row['mode'] = $attendance->skema_kerja ?? '-';
            }

            $row['lokasi'] = $this->formatLocation($attendance->latitude, $attendance->longitude);
            $row['status'] = $status;

            return $row;
        };

        if ($request->query('status') === 'Tidak Hadir') {
            $rows = $this->getAbsentRecords($request)->map(function (Attendance $attendance) use ($mapPdfRow) {
                return $mapPdfRow($attendance, 'Tidak Hadir', null, null);
            });
        } else {
            $rows = $this->attendanceQuery($request)
                ->get()
                ->map(function (Attendance $attendance) use ($mapPdfRow) {
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
                    return $mapPdfRow($attendance, $status, $attendance->jam_checkin, $attendance->jam_checkout);
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
            'rows'        => $rows,
            'generatedAt' => now()->translatedFormat('d F Y H:i'),
            'hasDivision' => $hasDivision,
            'hasWfoWfh'   => $hasWfoWfh,
        ]);

        $filename = 'laporan-kehadiran-' . now()->format('Ymd-His') . '.pdf';

        return $pdf->download($filename);
    }
}
