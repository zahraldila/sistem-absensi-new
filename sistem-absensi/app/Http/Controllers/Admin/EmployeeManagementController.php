<?php

namespace App\Http\Controllers\Admin;

use App\Helpers\logHelpers;
use App\Http\Controllers\Controller;
use App\Models\Pegawai;
use App\Services\EmployeeManagementService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;
use App\Helpers\OrganizationHelper;

class EmployeeManagementController extends Controller
{
    public function __construct(protected EmployeeManagementService $service)
    {
    }

    public function index(Request $request)
    {
        $search = $request->get('search');
        $employees = $this->service->listEmployees($search);
        $filters = $this->service->getFilterOptions();

        return view('admin.employee-management.index', compact('employees', 'filters', 'search'));
    }

    public function create()
    {
        $filters = $this->service->getFilterOptions();

        return view('admin.employee-management.create', compact('filters'));
    }

    public function store(Request $request)
    {
        $orgId = OrganizationHelper::requireActiveOrganization();
        $org = OrganizationHelper::active();
        $hasDivision = $org?->hasFeature('division') ?? false;
        $hasPosition = $org?->hasFeature('position') ?? false;

        $nipTerm = OrganizationHelper::term('member_id', 'NIP');
        $memberTerm = OrganizationHelper::term('member', 'Anggota');

        $rules = [
            'nip' => [
                'required', 'string', 'max:50',
                Rule::unique('pegawai', 'nip')->where('organization_id', $orgId)
            ],
            'nama_pegawai' => 'required|string|max:255',
            'nfc_id' => [
                'nullable', 'string', 'max:255',
                Rule::unique('nfc', 'nfc_serial_number'),
            ],
            'email' => [
                'nullable', 'email', 'max:255',
                Rule::unique('pegawai', 'email')->where('organization_id', $orgId)
            ],
            'no_handphone' => 'nullable|string|regex:/^[0-9]+$/|max:20',
            'foto_profile' => 'nullable|file|image|mimes:jpg,jpeg,png|max:2048',
            'role_id' => [
                'nullable', 'integer',
                Rule::exists('role', 'role_id')->where('organization_id', $orgId)
            ],
            'role' => [
                'required', 'string', 'max:50',
                Rule::exists('role', 'nama_role')->where('organization_id', $orgId),
            ],
            'username' => ['nullable', 'string', 'max:100', 'unique:akun,username'],
            'password' => 'required|string|min:6|confirmed',
            'status' => 'nullable|string|max:50',
        ];

        if ($hasDivision) {
            $rules['divisi_id'] = [
                'nullable', 'integer',
                Rule::exists('master_divisi', 'divisi_id')->where('organization_id', $orgId)
            ];
        }

        if ($hasPosition) {
            $rules['jabatan_id'] = [
                'nullable', 'integer',
                Rule::exists('master_jabatan', 'jabatan_id')->where('organization_id', $orgId)
            ];
        }

        $messages = [
            'nama_pegawai.required' => "Nama lengkap {$memberTerm} wajib diisi.",
            'nama_pegawai.max' => "Nama lengkap {$memberTerm} tidak boleh lebih dari 255 karakter.",
            'nip.required' => "{$nipTerm} wajib diisi.",
            'nip.unique' => "{$nipTerm} sudah terdaftar.",
            'nip.max' => "{$nipTerm} tidak boleh lebih dari 50 karakter.",
            'nfc_id.unique' => "UID NFC sudah terdaftar pada {$memberTerm} lain.",
            'email.email' => 'Format email tidak valid.',
            'email.unique' => "Email sudah digunakan oleh {$memberTerm} lain.",
            'no_handphone.regex' => 'Format nomor handphone tidak valid.',
            'no_handphone.max' => 'Nomor handphone tidak boleh lebih dari 20 karakter.',
            'username.unique' => 'Username sudah digunakan.',
            'username.max' => 'Username tidak boleh lebih dari 100 karakter.',
            'password.required' => 'Password wajib diisi.',
            'password.min' => 'Password minimal 6 karakter.',
            'password.confirmed' => 'Konfirmasi password tidak cocok.',
            'role.required' => 'Role Akses wajib dipilih.',
            'foto_profile.file' => 'Foto profil harus berupa file yang valid.',
            'foto_profile.image' => 'File harus berupa gambar.',
            'foto_profile.mimes' => 'Format foto harus berupa JPG, JPEG, atau PNG.',
            'foto_profile.max' => 'Ukuran foto maksimal 2MB.',
        ];

        $data = $request->validate($rules, $messages);

        if (! $hasDivision) {
            $data['divisi_id'] = null;
        }

        if (! $hasPosition) {
            $data['jabatan_id'] = null;
        }

        // Minimal salah satu Email atau Username wajib diisi agar pegawai bisa login.
        if (empty(trim($data['email'] ?? '')) && empty(trim($data['username'] ?? ''))) {
            return back()
                ->withInput()
                ->withErrors([
                    'email' => 'Email atau Username wajib diisi (minimal salah satu) agar anggota dapat login.',
                ]);
        }

        // Include uploaded file instance for the service
        $data['foto_profile_file'] = $request->file('foto_profile');

        // Simpan data pegawai
        $result = $this->service->saveEmployee($data);

        // Catat aktivitas setelah proses berhasil
        $user = Auth::user();

        if ($user && $user->akun_id) {
            logHelpers::record(
                $user->akun_id,
                "Menambahkan data anggota: {$result['pegawai']->nama_pegawai}"
            );
        }

        return redirect()
            ->route('admin.employee-management.index')
            ->with('success', 'Akun anggota berhasil ditambahkan.');
    }

    public function edit(Pegawai $pegawai)
    {
        // Verify ownership
        if ($pegawai->organization_id !== OrganizationHelper::requireActiveOrganization()) {
            abort(403, 'Akses ditolak.');
        }

        $employee = $this->service->getEmployee($pegawai->pegawai_id);
        $filters = $this->service->getFilterOptions();

        return view('admin.employee-management.edit', compact('employee', 'filters'));
    }

    public function update(Request $request, Pegawai $pegawai)
    {
        $orgId = OrganizationHelper::requireActiveOrganization();
        $org = OrganizationHelper::active();
        $hasDivision = $org?->hasFeature('division') ?? false;
        $hasPosition = $org?->hasFeature('position') ?? false;
        
        // Verify ownership
        if ($pegawai->organization_id !== $orgId) {
            abort(403, 'Akses ditolak.');
        }

        $nipTerm = OrganizationHelper::term('member_id', 'NIP');
        $memberTerm = OrganizationHelper::term('member', 'Anggota');

        $rules = [
            'nip' => [
                'nullable', 'string', 'max:50',
                Rule::unique('pegawai', 'nip')
                    ->where('organization_id', $orgId)
                    ->ignore($pegawai->pegawai_id, 'pegawai_id')
            ],
            'nama_pegawai' => 'required|string|max:255',
            'nfc_id' => [
                'nullable', 'string', 'max:255',
                Rule::unique('nfc', 'nfc_serial_number')
                    ->ignore($pegawai->nfc?->nfc_id, 'nfc_id'),
            ],
            'email' => [
                'nullable', 'email', 'max:255',
                Rule::unique('pegawai', 'email')
                    ->where('organization_id', $orgId)
                    ->ignore($pegawai->pegawai_id, 'pegawai_id')
            ],
            'no_handphone' => 'nullable|string|regex:/^[0-9]+$/|max:20',
            'foto_profile' => 'nullable|file|image|mimes:jpg,jpeg,png|max:2048',
            'role_id' => [
                'nullable', 'integer',
                Rule::exists('role', 'role_id')->where('organization_id', $orgId)
            ],
            'role' => [
                'nullable', 'string', 'max:50',
                Rule::exists('role', 'nama_role')->where('organization_id', $orgId),
            ],
            'username' => [
                'nullable',
                'string',
                'max:100',
                Rule::unique('akun', 'username')
                    ->ignore($pegawai->akun?->id, 'id')
            ],
            'password' => 'nullable|string|min:6|confirmed',
            'status' => 'nullable|string|max:50',
        ];

        if ($hasDivision) {
            $rules['divisi_id'] = [
                'nullable', 'integer',
                Rule::exists('master_divisi', 'divisi_id')->where('organization_id', $orgId)
            ];
        }

        if ($hasPosition) {
            $rules['jabatan_id'] = [
                'nullable', 'integer',
                Rule::exists('master_jabatan', 'jabatan_id')->where('organization_id', $orgId)
            ];
        }

        $messages = [
            'nama_pegawai.required' => "Nama lengkap {$memberTerm} wajib diisi.",
            'nama_pegawai.max' => "Nama lengkap {$memberTerm} tidak boleh lebih dari 255 karakter.",
            'nip.unique' => "{$nipTerm} sudah terdaftar.",
            'nip.max' => "{$nipTerm} tidak boleh lebih dari 50 karakter.",
            'nfc_id.unique' => "UID NFC sudah terdaftar pada {$memberTerm} lain.",
            'email.email' => 'Format email tidak valid.',
            'email.unique' => "Email sudah digunakan oleh {$memberTerm} lain.",
            'no_handphone.regex' => 'Format nomor handphone tidak valid.',
            'no_handphone.max' => 'Nomor handphone tidak boleh lebih dari 20 karakter.',
            'username.unique' => 'Username sudah digunakan.',
            'username.max' => 'Username tidak boleh lebih dari 100 karakter.',
            'password.min' => 'Password minimal 6 karakter.',
            'password.confirmed' => 'Konfirmasi password tidak cocok.',
            'foto_profile.file' => 'Foto profil harus berupa file yang valid.',
            'foto_profile.image' => 'File harus berupa gambar.',
            'foto_profile.mimes' => 'Format foto harus berupa JPG, JPEG, atau PNG.',
            'foto_profile.max' => 'Ukuran foto maksimal 2MB.',
        ];

        $data = $request->validate($rules, $messages);

        if (! $hasDivision) {
            $data['divisi_id'] = null;
        }

        if (! $hasPosition) {
            $data['jabatan_id'] = null;
        }

        $data['foto_profile_file'] = $request->file('foto_profile');

        // Minimal salah satu Email atau Username wajib diisi agar pegawai bisa login.
        if (empty(trim($data['email'] ?? '')) && empty(trim($data['username'] ?? ''))) {
            return back()
                ->withInput()
                ->withErrors([
                    'email'    => "Email atau Username wajib diisi (minimal salah satu) agar {$memberTerm} dapat login.",
                    'username' => "Email atau Username wajib diisi (minimal salah satu) agar {$memberTerm} dapat login.",
                ]);
        }

        // Update data pegawai
        $result = $this->service->updateEmployee($pegawai, $data);

        // Catat aktivitas setelah proses berhasil
        $user = Auth::user();

        if ($user && $user->akun_id) {
            logHelpers::record(
                $user->akun_id,
                "Memperbarui data {$memberTerm}: {$result['pegawai']->nama_pegawai}"
            );
        }

        return redirect()
            ->route('admin.employee-management.index')
            ->with('success', "Akun {$memberTerm} berhasil diperbarui.");
    }

    public function storeDivision(Request $request)
    {
        $orgId = OrganizationHelper::requireActiveOrganization();
        $org = OrganizationHelper::active();
        if (! $org?->hasFeature('division')) {
            abort(404, 'Fitur divisi tidak aktif untuk organisasi ini.');
        }

        $divisionTerm = OrganizationHelper::term('division', 'Divisi');
        
        $data = $request->validate([
            'nama_divisi' => [
                'required', 'string', 'max:255',
                Rule::unique('master_divisi', 'nama_divisi')->where('organization_id', $orgId)
            ],
        ], [
            'nama_divisi.required' => "Nama {$divisionTerm} wajib diisi.",
            'nama_divisi.unique' => "Nama {$divisionTerm} sudah ada.",
            'nama_divisi.max' => "Nama {$divisionTerm} tidak boleh lebih dari 255 karakter.",
        ]);

        // Simpan divisi
        $division = $this->service->createDivision($data['nama_divisi']);

        // Catat aktivitas setelah proses berhasil
        $user = Auth::user();

        if ($user && $user->akun_id) {
            logHelpers::record(
                $user->akun_id,
                "Menambahkan {$divisionTerm} baru: {$division->nama_divisi}"
            );
        }

        return response()->json([
            'message' => "{$divisionTerm} berhasil ditambahkan.",
            'division' => $division,
        ], 201);
    }

    public function storeRole(Request $request)
    {
        $orgId = OrganizationHelper::requireActiveOrganization();
        $org = OrganizationHelper::active();
        if (! $org?->hasFeature('position')) {
            abort(404, 'Fitur jabatan tidak aktif untuk organisasi ini.');
        }

        $positionTerm = OrganizationHelper::term('position', 'Jabatan');
        
        $data = $request->validate([
            'nama_jabatan' => [
                'required', 'string', 'max:255',
                Rule::unique('master_jabatan', 'nama_jabatan')->where('organization_id', $orgId)
            ],
        ], [
            'nama_jabatan.required' => "Nama {$positionTerm} wajib diisi.",
            'nama_jabatan.unique' => "Nama {$positionTerm} sudah ada.",
            'nama_jabatan.max' => "Nama {$positionTerm} tidak boleh lebih dari 255 karakter.",
        ]);

        // Simpan jabatan
        $role = $this->service->createRole($data['nama_jabatan']);

        // Catat aktivitas setelah proses berhasil
        $user = Auth::user();

        if ($user && $user->akun_id) {
            logHelpers::record(
                $user->akun_id,
                "Menambahkan {$positionTerm} baru: {$role->nama_jabatan}"
            );
        }

        return response()->json([
            'message' => "{$positionTerm} berhasil ditambahkan.",
            'role' => $role,
        ], 201);
    }
}