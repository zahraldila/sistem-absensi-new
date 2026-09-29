<?php

namespace App\Http\Controllers;

use App\Helpers\logHelpers;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use App\Helpers\OrganizationHelper;

class AdminPlaceholderController extends Controller
{
    public function dashboard()
    {
        return redirect()->route('admin.dashboard');
    }

    public function laporanKehadiran()
    {
        return view('admin.placeholder', ['title' => 'Laporan Kehadiran']);
    }

    public function manajemenAkun()
    {
        return view('admin.placeholder', ['title' => 'Manajemen Akun']);
    }

    public function persetujuan()
    {
        return view('admin.placeholder', ['title' => 'Persetujuan']);
    }

    public function logAktivitas()
    {
        return view('admin.placeholder', ['title' => 'Log Aktivitas']);
    }

    public function tampilanBranding(Request $request)
    {
        try {
            $orgId = OrganizationHelper::requireActiveOrganization();
            
            $savedColor = \App\Models\Setting::get('primary_color', '#123D91');
            $savedLogo  = company_logo_url();
            $organizationName = \App\Models\Organization::where('organization_id', $orgId)->value('nama_organisasi');
            $organizationInitials = getInitials($organizationName);
            $activeTab  = $request->query('tab', 'branding');

            // ── Tahap 4B: Proteksi per-tab Settings ──────────────────────────
            // Route Settings hanya satu (?tab=branding/lokasi/roles).
            // Cek privilege berdasarkan tab yang sedang diakses.
            $tabPrivilegeMap = [
                'branding'   => 'kelola_tampilan_branding',
                'lokasi'     => 'kelola_lokasi_cabang',
                'roles'      => 'kelola_role_hak_akses',
                'role-akses' => 'kelola_role_hak_akses',
            ];

            /** @var \App\Models\Role|null $currentRole */
            $currentRole = Auth::user()?->roleAkses;

            $requiredPrivilege = $tabPrivilegeMap[$activeTab] ?? null;

            if ($requiredPrivilege && (! $currentRole || ! $currentRole->hasPrivilege($requiredPrivilege))) {
                // Tab yang diminta tidak boleh diakses — coba redirect ke tab yang bisa
                foreach ($tabPrivilegeMap as $tab => $priv) {
                    if ($currentRole && $currentRole->hasPrivilege($priv)) {
                        return redirect()->route('admin.tampilan-branding', ['tab' => $tab]);
                    }
                }
                // Tidak ada tab Settings yang bisa diakses sama sekali
                abort(403, 'Anda tidak memiliki akses ke halaman Settings ini.');
            }
            // ── /Tahap 4B ─────────────────────────────────────────────────────

            $daftarLokasi = DB::table('lokasi_kantor')->where('organization_id', $orgId)->get();

            // Data Master Role & Privileges untuk Tab Role & Hak Akses
            $daftarRole = \App\Models\Role::with('privileges')
                ->withCount('akun')
                ->where('organization_id', $orgId)
                ->whereRaw('LOWER(nama_role) != ?', ['anggota'])
                ->orderBy('role_id')
                ->get();
            $jumlahAnggota = (int) \App\Models\Role::where('organization_id', $orgId)
                ->whereRaw('LOWER(nama_role) = ?', ['anggota'])
                ->withCount('akun')
                ->value('akun_count');
            $daftarPrivilege = \App\Models\Privilege::orderBy('privilege_id')->get()->groupBy('kategori');
            $selectedRoleId = (int) $request->query('role_id', ($daftarRole->first()?->role_id ?? 1));

            return view('admin.settings.branding', compact(
                'savedColor',
                'savedLogo',
                'organizationName',
                'organizationInitials',
                'daftarLokasi',
                'activeTab',
                'daftarRole',
                'jumlahAnggota',
                'daftarPrivilege',
                'selectedRoleId'
            ));
        } catch (\Exception $e) {
            return redirect()->route('admin.dashboard')->with('error', 'Gagal memuat halaman Settings: ' . $e->getMessage());
        }
    }

    public function simpanRolePrivilege(Request $request)
    {
        $orgId = OrganizationHelper::requireActiveOrganization();

        $request->validate([
            'role_id'         => [
                'required', 'integer',
                Rule::exists('role', 'role_id')->where('organization_id', $orgId),
            ],
            'privilege_ids'   => 'nullable|array',
            'privilege_ids.*' => 'integer|exists:privilege,privilege_id',
        ], [
            'role_id.required' => 'Role wajib dipilih.',
            'role_id.exists'   => 'Role tidak valid.',
        ]);

        try {
            $role = \App\Models\Role::where('organization_id', $orgId)
                ->findOrFail($request->role_id);
            if ($role->isMemberRole()) {
                return redirect()
                    ->route('admin.tampilan-branding', ['tab' => 'roles'])
                    ->with('error', 'Hak akses Anggota tidak dapat dikonfigurasi karena role ini tidak memiliki akses Web Admin.');
            }
            $privilegeIds = $request->input('privilege_ids', []);

            // Sinkronisasi hak akses ke tabel pivot role_privilege
            $role->privileges()->sync($privilegeIds);

            // Catat log aktivitas
            $user = Auth::user();
            if ($user && isset($user->akun_id)) {
                logHelpers::record(
                    $user->akun_id,
                    "Memperbarui hak akses (privilege) untuk role: {$role->nama_role}"
                );
            }

            return redirect()
                ->route('admin.tampilan-branding', [
                    'tab' => 'roles',
                    'role_id' => $role->role_id,
                ])
                ->with('success', "Hak akses untuk role \"{$role->nama_role}\" berhasil diperbarui.");
        } catch (\Exception $e) {
            Log::error('Gagal menyimpan hak akses role: ' . $e->getMessage(), [
                'exception' => $e,
                'role_id' => $request->role_id,
            ]);

            return redirect()
                ->route('admin.tampilan-branding', [
                    'tab' => 'roles',
                    'role_id' => $request->role_id,
                ])
                ->with('error', 'Gagal menyimpan hak akses role. Silakan periksa kembali atau hubungi administrator.');
        }
    }

    public function tambahRole(Request $request)
    {
        $orgId = OrganizationHelper::requireActiveOrganization();

        $validator = \Illuminate\Support\Facades\Validator::make($request->all(), [
            'nama_role' => [
                'required', 'string', 'min:2', 'max:50',
                function ($attribute, $value, $fail) use ($orgId) {
                    if (strcasecmp(trim($value), 'Super Admin') === 0) {
                        $fail('Nama role Super Admin dicadangkan untuk akses sistem.');
                        return;
                    }
                    if (strcasecmp(trim($value), 'Anggota') === 0) {
                        $fail('Role Anggota dikelola oleh sistem dan tidak dapat dibuat ulang.');
                        return;
                    }

                    $exists = \App\Models\Role::where('organization_id', $orgId)
                        ->whereRaw('LOWER(nama_role) = ?', [strtolower(trim($value))])
                        ->exists();

                    if ($exists) {
                        $fail('Nama role sudah terdaftar di organisasi ini.');
                    }
                },
            ],
            'deskripsi' => 'nullable|string|max:200',
        ], [
            'nama_role.required' => 'Nama role wajib diisi.',
            'nama_role.min'      => 'Nama role minimal 2 karakter.',
            'nama_role.max'      => 'Nama role maksimal 50 karakter.',
            'nama_role.unique'   => 'Nama role sudah terdaftar, silakan gunakan nama lain.',
            'deskripsi.max'      => 'Deskripsi role maksimal 200 karakter.',
        ]);

        if ($validator->fails()) {
            return redirect()
                ->route('admin.tampilan-branding', [
                    'tab' => 'roles',
                ])
                ->withErrors($validator, 'tambah_role')
                ->withInput()
                ->with('error', $validator->errors()->first());
        }

        try {
            $role = \App\Models\Role::create([
                'nama_role' => trim($request->nama_role),
                'deskripsi' => $request->filled('deskripsi') ? trim($request->deskripsi) : null,
                'organization_id' => $orgId, // Inject active organization ID
            ]);

            // Catat log aktivitas
            $user = Auth::user();
            if ($user && isset($user->akun_id)) {
                logHelpers::record(
                    $user->akun_id,
                    "Menambahkan role master baru: {$role->nama_role}"
                );
            }

            return redirect()
                ->route('admin.tampilan-branding', [
                    'tab' => 'roles',
                    'role_id' => $role->role_id,
                ])
                ->with('success', "Role master \"{$role->nama_role}\" berhasil ditambahkan. Silakan atur hak akses privilege untuk role ini.");
        } catch (\Exception $e) {
            Log::error('Gagal menambahkan role baru: ' . $e->getMessage(), [
                'exception' => $e,
                'input' => $request->except(['_token']),
            ]);

            return redirect()
                ->route('admin.tampilan-branding', [
                    'tab' => 'roles',
                ])
                ->withInput()
                ->with('error', 'Gagal menambahkan role baru. Silakan coba beberapa saat lagi.');
        }
    }

    public function hapusRole($id)
    {
        try {
            $orgId = OrganizationHelper::requireActiveOrganization();
            $role = \App\Models\Role::withCount('akun')
                ->where('organization_id', $orgId)
                ->find($id);
            if (! $role) {
                return redirect()
                    ->route('admin.tampilan-branding', ['tab' => 'roles'])
                    ->with('error', 'Data role tidak ditemukan.');
            }

            if ($role->isMemberRole()) {
                return redirect()
                    ->route('admin.tampilan-branding', ['tab' => 'roles'])
                    ->with('error', 'Role Anggota dikelola oleh sistem dan tidak dapat dihapus.');
            }

            // Periksa apakah role masih direferensikan oleh akun/user
            if ($role->akun_count > 0 || \App\Models\Akun::where('role_id', $role->role_id)->exists()) {
                return redirect()
                    ->route('admin.tampilan-branding', [
                        'tab' => 'roles',
                        'role_id' => $role->role_id,
                    ])
                    ->with('error', "Role \"{$role->nama_role}\" tidak dapat dihapus karena masih digunakan oleh {$role->akun_count} akun pengguna. Silakan alihkan atau lepaskan role tersebut dari akun pengguna terlebih dahulu.");
            }

            $namaRole = $role->nama_role;

            // Lepaskan relasi privilege pada tabel pivot role_privilege
            $role->privileges()->detach();

            // Hapus record Role dari database
            $role->delete();

            // Catat log aktivitas
            $user = Auth::user();
            if ($user && isset($user->akun_id)) {
                logHelpers::record(
                    $user->akun_id,
                    "Menghapus role master: {$namaRole} (ID: {$id})"
                );
            }

            return redirect()
                ->route('admin.tampilan-branding', ['tab' => 'roles'])
                ->with('success', "Role \"{$namaRole}\" berhasil dihapus.");
        } catch (\Exception $e) {
            Log::error('Gagal menghapus role: ' . $e->getMessage(), [
                'exception' => $e,
                'role_id' => $id,
            ]);

            return redirect()
                ->route('admin.tampilan-branding', ['tab' => 'roles'])
                ->with('error', 'Gagal menghapus role. Silakan coba beberapa saat lagi.');
        }
    }

    public function simpanBranding(Request $request)
    {
        $request->validate([
            'primary_color' => 'required|string|regex:/^#[0-9A-Fa-f]{6}$/',
            'logo'          => 'nullable|image|mimes:jpeg,png,jpg,svg,webp|max:2048',
        ]);
    
        \App\Models\Setting::set('primary_color', $request->primary_color);
    
        if ($request->hasFile('logo')) {
            $oldLogo = \App\Models\Setting::get('company_logo');
            $newPath = $this->uploadCompanyLogo($request->file('logo'));
            \App\Models\Setting::set('company_logo', $newPath);

            if ($oldLogo && $oldLogo !== $newPath) {
                $this->deleteCompanyLogo($oldLogo);
            }
        }
    
        // Catat aktivitas admin
        $user = Auth::user();
        if ($user && isset($user->akun_id)) {
            $aktivitas = $request->hasFile('logo')
                ? 'Mengubah tampilan branding dan logo sistem'
                : 'Mengubah warna branding sistem';
    
            logHelpers::record(
                $user->akun_id,
                $aktivitas
            );
        }
    
        return redirect()
            ->route('admin.tampilan-branding', ['tab' => 'branding'])
            ->with('success', 'Pengaturan tampilan & branding berhasil disimpan.');
    }

    public function resetBranding()
    {
        $orgId = OrganizationHelper::requireActiveOrganization();
        
        $oldLogo = \App\Models\Setting::get('company_logo');
        if ($oldLogo) {
            $this->deleteCompanyLogo($oldLogo);
        }

        \App\Models\Setting::where('organization_id', $orgId)->where('key', 'primary_color')->delete();
        \App\Models\Setting::where('organization_id', $orgId)->where('key', 'company_logo')->delete();
    
        // Catat aktivitas admin
        $user = Auth::user();
        if ($user && isset($user->akun_id)) {
            logHelpers::record(
                $user->akun_id,
                'Mereset tampilan branding sistem ke pengaturan awal'
            );
        }
    
        return redirect()
            ->route('admin.tampilan-branding', ['tab' => 'branding'])
            ->with('success', 'Tampilan & branding berhasil direset ke pengaturan awal.');
    }

    public function simpanLogo(Request $request)
    {
        $request->validate([
            'logo' => 'required|image|mimes:jpeg,png,jpg,svg,webp|max:2048',
        ]);
    
        if ($request->hasFile('logo')) {
            $oldLogo = \App\Models\Setting::get('company_logo');
            $newPath = $this->uploadCompanyLogo($request->file('logo'));
            \App\Models\Setting::set('company_logo', $newPath);

            if ($oldLogo && $oldLogo !== $newPath) {
                $this->deleteCompanyLogo($oldLogo);
            }
        }
    
        // Catat aktivitas admin
        $user = Auth::user();
        if ($user && isset($user->akun_id)) {
            logHelpers::record(
                $user->akun_id,
                'Mengubah logo sistem'
            );
        }
    
        return redirect()
            ->route('admin.tampilan-branding', ['tab' => 'branding'])
            ->with('success', 'Logo berhasil diperbarui.');
    }

    public function simpanLokasi(Request $request)
    {
        $validator = \Illuminate\Support\Facades\Validator::make($request->all(), [
            'lokasi_id'     => 'nullable|integer',
            'nama_kantor'   => 'required|string|min:2|max:100',
            'latitude'      => 'required|numeric|between:-90,90',
            'longitude'     => 'required|numeric|between:-180,180',
            'radius_meter'  => 'required|integer|min:1|max:50000',
        ], [
            'nama_kantor.required'  => 'Nama kantor cabang wajib diisi.',
            'nama_kantor.min'       => 'Nama kantor cabang minimal 2 karakter.',
            'nama_kantor.max'       => 'Nama kantor cabang maksimal 100 karakter.',
            'latitude.required'     => 'Latitude koordinat wajib diisi.',
            'latitude.numeric'      => 'Latitude harus berupa angka desimal koordinat.',
            'latitude.between'      => 'Latitude harus berada dalam rentang -90 sampai 90 derajat.',
            'longitude.required'    => 'Longitude koordinat wajib diisi.',
            'longitude.numeric'     => 'Longitude harus berupa angka desimal koordinat.',
            'longitude.between'     => 'Longitude harus berada dalam rentang -180 sampai 180 derajat.',
            'radius_meter.required' => 'Radius presensi wajib diisi.',
            'radius_meter.integer'  => 'Radius presensi harus berupa angka bulat dalam meter.',
            'radius_meter.min'      => 'Radius presensi minimal 1 meter.',
            'radius_meter.max'      => 'Radius presensi maksimal 50.000 meter.',
        ]);

        if ($validator->fails()) {
            return redirect()
                ->route('admin.tampilan-branding', ['tab' => 'lokasi'])
                ->withErrors($validator, 'lokasi_kantor')
                ->withInput()
                ->with('error', $validator->errors()->first());
        }

        try {
            $orgId = OrganizationHelper::requireActiveOrganization();
            
            $lokasiId = $request->input('lokasi_id');
            $namaKantor = trim($request->input('nama_kantor'));
            $latitude = (float) $request->input('latitude');
            $longitude = (float) $request->input('longitude');
            $radiusMeter = (int) $request->input('radius_meter');

            if ($lokasiId) {
                // Update existing location
                $lokasi = \App\Models\WorkLocation::where('organization_id', $orgId)
                    ->where('lokasi_id', $lokasiId)
                    ->first();

                if (!$lokasi) {
                    return redirect()
                        ->route('admin.tampilan-branding', ['tab' => 'lokasi'])
                        ->with('error', 'Data kantor cabang tidak ditemukan atau bukan milik organisasi Anda.');
                }

                $lokasi->update([
                    'nama_kantor'  => $namaKantor,
                    'latitude'     => $latitude,
                    'longitude'    => $longitude,
                    'radius_meter' => $radiusMeter,
                ]);

                $pesan = "Kantor cabang '{$namaKantor}' berhasil diperbarui.";
            } else {
                // Insert new location via Eloquent to ensure timestamps
                $lokasi = \App\Models\WorkLocation::create([
                    'organization_id' => $orgId,
                    'nama_kantor'     => $namaKantor,
                    'latitude'        => $latitude,
                    'longitude'       => $longitude,
                    'radius_meter'    => $radiusMeter,
                ]);

                $lokasiId = $lokasi->lokasi_id;
                $pesan = "Kantor cabang '{$namaKantor}' berhasil ditambahkan.";
            }

            // Audit log with integer akun_id
            $user = Auth::user();
            if ($user && isset($user->akun_id)) {
                logHelpers::record(
                    $user->akun_id,
                    "Admin menyimpan konfigurasi kantor cabang: {$namaKantor} (ID: {$lokasiId})"
                );
            }

            return redirect()->route('admin.tampilan-branding', ['tab' => 'lokasi'])->with('success', $pesan);
        } catch (\Exception $e) {
            Log::error('Gagal menyimpan data kantor cabang: ' . $e->getMessage(), [
                'exception' => $e,
                'input' => $request->except(['_token']),
            ]);

            return redirect()
                ->route('admin.tampilan-branding', ['tab' => 'lokasi'])
                ->withInput()
                ->with('error', 'Gagal menyimpan data kantor cabang. Silakan periksa kembali input Anda.');
        }
    }

    public function hapusLokasi($id)
    {
        $orgId = OrganizationHelper::requireActiveOrganization();
        
        try {
            $lokasi = DB::table('lokasi_kantor')
                ->where('organization_id', $orgId)
                ->where('lokasi_id', $id)
                ->first();
                
            if (!$lokasi) {
                return redirect()->route('admin.tampilan-branding', ['tab' => 'lokasi'])->with('error', 'Data kantor cabang tidak ditemukan atau bukan milik organisasi Anda.');
            }

            $nama = $lokasi->nama_kantor;

            // Delete location
            DB::table('lokasi_kantor')->where('lokasi_id', $id)->delete();

            // Audit log with integer akun_id
            $user = Auth::user();
            if ($user && isset($user->akun_id)) {
                logHelpers::record(
                    $user->akun_id,
                    "Admin menghapus kantor cabang: {$nama} (ID: {$id})"
                );
            }

            return redirect()->route('admin.tampilan-branding', ['tab' => 'lokasi'])->with('success', "Kantor cabang '{$nama}' berhasil dihapus.");
        } catch (\Exception $e) {
            return redirect()->route('admin.tampilan-branding', ['tab' => 'lokasi'])->with('error', 'Gagal menghapus kantor cabang: ' . $e->getMessage());
        }
    }

    protected function uploadCompanyLogo($file): string
    {
        $orgId = \App\Helpers\OrganizationHelper::requireActiveOrganization();
        $ext = $file->getClientOriginalExtension() ?: $file->extension() ?: 'png';
        $fileName = 'logo_' . time() . '_' . Str::random(6) . '.' . strtolower($ext);
        $remotePath = 'logos/organization-' . $orgId . '/' . $fileName;

        $baseUrl = supabase_url() ?: config('supabase.url');
        $apiKey = supabase_key() ?: config('supabase.key');

        // Fallback to local storage if Supabase is not configured
        if (! $baseUrl || ! $apiKey) {
            $file->storeAs('logos', $fileName, 'public');
            return 'storage/' . $remotePath;
        }

        $bucket = config('supabase.assets_bucket', 'company-assets');
        $caBundle = config('supabase.ca_bundle');
        if ($caBundle && ! is_file($caBundle)) {
            throw new \RuntimeException('CA bundle Supabase tidak ditemukan. Periksa SUPABASE_CA_BUNDLE.');
        }

        $baseUrl = rtrim($baseUrl, '/');
        if (! preg_match('/^https?:\/\//i', $baseUrl)) {
            $baseUrl = 'https://' . ltrim($baseUrl, '/');
        }

        $uploadUrl = $baseUrl . '/storage/v1/object/' . rawurlencode($bucket) . '/' . $remotePath;

        $resp = Http::withOptions(['verify' => $caBundle ?: true])->withHeaders([
            'Authorization' => 'Bearer ' . $apiKey,
            'apikey' => $apiKey,
            'Content-Type' => $file->getClientMimeType(),
            'x-upsert' => 'true',
        ])->withBody(file_get_contents($file->getRealPath()), $file->getClientMimeType())->post($uploadUrl);

        if (! $resp->successful()) {
            $resp = Http::withOptions(['verify' => $caBundle ?: true])->withHeaders([
                'Authorization' => 'Bearer ' . $apiKey,
                'apikey' => $apiKey,
                'Content-Type' => $file->getClientMimeType(),
            ])->withBody(file_get_contents($file->getRealPath()), $file->getClientMimeType())->put($uploadUrl);
        }

        if (! $resp->successful()) {
            throw new \RuntimeException('Gagal mengunggah logo ke Supabase Storage: ' . $resp->body());
        }

        return rtrim($bucket, '/') . '/' . $remotePath;
    }

    protected function deleteCompanyLogo(?string $path): void
    {
        if (! $path || trim($path) === '') {
            return;
        }

        $path = trim($path);
        
        // Handle local storage deletion
        if (str_starts_with($path, 'storage/')) {
            $localPath = substr($path, strlen('storage/'));
            if (\Illuminate\Support\Facades\Storage::disk('public')->exists($localPath)) {
                \Illuminate\Support\Facades\Storage::disk('public')->delete($localPath);
            }
            return;
        }
        
        if (preg_match('/^https?:\/\//i', $path) || str_starts_with($path, 'images/') || str_starts_with($path, 'assets/') || str_ends_with($path, 'logo-sip.png')) {
            return;
        }

        $bucket = config('supabase.assets_bucket', 'company-assets');
        $baseUrl = supabase_url() ?: config('supabase.url');
        $apiKey = supabase_key() ?: config('supabase.key');
        if (! $baseUrl || ! $apiKey) {
            return;
        }

        $baseUrl = rtrim($baseUrl, '/');
        if (! preg_match('/^https?:\/\//i', $baseUrl)) {
            $baseUrl = 'https://' . ltrim($baseUrl, '/');
        }

        $objectPath = ltrim($path, '/');
        if (str_starts_with($objectPath, 'public/' . $bucket . '/')) {
            $objectPath = substr($objectPath, strlen('public/' . $bucket . '/'));
        }
        if (str_starts_with($objectPath, $bucket . '/')) {
            $objectPath = substr($objectPath, strlen($bucket . '/'));
        }

        $deleteUrl = $baseUrl . '/storage/v1/object/' . rawurlencode($bucket) . '/' . implode('/', array_map('rawurlencode', explode('/', $objectPath)));

        Http::withHeaders([
            'Authorization' => 'Bearer ' . $apiKey,
            'apikey' => $apiKey,
        ])->delete($deleteUrl);
    }

    public function pengaturan()
    {
        return view('admin.placeholder', ['title' => 'Pengaturan']);
    }

    public function bantuan()
    {
        return view('admin.placeholder', ['title' => 'Bantuan']);
    }
}