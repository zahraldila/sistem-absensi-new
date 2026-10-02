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
        <h1>{{ $organization->name ?? 'SISTEM ABSENSI' }}</h1>
        <h2>LAPORAN {{ strtoupper(\App\Helpers\OrganizationHelper::term('member_management')) }}</h2>
    </div>
    <table class="filters">
        <tbody>
            <tr>
                <td><strong>Status</strong></td>
                <td>{{ $filters['status'] }}</td>
                @if(!empty($hasDivision))
                <td><strong>{{ \App\Helpers\OrganizationHelper::term('division') }}</strong></td>
                <td>{{ $filters['divisi'] }}</td>
                @else
                <td></td>
                <td></td>
                @endif
            </tr>
            <tr>
                @if(!empty($hasPosition))
                <td><strong>{{ \App\Helpers\OrganizationHelper::term('position') }}</strong></td>
                <td>{{ $filters['role'] }}</td>
                @else
                <td></td>
                <td></td>
                @endif
                <td><strong>{{ \App\Helpers\OrganizationHelper::term('member') }}</strong></td>
                <td>{{ $filters['pegawai'] }}</td>
            </tr>
        </tbody>
    </table>

    <table>
        <thead>
            <tr>
                <th style="width: 5%;">No</th>
                <th style="width: 20%;">Nama {{ \App\Helpers\OrganizationHelper::term('member') }}</th>
                <th style="width: 14%;">{{ \App\Helpers\OrganizationHelper::term('member_id') }}</th>
                @if(!empty($hasDivision))
                <th style="width: 15%;">{{ \App\Helpers\OrganizationHelper::term('division') }}</th>
                @endif
                @if(!empty($hasPosition))
                <th style="width: 15%;">{{ \App\Helpers\OrganizationHelper::term('position') }}</th>
                @endif
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
                @if(!empty($hasDivision))
                <td>{{ $row['Divisi'] ?? '-' }}</td>
                @endif
                @if(!empty($hasPosition))
                <td>{{ $row['Jabatan'] ?? '-' }}</td>
                @endif
                <td>{{ $row['Status'] }}</td>
                <td>{{ $row['Email'] }}</td>
                <td>{{ $row['No Handphone'] }}</td>
            </tr>
        @endforeach
        </tbody>
    </table>
</body>
</html>
