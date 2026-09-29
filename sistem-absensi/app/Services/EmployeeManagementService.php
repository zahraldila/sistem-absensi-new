<?php

namespace App\Services;

use App\Models\Akun;
use App\Models\Pegawai;
use App\Repositories\EmployeeManagementRepository;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\StreamedResponse;

class EmployeeManagementService
{
    public function __construct(protected EmployeeManagementRepository $repository)
    {
    }

    public function exportAccounts(array $filters, string $format = 'csv')
    {
        $rows = $this->getExportRows($filters);

        if ($rows->isEmpty()) {
            return null;
        }

        return match ($format) {
            'xlsx' => $this->exportXlsx($rows),
            'pdf' => $this->exportPdf($rows, $filters),
            default => $this->exportCsv($rows),
        };
    }

    public function getExportRows(array $filters): Collection
    {
        return $this->repository->getAccountsForExport($filters);
    }

    protected function buildExportRows(Collection $rows): Collection
    {
        return $rows->values()->map(function ($pegawai, $index) {
            return [
                'No' => $index + 1,
                'Nama Pegawai' => $pegawai->nama_pegawai,
                'NIP' => $pegawai->nip ?? '',
                'Employee ID' => $pegawai->pegawai_id ?? '',
                'Divisi' => $pegawai->masterDivisi->nama_divisi ?? '',
                'Jabatan' => $pegawai->masterJabatan->nama_jabatan ?? '',
                'Role Akses' => $pegawai->akun->role ?? '',
                'Status' => $pegawai->status ?? '',
                'Email' => $pegawai->email ?? '',
                'No Handphone' => $pegawai->no_handphone ?? '',
                'Username' => $pegawai->akun->username ?? '',
            ];
        });
    }

    protected function buildCsvRows(Collection $rows): Collection
    {
        return $rows->values()->map(function ($pegawai, $index) {
            return [
                'No' => $index + 1,
                'Nama Pegawai' => $pegawai->nama_pegawai,
                'NIP' => $pegawai->nip ?? '',
                'Employee ID' => $pegawai->pegawai_id ?? '',
                'Email' => $pegawai->email ?? '',
                'No Handphone' => $pegawai->no_handphone ?? '',
                'Divisi' => $pegawai->masterDivisi->nama_divisi ?? '',
                'Jabatan' => $pegawai->masterJabatan->nama_jabatan ?? '',
                'Role Akses' => $pegawai->akun->role ?? '',
                'Status' => $pegawai->status ?? '',
            ];
        });
    }

    protected function exportCsv(Collection $rows)
    {
        $filename = 'manajemen-akun-' . date('Ymd_His') . '.csv';
        $exportRows = $this->buildCsvRows($rows);

        $callback = function () use ($exportRows) {
            $out = fopen('php://output', 'w');
            fwrite($out, "\xEF\xBB\xBF");
            fwrite($out, "sep=;\r\n");
            fputcsv($out, array_keys($exportRows->first()), ';');

            foreach ($exportRows as $row) {
                fputcsv($out, array_values($row), ';');
            }

            fclose($out);
        };

        return new StreamedResponse($callback, 200, [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'Content-Disposition' => 'attachment; filename="' . $filename . '"',
            'Pragma' => 'public',
            'Cache-Control' => 'must-revalidate, post-check=0, pre-check=0',
        ]);
    }

    protected function exportXlsx(Collection $rows)
    {
        $filename = 'manajemen-akun-' . date('Ymd_His') . '.xlsx';
        $exportRows = $this->buildCsvRows($rows);

        $callback = function () use ($exportRows) {
            $spreadsheet = new \PhpOffice\PhpSpreadsheet\Spreadsheet();
            $sheet = $spreadsheet->getActiveSheet();
            $sheet->fromArray(array_keys($exportRows->first()), null, 'A1');

            $rowIndex = 2;
            foreach ($exportRows as $row) {
                $sheet->fromArray(array_values($row), null, 'A' . $rowIndex++);
            }

            $writer = new \PhpOffice\PhpSpreadsheet\Writer\Xlsx($spreadsheet);
            $writer->save('php://output');
        };

        return new StreamedResponse($callback, 200, [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            'Content-Disposition' => 'attachment; filename="' . $filename . '"',
            'Pragma' => 'public',
            'Cache-Control' => 'must-revalidate, post-check=0, pre-check=0',
        ]);
    }

    protected function exportPdf(Collection $rows, array $filters)
    {
        $filename = 'manajemen-akun-' . date('Ymd_His') . '.pdf';
        $exportRows = $this->buildExportRows($rows)->map(function ($row) {
            return [
                'No' => $row['No'],
                'Nama Pegawai' => $row['Nama Pegawai'],
                'NIP' => $row['NIP'],
                'Employee ID' => $row['Employee ID'],
                'Divisi' => $row['Divisi'] ?? $row['Department/Divisi'] ?? '',
                'Jabatan' => $row['Jabatan'] ?? $row['Role'] ?? '',
                'Status' => $row['Status'],
                'Email' => $row['Email'],
                'No Handphone' => $row['No Handphone'],
            ];
        });

        $data = [
            'rows' => $exportRows,
            'filters' => [
                'status' => (!empty($filters['status'])) ? $filters['status'] : 'Semua',
                'divisi' => (!empty($filters['divisi_id'])) ? ($this->repository->getDivisions()->firstWhere('divisi_id', $filters['divisi_id'])->nama_divisi ?? 'Semua') : 'Semua',
                'role' => (!empty($filters['jabatan_id'])) ? ($this->repository->getRoles()->firstWhere('jabatan_id', $filters['jabatan_id'])->nama_jabatan ?? 'Semua') : 'Semua',
                'pegawai' => (!empty($filters['pegawai_id'])) ? ($this->repository->findByPegawaiId($filters['pegawai_id'])->nama_pegawai ?? $filters['pegawai_id']) : 'Semua',
            ],
        ];

        $pdf = app('dompdf.wrapper')->loadView('admin.employee-management.export-pdf', $data)->setPaper('a4', 'landscape');

        return $pdf->download($filename);
    }

    public function listEmployees(?string $search = null): LengthAwarePaginator
    {
        $query = $this->repository->query();

        if ($search) {
            $query->where(function ($q) use ($search) {
                $q->where('nama_pegawai', 'ilike', '%' . $search . '%')
                    ->orWhere('nip', 'ilike', '%' . $search . '%')
                    ->orWhere('email', 'ilike', '%' . $search . '%')
                    ->orWhereHas('masterDivisi', function ($q2) use ($search) {
                        $q2->where('nama_divisi', 'ilike', '%' . $search . '%');
                    })
                    ->orWhereHas('masterJabatan', function ($q2) use ($search) {
                        $q2->where('nama_jabatan', 'ilike', '%' . $search . '%');
                    })
                    ->orWhereHas('akun', function ($q2) use ($search) {
                        $q2->where('role', 'ilike', '%' . $search . '%');
                    });
            });
        }

        return $query->orderBy('nama_pegawai')->paginate(5)->withQueryString();
    }

    public function getEmployee(int $pegawaiId): ?Pegawai
    {
        return $this->repository->findByPegawaiId($pegawaiId);
    }

    public function getFilterOptions(): array
    {
        return [
            'divisions' => $this->repository->getDivisions(),
            'roles' => $this->repository->getRoles(),
            'master_roles' => $this->repository->getMasterRoles(),
        ];
    }

    public function resolveRoleData(mixed $roleInput): array
    {
        $masterRoles = $this->repository->getMasterRoles();

        if ($roleInput === null || (is_string($roleInput) && trim($roleInput) === '')) {
            $memberRole = $masterRoles->first(fn ($role) => $role->isMemberRole());
            if ($memberRole) {
                return ['role_id' => $memberRole->role_id, 'nama_role' => $memberRole->nama_role];
            }
        }

        if (is_numeric($roleInput)) {
            $found = $masterRoles->firstWhere('role_id', (int) $roleInput);
            if ($found) {
                return ['role_id' => $found->role_id, 'nama_role' => $found->nama_role];
            }
        }

        if (is_string($roleInput) && trim($roleInput) !== '') {
            $normalized = trim($roleInput);
            $found = $masterRoles->first(function ($r) use ($normalized) {
                return strcasecmp($r->nama_role, $normalized) === 0;
            });

            if ($found) {
                return ['role_id' => $found->role_id, 'nama_role' => $found->nama_role];
            }
        }

        throw \Illuminate\Validation\ValidationException::withMessages([
            'role' => 'Role akses tidak valid untuk organisasi ini.',
        ]);
    }

    public function createDivision(string $name)
    {
        return $this->repository->createDivision([
            'nama_divisi' => $name,
        ]);
    }

    public function createRole(string $name)
    {
        return $this->repository->createRole([
            'nama_jabatan' => $name,
        ]);
    }

    public function saveEmployee(array $data): array
    {
        return DB::transaction(function () use ($data) {
            $pegawai = $this->repository->create([
                'nip' => $data['nip'] ?? null,
                'nama_pegawai' => $data['nama_pegawai'] ?? null,
                'email' => $data['email'] ?? null,
                'no_handphone' => $data['no_handphone'] ?? null,
                'divisi_id' => $data['divisi_id'] ?? null,
                'jabatan_id' => $data['jabatan_id'] ?? null,
                'status' => $data['status'] ?? 'Aktif',
            ]);

            if (!empty($data['foto_profile_file'])) {
                $fotoPath = $this->uploadProfilePhoto($data['foto_profile_file'], $pegawai->pegawai_id);
                $this->repository->update($pegawai, ['foto_profile' => $fotoPath]);
            }

            $username = !empty($data['username']) ? trim($data['username']) : $this->buildUsername($pegawai);

            if (!empty(trim($data['nfc_id'] ?? ''))) {
                $this->repository->createNfc([
                    'pegawai_id' => $pegawai->pegawai_id,
                    'nfc_serial_number' => trim($data['nfc_id']),
                ]);
            }

            $roleData = $this->resolveRoleData($data['role_id'] ?? $data['role'] ?? null);

            $akunData = [
                'pegawai_id' => $pegawai->pegawai_id,
                'email' => $data['email'] ?? null,
                'username' => $username,
                'password' => Hash::make($data['password'] ?? 'password123'),
                'role_id' => $roleData['role_id'],
                'role' => $roleData['nama_role'],
                'status' => $data['status'] ?? 'Aktif',
            ];

            $akun = $this->repository->createAccount($akunData);

            return ['pegawai' => $pegawai, 'akun' => $akun];
        });
    }

    protected function buildUsername(Pegawai $pegawai): string
    {
        $username = trim((string) $pegawai->nip);

        if ($username === '') {
            $username = 'user-' . Str::random(8);
        }

        return $this->ensureUniqueUsername($username);
    }

    protected function ensureUniqueUsername(string $username): string
    {
        $original = $username;
        $suffix = 0;

        while ($this->repository->usernameExists($username)) {
            $suffix++;
            $username = $original . $suffix;
        }

        return $username;
    }

    public function updateEmployee(Pegawai $pegawai, array $data): array
    {
        $oldPhoto = $pegawai->foto_profile;
        $updateData = [
            'nip' => $data['nip'] ?? $pegawai->nip,
            'nama_pegawai' => $data['nama_pegawai'] ?? $pegawai->nama_pegawai,
            'email' => $data['email'] ?? $pegawai->email,
            'no_handphone' => $data['no_handphone'] ?? $pegawai->no_handphone,
            'divisi_id' => $data['divisi_id'] ?? $pegawai->divisi_id,
            'jabatan_id' => $data['jabatan_id'] ?? $pegawai->jabatan_id,
            'status' => $data['status'] ?? $pegawai->status,
        ];

        if (!empty($data['foto_profile_file'])) {
            $newPhotoPath = $this->uploadProfilePhoto($data['foto_profile_file'], $pegawai->pegawai_id);
            $updateData['foto_profile'] = $newPhotoPath;
        }

        $this->repository->update($pegawai, $updateData);

        if ($pegawai->akun) {
            // Simpan null jika username dikosongkan (kolom sudah nullable di DB).
            // Validasi di controller sudah memastikan minimal email atau username ada.
            $akunUpdate = [
                'username' => trim($data['username'] ?? '') !== '' ? trim($data['username']) : null,
            ];

            if (isset($data['role_id']) || isset($data['role'])) {
                $roleData = $this->resolveRoleData($data['role_id'] ?? $data['role']);
                $akunUpdate['role_id'] = $roleData['role_id'];
                $akunUpdate['role'] = $roleData['nama_role'];
            }

            if (!empty($data['password'])) {
                $akunUpdate['password'] = Hash::make($data['password']);
            }

            $this->repository->updateAccount($pegawai->akun, $akunUpdate);
        } else {
            // Jika pegawai belum memiliki data akun (misal data legacy/import), buat akun baru
            $roleData = $this->resolveRoleData($data['role_id'] ?? $data['role'] ?? 'Anggota');
            $username = trim($data['username'] ?? '') !== '' ? trim($data['username']) : $this->buildUsername($pegawai);

            $akunData = [
                'pegawai_id' => $pegawai->pegawai_id,
                'username'   => $username,
                'password'   => Hash::make($data['password'] ?? 'password123'),
                'role_id'    => $roleData['role_id'],
                'role'       => $roleData['nama_role'],
            ];

            $this->repository->createAccount($akunData);
            $pegawai->load('akun');
        }

        $oldPhoto = $pegawai->foto_profile;
        if (!empty($data['foto_profile_file']) && !empty($oldPhoto) && ($updateData['foto_profile'] ?? null) !== $oldPhoto) {
            try {
                $this->deleteProfilePhoto($oldPhoto);
            } catch (\Exception $e) {
                // Do not block update if cleanup fails.
            }
        }

        $nfcSerial = trim($data['nfc_id'] ?? '');
        $existingNfc = $this->repository->findNfcByPegawaiId($pegawai->pegawai_id);

        if ($nfcSerial !== '') {
            if ($existingNfc) {
                $this->repository->updateNfc($existingNfc, [
                    'nfc_serial_number' => $nfcSerial,
                ]);
            } else {
                $this->repository->createNfc([
                    'pegawai_id' => $pegawai->pegawai_id,
                    'nfc_serial_number' => $nfcSerial,
                ]);
            }
        } elseif ($existingNfc) {
            $this->repository->deleteNfcByPegawaiId($pegawai->pegawai_id);
        }

        return ['pegawai' => $pegawai, 'akun' => $pegawai->akun];
    }

    protected function uploadProfilePhoto($file, int $pegawaiId): string
    {
        $bucket = config('supabase.bucket', 'profile-images');
        $fileName = 'pegawai_' . $pegawaiId . '_' . time() . '_' . Str::random(6) . '.' . $file->getClientOriginalExtension();
        $remotePath = trim($fileName, '/');

        $baseUrl = supabase_url() ?: config('supabase.url');
        $apiKey = supabase_key() ?: config('supabase.key');

        if (!empty($baseUrl) && !empty($apiKey)) {
            $baseUrl = rtrim($baseUrl, '/');
            if (! preg_match('/^https?:\/\//i', $baseUrl)) {
                $baseUrl = 'https://' . ltrim($baseUrl, '/');
            }

            $uploadUrl = $baseUrl . '/storage/v1/object/' . rawurlencode($bucket) . '/' . rawurlencode($remotePath);

            try {
                $resp = Http::timeout(10)->withHeaders([
                    'Authorization' => 'Bearer ' . $apiKey,
                    'apikey' => $apiKey,
                    'Content-Type' => $file->getClientMimeType(),
                    'x-upsert' => 'true',
                ])->withBody(file_get_contents($file->getRealPath()), $file->getClientMimeType())->post($uploadUrl);

                if ($resp->successful() || $resp->status() === 200 || $resp->status() === 201) {
                    return rtrim($bucket, '/') . '/' . $remotePath;
                }
            } catch (\Exception $e) {
                // Fallback to local storage if network or Supabase is unavailable
            }
        }

        // Fallback local storage
        $stored = $file->storeAs('profile-images', $fileName, 'public');
        return 'profile-images/' . $fileName;
    }

    protected function deleteProfilePhoto(string $fotoPath): void
    {
        $bucket = config('supabase.bucket', 'profile-images');
        $baseUrl = supabase_url() ?: config('supabase.url');
        if (! $baseUrl) {
            return;
        }

        $baseUrl = rtrim($baseUrl, '/');
        if (! preg_match('/^https?:\/\//i', $baseUrl)) {
            $baseUrl = 'https://' . ltrim($baseUrl, '/');
        }

        $path = ltrim($fotoPath, '/');
        if (str_starts_with($path, $bucket . '/')) {
            $path = substr($path, strlen($bucket . '/'));
        }

        $deleteUrl = $baseUrl . '/storage/v1/object/' . rawurlencode($bucket) . '/' . implode('/', array_map('rawurlencode', explode('/', $path)));
        $apiKey = supabase_key() ?: config('supabase.key');
        if (! $apiKey) {
            return;
        }

        Http::withHeaders([
            'Authorization' => 'Bearer ' . $apiKey,
            'apikey' => $apiKey,
        ])->delete($deleteUrl);
    }
}
