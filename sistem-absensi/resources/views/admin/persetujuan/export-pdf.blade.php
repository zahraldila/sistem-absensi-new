<!doctype html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <title>Export Persetujuan Pengajuan</title>
    <style>
        body { font-family: DejaVu Sans, Arial, Helvetica, sans-serif; font-size: 11px; color: #1f2937; }
        .header { margin-bottom: 12px; }
        .header h1 { font-size: 18px; margin: 0; }
        .header h2 { font-size: 13px; margin: 0; color: #555; }
        .header p { margin: 4px 0 0; color: #6b7280; font-size: 11px; }
        .filters { margin-top: 12px; margin-bottom: 14px; width: 100%; border-collapse: collapse; }
        .filters td { padding: 3px 4px; font-size: 11px; }
        table.data-table { width: 100%; border-collapse: collapse; margin-top: 6px; }
        table.data-table th, table.data-table td { border: 1px solid #ddd; padding: 7px 8px; font-size: 11px; text-align: left; }
        table.data-table th { background: #f8fafc; color: #475569; font-weight: bold; }
        .badge { display: inline-block; padding: 2px 8px; border-radius: 9999px; font-size: 10px; font-weight: 700; }
        .badge-disetujui { background: #dcfce7; color: #166534; }
        .badge-pending { background: #fef3c7; color: #92400e; }
        .badge-ditolak { background: #fee2e2; color: #991b1b; }
        .badge-default { background: #f8fafc; color: #475569; }
    </style>
</head>
<body>
    <div class="header">
        <h1>{{ strtoupper($organizationName ?? 'ORGANISASI') }}</h1>
        <h2>LAPORAN PERSETUJUAN PENGAJUAN</h2>
        <p>Diunduh pada {{ $generatedAt }}</p>
    </div>
    <table class="filters">
        <tbody>
            <tr>
                <td style="width: 15%;"><strong>Status</strong></td>
                <td style="width: 35%;">: {{ $filters['status'] }}</td>
                <td style="width: 15%;"><strong>Tanggal Awal</strong></td>
                <td style="width: 35%;">: {{ $filters['tanggal_awal'] }}</td>
            </tr>
            <tr>
                <td><strong>Jenis Pengajuan</strong></td>
                <td>: {{ $filters['jenis'] }}</td>
                <td><strong>Tanggal Akhir</strong></td>
                <td>: {{ $filters['tanggal_akhir'] }}</td>
            </tr>
            <tr>
                <td><strong>Anggota</strong></td>
                <td colspan="3">: {{ $filters['pegawai'] }}</td>
            </tr>
        </tbody>
    </table>

    <table class="data-table">
        <thead>
            <tr>
                <th style="width: 5%;">No</th>
                <th style="width: 20%;">Nama Anggota</th>
                <th style="width: 15%;">Divisi</th>
                <th style="width: 14%;">Jenis Pengajuan</th>
                <th style="width: 15%;">Tanggal Pengajuan</th>
                <th style="width: 12%;">Status</th>
                <th style="width: 19%;">Keterangan</th>
            </tr>
        </thead>
        <tbody>
        @foreach($rows as $row)
            @php
                $statusLower = strtolower($row['status']);
                $badgeClass = match($statusLower) {
                    'disetujui' => 'badge-disetujui',
                    'pending' => 'badge-pending',
                    'ditolak' => 'badge-ditolak',
                    default => 'badge-default',
                };
            @endphp
            <tr>
                <td>{{ $row['no'] }}</td>
                <td>{{ $row['nama'] }}</td>
                <td>{{ $row['divisi'] }}</td>
                <td>{{ $row['jenis_pengajuan'] }}</td>
                <td>{{ $row['tanggal_pengajuan'] }}</td>
                <td><span class="badge {{ $badgeClass }}">{{ $row['status'] }}</span></td>
                <td>{{ $row['keterangan'] }}</td>
            </tr>
        @endforeach
        </tbody>
    </table>
</body>
</html>
