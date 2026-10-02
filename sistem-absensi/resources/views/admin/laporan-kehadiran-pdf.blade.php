<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>Laporan Kehadiran</title>
    <style>
        body { font-family: Arial, Helvetica, sans-serif; color: #1f2937; }
        .header { margin-bottom: 24px; }
        .header h1 { margin: 0; font-size: 20px; }
        .header p { margin: 4px 0 0; color: #6b7280; font-size: 12px; }
        table { width: 100%; border-collapse: collapse; margin-top: 16px; }
        th, td { border: 1px solid #e5e7eb; padding: 10px; text-align: left; font-size: 12px; }
        th { background: #f8fafc; color: #475569; }
        .badge { display: inline-block; padding: 4px 10px; border-radius: 9999px; font-size: 10px; font-weight: 700; }
        .badge-hadir { background: #dcfce7; color: #166534; }
        .badge-terlambat { background: #fef3c7; color: #92400e; }
        .badge-tidak-hadir { background: #fee2e2; color: #991b1b; }
        .badge-default { background: #f8fafc; color: #475569; }
    </style>
</head>
<body>
    <div class="header">
        <h1>Laporan Kehadiran</h1>
        <p>Diunduh pada {{ $generatedAt }}</p>
    </div>

    <table>
        <thead>
            <tr>
                <th>Nama</th>
                @if($hasDivision ?? true)
                    <th>Divisi</th>
                @endif
                <th>Tanggal</th>
                <th>Jam Masuk</th>
                <th>Jam Keluar</th>
                <th>Durasi</th>
                @if($hasWfoWfh ?? true)
                    <th>Mode</th>
                @endif
                <th>Lokasi</th>
                <th>Status</th>
            </tr>
        </thead>
        <tbody>
            @foreach($rows as $row)
                <tr>
                    <td>{{ $row['nama'] }}</td>
                    @if($hasDivision ?? true)
                        <td>{{ $row['divisi'] ?? '-' }}</td>
                    @endif
                    <td>{{ $row['tanggal'] }}</td>
                    <td>{{ $row['jam_masuk'] }}</td>
                    <td>{{ $row['jam_keluar'] }}</td>
                    <td>{{ $row['durasi'] }}</td>
                    @if($hasWfoWfh ?? true)
                        <td>{{ $row['mode'] ?? '-' }}</td>
                    @endif
                    <td>{{ $row['lokasi'] }}</td>
                    <td>
                        @php
                            $normalized = strtolower($row['status']);
                            if (str_contains($normalized, 'hadir')) {
                                $class = 'badge-hadir';
                            } elseif (str_contains($normalized, 'terlambat')) {
                                $class = 'badge-terlambat';
                            } elseif (str_contains($normalized, 'tidak hadir')) {
                                $class = 'badge-tidak-hadir';
                            } else {
                                $class = 'badge-default';
                            }
                        @endphp
                        <span class="badge {{ $class }}">{{ $row['status'] }}</span>
                    </td>
                </tr>
            @endforeach
        </tbody>
    </table>
</body>
</html>
