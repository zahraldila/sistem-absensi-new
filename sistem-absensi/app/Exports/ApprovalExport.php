<?php

namespace App\Exports;

use App\Helpers\OrganizationHelper;
use App\Models\Approval;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Maatwebsite\Excel\Concerns\FromQuery;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;

class ApprovalExport implements FromQuery, WithHeadings, WithMapping
{
    protected array $filters;

    public function __construct(array $filters = [])
    {
        $this->filters = $filters;
    }

    public function query(): Builder
    {
        $orgId = $this->filters['organization_id'] ?? OrganizationHelper::getActiveOrganizationId();
        $org = OrganizationHelper::active();
        $hasWfoWfh = $org?->hasFeature('wfo_wfh') ?? false;

        $query = Approval::query()
            ->with(['pegawai.masterDivisi', 'pegawai.masterJabatan']);

        if ($orgId) {
            $query->whereHas('pegawai', function ($q) use ($orgId) {
                $q->where('organization_id', $orgId);
            });
        }

        if (!$hasWfoWfh) {
            $query->whereNotIn('jenis_pengajuan', ['WFH', 'WFC', 'Dinas']);
        }

        if (!empty($this->filters['tanggal_awal'])) {
            $query->whereDate(
                'tanggal_pengajuan',
                '>=',
                $this->filters['tanggal_awal']
            );
        }

        if (!empty($this->filters['tanggal_akhir'])) {
            $query->whereDate(
                'tanggal_pengajuan',
                '<=',
                $this->filters['tanggal_akhir']
            );
        }

        if (!empty($this->filters['status']) && $this->filters['status'] !== 'Semua') {
            $statusVal = strtolower(trim($this->filters['status']));
            if ($statusVal === 'pending' || $statusVal === 'menunggu') {
                $query->whereIn('status_pengajuan', ['Pending', 'Menunggu']);
            } elseif ($statusVal === 'disetujui') {
                $query->where('status_pengajuan', 'Disetujui');
            } elseif ($statusVal === 'ditolak') {
                $query->where('status_pengajuan', 'Ditolak');
            } else {
                $query->where('status_pengajuan', $this->filters['status']);
            }
        }

        if (!empty($this->filters['pegawai_id']) && $this->filters['pegawai_id'] !== 'Semua') {
            $query->where(
                'pegawai_id',
                $this->filters['pegawai_id']
            );
        }

        if (!empty($this->filters['jenis_pengajuan']) && $this->filters['jenis_pengajuan'] !== 'Semua') {
            if (!$hasWfoWfh && in_array($this->filters['jenis_pengajuan'], ['WFH', 'WFC', 'Dinas'], true)) {
                $query->whereRaw('1 = 0');
            } else {
                $query->where(
                    'jenis_pengajuan',
                    $this->filters['jenis_pengajuan']
                );
            }
        }

        return $query->orderByDesc('tanggal_pengajuan');
    }

    public function headings(): array
    {
        $org = OrganizationHelper::active();
        $hasDivision = $org?->hasFeature('division') ?? false;
        $memberTerm = OrganizationHelper::term('member', 'Anggota');
        $divisionTerm = OrganizationHelper::term('division', 'Divisi');

        $headings = [
            'No',
            'Nama ' . $memberTerm,
        ];

        if ($hasDivision) {
            $headings[] = $divisionTerm;
        }

        $headings[] = 'Jenis Pengajuan';
        $headings[] = 'Tanggal Pengajuan';
        $headings[] = 'Status';
        $headings[] = 'Keterangan';

        return $headings;
    }

    public function map($approval): array
    {
        static $no = 0;

        $no++;

        $org = OrganizationHelper::active();
        $hasDivision = $org?->hasFeature('division') ?? false;

        $formattedDate = $approval->tanggal_pengajuan;
        if ($formattedDate) {
            try {
                $formattedDate = Carbon::parse($approval->tanggal_pengajuan)->translatedFormat('d F Y');
            } catch (\Throwable $e) {
                $formattedDate = $approval->tanggal_pengajuan;
            }
        } else {
            $formattedDate = '-';
        }

        $statusDisplay = in_array($approval->status_pengajuan, ['Pending', 'Menunggu'], true)
            ? 'Pending'
            : ($approval->status_pengajuan ?? '-');

        $row = [
            $no,
            $approval->pegawai?->nama_pegawai ?? '-',
        ];

        if ($hasDivision) {
            $row[] = $approval->pegawai?->masterDivisi?->nama_divisi ?? $approval->pegawai?->jabatan ?? '-';
        }

        $row[] = $approval->jenis_pengajuan ?? '-';
        $row[] = $formattedDate;
        $row[] = $statusDisplay;
        $row[] = $approval->keterangan ?? '-';

        return $row;
    }
}