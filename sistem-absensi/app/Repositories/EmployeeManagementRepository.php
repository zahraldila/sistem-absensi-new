<?php

namespace App\Repositories;

use App\Models\Akun;
use App\Models\MasterDivisi;
use App\Models\MasterJabatan;
use App\Models\Nfc;
use App\Models\Pegawai;
use App\Models\Role;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

class EmployeeManagementRepository
{
    public function query(): Builder
    {
        $orgId = \App\Helpers\OrganizationHelper::requireActiveOrganization();
        return Pegawai::query()
            ->where('organization_id', $orgId)
            ->with(['akun.roleAkses', 'masterDivisi', 'masterJabatan', 'nfc']);
    }

    public function findByPegawaiId(int $pegawaiId): ?Pegawai
    {
        $orgId = \App\Helpers\OrganizationHelper::requireActiveOrganization();
        return Pegawai::with(['akun.roleAkses', 'masterDivisi', 'masterJabatan', 'nfc'])
            ->where('organization_id', $orgId)
            ->where('pegawai_id', $pegawaiId)
            ->first();
    }

    public function create(array $data): Pegawai
    {
        $data['organization_id'] = \App\Helpers\OrganizationHelper::requireActiveOrganization();
        return Pegawai::create($data);
    }

    public function update(Pegawai $pegawai, array $data): Pegawai
    {
        if ($pegawai->organization_id !== \App\Helpers\OrganizationHelper::requireActiveOrganization()) {
            abort(403, 'Akses ditolak.');
        }
        $pegawai->update($data);

        return $pegawai;
    }

    public function findNfcByPegawaiId(int $pegawaiId): ?Nfc
    {
        return Nfc::where('pegawai_id', $pegawaiId)->first();
    }

    public function createNfc(array $data): Nfc
    {
        return Nfc::create($data);
    }

    public function updateNfc(Nfc $nfc, array $data): Nfc
    {
        $nfc->update($data);

        return $nfc;
    }

    public function deleteNfcByPegawaiId(int $pegawaiId): void
    {
        Nfc::where('pegawai_id', $pegawaiId)->delete();
    }

    public function createAccount(array $data): Akun
    {
        if (isset($data['pegawai_id'])) {
            return Akun::firstOrCreate(
                ['pegawai_id' => $data['pegawai_id']],
                $data
            );
        }

        return Akun::create($data);
    }

    public function updateAccount(Akun $akun, array $data): Akun
    {
        $akun->update($data);

        return $akun;
    }

    public function usernameExists(string $username): bool
    {
        return Akun::where('username', $username)->exists();
    }

    public function getDivisions(): Collection
    {
        $orgId = \App\Helpers\OrganizationHelper::requireActiveOrganization();
        return MasterDivisi::query()
            ->where('organization_id', $orgId)
            ->orderBy('nama_divisi')
            ->get();
    }

    public function getRoles(): Collection
    {
        $orgId = \App\Helpers\OrganizationHelper::requireActiveOrganization();
        return MasterJabatan::query()
            ->where('organization_id', $orgId)
            ->orderBy('nama_jabatan')
            ->get();
    }

    public function getMasterRoles(): Collection
    {
        $orgId = \App\Helpers\OrganizationHelper::requireActiveOrganization();
        return Role::query()
            ->where('organization_id', $orgId)
            ->orderBy('role_id')
            ->get();
    }

    public function getAccountsForExport(array $filters = []): Collection
    {
        $orgId = \App\Helpers\OrganizationHelper::requireActiveOrganization();
        $query = Pegawai::with(['akun', 'masterDivisi', 'masterJabatan'])
            ->where('organization_id', $orgId)
            ->when(!empty($filters['status']), function ($q) use ($filters) {
                $q->where('status', $filters['status']);
            })
            ->when(!empty($filters['divisi_id']), function ($q) use ($filters) {
                $q->where('divisi_id', $filters['divisi_id']);
            })
            ->when(!empty($filters['pegawai_id']), function ($q) use ($filters) {
                $q->where('pegawai_id', $filters['pegawai_id']);
            })
            ->when(!empty($filters['jabatan_id']), function ($q) use ($filters) {
                $q->where('jabatan_id', $filters['jabatan_id']);
            })
            ->orderBy('nama_pegawai');

        return $query->get();
    }

    public function createDivision(array $data): MasterDivisi
    {
        $data['organization_id'] = \App\Helpers\OrganizationHelper::requireActiveOrganization();
        return MasterDivisi::create($data);
    }

    public function createRole(array $data): MasterJabatan
    {
        $data['organization_id'] = \App\Helpers\OrganizationHelper::requireActiveOrganization();
        return MasterJabatan::create($data);
    }
}
