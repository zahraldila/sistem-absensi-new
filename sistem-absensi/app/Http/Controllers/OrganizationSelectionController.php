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

        // Ambil semua organisasi beserta kategori dan fitur terkait
        $organizations = Organization::with(['category', 'organizationFeatures'])
            ->orderBy('nama_organisasi')
            ->get();

        // Ambil katalog fitur untuk modal Kelola Fitur
        $allFeatures = \App\Models\Feature::where('status', 'active')
            ->orderBy('category_group')
            ->orderBy('name')
            ->get();

        return view('admin.select-organization', compact('organizations', 'allFeatures'));
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

        $categories = \App\Models\OrganizationCategory::where('status', 'active')->orderBy('name')->get();

        return view('admin.create-organization', compact('categories'));
    }

    /**
     * Proses tambah organisasi baru
     */
    public function storeNew(Request $request, \App\Services\OrganizationProvisioningService $provisioningService)
    {
        if (! Auth::user()->isSuperAdmin()) {
            abort(403, 'Akses ditolak.');
        }

        $request->validate([
            'nama_organisasi' => 'required|string|max:255',
            'kode_organisasi' => 'required|string|max:50|unique:organizations,kode_organisasi',
            'alamat'          => 'nullable|string',
            'category_id'     => 'nullable|exists:organization_categories,id',
        ], [
            'nama_organisasi.required' => 'Nama organisasi wajib diisi.',
            'kode_organisasi.required' => 'Kode organisasi wajib diisi.',
            'kode_organisasi.unique'   => 'Kode organisasi sudah digunakan, silakan gunakan kode lain.',
            'category_id.exists'       => 'Kategori organisasi yang dipilih tidak valid.',
        ]);

        $organization = $provisioningService->provision($request->only([
            'nama_organisasi',
            'kode_organisasi',
            'alamat',
            'category_id',
        ]));

        return redirect()->route('admin.organization.select')->with('success', 'Organisasi ' . $organization->nama_organisasi . ' berhasil ditambahkan dan fitur telah diprovisioning otomatis.');
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
