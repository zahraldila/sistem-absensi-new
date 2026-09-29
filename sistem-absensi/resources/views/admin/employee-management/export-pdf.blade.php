<!doctype html>
<html>
<head>
    <meta charset="utf-8">
    <title>Export Manajemen Akun</title>
    <style>
        body { font-family: DejaVu Sans, sans-serif; font-size: 12px; }
        .header { margin-bottom: 12px; }
        .header h1 { font-size: 18px; margin: 0; }
        .header h2 { font-size: 14px; margin: 0; color: #555; }
        .filters { margin-top: 16px; margin-bottom: 16px; }
        .filters td { padding: 4px 6px; }
        table { width: 100%; border-collapse: collapse; }
        th, td { border: 1px solid #ddd; padding: 8px; }
        th { background: #f4f4f4; text-align: left; }
    </style>
</head>
<body>
    <div class="header">
        <h1>PT SELADA INDONESIA PRODUKTIF</h1>
        <h2>LAPORAN MANAJEMEN AKUN</h2>
    </div>
    <table class="filters">
        <tbody>
            <tr>
                <td><strong>Status</strong></td>
                <td>{{ $filters['status'] }}</td>
                <td><strong>Divisi</strong></td>
                <td>{{ $filters['divisi'] }}</td>
            </tr>
            <tr>
                <td><strong>Jabatan</strong></td>
                <td>{{ $filters['role'] }}</td>
                <td><strong>Anggota</strong></td>
                <td>{{ $filters['pegawai'] }}</td>
            </tr>
        </tbody>
    </table>

    <table>
        <thead>
            <tr>
                <th style="width: 5%;">No</th>
                <th style="width: 18%;">Nama Anggota</th>
                <th style="width: 12%;">NIP</th>
                <th style="width: 12%;">Employee ID</th>
                <th style="width: 18%;">Divisi</th>
                <th style="width: 12%;">Jabatan</th>
                <th style="width: 10%;">Status</th>
                <th style="width: 18%;">Email</th>
                <th style="width: 15%;">No Handphone</th>
            </tr>
        </thead>
        <tbody>
        @foreach($rows as $row)
            <tr>
                <td>{{ $row['No'] }}</td>
                <td>{{ $row['Nama Anggota'] }}</td>
                <td>{{ $row['NIP'] }}</td>
                <td>{{ $row['Employee ID'] }}</td>
                <td>{{ $row['Divisi'] ?? $row['Department'] }}</td>
                <td>{{ $row['Jabatan'] ?? $row['Role'] }}</td>
                <td>{{ $row['Status'] }}</td>
                <td>{{ $row['Email'] }}</td>
                <td>{{ $row['No Handphone'] }}</td>
            </tr>
        @endforeach
        </tbody>
    </table>
</body>
</html>
