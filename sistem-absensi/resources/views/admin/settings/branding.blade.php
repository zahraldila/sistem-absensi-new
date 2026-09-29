@extends('layouts.admin.app')

@section('title', 'Settings')

@section('content')

@php
    $savedColor = $savedColor ?? '#123D91';
    $savedLogo  = $savedLogo ?? '';
    $organizationName = $organizationName ?? '';
    $organizationInitials = $organizationInitials ?? getInitials($organizationName);
    $activeTab  = $activeTab ?? 'branding';
    $daftarLokasi = $daftarLokasi ?? [];
    $daftarRole = $daftarRole ?? [];
    $daftarPrivilege = $daftarPrivilege ?? collect([]);
    $selectedRoleId = $selectedRoleId ?? ($daftarRole->first()?->role_id ?? 1);

    $currentRole = Auth::user()?->roleAkses ?? null;
    $canBranding = $currentRole?->hasPrivilege('kelola_tampilan_branding') ?? false;
    $canLokasi   = $currentRole?->hasPrivilege('kelola_lokasi_cabang') ?? false;
    $canWifi     = $currentRole?->hasPrivilege('kelola_wifi_kantor') ?? false;
    $canRoles    = $currentRole?->hasPrivilege('kelola_role_hak_akses') ?? false;
@endphp

<div class="space-y-8" x-data="settingsMasterApp('{{ $savedColor }}', '{{ $savedLogo }}', '{{ $activeTab }}', {{ json_encode($daftarRole->map(fn($r) => ['role_id' => $r->role_id, 'nama_role' => $r->nama_role, 'akun_count' => $r->akun_count ?? 0, 'privilege_ids' => $r->privileges->pluck('privilege_id')->toArray()])) }}, {{ $selectedRoleId }})">

    {{-- ===== HEADER ===== --}}
    <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
        <div>
            <h1 class="text-[32px] font-bold text-slate-900">Settings</h1>
            <p class="mt-1 text-[15px] text-slate-500">
                @if($activeTab === 'branding')
                Sesuaikan identitas visual sistem, pratinjau perubahan sebelum disimpan.
                @elseif($activeTab === 'lokasi')
                Kelola daftar kantor cabang, titik koordinat GPS presensi, dan radius kehadiran.
                @else
                Kelola daftar role pengguna dan atur hak akses (privilege) fitur Web Admin untuk masing-masing role.
                @endif
            </p>
        </div>

        @if($activeTab === 'branding')
        {{-- Step indicator --}}
        <div class="flex items-center gap-2 text-sm font-medium text-slate-500">
            <span class="flex h-7 w-7 items-center justify-center rounded-full text-xs font-bold"
                  :class="hasChanges ? 'bg-primary text-white' : 'bg-slate-200 text-slate-500'">1</span>
            <span :class="hasChanges ? 'text-primary' : ''">Ubah</span>
            <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/>
            </svg>
            <span class="flex h-7 w-7 items-center justify-center rounded-full text-xs font-bold"
                  :class="hasChanges ? 'bg-primary text-white' : 'bg-slate-200 text-slate-500'">2</span>
            <span :class="hasChanges ? 'text-primary' : ''">Pratinjau</span>
            <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/>
            </svg>
            <span class="flex h-7 w-7 items-center justify-center rounded-full text-xs font-bold"
                  :class="hasChanges ? 'bg-green-500 text-white' : 'bg-slate-200 text-slate-500'">3</span>
            <span :class="hasChanges ? 'text-green-600' : ''">Simpan</span>
        </div>
        @elseif($activeTab === 'lokasi')
        @if($canLokasi)
        <button type="button" @click="openModalTambah()" class="inline-flex items-center gap-2 px-5 py-2.5 rounded-2xl bg-primary text-white font-bold text-sm shadow-md hover:bg-primary/90 transition">
            <i class="fa-solid fa-plus text-sm"></i>
            <span>Tambah Kantor Cabang</span>
        </button>
        @else
        <button type="button" disabled title="Anda tidak memiliki hak akses untuk mengelola lokasi cabang" class="inline-flex items-center gap-2 px-5 py-2.5 rounded-2xl bg-slate-200 text-slate-400 font-bold text-sm opacity-60 cursor-not-allowed select-none">
            <i class="fa-solid fa-plus text-sm"></i>
            <span>Tambah Kantor Cabang</span>
        </button>
        @endif
        @else
        @if($canRoles)
        <button type="button" @click="openModalTambahRole()" class="inline-flex items-center gap-2 px-5 py-2.5 rounded-2xl bg-primary text-white font-bold text-sm shadow-md hover:bg-primary/90 transition">
            <i class="fa-solid fa-plus text-sm"></i>
            <span>Tambah Role</span>
        </button>
        @else
        <button type="button" disabled title="Anda tidak memiliki hak akses untuk mengelola role & hak akses" class="inline-flex items-center gap-2 px-5 py-2.5 rounded-2xl bg-slate-200 text-slate-400 font-bold text-sm opacity-60 cursor-not-allowed select-none">
            <i class="fa-solid fa-plus text-sm"></i>
            <span>Tambah Role</span>
        </button>
        @endif
        @endif
    </div>

    {{-- ===== TAB SWITCHER ===== --}}
    <div class="flex flex-wrap items-center gap-2 border-b border-slate-200 pb-4">
        @if($canBranding)
            <a href="{{ route('admin.tampilan-branding', ['tab' => 'branding']) }}"
               class="flex items-center gap-2 px-5 py-2.5 rounded-2xl text-sm font-bold transition {{ $activeTab === 'branding' ? 'bg-primary text-white shadow-sm' : 'bg-white text-slate-600 hover:bg-slate-50 border border-slate-200' }}">
                <i class="fa-solid fa-palette text-sm"></i>
                <span>Tampilan & Branding</span>
            </a>
        @else
            <span title="Anda tidak memiliki hak akses untuk mengelola tampilan & branding"
               class="flex items-center gap-2 px-5 py-2.5 rounded-2xl text-sm font-bold text-slate-300 opacity-50 cursor-not-allowed select-none bg-slate-50 border border-slate-200">
                <i class="fa-solid fa-palette text-sm"></i>
                <span>Tampilan & Branding</span>
            </span>
        @endif

        @if($canLokasi)
            <a href="{{ route('admin.tampilan-branding', ['tab' => 'lokasi']) }}"
               class="flex items-center gap-2 px-5 py-2.5 rounded-2xl text-sm font-bold transition {{ $activeTab === 'lokasi' ? 'bg-primary text-white shadow-sm' : 'bg-white text-slate-600 hover:bg-slate-50 border border-slate-200' }}">
                <i class="fa-solid fa-building-circle-check text-sm"></i>
                <span>Lokasi Kantor & Cabang</span>
                <span class="ml-1 px-2.5 py-0.5 rounded-full text-xs {{ $activeTab === 'lokasi' ? 'bg-white/20 text-white' : 'bg-slate-100 text-slate-700' }}">{{ count($daftarLokasi) }}</span>
            </a>
        @else
            <span title="Anda tidak memiliki hak akses untuk mengelola lokasi & cabang"
               class="flex items-center gap-2 px-5 py-2.5 rounded-2xl text-sm font-bold text-slate-300 opacity-50 cursor-not-allowed select-none bg-slate-50 border border-slate-200">
                <i class="fa-solid fa-building-circle-check text-sm"></i>
                <span>Lokasi Kantor & Cabang</span>
                <span class="ml-1 px-2.5 py-0.5 rounded-full text-xs bg-slate-100 text-slate-400">{{ count($daftarLokasi) }}</span>
            </span>
        @endif

        @if($canRoles)
            <a href="{{ route('admin.tampilan-branding', ['tab' => 'roles']) }}"
               class="flex items-center gap-2 px-5 py-2.5 rounded-2xl text-sm font-bold transition {{ in_array($activeTab, ['roles', 'role-akses']) ? 'bg-primary text-white shadow-sm' : 'bg-white text-slate-600 hover:bg-slate-50 border border-slate-200' }}">
                <i class="fa-solid fa-shield-halved text-sm"></i>
                <span>Role & Hak Akses</span>
                <span class="ml-1 px-2.5 py-0.5 rounded-full text-xs {{ in_array($activeTab, ['roles', 'role-akses']) ? 'bg-white/20 text-white' : 'bg-slate-100 text-slate-700' }}">{{ count($daftarRole) }}</span>
            </a>
        @else
            <span title="Anda tidak memiliki hak akses untuk mengelola role & hak akses"
               class="flex items-center gap-2 px-5 py-2.5 rounded-2xl text-sm font-bold text-slate-300 opacity-50 cursor-not-allowed select-none bg-slate-50 border border-slate-200">
                <i class="fa-solid fa-shield-halved text-sm"></i>
                <span>Role & Hak Akses</span>
                <span class="ml-1 px-2.5 py-0.5 rounded-full text-xs bg-slate-100 text-slate-400">{{ count($daftarRole) }}</span>
            </span>
        @endif
    </div>

    {{-- ===== FLASH ===== --}}
    @if(session('success'))
    <div x-data="{ show: true }"
         x-show="show"
         x-init="setTimeout(() => show = false, 4000)"
         x-transition:leave="transition ease-in duration-300"
         x-transition:leave-start="opacity-100"
         x-transition:leave-end="opacity-0"
         class="flex items-center gap-3 rounded-2xl border border-green-200 bg-green-50 px-5 py-4 text-sm text-green-800 shadow-sm">
        <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 flex-shrink-0 text-green-500" fill="none" viewBox="0 0 24 24" stroke="currentColor">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/>
        </svg>
        <span>{{ session('success') }}</span>
    </div>
    @endif

    @if(session('error'))
    <div x-data="{ show: true }"
         x-show="show"
         x-init="setTimeout(() => show = false, 4000)"
         class="flex items-center gap-3 rounded-2xl border border-rose-200 bg-rose-50 px-5 py-4 text-sm text-rose-800 shadow-sm">
        <i class="fa-solid fa-circle-exclamation text-rose-500 text-lg"></i>
        <span>{{ session('error') }}</span>
    </div>
    @endif

    {{-- ======================================================== --}}
    {{-- TAB 1: TAMPILAN & BRANDING (EXACT 100% ORIGINAL DESIGN) --}}
    {{-- ======================================================== --}}
    @if($activeTab === 'branding')
    <div class="grid grid-cols-1 gap-8 xl:grid-cols-5">

        {{-- LEFT: Settings (3 cols) --}}
        <div class="xl:col-span-3 space-y-6">

            {{-- LOGO --}}
            <div class="rounded-3xl bg-white p-8 shadow-card">
                <h2 class="text-lg font-bold text-slate-900">Logo Perusahaan</h2>
                <p class="mt-1 text-sm text-slate-500 mb-6">
                    Pilih file logo baru, pratinjau akan langsung berubah di sebelah kanan.
                </p>
                <div class="flex items-start gap-6">
                    <div class="relative flex-shrink-0">
                        <div class="h-24 w-24 overflow-hidden rounded-2xl border-2 border-dashed border-slate-300 bg-slate-50 flex items-center justify-center">
                            <img x-show="logoPreviewUrl && !logoImageFailed" :src="logoPreviewUrl" alt="Logo {{ $organizationName }}"
                                 class="h-20 w-20 object-contain" x-on:error="logoImageFailed = true">
                            <span x-show="!logoPreviewUrl || logoImageFailed" class="h-20 w-20 rounded-xl bg-primary text-white flex items-center justify-center text-xl font-bold">
                                @if($organizationInitials)
                                    {{ $organizationInitials }}
                                @else
                                    <i class="fa-solid fa-building" aria-hidden="true"></i>
                                @endif
                            </span>
                        </div>
                        <div x-show="logoChanged"
                             class="absolute -top-2 -right-2 flex h-6 w-6 items-center justify-center rounded-full bg-green-500 shadow">
                            <svg xmlns="http://www.w3.org/2000/svg" class="h-3.5 w-3.5 text-white" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="3" d="M5 13l4 4L19 7"/>
                            </svg>
                        </div>
                    </div>
                    <div class="flex-1">
                        <p class="text-sm font-semibold text-slate-800 mb-1">Unggah Logo Baru</p>
                        <p class="text-xs text-slate-500 mb-4">PNG, SVG, atau JPG. Disarankan 256x256px, maks 2MB.</p>
                        <label class="inline-flex cursor-pointer items-center gap-2 rounded-xl border border-slate-200 bg-white px-4 py-2.5 text-sm font-semibold text-slate-700 shadow-sm transition hover:bg-slate-50">
                            <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 text-slate-500" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-8l-4-4m0 0L8 8m4-4v12"/>
                            </svg>
                            Pilih File
                            <input id="logoInput" type="file" class="hidden" accept="image/png,image/jpeg,image/svg+xml" @change="onLogoChange">
                        </label>
                        <p x-show="logoFileName && !logoErrorMessage" x-text="logoFileName ? '-- ' + logoFileName : ''"
                           class="mt-3 text-xs font-medium text-primary"></p>
                        <div x-show="logoErrorMessage" 
                             x-transition
                             class="mt-3 flex items-center gap-2 rounded-xl bg-rose-50 border border-rose-200 px-3.5 py-2.5 text-xs font-medium text-rose-700 shadow-xs">
                            <i class="fa-solid fa-circle-exclamation text-rose-500 flex-shrink-0 text-sm"></i>
                            <span x-text="logoErrorMessage"></span>
                        </div>
                    </div>
                </div>
            </div>

            {{-- WARNA --}}
            <div class="rounded-3xl bg-white p-8 shadow-card">
                <h2 class="text-lg font-bold text-slate-900">Warna Utama</h2>
                <p class="mt-1 text-sm text-slate-500 mb-6">
                    Pilih warna brand perusahaan, pratinjau akan langsung berubah.
                </p>
                <div class="mb-6 flex items-center gap-4">
                    <button type="button" @click="openPicker()"
                            class="group relative flex h-14 w-14 items-center justify-center rounded-2xl border-2 border-slate-200 transition hover:border-slate-400 hover:shadow-md"
                            :style="`background-color: ${selectedHex}`"
                            title="Klik untuk ganti warna">
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 text-white opacity-0 group-hover:opacity-100 transition drop-shadow" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15.232 5.232l3.536 3.536m-2.036-5.036a2.5 2.5 0 113.536 3.536L6.5 21.036H3v-3.572L16.732 3.732z"/>
                        </svg>
                    </button>
                    <div>
                        <p class="font-mono text-xl font-bold text-slate-900" x-text="selectedHex.toUpperCase()"></p>
                        <p class="text-xs text-slate-500">Klik kotak warna untuk mengubah</p>
                    </div>
                    <div x-show="colorChanged"
                         class="ml-auto flex items-center gap-1.5 rounded-full bg-blue-50 px-3 py-1 text-xs font-semibold text-primary">
                        <span class="h-2 w-2 rounded-full bg-primary animate-pulse"></span>
                        Warna diubah
                    </div>
                </div>

                {{-- Quick palette --}}
                <p class="text-xs font-semibold uppercase tracking-wider text-slate-400 mb-3">Pilih cepat:</p>
                <div class="flex items-center gap-3 flex-wrap">
                    @foreach(['#123D91','#1D4ED8','#059669','#D97706','#DC2626','#7C3AED','#0F172A','#0891B2'] as $c)
                    <button type="button" @click="quickColor('{{ $c }}')"
                            class="relative flex h-10 w-10 items-center justify-center rounded-full border-2 transition hover:scale-110 shadow-sm"
                            :class="selectedHex.toUpperCase() === '{{ $c }}' ? 'border-slate-800 ring-2 ring-offset-2 ring-slate-800 scale-110' : 'border-white'"
                            style="background-color: {{ $c }}">
                        <svg x-show="selectedHex.toUpperCase() === '{{ $c }}'"
                             xmlns="http://www.w3.org/2000/svg" class="h-4 w-4 text-white drop-shadow" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="3" d="M5 13l4 4L19 7"/>
                        </svg>
                    </button>
                    @endforeach

                    {{-- Tombol + untuk Buka Picker --}}
                    <button type="button" @click="openPicker()"
                            class="flex h-10 w-10 items-center justify-center rounded-full border-2 border-dashed border-slate-300 text-slate-400 transition hover:border-slate-500 hover:text-slate-600 hover:scale-110"
                            title="Kustom Warna Lainnya">
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/>
                        </svg>
                    </button>
                </div>
            </div>

            {{-- TOMBOL AKSI --}}
            <div class="flex items-center justify-between pt-2">
                @if($canBranding)
                    <button type="button" @click="confirmReset = true"
                            class="flex items-center gap-2 rounded-2xl border border-slate-200 bg-white px-6 py-3 text-sm font-semibold text-slate-600 shadow-sm transition hover:bg-slate-50 hover:text-slate-900">
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4 text-slate-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"/>
                        </svg>
                        Reset
                    </button>

                    <button type="button" @click="onSubmit()"
                            :disabled="!hasChanges"
                            :class="hasChanges ? 'bg-primary hover:bg-primary-hover shadow-lg shadow-blue-500/20 text-white cursor-pointer' : 'bg-slate-200 text-slate-400 cursor-not-allowed'"
                            class="flex items-center gap-2 rounded-2xl px-8 py-3 text-sm font-semibold transition">
                        <svg x-show="hasChanges" xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/>
                        </svg>
                        <span x-text="hasChanges ? 'Simpan Perubahan' : 'Belum Ada Perubahan'"></span>
                    </button>
                @else
                    <button type="button" disabled title="Anda tidak memiliki hak akses untuk mengubah branding"
                            class="flex items-center gap-2 rounded-2xl border border-slate-200 bg-slate-100 px-6 py-3 text-sm font-semibold text-slate-400 opacity-60 cursor-not-allowed select-none">
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4 text-slate-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"/>
                        </svg>
                        Reset
                    </button>

                    <button type="button" disabled title="Anda tidak memiliki hak akses untuk mengubah branding"
                            class="flex items-center gap-2 rounded-2xl bg-slate-200 text-slate-400 px-8 py-3 text-sm font-semibold opacity-60 cursor-not-allowed select-none">
                        <span>Simpan Perubahan</span>
                    </button>
                @endif
            </div>

        </div>

        {{-- RIGHT: Live Preview (2 cols) --}}
        <div class="xl:col-span-2 space-y-6">

            <div>
                <p class="text-base font-bold text-slate-800 mb-1">Pratinjau Langsung</p>
                <p class="text-xs text-slate-400 mb-4">Perubahan warna dan logo akan langsung terlihat di sini.</p>
            </div>

            {{-- MOCKUP DASHBOARD --}}
            <div class="rounded-3xl border border-slate-200/80 bg-white p-5 shadow-card space-y-4">
                <div class="flex items-center justify-between border-b border-slate-100 pb-3">
                    <div class="flex items-center gap-1.5">
                        <span class="h-2.5 w-2.5 rounded-full bg-red-400"></span>
                        <span class="h-2.5 w-2.5 rounded-full bg-amber-400"></span>
                        <span class="h-2.5 w-2.5 rounded-full bg-green-400"></span>
                    </div>
                    <span class="text-[11px] font-medium text-slate-400">Tampilan Dashboard</span>
                </div>

                {{-- Header Mockup --}}
                <div class="flex items-center justify-between gap-3">
                    <div class="flex items-center gap-2.5">
                        <img x-show="logoPreviewUrl && !logoImageFailed" :src="logoPreviewUrl" alt="Logo {{ $organizationName }}" class="h-7 w-7 object-contain rounded-lg" x-on:error="logoImageFailed = true">
                        <span x-show="!logoPreviewUrl || logoImageFailed" class="h-7 w-7 rounded-lg flex items-center justify-center text-[9px] font-bold text-white" :style="`background-color: ${selectedHex}`">
                            {{ $organizationInitials ?: '' }}
                            @if(!$organizationInitials)<i class="fa-solid fa-building" aria-hidden="true"></i>@endif
                        </span>
                        <div class="h-3 w-16 rounded bg-slate-800"></div>
                    </div>
                    <div class="flex items-center gap-2">
                        <div class="h-6 w-20 rounded-full bg-slate-100"></div>
                        <div class="h-6 w-6 rounded-full flex items-center justify-center text-white text-[10px] font-bold"
                             :style="`background-color: ${selectedHex}`">A</div>
                    </div>
                </div>

                {{-- Body Mockup --}}
                <div class="grid grid-cols-3 gap-3">
                    {{-- Mini Sidebar --}}
                    <div class="col-span-1 space-y-2 rounded-2xl bg-slate-50 p-2.5">
                        <div class="h-4 rounded-lg flex items-center gap-1.5 px-2 text-[9px] font-semibold text-white"
                             :style="`background-color: ${selectedHex}`">
                            <span class="h-1.5 w-1.5 rounded-full bg-white"></span>
                        </div>
                        <div class="h-2.5 w-12 rounded bg-slate-200"></div>
                        <div class="h-2.5 w-14 rounded bg-slate-200"></div>
                        <div class="h-2.5 w-10 rounded bg-slate-200"></div>
                        <div class="mt-4 pt-2 border-t border-slate-200">
                            <div class="h-4 rounded-lg flex items-center justify-center text-[8px] font-semibold text-white"
                                 :style="`background-color: ${selectedHex}`">Logout</div>
                        </div>
                    </div>

                    {{-- Mini Content --}}
                    <div class="col-span-2 space-y-2.5">
                        <div class="h-3.5 w-24 rounded bg-slate-200"></div>
                        <div class="grid grid-cols-2 gap-2">
                            <div class="rounded-xl border border-slate-100 p-2 space-y-1">
                                <div class="h-2 w-8 rounded bg-slate-200"></div>
                                <div class="h-4 w-12 rounded" :style="`background-color: ${selectedHex}`"></div>
                            </div>
                            <div class="rounded-xl border border-slate-100 p-2 space-y-1">
                                <div class="h-2 w-8 rounded bg-slate-200"></div>
                                <div class="h-4 w-12 rounded bg-emerald-500"></div>
                            </div>
                        </div>
                        <div class="rounded-xl border border-slate-100 p-2 space-y-1.5">
                            <div class="h-2 w-full rounded bg-slate-100"></div>
                            <div class="h-2 w-3/4 rounded bg-slate-100"></div>
                            <div class="h-3 w-14 rounded" :style="`background-color: ${selectedHex}`"></div>
                        </div>
                    </div>
                </div>
            </div>

            {{-- MOCKUP LOGIN --}}
            <div class="rounded-3xl border border-slate-200/80 bg-white p-5 shadow-card space-y-4">
                <div class="flex items-center justify-between border-b border-slate-100 pb-3">
                    <div class="flex items-center gap-1.5">
                        <span class="h-2.5 w-2.5 rounded-full bg-red-400"></span>
                        <span class="h-2.5 w-2.5 rounded-full bg-amber-400"></span>
                        <span class="h-2.5 w-2.5 rounded-full bg-green-400"></span>
                    </div>
                    <span class="text-[11px] font-medium text-slate-400">Halaman Login</span>
                </div>

                <div class="flex flex-col items-center justify-center py-4 px-6 bg-slate-50 rounded-2xl">
                    <div class="w-full max-w-[200px] bg-white rounded-2xl p-4 shadow-sm border border-slate-100 space-y-3 text-center">
                        <img x-show="logoPreviewUrl && !logoImageFailed" :src="logoPreviewUrl" alt="Logo {{ $organizationName }}" class="h-8 w-8 object-contain mx-auto rounded-lg" x-on:error="logoImageFailed = true">
                        <span x-show="!logoPreviewUrl || logoImageFailed" class="h-8 w-8 rounded-lg mx-auto flex items-center justify-center text-[10px] font-bold text-white" :style="`background-color: ${selectedHex}`">
                            {{ $organizationInitials ?: '' }}
                            @if(!$organizationInitials)<i class="fa-solid fa-building" aria-hidden="true"></i>@endif
                        </span>
                        <div class="h-2.5 w-16 rounded bg-slate-800 mx-auto"></div>
                        <div class="space-y-1.5">
                            <div class="h-4 w-full rounded-lg bg-slate-100 border border-slate-200"></div>
                            <div class="h-4 w-full rounded-lg bg-slate-100 border border-slate-200"></div>
                        </div>
                        <div class="h-5 w-full rounded-lg flex items-center justify-center text-[9px] font-bold text-white shadow-sm"
                             :style="`background-color: ${selectedHex}`">Masuk</div>
                    </div>
                </div>
            </div>

        </div>

    </div>

    {{-- Form tersembunyi untuk submit data branding --}}
    <form x-ref="brandingForm" method="POST" action="{{ route('admin.tampilan-branding.simpan') }}" enctype="multipart/form-data" class="hidden">
        @csrf
        <input type="hidden" name="primary_color" :value="selectedHex">
        <input type="file" name="logo" x-ref="hiddenLogoInput" class="hidden">
    </form>
    @endif

    {{-- ======================================================== --}}
    {{-- TAB 2: LOKASI KANTOR & CABANG (DYNAMIC CRUD) --}}
    {{-- ======================================================== --}}
    @if($activeTab === 'lokasi')
    <div class="space-y-6">

        {{-- Top Description & Quick Guide --}}
        <div class="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm flex flex-col md:flex-row md:items-center justify-between gap-4">
            <div>
                <h2 class="text-lg font-bold text-slate-800">Manajemen Lokasi Kantor & Cabang</h2>
                <p class="text-xs text-slate-500 mt-1 leading-relaxed">
                    Setiap kantor cabang yang ditambahkan di sini akan <strong class="text-slate-700">otomatis muncul sebagai tab di Dashboard TV</strong> dan digunakan untuk verifikasi radius geo-location presensi pegawai.
                </p>
            </div>
            <div class="flex items-center gap-3 flex-shrink-0">
                <span class="text-xs font-bold text-slate-600 bg-slate-100 px-3.5 py-1.5 rounded-xl border border-slate-200">
                    Total: {{ count($daftarLokasi) }} Cabang
                </span>
            </div>
        </div>

        {{-- Location Cards Grid --}}
        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
            @forelse($daftarLokasi as $lokasi)
            <div class="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm hover:shadow-md transition flex flex-col justify-between space-y-5">
                <div>
                    {{-- Card Header --}}
                    <div class="flex items-start justify-between gap-3">
                        <div class="flex items-center gap-3">
                            <div class="h-12 w-12 rounded-2xl bg-blue-50 text-primary flex items-center justify-center font-bold text-xl flex-shrink-0">
                                <i class="fa-solid fa-building"></i>
                            </div>
                            <div>
                                <h3 class="text-base font-extrabold text-slate-900 leading-tight">{{ $lokasi->nama_kantor }}</h3>
                            </div>
                        </div>
                    </div>

                    {{-- Card Details --}}
                    <div class="mt-5 space-y-2.5 text-xs text-slate-600 divide-y divide-slate-50">
                        {{-- Coordinates --}}
                        <div class="pt-1 flex items-center justify-between">
                            <span class="text-slate-400 font-semibold flex items-center gap-1.5">
                                <i class="fa-solid fa-location-dot text-slate-400"></i> Koordinat GPS
                            </span>
                            <a href="https://www.google.com/maps?q={{ $lokasi->latitude }},{{ $lokasi->longitude }}" target="_blank" class="font-mono font-bold text-primary hover:underline flex items-center gap-1">
                                <span>{{ number_format((float)$lokasi->latitude, 4) }}, {{ number_format((float)$lokasi->longitude, 4) }}</span>
                                <i class="fa-solid fa-arrow-up-right-from-square text-[10px]"></i>
                            </a>
                        </div>

                        {{-- Radius --}}
                        <div class="pt-2 flex items-center justify-between">
                            <span class="text-slate-400 font-semibold flex items-center gap-1.5">
                                <i class="fa-solid fa-circle-notch text-slate-400"></i> Radius Kehadiran
                            </span>
                            <span class="font-bold text-slate-800">{{ $lokasi->radius_meter }} Meter</span>
                        </div>


                    </div>
                </div>

                {{-- Action Buttons --}}
                <div class="pt-3 border-t border-slate-100 flex items-center justify-end gap-2">
                    @if($canLokasi)
                        <button type="button"
                                @click="openModalEdit({{ json_encode($lokasi) }})"
                                class="px-3.5 py-1.5 rounded-xl text-xs font-bold text-primary hover:bg-blue-50 transition flex items-center gap-1.5">
                            <i class="fa-solid fa-pen-to-square"></i>
                            <span>Edit</span>
                        </button>

                        <button type="button"
                                @click="confirmDeleteLokasi({{ $lokasi->lokasi_id }}, '{{ addslashes($lokasi->nama_kantor) }}')"
                                class="px-3.5 py-1.5 rounded-xl text-xs font-bold text-rose-600 hover:bg-rose-50 transition flex items-center gap-1.5">
                            <i class="fa-solid fa-trash"></i>
                            <span>Hapus</span>
                        </button>
                    @else
                        <button type="button" disabled title="Anda tidak memiliki hak akses untuk mengubah lokasi cabang"
                                class="px-3.5 py-1.5 rounded-xl text-xs font-bold text-slate-300 opacity-60 cursor-not-allowed select-none flex items-center gap-1.5">
                            <i class="fa-solid fa-pen-to-square"></i>
                            <span>Edit</span>
                        </button>

                        <button type="button" disabled title="Anda tidak memiliki hak akses untuk menghapus lokasi cabang"
                                class="px-3.5 py-1.5 rounded-xl text-xs font-bold text-slate-300 opacity-60 cursor-not-allowed select-none flex items-center gap-1.5">
                            <i class="fa-solid fa-trash"></i>
                            <span>Hapus</span>
                        </button>
                    @endif
                </div>
            </div>
            @empty
            <div class="col-span-full rounded-2xl border-2 border-dashed border-slate-200 bg-white p-12 text-center">
                <div class="h-16 w-16 mx-auto rounded-full bg-slate-50 flex items-center justify-center text-slate-300 text-2xl mb-3">
                    <i class="fa-solid fa-building-circle-xmark"></i>
                </div>
                <h4 class="text-base font-bold text-slate-700">Belum Ada Kantor Cabang</h4>
                <p class="text-xs text-slate-400 mt-1 max-w-sm mx-auto">Tambahkan kantor cabang baru agar presensi geo-location dan Dashboard TV otomatis terkonfigurasi.</p>
                <button type="button" @click="openModalTambah()" class="mt-4 px-5 py-2 rounded-xl bg-primary text-white text-xs font-bold shadow-sm">
                    + Tambah Kantor Cabang Pertama
                </button>
            </div>
            @endforelse
    </div>
    @endif

    {{-- ======================================================== --}}
    {{-- TAB 3: ROLE & HAK AKSES (PRIVILEGE MANAGEMENT)          --}}
    {{-- ======================================================== --}}
    @if(in_array($activeTab, ['roles', 'role-akses']))
    <div class="space-y-8">

        {{-- Top Description Card --}}
        <div class="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm flex flex-col md:flex-row md:items-center justify-between gap-4">
            <div>
                <h2 class="text-lg font-bold text-slate-800 flex items-center gap-2.5">
                    <span class="h-8 w-8 rounded-xl bg-blue-50 text-primary flex items-center justify-center text-sm">
                        <i class="fa-solid fa-shield-halved"></i>
                    </span>
                    <span>Manajemen Role & Hak Akses (Privilege)</span>
                </h2>
                <p class="text-xs text-slate-500 mt-1 leading-relaxed">
                    Atur kewenangan fitur Web Admin untuk setiap kelompok role pengguna. Pilih role di bawah untuk melihat dan mengonfigurasi privilege yang diizinkan.
                </p>
            </div>
            <div class="flex items-center gap-2 flex-wrap flex-shrink-0">
                <span class="text-xs font-bold text-slate-600 bg-slate-100 px-3.5 py-1.5 rounded-xl border border-slate-200">
                    Total: {{ count($daftarRole) + 1 }} Role Master
                </span>
                <span class="text-xs font-bold text-primary bg-blue-50 px-3.5 py-1.5 rounded-xl border border-blue-100">
                    {{ $daftarPrivilege->flatten()->count() }} Fitur Privilege
                </span>
            </div>
        </div>

        {{-- Role Selector Cards (Horizontal Grid) --}}
        <div>
            <label class="block text-xs font-bold text-slate-400 uppercase tracking-wider mb-3">Pilih Role Pengguna :</label>
            <div class="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-3 lg:grid-cols-4 gap-4">
                <div class="relative flex flex-col justify-between p-5 rounded-2xl border border-slate-200 bg-slate-50 text-left overflow-hidden min-w-0 opacity-80"
                     aria-disabled="true" title="Anggota tidak memiliki akses Web Admin">
                    <div class="min-w-0">
                        <div class="flex items-center justify-between gap-2 mb-3">
                            <span class="h-10 w-10 rounded-xl flex items-center justify-center text-base bg-slate-200 text-slate-600 flex-shrink-0">
                                <i class="fa-solid fa-users" aria-hidden="true"></i>
                            </span>
                            <span class="text-[11px] font-bold px-2.5 py-1 rounded-lg bg-white text-slate-600 border border-slate-200">
                                {{ $jumlahAnggota ?? 0 }} Akun
                            </span>
                        </div>
                        <h3 class="text-base font-bold text-slate-900">Anggota</h3>
                        <p class="text-xs text-slate-500 mt-1">Untuk login aplikasi mobile dan presensi. Tidak memiliki akses Web Admin.</p>
                    </div>
                    <div class="mt-4 pt-3 border-t border-slate-200/60 flex items-center justify-between">
                        <span class="text-xs font-semibold text-slate-500">Akses Web Admin: Tidak ada</span>
                        <span class="text-xs font-bold text-slate-500">Nonaktif</span>
                    </div>
                </div>
                @foreach($daftarRole as $roleItem)
                @php
                    $isSuper = strcasecmp($roleItem->nama_role, 'Super Admin') === 0;
                    $isHR = strcasecmp($roleItem->nama_role, 'HR / HRD') === 0;
                    $isDir = strcasecmp($roleItem->nama_role, 'Direktur') === 0;
                    $isPegawai = strcasecmp($roleItem->nama_role, 'Pegawai') === 0;
                    $roleIcon = $isSuper ? 'fa-shield-halved' : ($isHR ? 'fa-user-tie' : ($isDir ? 'fa-building-user' : ($isPegawai ? 'fa-users' : 'fa-id-badge')));
                @endphp
                <div @click="selectRole({{ $roleItem->role_id }})"
                     class="relative flex flex-col justify-between p-5 rounded-2xl border text-left transition-all duration-200 hover:shadow-md cursor-pointer overflow-hidden min-w-0"
                     :class="currentRoleId === {{ $roleItem->role_id }} ? 'bg-blue-50/70 border-primary ring-2 ring-primary/20 shadow-sm' : 'bg-white border-slate-200 hover:border-slate-300'">
                    
                    <div class="min-w-0">
                        <div class="flex items-center justify-between gap-2 mb-3">
                            <span class="h-10 w-10 rounded-xl flex items-center justify-center text-base flex-shrink-0"
                                  :class="currentRoleId === {{ $roleItem->role_id }} ? 'bg-primary text-white shadow-sm' : 'bg-slate-100 text-slate-600'">
                                <i class="fa-solid {{ $roleIcon }}"></i>
                            </span>
                            <div class="flex items-center gap-2 flex-shrink-0">
                                <span class="text-[11px] font-bold px-2.5 py-1 rounded-lg"
                                      :class="currentRoleId === {{ $roleItem->role_id }} ? 'bg-primary/10 text-primary' : 'bg-slate-100 text-slate-600'">
                                    {{ $roleItem->akun_count ?? 0 }} Akun
                                </span>
                                @if($canRoles)
                                <button type="button"
                                        @click.stop="confirmDeleteRole({{ $roleItem->role_id }}, '{{ addslashes($roleItem->nama_role) }}', {{ $roleItem->akun_count ?? 0 }})"
                                        title="Hapus Role {{ $roleItem->nama_role }}"
                                        class="h-8 w-8 rounded-xl bg-rose-50 hover:bg-rose-100 text-rose-600 hover:text-rose-700 border border-rose-100 flex items-center justify-center transition">
                                    <i class="fa-solid fa-trash-can text-xs"></i>
                                </button>
                                @else
                                <button type="button" disabled title="Anda tidak memiliki hak akses untuk menghapus role"
                                        class="h-8 w-8 rounded-xl bg-slate-50 border border-slate-100 text-slate-300 opacity-60 cursor-not-allowed flex items-center justify-center">
                                    <i class="fa-solid fa-trash-can text-xs"></i>
                                </button>
                                @endif
                            </div>
                        </div>

                        <h3 class="text-base font-bold text-slate-900 leading-snug break-words hyphens-auto line-clamp-2" title="{{ $roleItem->nama_role }}">{{ $roleItem->nama_role }}</h3>
                        <p class="text-xs text-slate-500 mt-1 break-words line-clamp-3 overflow-hidden">
                            @if($isSuper)
                            Akses sistem penuh dan konfigurasi master.
                            @elseif($isHR)
                            Manajemen pegawai, pengajuan, & kehadiran.
                            @elseif($isDir)
                            Monitoring laporan eksekutif & persetujuan.
                            @elseif($isPegawai)
                            Akses standar operasional kehadiran pegawai.
                            @else
                            {{ $roleItem->deskripsi ?: 'Role pengguna kustom.' }}
                            @endif
                        </p>
                    </div>

                    <div class="mt-4 pt-3 border-t border-slate-200/60 flex items-center justify-between">
                        <span class="text-xs font-semibold text-slate-600"
                              x-text="getRolePrivilegeCount({{ $roleItem->role_id }}) + ' / {{ $daftarPrivilege->flatten()->count() }} Hak Akses'">
                            {{ $roleItem->privileges->count() }} / {{ $daftarPrivilege->flatten()->count() }} Hak Akses
                        </span>
                        <span x-show="currentRoleId === {{ $roleItem->role_id }}" class="text-primary text-xs font-bold flex items-center gap-1">
                            <span>Aktif</span>
                            <i class="fa-solid fa-circle-check text-xs"></i>
                        </span>
                    </div>
                </div>
                @endforeach
            </div>
        </div>

        {{-- Active Role Privilege Editor Form --}}
        @php
            $corePrivId = \App\Models\Privilege::where('nama_privilege', 'kelola_role_hak_akses')->value('privilege_id') ?? 16;
            $allPrivIds = $daftarPrivilege->flatten()->pluck('privilege_id')->toArray();
        @endphp

        <form method="POST" action="{{ route('admin.settings.roles.simpan') }}" @submit="if (isSavingPrivileges) { $event.preventDefault(); return false; } isSavingPrivileges = true;" class="space-y-6">
            @csrf
            <input type="hidden" name="role_id" :value="currentRoleId">

            {{-- Role Header & Quick Controls --}}
            <div class="rounded-3xl border border-slate-200 bg-white p-6 sm:p-7 shadow-card">
                <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4 pb-6 border-b border-slate-100">
                    <div>
                        <div class="flex items-center gap-2.5">
                            <span class="px-3 py-1 rounded-xl bg-primary text-white text-xs font-bold">Role Terpilih</span>
                            <h3 class="text-xl font-bold text-slate-900" x-text="getCurrentRoleName()"></h3>
                        </div>
                        <p class="text-xs text-slate-500 mt-1">
                            Centang fitur-fitur yang diizinkan untuk diakses oleh role <strong class="text-slate-800 font-bold" x-text="getCurrentRoleName()"></strong>.
                        </p>
                    </div>

                    {{-- Quick Action Buttons --}}
                    <div class="flex items-center gap-2.5 flex-wrap">
                        <button type="button"
                                @click="selectAllPrivileges({{ json_encode($allPrivIds) }})"
                                class="inline-flex items-center gap-1.5 px-4 py-2 rounded-xl border border-slate-200 bg-slate-50 hover:bg-slate-100 text-slate-700 text-xs font-bold transition">
                            <i class="fa-solid fa-check-double text-xs text-primary"></i>
                            <span>Pilih Semua</span>
                        </button>
                        <button type="button"
                                @click="unselectAllPrivileges({{ $corePrivId }})"
                                class="inline-flex items-center gap-1.5 px-4 py-2 rounded-xl border border-slate-200 bg-slate-50 hover:bg-slate-100 text-slate-700 text-xs font-bold transition">
                            <i class="fa-solid fa-xmark text-xs text-rose-500"></i>
                            <span>Batalkan Semua</span>
                        </button>
                    </div>
                </div>

                {{-- Super Admin Lock Notice --}}
                <div x-show="isSuperAdminActive()" class="mt-4 p-4 rounded-2xl bg-amber-50 border border-amber-200 flex items-start gap-3">
                    <i class="fa-solid fa-triangle-exclamation text-amber-600 text-base mt-0.5 flex-shrink-0"></i>
                    <div class="text-xs text-amber-900 leading-relaxed">
                        <strong class="font-bold">Proteksi Akses Super Admin:</strong> Hak akses <em>"Kelola Role & Hak Akses"</em> dilindungi secara otomatis agar Super Admin tidak dapat kehilangan wewenang pengaturan sistem.
                    </div>
                </div>

                {{-- Categories Grid --}}
                <div class="mt-6 grid grid-cols-1 lg:grid-cols-2 gap-6">
                    @php
                        $categoryIcons = [
                            'Dashboard' => 'fa-chart-pie',
                            'Laporan Kehadiran' => 'fa-file-invoice',
                            'Manajemen Akun' => 'fa-users-gear',
                            'Persetujuan' => 'fa-clipboard-check',
                            'Log Aktivitas' => 'fa-clock-rotate-left',
                            'Jadwal Kerja' => 'fa-calendar-days',
                            'Settings' => 'fa-sliders',
                        ];
                    @endphp

                    @foreach($daftarPrivilege as $kategori => $privs)
                    @php
                        $catPrivIds = $privs->pluck('privilege_id')->toArray();
                        $iconClass = $categoryIcons[$kategori] ?? 'fa-cube';
                    @endphp
                    <div class="rounded-2xl border border-slate-200 bg-slate-50/50 p-5 space-y-4">
                        
                        {{-- Category Header --}}
                        <div class="flex items-center justify-between pb-3 border-b border-slate-200/80">
                            <div class="flex items-center gap-2.5">
                                <span class="h-7 w-7 rounded-lg bg-white shadow-xs border border-slate-200 text-primary flex items-center justify-center text-xs">
                                    <i class="fa-solid {{ $iconClass }}"></i>
                                </span>
                                <h4 class="text-sm font-bold text-slate-800">{{ $kategori }}</h4>
                            </div>
                            <button type="button"
                                    @click="toggleCategoryPrivileges({{ json_encode($catPrivIds) }}, {{ $corePrivId }})"
                                    class="text-[11px] font-bold text-primary hover:text-primary/80 transition">
                                <span x-text="isCategoryFullySelected({{ json_encode($catPrivIds) }}) ? 'Batal Semua' : 'Pilih Semua'"></span>
                            </button>
                        </div>

                        {{-- Privilege Items Checklist --}}
                        <div class="space-y-2.5">
                            @foreach($privs as $priv)
                            @php
                                $isCore = $priv->nama_privilege === 'kelola_role_hak_akses';
                            @endphp
                            <label class="flex items-start gap-3.5 p-3 rounded-xl border transition-all cursor-pointer select-none"
                                   :class="isPrivilegeChecked({{ $priv->privilege_id }}) ? 'bg-white border-primary/40 shadow-xs ring-1 ring-primary/10' : 'bg-white/60 border-slate-200/80 hover:bg-white hover:border-slate-300'">
                                
                                {{-- Checkbox --}}
                                <div class="relative flex items-center pt-0.5">
                                    <input type="checkbox"
                                           name="privilege_ids[]"
                                           value="{{ $priv->privilege_id }}"
                                           :checked="isPrivilegeChecked({{ $priv->privilege_id }})"
                                           @change="togglePrivilegeCheck({{ $priv->privilege_id }})"
                                           :disabled="isSuperAdminActive() && {{ $isCore ? 'true' : 'false' }}"
                                           class="h-4 w-4 rounded border-slate-300 text-primary focus:ring-primary">
                                    
                                    {{-- Hidden input fallback for locked Super Admin core privilege --}}
                                    @if($isCore)
                                    <template x-if="isSuperAdminActive()">
                                        <input type="hidden" name="privilege_ids[]" value="{{ $priv->privilege_id }}">
                                    </template>
                                    @endif
                                </div>

                                {{-- Text & Description --}}
                                <div class="flex-1 min-w-0">
                                    <div class="flex items-center gap-2 flex-wrap">
                                        <span class="text-xs font-bold text-slate-800">{{ $priv->label_privilege }}</span>
                                        @if($isCore)
                                        <span x-show="isSuperAdminActive()" class="text-[9px] font-bold text-amber-700 bg-amber-100 px-1.5 py-0.5 rounded">Terkunci</span>
                                        @endif
                                    </div>
                                    @if($priv->deskripsi)
                                    <p class="text-[11px] text-slate-500 mt-0.5 leading-relaxed">{{ $priv->deskripsi }}</p>
                                    @endif
                                </div>
                            </label>
                            @endforeach
                        </div>

                    </div>
                    @endforeach
                </div>

                {{-- Submit Bar --}}
                <div class="mt-8 pt-6 border-t border-slate-100 flex flex-col sm:flex-row sm:items-center justify-between gap-4">
                    <div class="flex items-center gap-2 text-xs text-slate-500 font-medium">
                        <i class="fa-solid fa-circle-info text-primary"></i>
                        <span>Privilege yang tersimpan akan langsung terhubung ke master data role di database.</span>
                    </div>

                    <div class="flex items-center gap-3">
                        @if($canRoles)
                            <button type="submit"
                                    :disabled="isSavingPrivileges"
                                    :class="isSavingPrivileges ? 'opacity-75 cursor-not-allowed' : 'hover:bg-primary/90 active:scale-[0.98]'"
                                    class="w-full sm:w-auto inline-flex items-center justify-center gap-2 px-7 py-3 rounded-2xl bg-primary text-white font-bold text-sm shadow-md transition">
                                <svg x-show="isSavingPrivileges" x-cloak class="h-4 w-4 animate-spin text-white" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                                    <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                    <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                                </svg>
                                <i x-show="!isSavingPrivileges" class="fa-solid fa-floppy-disk text-sm"></i>
                                <span x-text="isSavingPrivileges ? 'Menyimpan...' : 'Simpan Hak Akses Role'">Simpan Hak Akses Role</span>
                            </button>
                        @else
                            <button type="button" disabled title="Anda tidak memiliki hak akses untuk mengelola role & hak akses"
                                    class="w-full sm:w-auto inline-flex items-center justify-center gap-2 px-7 py-3 rounded-2xl bg-slate-200 text-slate-400 font-bold text-sm opacity-60 cursor-not-allowed select-none shadow-none">
                                <i class="fa-solid fa-floppy-disk text-sm"></i>
                                <span>Simpan Hak Akses Role</span>
                            </button>
                        @endif
                    </div>
                </div>

            </div>
        </form>

    </div>
    @endif

    {{-- ======================================================== --}}
    {{-- TELEPORTED MODALS --}}
    {{-- ======================================================== --}}

    {{-- MODAL 1: COLOR PICKER (FOR BRANDING TAB) --}}
    <template x-teleport="body">
        <div x-show="colorPickerOpen" x-cloak class="fixed inset-0 z-[9999] flex items-center justify-center" aria-modal="true" role="dialog">
            <div @click="colorPickerOpen = false" x-show="colorPickerOpen" x-transition.opacity class="fixed inset-0 bg-black/50 backdrop-blur-sm"></div>
            <div x-show="colorPickerOpen"
                 x-transition:enter="transition ease-out duration-200"
                 x-transition:enter-start="opacity-0 scale-95"
                 x-transition:enter-end="opacity-100 scale-100"
                 x-transition:leave="transition ease-in duration-150"
                 x-transition:leave-start="opacity-100 scale-100"
                 x-transition:leave-end="opacity-0 scale-95"
                 class="relative z-50 w-full max-w-sm rounded-3xl bg-white p-6 shadow-2xl space-y-4">
                <div class="flex items-center justify-between">
                    <p class="text-base font-bold text-slate-900">Pilih Warna</p>
                    <button @click="colorPickerOpen = false" class="text-slate-400 hover:text-slate-600">
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                        </svg>
                    </button>
                </div>

                {{-- Saturation/Value Area --}}
                <div class="relative h-44 w-full cursor-crosshair rounded-2xl overflow-hidden shadow-inner"
                     x-ref="svArea"
                     :style="`background-color: ${hueColor}; background-image: linear-gradient(to right, #fff, transparent), linear-gradient(to top, #000, transparent);`"
                     @mousedown="startDragSV"
                     @mousemove.window="dragSV"
                     @mouseup.window="stopDragSV">
                    <div class="pointer-events-none absolute h-4 w-4 -translate-x-1/2 -translate-y-1/2 rounded-full border-2 border-white shadow-md"
                         :style="`left: ${saturation}%; top: ${100 - value}%; background-color: ${tempHex};`"></div>
                </div>

                {{-- Hue Slider --}}
                <div class="relative h-4 w-full cursor-pointer rounded-full"
                     x-ref="hueArea"
                     style="background: linear-gradient(to right, #f00 0%, #ff0 17%, #0f0 33%, #0ff 50%, #00f 67%, #f0f 83%, #f00 100%);"
                     @mousedown="startDragHue"
                     @mousemove.window="dragHue"
                     @mouseup.window="stopDragHue">
                    <div class="pointer-events-none absolute top-1/2 h-5 w-5 -translate-x-1/2 -translate-y-1/2 rounded-full border-2 border-white bg-white shadow"
                         :style="`left: ${(hue / 360) * 100}%;`"></div>
                </div>

                {{-- HEX Input & Preview --}}
                <div class="flex items-center gap-3">
                    <div class="h-10 w-10 flex-shrink-0 rounded-xl border border-slate-200 shadow-sm"
                         :style="`background-color: ${tempHex}`"></div>
                    <div class="relative flex-1">
                        <span class="absolute left-3 top-1/2 -translate-y-1/2 font-mono text-sm text-slate-400">#</span>
                        <input type="text"
                               :value="tempHex.replace('#', '')"
                               @input="tempHex = '#' + $event.target.value.toUpperCase(); updateHsvFromHex()"
                               maxlength="6"
                               class="w-full rounded-xl border border-slate-200 pl-7 pr-3 py-2 font-mono text-sm font-bold uppercase text-slate-800 focus:border-primary focus:outline-none focus:ring-1 focus:ring-primary">
                    </div>
                </div>

                {{-- Quick Swatches in Picker --}}
                <div class="pt-1">
                    <p class="text-xs font-semibold text-slate-400 mb-2">Preset Cepat:</p>
                    <div class="flex items-center gap-2 flex-wrap">
                        @foreach(['#123D91','#1D4ED8','#059669','#D97706','#DC2626','#7C3AED','#0F172A','#0891B2'] as $c)
                        <div @click="quickColor('{{ $c }}')"
                             class="h-7 w-7 cursor-pointer rounded-full border-2 border-white shadow transition hover:scale-110"
                             style="background-color: {{ $c }}"></div>
                        @endforeach
                    </div>
                </div>

                <div class="flex items-center justify-center gap-3 pt-2">
                    <button @click="colorPickerOpen = false" class="w-1/2 rounded-xl border border-slate-300 bg-white px-4 py-2.5 text-xs font-semibold text-slate-700 transition hover:bg-slate-50">Batal</button>
                    <button @click="applyColor()" class="w-1/2 rounded-xl px-4 py-2.5 text-xs font-semibold text-white shadow transition hover:opacity-90" :style="`background-color: ${tempHex}`">Terapkan</button>
                </div>
            </div>
        </div>
    </template>

    {{-- MODAL 2: CONFIRM RESET BRANDING --}}
    <template x-teleport="body">
        <div x-show="confirmReset" x-cloak class="fixed inset-0 z-[9999] flex items-center justify-center" aria-modal="true" role="dialog">
            <div @click="confirmReset = false" x-show="confirmReset" x-transition.opacity class="fixed inset-0 bg-black/50 backdrop-blur-sm"></div>
            <div x-show="confirmReset"
                 x-transition:enter="transition ease-out duration-200"
                 x-transition:enter-start="opacity-0 scale-95"
                 x-transition:enter-end="opacity-100 scale-100"
                 x-transition:leave="transition ease-in duration-150"
                 x-transition:leave-start="opacity-100 scale-100"
                 x-transition:leave-end="opacity-0 scale-95"
                 class="relative z-50 w-full max-w-sm rounded-3xl bg-white p-8 text-center shadow-2xl">
                <div class="mx-auto mb-5 flex h-16 w-16 items-center justify-center rounded-full bg-red-100">
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-8 w-8 text-red-500" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/>
                    </svg>
                </div>
                <h3 class="mb-2 text-lg font-bold text-slate-900">Reset ke Default?</h3>
                <p class="mb-6 text-sm text-slate-500">Logo dan warna akan dikembalikan ke pengaturan awal. Tindakan ini tidak dapat dibatalkan.</p>
                <div class="flex items-center justify-center gap-3">
                    <button @click="confirmReset = false" class="w-1/2 rounded-xl border border-slate-200 px-4 py-2.5 text-xs font-semibold text-slate-600 transition hover:bg-slate-50">Batal</button>
                    <form method="POST" action="{{ route('admin.tampilan-branding.reset') }}" class="w-1/2 inline">
                        @csrf
                        <button type="submit" class="w-full rounded-xl bg-red-500 px-4 py-2.5 text-xs font-semibold text-white transition hover:bg-red-600">Ya, Reset</button>
                    </form>
                </div>
            </div>
        </div>
    </template>

    {{-- MODAL 3: TAMBAH / EDIT KANTOR CABANG --}}
    <template x-teleport="body">
        <div x-show="showLokasiModal"
             x-transition.opacity.duration.300ms
             class="fixed inset-0 z-[9999] flex items-center justify-center bg-slate-900/60 backdrop-blur-sm p-4 overflow-y-auto"
             x-cloak
             style="display: none;">
            <div @click.away="showLokasiModal = false; lokasiValidationError = ''; hasServerLokasiErrors = false;"
                 class="bg-white rounded-3xl shadow-2xl max-w-lg w-full p-7 relative border border-slate-100 my-auto">
                
                <div class="flex items-center justify-between pb-4 border-b border-slate-100">
                    <div class="flex items-center gap-3">
                        <div class="h-10 w-10 rounded-xl bg-blue-50 text-primary flex items-center justify-center text-lg">
                            <i class="fa-solid fa-building"></i>
                        </div>
                        <div>
                            <h3 class="text-lg font-bold text-slate-900" x-text="isEdit ? 'Edit Kantor Cabang' : 'Tambah Kantor Cabang Baru'"></h3>
                            <p class="text-xs text-slate-400 mt-0.5">Konfigurasi koordinat GPS dan radius presensi kehadiran</p>
                        </div>
                    </div>
                    <button @click="showLokasiModal = false; lokasiValidationError = ''; hasServerLokasiErrors = false;" class="text-slate-400 hover:text-slate-600 p-1.5 rounded-full hover:bg-slate-50 transition">
                        <i class="fa-solid fa-xmark text-lg"></i>
                    </button>
                </div>

                <form action="{{ route('admin.settings.lokasi.simpan') }}" method="POST" novalidate
                      @submit="
                        lokasiValidationError = '';
                        if (!formLokasi.nama_kantor || !formLokasi.nama_kantor.trim()) {
                            $event.preventDefault();
                            lokasiValidationError = 'Nama kantor cabang wajib diisi.';
                            return false;
                        }
                        if (formLokasi.nama_kantor.trim().length > 100) {
                            $event.preventDefault();
                            lokasiValidationError = 'Nama kantor cabang maksimal 100 karakter.';
                            return false;
                        }
                        if (formLokasi.latitude === '' || formLokasi.latitude === null || isNaN(formLokasi.latitude)) {
                            $event.preventDefault();
                            lokasiValidationError = 'Latitude koordinat wajib diisi dengan format angka desimal.';
                            return false;
                        }
                        let lat = parseFloat(formLokasi.latitude);
                        if (lat < -90 || lat > 90) {
                            $event.preventDefault();
                            lokasiValidationError = 'Latitude harus berada dalam rentang -90 sampai 90 derajat.';
                            return false;
                        }
                        if (formLokasi.longitude === '' || formLokasi.longitude === null || isNaN(formLokasi.longitude)) {
                            $event.preventDefault();
                            lokasiValidationError = 'Longitude koordinat wajib diisi dengan format angka desimal.';
                            return false;
                        }
                        let lng = parseFloat(formLokasi.longitude);
                        if (lng < -180 || lng > 180) {
                            $event.preventDefault();
                            lokasiValidationError = 'Longitude harus berada dalam rentang -180 sampai 180 derajat.';
                            return false;
                        }
                        if (formLokasi.radius_meter === '' || formLokasi.radius_meter === null || isNaN(formLokasi.radius_meter) || parseInt(formLokasi.radius_meter) < 1) {
                            $event.preventDefault();
                            lokasiValidationError = 'Radius presensi wajib diisi minimal 1 meter.';
                            return false;
                        }
                        if (isSavingLokasi) {
                            $event.preventDefault();
                            return false;
                        }
                        isSavingLokasi = true;
                      "
                      class="mt-5 space-y-4">
                    @csrf
                    <input type="hidden" name="lokasi_id" x-model="formLokasi.lokasi_id">

                    {{-- Client-side & Backend Validation Error Box inside Modal --}}
                    <div x-show="lokasiValidationError" x-cloak class="p-3.5 rounded-xl bg-rose-50 border border-rose-200 text-xs text-rose-700 flex items-center gap-2.5">
                        <i class="fa-solid fa-circle-exclamation text-rose-500 text-sm flex-shrink-0"></i>
                        <span x-text="lokasiValidationError"></span>
                    </div>

                    @if($errors->lokasi_kantor->any())
                    <div x-show="hasServerLokasiErrors" x-cloak class="p-3.5 rounded-xl bg-rose-50 border border-rose-200 text-xs text-rose-700 flex items-center gap-2.5">
                        <i class="fa-solid fa-circle-exclamation text-rose-500 text-sm flex-shrink-0"></i>
                        <span>{{ $errors->lokasi_kantor->first() }}</span>
                    </div>
                    @endif

                    {{-- Nama Kantor --}}
                    <div>
                        <div class="flex items-center justify-between">
                            <label class="text-xs font-bold text-slate-700">Nama Kantor Cabang <span class="text-rose-500">*</span></label>
                            <span class="text-[11px] font-semibold text-slate-400" x-text="(formLokasi.nama_kantor ? formLokasi.nama_kantor.length : 0) + '/100'"></span>
                        </div>
                        <input type="text" name="nama_kantor" x-model="formLokasi.nama_kantor" maxlength="100"
                               @input="lokasiValidationError = ''; hasServerLokasiErrors = false;"
                               placeholder="Contoh: Kantor Cabang Jakarta"
                               class="mt-1.5 w-full rounded-xl border border-slate-200 px-3.5 py-2.5 text-sm font-semibold text-slate-800 focus:border-primary focus:outline-none focus:ring-1 focus:ring-primary"
                               :class="(lokasiValidationError || hasServerLokasiErrors) ? 'border-rose-300 ring-1 ring-rose-300' : ''">
                        @error('nama_kantor', 'lokasi_kantor')
                        <p x-show="hasServerLokasiErrors" x-cloak class="text-xs text-rose-500 font-semibold mt-1.5 flex items-center gap-1">
                            <i class="fa-solid fa-circle-exclamation text-[11px]"></i>
                            <span>{{ $message }}</span>
                        </p>
                        @enderror
                    </div>

                    {{-- Latitude & Longitude --}}
                    <div class="grid grid-cols-2 gap-3">
                        <div>
                            <label class="text-xs font-bold text-slate-700">Latitude <span class="text-rose-500">*</span></label>
                            <input type="text" name="latitude" x-model="formLokasi.latitude"
                                   @input="lokasiValidationError = ''; hasServerLokasiErrors = false;"
                                   placeholder="Contoh: -6.910194"
                                   class="mt-1.5 w-full rounded-xl border border-slate-200 px-3.5 py-2.5 text-sm font-mono text-slate-800 focus:border-primary focus:outline-none focus:ring-1 focus:ring-primary"
                                   :class="(lokasiValidationError || hasServerLokasiErrors) ? 'border-rose-300 ring-1 ring-rose-300' : ''">
                            @error('latitude', 'lokasi_kantor')
                            <p x-show="hasServerLokasiErrors" x-cloak class="text-xs text-rose-500 font-semibold mt-1.5 flex items-center gap-1">
                                <i class="fa-solid fa-circle-exclamation text-[11px]"></i>
                                <span>{{ $message }}</span>
                            </p>
                            @enderror
                        </div>
                        <div>
                            <label class="text-xs font-bold text-slate-700">Longitude <span class="text-rose-500">*</span></label>
                            <input type="text" name="longitude" x-model="formLokasi.longitude"
                                   @input="lokasiValidationError = ''; hasServerLokasiErrors = false;"
                                   placeholder="Contoh: 107.650728"
                                   class="mt-1.5 w-full rounded-xl border border-slate-200 px-3.5 py-2.5 text-sm font-mono text-slate-800 focus:border-primary focus:outline-none focus:ring-1 focus:ring-primary"
                                   :class="(lokasiValidationError || hasServerLokasiErrors) ? 'border-rose-300 ring-1 ring-rose-300' : ''">
                            @error('longitude', 'lokasi_kantor')
                            <p x-show="hasServerLokasiErrors" x-cloak class="text-xs text-rose-500 font-semibold mt-1.5 flex items-center gap-1">
                                <i class="fa-solid fa-circle-exclamation text-[11px]"></i>
                                <span>{{ $message }}</span>
                            </p>
                            @enderror
                        </div>
                    </div>

                    {{-- Radius Meter --}}
                    <div>
                        <label class="text-xs font-bold text-slate-700">Radius Presensi (Meter) <span class="text-rose-500">*</span></label>
                        <input type="number" name="radius_meter" x-model="formLokasi.radius_meter" min="1" max="50000"
                               @input="lokasiValidationError = ''; hasServerLokasiErrors = false;"
                               placeholder="Contoh: 100"
                               class="mt-1.5 w-full rounded-xl border border-slate-200 px-3.5 py-2.5 text-sm font-semibold text-slate-800 focus:border-primary focus:outline-none focus:ring-1 focus:ring-primary"
                               :class="(lokasiValidationError || hasServerLokasiErrors) ? 'border-rose-300 ring-1 ring-rose-300' : ''">
                        @error('radius_meter', 'lokasi_kantor')
                        <p x-show="hasServerLokasiErrors" x-cloak class="text-xs text-rose-500 font-semibold mt-1.5 flex items-center gap-1">
                            <i class="fa-solid fa-circle-exclamation text-[11px]"></i>
                            <span>{{ $message }}</span>
                        </p>
                        @enderror
                    </div>

                    {{-- Submit Buttons --}}
                    <div class="pt-4 border-t border-slate-100 flex items-center justify-end gap-3">
                        <button type="button" :disabled="isSavingLokasi" @click="showLokasiModal = false; lokasiValidationError = ''; hasServerLokasiErrors = false;" :class="isSavingLokasi ? 'opacity-50 cursor-not-allowed' : ''" class="px-4 py-2.5 rounded-xl text-xs font-bold text-slate-500 hover:bg-slate-50 transition">
                            Batal
                        </button>
                        <button type="submit" :disabled="isSavingLokasi" :class="isSavingLokasi ? 'opacity-75 cursor-not-allowed' : 'hover:bg-primary/90'" class="px-6 py-2.5 rounded-xl bg-primary text-white text-xs font-bold shadow-md transition flex items-center gap-1.5">
                            <svg x-show="isSavingLokasi" x-cloak class="h-3.5 w-3.5 animate-spin text-white" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                            </svg>
                            <i x-show="!isSavingLokasi" class="fa-solid fa-floppy-disk text-xs"></i>
                            <span x-text="isSavingLokasi ? 'Menyimpan...' : 'Simpan Kantor Cabang'">Simpan Kantor Cabang</span>
                        </button>
                    </div>
                </form>

            </div>
        </div>
    </template>

    {{-- MODAL 4: CUSTOM CONFIRM DELETE KANTOR CABANG --}}
    <template x-teleport="body">
        <div x-show="showDeleteLokasiModal"
             x-transition.opacity.duration.300ms
             class="fixed inset-0 z-[9999] flex items-center justify-center bg-slate-900/60 backdrop-blur-sm p-4"
             x-cloak
             style="display: none;">
            <div @click.away="showDeleteLokasiModal = false"
                 class="bg-white rounded-3xl shadow-2xl max-w-md w-full p-6 sm:p-7 relative border border-slate-100 text-center">
                
                {{-- Danger Warning Icon --}}
                <div class="h-16 w-16 mx-auto rounded-2xl bg-rose-50 border border-rose-100 text-rose-600 flex items-center justify-center text-2xl mb-4">
                    <i class="fa-solid fa-trash-can"></i>
                </div>

                <h3 class="text-lg font-extrabold text-slate-900">Hapus Kantor Cabang?</h3>
                <p class="text-xs text-slate-500 mt-2 leading-relaxed">
                    Apakah Anda yakin ingin menghapus kantor cabang <strong class="text-slate-800 font-bold" x-text="deleteLokasiData.nama"></strong>? Seluruh tautan Wi-Fi terkait akan dilepaskan dan tindakan ini tidak dapat dibatalkan.
                </p>

                <form id="deleteLokasiForm" :action="'/admin/settings/lokasi/' + deleteLokasiData.id" method="POST" class="mt-6 flex items-center justify-center gap-3">
                    @csrf
                    <input type="hidden" name="_method" value="DELETE">
                    <button type="button" @click="showDeleteLokasiModal = false" class="w-1/2 py-2.5 rounded-xl border border-slate-200 text-xs font-bold text-slate-600 hover:bg-slate-50 transition">
                        Batal
                    </button>
                    <button type="submit" class="w-1/2 py-2.5 rounded-xl bg-rose-600 hover:bg-rose-700 text-white text-xs font-bold shadow-md shadow-rose-100 transition flex items-center justify-center gap-1.5">
                        <i class="fa-solid fa-trash text-xs"></i>
                        <span>Ya, Hapus Cabang</span>
                    </button>
                </form>

            </div>
        </div>
    </template>

    {{-- MODAL 5: TAMBAH ROLE MASTER BARU --}}
    <template x-teleport="body">
        <div x-show="showTambahRoleModal"
             x-transition.opacity.duration.300ms
             class="fixed inset-0 z-[9999] flex items-center justify-center bg-slate-900/60 backdrop-blur-sm p-4"
             x-cloak
             style="display: none;">
            <div @click.away="showTambahRoleModal = false"
                 class="bg-white rounded-3xl shadow-2xl max-w-lg w-full p-7 relative border border-slate-100 my-auto">
                
                <div class="flex items-center justify-between pb-4 border-b border-slate-100">
                    <div class="flex items-center gap-3">
                        <div class="h-10 w-10 rounded-xl bg-blue-50 text-primary flex items-center justify-center text-lg">
                            <i class="fa-solid fa-shield-plus"></i>
                        </div>
                        <div>
                            <h3 class="text-lg font-bold text-slate-900">Tambah Role Master Baru</h3>
                            <p class="text-xs text-slate-400 mt-0.5">Buat role baru dan konfigurasikan hak akses privilege-nya</p>
                        </div>
                    </div>
                    <button @click="showTambahRoleModal = false; roleValidationError = ''; hasServerRoleErrors = false;" class="text-slate-400 hover:text-slate-600 p-1.5 rounded-full hover:bg-slate-50 transition">
                        <i class="fa-solid fa-xmark text-lg"></i>
                    </button>
                </div>

                <form action="{{ route('admin.settings.roles.tambah') }}" method="POST" novalidate
                      @submit="
                        roleValidationError = '';
                        if (!formRole.nama_role || !formRole.nama_role.trim()) {
                            $event.preventDefault();
                            roleValidationError = 'Nama role wajib diisi.';
                            return false;
                        }
                        if (formRole.nama_role.trim().length > 50) {
                            $event.preventDefault();
                            roleValidationError = 'Nama role maksimal 50 karakter.';
                            return false;
                        }
                        if (formRole.deskripsi && formRole.deskripsi.trim().length > 200) {
                            $event.preventDefault();
                            roleValidationError = 'Deskripsi role maksimal 200 karakter.';
                            return false;
                        }
                        if (isSavingRole) { 
                            $event.preventDefault(); 
                            return false; 
                        } 
                        isSavingRole = true;
                      " 
                      class="mt-5 space-y-4">
                    @csrf

                    {{-- Client-side & Backend Validation Error Box inside Modal --}}
                    <div x-show="roleValidationError" x-cloak class="p-3.5 rounded-xl bg-rose-50 border border-rose-200 text-xs text-rose-700 flex items-center gap-2.5">
                        <i class="fa-solid fa-circle-exclamation text-rose-500 text-sm flex-shrink-0"></i>
                        <span x-text="roleValidationError"></span>
                    </div>

                    @if($errors->tambah_role->any())
                    <div x-show="hasServerRoleErrors" x-cloak class="p-3.5 rounded-xl bg-rose-50 border border-rose-200 text-xs text-rose-700 flex items-center gap-2.5">
                        <i class="fa-solid fa-circle-exclamation text-rose-500 text-sm flex-shrink-0"></i>
                        <span>{{ $errors->tambah_role->first() }}</span>
                    </div>
                    @endif

                    {{-- Nama Role --}}
                    <div>
                        <div class="flex items-center justify-between">
                            <label class="text-xs font-bold text-slate-700">Nama Role <span class="text-rose-500">*</span></label>
                            <span class="text-[11px] font-semibold text-slate-400" x-text="(formRole.nama_role ? formRole.nama_role.length : 0) + '/50'"></span>
                        </div>
                        <input type="text" name="nama_role" x-model="formRole.nama_role" maxlength="50"
                               @input="roleValidationError = ''; hasServerRoleErrors = false;"
                               placeholder="Contoh: Supervisor, Finance, Manager"
                               class="mt-1.5 w-full rounded-xl border border-slate-200 px-3.5 py-2.5 text-sm font-semibold text-slate-800 focus:border-primary focus:outline-none focus:ring-1 focus:ring-primary"
                               :class="(roleValidationError || hasServerRoleErrors) ? 'border-rose-300 ring-1 ring-rose-300' : ''">
                        @error('nama_role', 'tambah_role')
                        <p x-show="hasServerRoleErrors" x-cloak class="text-xs text-rose-500 font-semibold mt-1.5 flex items-center gap-1">
                            <i class="fa-solid fa-circle-exclamation text-[11px]"></i>
                            <span>{{ $message }}</span>
                        </p>
                        @enderror
                    </div>

                    {{-- Deskripsi Role --}}
                    <div>
                        <div class="flex items-center justify-between">
                            <label class="text-xs font-bold text-slate-700">Deskripsi Role <span class="text-xs text-slate-400 font-normal">(Opsional)</span></label>
                            <span class="text-[11px] font-semibold text-slate-400" x-text="(formRole.deskripsi ? formRole.deskripsi.length : 0) + '/200'"></span>
                        </div>
                        <textarea name="deskripsi" x-model="formRole.deskripsi" rows="3" maxlength="200"
                                  @input="roleValidationError = ''; hasServerRoleErrors = false;"
                                  placeholder="Contoh: Mengelola operasional tim dan menyetujui jadwal"
                                  class="mt-1.5 w-full rounded-xl border border-slate-200 px-3.5 py-2.5 text-sm font-medium text-slate-800 focus:border-primary focus:outline-none focus:ring-1 focus:ring-primary"></textarea>
                        @error('deskripsi', 'tambah_role')
                        <p x-show="hasServerRoleErrors" x-cloak class="text-xs text-rose-500 font-semibold mt-1.5 flex items-center gap-1">
                            <i class="fa-solid fa-circle-exclamation text-[11px]"></i>
                            <span>{{ $message }}</span>
                        </p>
                        @enderror
                    </div>

                    {{-- Default Privilege Info Notice --}}
                    <div class="flex items-start gap-2.5 p-3 rounded-xl bg-blue-50/80 border border-blue-100 text-xs text-blue-800">
                        <i class="fa-solid fa-circle-info text-primary mt-0.5 flex-shrink-0"></i>
                        <span>Role baru akan memiliki <strong>0 / {{ $daftarPrivilege->flatten()->count() }} Hak Akses</strong> secara default. Anda dapat langsung mencentang dan menyimpan hak akses yang diizinkan setelah role dibuat.</span>
                    </div>

                    {{-- Submit Buttons --}}
                    <div class="pt-4 border-t border-slate-100 flex items-center justify-end gap-3">
                        <button type="button" :disabled="isSavingRole" @click="showTambahRoleModal = false; roleValidationError = ''; hasServerRoleErrors = false;" :class="isSavingRole ? 'opacity-50 cursor-not-allowed' : ''" class="px-4 py-2.5 rounded-xl text-xs font-bold text-slate-500 hover:bg-slate-50 transition">
                            Batal
                        </button>
                        <button type="submit" :disabled="isSavingRole" :class="isSavingRole ? 'opacity-75 cursor-not-allowed' : 'hover:bg-primary/90'" class="px-6 py-2.5 rounded-xl bg-primary text-white text-xs font-bold shadow-md transition flex items-center gap-1.5">
                            <svg x-show="isSavingRole" x-cloak class="h-3.5 w-3.5 animate-spin text-white" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                            </svg>
                            <i x-show="!isSavingRole" class="fa-solid fa-plus text-xs"></i>
                            <span x-text="isSavingRole ? 'Menyimpan...' : 'Simpan & Buat Role'">Simpan & Buat Role</span>
                        </button>
                    </div>
                </form>

            </div>
        </div>
    </template>

    {{-- MODAL 6: CUSTOM CONFIRM DELETE ROLE MASTER --}}
    <template x-teleport="body">
        <div x-show="showDeleteRoleModal"
             x-transition.opacity.duration.300ms
             class="fixed inset-0 z-[9999] flex items-center justify-center bg-slate-900/60 backdrop-blur-sm p-4"
             x-cloak
             style="display: none;">
            <div @click.away="showDeleteRoleModal = false"
                 class="bg-white rounded-3xl shadow-2xl max-w-md w-full p-6 sm:p-7 relative border border-slate-100 text-center">
                
                {{-- Danger Warning Icon --}}
                <div class="h-16 w-16 mx-auto rounded-2xl bg-rose-50 border border-rose-100 text-rose-600 flex items-center justify-center text-2xl mb-4">
                    <i class="fa-solid fa-trash-can"></i>
                </div>

                <h3 class="text-lg font-extrabold text-slate-900">Hapus Role Master?</h3>
                <p class="text-xs text-slate-500 mt-2 leading-relaxed">
                    Apakah Anda yakin ingin menghapus role <strong class="text-slate-800 font-bold" x-text="deleteRoleData.nama"></strong>? Seluruh pemetaan hak akses privilege terkait role ini akan dihapus dan tindakan ini tidak dapat dibatalkan.
                </p>

                <template x-if="deleteRoleData.akun_count > 0">
                    <div class="mt-3 p-3 rounded-xl bg-amber-50 border border-amber-200 text-xs text-amber-800 flex items-start gap-2 text-left">
                        <i class="fa-solid fa-triangle-exclamation text-amber-600 mt-0.5 flex-shrink-0"></i>
                        <span>Role ini saat ini masih digunakan oleh <strong x-text="deleteRoleData.akun_count"></strong> akun pengguna. Lepaskan atau ubah role akun terlebih dahulu sebelum menghapus.</span>
                    </div>
                </template>

                <form id="deleteRoleForm" :action="'/admin/settings/roles/' + deleteRoleData.id" method="POST" @submit="if (isDeletingRole) { $event.preventDefault(); return false; } isDeletingRole = true;" class="mt-6 flex items-center justify-center gap-3">
                    @csrf
                    <input type="hidden" name="_method" value="DELETE">
                    <button type="button" :disabled="isDeletingRole" @click="showDeleteRoleModal = false" :class="isDeletingRole ? 'opacity-50 cursor-not-allowed' : ''" class="w-1/2 py-2.5 rounded-xl border border-slate-200 text-xs font-bold text-slate-600 hover:bg-slate-50 transition">
                        Batal
                    </button>
                    <button type="submit" :disabled="isDeletingRole" :class="isDeletingRole ? 'opacity-75 cursor-not-allowed' : 'hover:bg-rose-700'" class="w-1/2 py-2.5 rounded-xl bg-rose-600 text-white text-xs font-bold shadow-md shadow-rose-100 transition flex items-center justify-center gap-1.5">
                        <svg x-show="isDeletingRole" x-cloak class="h-3.5 w-3.5 animate-spin text-white" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                            <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                        </svg>
                        <i x-show="!isDeletingRole" class="fa-solid fa-trash text-xs"></i>
                        <span x-text="isDeletingRole ? 'Menghapus...' : 'Ya, Hapus Role'">Ya, Hapus Role</span>
                    </button>
                </form>

            </div>
        </div>
    </template>

</div>

<script>
document.addEventListener('alpine:init', () => {
    Alpine.data('settingsMasterApp', (initialColor, initialLogo, initialTab, initialRoles, initialSelectedRoleId) => ({
        activeTab: initialTab || 'branding',
        savedColor:     initialColor,
        savedLogo:      initialLogo,
        selectedHex:    initialColor,
        logoPreviewUrl: initialLogo,
        logoImageFailed: false,
        logoFileName:   '',
        logoFile:       null,
        logoErrorMessage: '',
        colorPickerOpen: false,
        confirmReset:    false,
        tempHex:    initialColor,
        hue:        0,
        saturation: 0,
        value:      100,
        isDraggingSV:  false,
        isDraggingHue: false,

        // Location modal states
        showLokasiModal: {{ $errors->lokasi_kantor->any() ? 'true' : 'false' }},
        hasServerLokasiErrors: {{ $errors->lokasi_kantor->any() ? 'true' : 'false' }},
        lokasiValidationError: '',
        isSavingLokasi: false,
        isEdit: false,
        formLokasi: {
            lokasi_id: '{{ old('lokasi_id') }}',
            nama_kantor: '{{ old('nama_kantor') }}',
            latitude: '{{ old('latitude') }}',
            longitude: '{{ old('longitude') }}',
            radius_meter: {{ old('radius_meter', 100) }},
        },
        showDeleteLokasiModal: false,
        deleteLokasiData: { id: '', nama: '' },

        // Role modal states
        showTambahRoleModal: {{ $errors->tambah_role->any() ? 'true' : 'false' }},
        hasServerRoleErrors: {{ $errors->tambah_role->any() ? 'true' : 'false' }},
        roleValidationError: '',
        isSavingRole: false,
        formRole: {
            nama_role: '{{ old('nama_role') }}',
            deskripsi: '{{ old('deskripsi') }}',
        },
        showDeleteRoleModal: false,
        isDeletingRole: false,
        deleteRoleData: { id: '', nama: '', akun_count: 0 },

        // Role & Privilege states
        isSavingPrivileges: false,
        roles: initialRoles || [],
        currentRoleId: initialSelectedRoleId || (initialRoles && initialRoles.length > 0 ? initialRoles[0].role_id : 1),
        rolePrivilegesMap: {},

        get hasChanges() {
            return this.selectedHex.toUpperCase() !== this.savedColor.toUpperCase() || this.logoFile !== null;
        },
        get colorChanged() {
            return this.selectedHex.toUpperCase() !== this.savedColor.toUpperCase();
        },
        get logoChanged() {
            return this.logoFile !== null;
        },
        get hueColor() {
            return `hsl(${this.hue}, 100%, 50%)`;
        },

        init() {
            this.hexToHsv(this.selectedHex);

            // Populate role privileges map
            (this.roles || []).forEach(r => {
                this.rolePrivilegesMap[r.role_id] = Array.isArray(r.privilege_ids) ? [...r.privilege_ids] : [];
            });
        },

        // Role & Privilege methods
        selectRole(roleId) {
            this.currentRoleId = roleId;
        },
        getCurrentRole() {
            return (this.roles || []).find(r => r.role_id === this.currentRoleId) || { role_id: this.currentRoleId, nama_role: 'Role' };
        },
        getCurrentRoleName() {
            return this.getCurrentRole().nama_role;
        },
        isSuperAdminActive() {
            const r = this.getCurrentRole();
            return (r.nama_role || '').toLowerCase() === 'super admin' || r.role_id === 1;
        },
        getRolePrivilegeCount(roleId) {
            const list = this.rolePrivilegesMap[roleId] || [];
            return list.length;
        },
        isPrivilegeChecked(privId) {
            const list = this.rolePrivilegesMap[this.currentRoleId] || [];
            return list.includes(privId);
        },
        togglePrivilegeCheck(privId) {
            if (!this.rolePrivilegesMap[this.currentRoleId]) {
                this.rolePrivilegesMap[this.currentRoleId] = [];
            }
            const idx = this.rolePrivilegesMap[this.currentRoleId].indexOf(privId);
            if (idx > -1) {
                this.rolePrivilegesMap[this.currentRoleId].splice(idx, 1);
            } else {
                this.rolePrivilegesMap[this.currentRoleId].push(privId);
            }
        },
        selectAllPrivileges(allPrivIds) {
            this.rolePrivilegesMap[this.currentRoleId] = [...allPrivIds];
        },
        unselectAllPrivileges(corePrivId) {
            if (this.isSuperAdminActive()) {
                this.rolePrivilegesMap[this.currentRoleId] = [corePrivId];
            } else {
                this.rolePrivilegesMap[this.currentRoleId] = [];
            }
        },
        isCategoryFullySelected(catPrivIds) {
            const list = this.rolePrivilegesMap[this.currentRoleId] || [];
            return catPrivIds.every(id => list.includes(id));
        },
        toggleCategoryPrivileges(catPrivIds, corePrivId) {
            if (!this.rolePrivilegesMap[this.currentRoleId]) {
                this.rolePrivilegesMap[this.currentRoleId] = [];
            }
            const fullySelected = this.isCategoryFullySelected(catPrivIds);
            if (fullySelected) {
                this.rolePrivilegesMap[this.currentRoleId] = this.rolePrivilegesMap[this.currentRoleId].filter(id => {
                    if (this.isSuperAdminActive() && id === corePrivId) return true;
                    return !catPrivIds.includes(id);
                });
            } else {
                catPrivIds.forEach(id => {
                    if (!this.rolePrivilegesMap[this.currentRoleId].includes(id)) {
                        this.rolePrivilegesMap[this.currentRoleId].push(id);
                    }
                });
            }
        },

        onLogoChange(event) {
            const file = event.target.files[0];
            this.logoErrorMessage = '';
            if (!file) return;

            if (file.size > 2 * 1024 * 1024) {
                const sizeMb = (file.size / (1024 * 1024)).toFixed(2);
                this.logoErrorMessage = `Ukuran file logo (${sizeMb} MB) melebihi batas maksimal 2 MB. Silakan pilih file yang lebih kecil.`;
                event.target.value = '';
                this.logoFile = null;
                this.logoFileName = '';
                return;
            }

            this.logoFile       = file;
            this.logoFileName   = file.name;
            this.logoPreviewUrl = URL.createObjectURL(file);
            this.logoImageFailed = false;
        },

        onSubmit() {
            if (this.logoFile) {
                const dt = new DataTransfer();
                dt.items.add(this.logoFile);
                this.$refs.hiddenLogoInput.files = dt.files;
            }
            this.$refs.brandingForm.submit();
        },

        openPicker() {
            this.tempHex = this.selectedHex;
            this.hexToHsv(this.tempHex);
            this.colorPickerOpen = true;
        },
        applyColor() {
            this.selectedHex = this.tempHex.startsWith('#') ? this.tempHex : '#' + this.tempHex;
            this.colorPickerOpen = false;
        },
        quickColor(hex) {
            this.selectedHex = hex;
            this.tempHex     = hex;
            this.hexToHsv(hex);
        },
        updateHsvFromHex() {
            const v = this.tempHex.startsWith('#') ? this.tempHex : '#' + this.tempHex;
            if (/^#[0-9A-Fa-f]{6}$/.test(v)) this.hexToHsv(v);
        },
        updateHexFromHsv() {
            let h=this.hue, s=this.saturation/100, v=this.value/100;
            let c=v*s, x=c*(1-Math.abs((h/60)%2-1)), m=v-c;
            let r=0,g=0,b=0;
            if(h<60){r=c;g=x;b=0;}
            else if(h<120){r=x;g=c;b=0;}
            else if(h<180){r=0;g=c;b=x;}
            else if(h<240){r=0;g=x;b=c;}
            else if(h<300){r=x;g=0;b=c;}
            else{r=c;g=0;b=x;}
            r=Math.round((r+m)*255);g=Math.round((g+m)*255);b=Math.round((b+m)*255);
            this.tempHex='#'+((1<<24|r<<16|g<<8|b).toString(16).slice(1)).toUpperCase();
        },
        hexToHsv(hex) {
            let r=parseInt(hex.slice(1,3),16)/255;
            let g=parseInt(hex.slice(3,5),16)/255;
            let b=parseInt(hex.slice(5,7),16)/255;
            let max=Math.max(r,g,b),min=Math.min(r,g,b),d=max-min;
            let h=0,s=max===0?0:d/max,v=max;
            if(max!==min){
                switch(max){
                    case r:h=(g-b)/d+(g<b?6:0);break;
                    case g:h=(b-r)/d+2;break;
                    case b:h=(r-g)/d+4;break;
                }
                h/=6;
            }
            this.hue=Math.round(h*360);
            this.saturation=Math.round(s*100);
            this.value=Math.round(v*100);
        },

        startDragSV(e){this.isDraggingSV=true;this.updateSV(e);},
        dragSV(e){if(!this.isDraggingSV)return;this.updateSV(e);},
        stopDragSV(){this.isDraggingSV=false;},
        updateSV(e){
            let rect=this.$refs.svArea.getBoundingClientRect();
            let x=Math.max(0,Math.min(e.clientX-rect.left,rect.width));
            let y=Math.max(0,Math.min(e.clientY-rect.top,rect.height));
            this.saturation=Math.round((x/rect.width)*100);
            this.value=Math.round((1-y/rect.height)*100);
            this.updateHexFromHsv();
        },

        startDragHue(e){this.isDraggingHue=true;this.updateHue(e);},
        dragHue(e){if(!this.isDraggingHue)return;this.updateHue(e);},
        stopDragHue(){this.isDraggingHue=false;},
        updateHue(e){
            let rect=this.$refs.hueArea.getBoundingClientRect();
            let x=Math.max(0,Math.min(e.clientX-rect.left,rect.width));
            this.hue=Math.round((x/rect.width)*360);
            this.updateHexFromHsv();
        },

        openModalTambah() {
            this.isEdit = false;
            this.isSavingLokasi = false;
            this.lokasiValidationError = '';
            this.hasServerLokasiErrors = false;
            this.formLokasi = {
                lokasi_id: '',
                nama_kantor: '',
                latitude: '',
                longitude: '',
                radius_meter: 100,
            };
            this.showLokasiModal = true;
        },

        openModalEdit(lokasi) {
            this.isEdit = true;
            this.isSavingLokasi = false;
            this.lokasiValidationError = '';
            this.hasServerLokasiErrors = false;
            this.formLokasi = {
                lokasi_id: lokasi.lokasi_id,
                nama_kantor: lokasi.nama_kantor,
                latitude: lokasi.latitude,
                longitude: lokasi.longitude,
                radius_meter: lokasi.radius_meter,
            };
            this.showLokasiModal = true;
        },

        confirmDeleteLokasi(id, nama) {
            this.deleteLokasiData = { id: id, nama: nama };
            this.showDeleteLokasiModal = true;
        },

        openModalTambahRole() {
            this.isSavingRole = false;
            this.roleValidationError = '';
            this.hasServerRoleErrors = false;
            this.formRole = {
                nama_role: '',
                deskripsi: '',
            };
            this.showTambahRoleModal = true;
        },

        confirmDeleteRole(id, nama, akunCount) {
            this.isDeletingRole = false;
            this.deleteRoleData = { id: id, nama: nama, akun_count: akunCount || 0 };
            this.showDeleteRoleModal = true;
        }
    }));
});
</script>

@endsection
