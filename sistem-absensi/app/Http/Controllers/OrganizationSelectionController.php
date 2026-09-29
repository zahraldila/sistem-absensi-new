<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use App\Models\Organization;

class OrganizationSelectionController extends Controller
{
    /**
     * Tampilkan form pemilihan organisasi untuk Super Admin
     */
    public function select()
    {
        // Pastikan hanya Super Admin yang bisa mengakses ini
        if (! Auth::user()->isSuperAdmin()) {
            abort(403, 'Akses ditolak.');
        }

        // Ambil semua organisasi yang aktif
        $organizations = Organization::where('status', 'active')->get();

        return view('admin.select-organization', compact('organizations'));
    }

    /**
     * Proses pemilihan organisasi
     */
    public function store(Request $request)
    {
        if (! Auth::user()->isSuperAdmin()) {
            abort(403, 'Akses ditolak.');
        }

        $request->validate([
            'organization_id' => 'required|exists:organizations,organization_id',
        ]);

        $organization = Organization::where('organization_id', $request->organization_id)
            ->where('status', 'active')
            ->firstOrFail();

        // Simpan ke session
        $request->session()->put('active_organization_id', $organization->organization_id);

        return redirect()->route('admin.dashboard')->with('success', 'Berhasil memilih organisasi: ' . $organization->nama_organisasi);
    }

    /**
     * Tampilkan form tambah organisasi baru
     */
    public function create()
    {
        if (! Auth::user()->isSuperAdmin()) {
            abort(403, 'Akses ditolak.');
        }

        return view('admin.create-organization');
    }

    /**
     * Proses tambah organisasi baru
     */
    public function storeNew(Request $request)
    {
        if (! Auth::user()->isSuperAdmin()) {
            abort(403, 'Akses ditolak.');
        }

        $request->validate([
            'nama_organisasi' => 'required|string|max:255',
            'kode_organisasi' => 'required|string|max:50|unique:organizations,kode_organisasi',
            'alamat' => 'nullable|string',
        ], [
            'nama_organisasi.required' => 'Nama organisasi wajib diisi.',
            'kode_organisasi.required' => 'Kode organisasi wajib diisi.',
            'kode_organisasi.unique' => 'Kode organisasi sudah digunakan, silakan gunakan kode lain.',
        ]);

        $organization = Organization::create([
            'nama_organisasi' => $request->nama_organisasi,
            'kode_organisasi' => $request->kode_organisasi,
            'alamat' => $request->alamat,
            'status' => 'active',
            'display_token' => (string) \Illuminate\Support\Str::uuid(),
        ]);

        $roleTemplates = \App\Models\Role::with('privileges')
            ->whereNull('organization_id')
            ->where('nama_role', '!=', 'Super Admin')
            ->get();

        foreach ($roleTemplates as $template) {
            $localRole = \App\Models\Role::firstOrCreate(
                [
                    'nama_role' => $template->nama_role,
                    'organization_id' => $organization->organization_id,
                ],
                ['deskripsi' => $template->deskripsi]
            );
            $localRole->privileges()->syncWithoutDetaching($template->privileges->modelKeys());
        }

        $adminRole = \App\Models\Role::firstOrCreate(
            [
                'nama_role' => 'Admin',
                'organization_id' => $organization->organization_id,
            ],
            ['deskripsi' => 'Administrator organisasi']
        );
        $adminRole->privileges()->syncWithoutDetaching(
            \App\Models\Privilege::pluck('privilege_id')->all()
        );

        // 3. Buat Pegawai dummy untuk Admin
        $pegawai = \App\Models\Pegawai::create([
            'nama_pegawai' => 'Admin ' . $organization->nama_organisasi,
            'email' => 'admin_' . strtolower(trim($organization->kode_organisasi)) . '@gmail.com',
            'status' => 'Aktif',
            'organization_id' => $organization->organization_id,
        ]);

        // 4. Buat Akun login untuk Admin tersebut
        \App\Models\Akun::create([
            'username' => 'admin_' . strtolower(trim($organization->kode_organisasi)),
            'password' => \Illuminate\Support\Facades\Hash::make('admin123'),
            'role' => 'Admin',
            'role_id' => $adminRole->role_id,
            'pegawai_id' => $pegawai->pegawai_id,
        ]);

        return redirect()->route('admin.organization.select')->with('success', 'Organisasi baru berhasil ditambahkan.');
    }

    /**
     * Proses ganti organisasi (hapus session)
     */
    public function switch(Request $request)
    {
        if (! Auth::user()->isSuperAdmin()) {
            abort(403, 'Akses ditolak.');
        }

        // Hapus session active_organization_id saja
        session()->forget('active_organization_id');

        return redirect()->route('admin.organization.select');
    }
}
