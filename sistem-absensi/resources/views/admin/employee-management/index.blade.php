@extends('layouts.admin.app')

@section('content')
@php
    $currentRole = Auth::user()?->roleAkses ?? null;
    $canTambahPegawai = $currentRole?->hasPrivilege('tambah_pegawai') ?? false;
    $canEditPegawai   = $currentRole?->hasPrivilege('edit_pegawai') ?? false;
    $canExportPegawai = $currentRole?->hasPrivilege('export_pegawai') ?? false;
@endphp

<div x-data="employeeModal()" x-init="init()">
    {{-- Header --}}
    <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between mb-5 sm:mb-6">
        <div>
            <h1 class="text-xl sm:text-2xl font-semibold text-gray-900 leading-tight">Manajemen Akun Karyawan</h1>
            <p class="mt-1 text-xs sm:text-sm text-gray-600">Kelola akun dan data pegawai yang terhubung dengan sistem absensi.</p>
        </div>
        @if($canTambahPegawai)
            <button type="button" @click.prevent="openCreate()" class="w-full sm:w-auto inline-flex items-center justify-center gap-2 rounded-2xl bg-primary px-4 py-3 text-xs sm:text-sm font-semibold text-white transition hover:bg-primary-hover flex-shrink-0 shadow-sm">
                <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4 flex-shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4" />
                </svg>
                <span>Tambah Pegawai</span>
            </button>
        @else
            <button type="button" disabled title="Anda tidak memiliki hak akses untuk menambah pegawai" class="w-full sm:w-auto inline-flex items-center justify-center gap-2 rounded-2xl bg-slate-200 px-4 py-3 text-xs sm:text-sm font-semibold text-slate-400 opacity-60 cursor-not-allowed select-none flex-shrink-0 shadow-none">
                <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4 flex-shrink-0 text-slate-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4" />
                </svg>
                <span>Tambah Pegawai</span>
            </button>
        @endif
    </div>

    {{-- Flash Notifications --}}
    @if (session('success'))
        <div x-data="{ show: true }" x-cloak x-init="setTimeout(() => show = false, 4000)" x-show="show" x-transition class="mb-4 relative rounded-xl border border-green-200 bg-green-50 px-4 py-3 text-xs sm:text-sm text-green-700">
            <div class="pr-6">{{ session('success') }}</div>
            <button type="button" @click="show = false" aria-label="Tutup notifikasi" class="absolute right-2 top-2 text-green-700 hover:text-green-900">
                <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                </svg>
            </button>
        </div>
    @endif

    @if (session('error'))
        <div x-data="{ show: true }" x-cloak x-init="setTimeout(() => show = false, 4000)" x-show="show" x-transition class="mb-4 relative rounded-xl border border-red-200 bg-red-50 px-4 py-3 text-xs sm:text-sm text-red-700">
            <div class="pr-6">{{ session('error') }}</div>
            <button type="button" @click="show = false" aria-label="Tutup notifikasi" class="absolute right-2 top-2 text-red-700 hover:text-red-900">
                <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                </svg>
            </button>
        </div>
    @endif

    {{-- Export Success Notification (dynamic) --}}
    <template x-if="exportSuccessMessage">
        <div x-data x-cloak x-init="setTimeout(() => $dispatch('clear-export-success'), 4000)"
            @clear-export-success.window="exportSuccessMessage = ''"
            class="mb-4 relative rounded-xl border border-green-200 bg-green-50 px-4 py-3 text-xs sm:text-sm text-green-700">
            <div class="pr-6" x-text="exportSuccessMessage"></div>
            <button type="button" @click="exportSuccessMessage = ''" aria-label="Tutup notifikasi" class="absolute right-2 top-2 text-green-700 hover:text-green-900">
                <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                </svg>
            </button>
        </div>
    </template>

    {{-- Search & Export Card --}}
    <div class="mb-5 sm:mb-6 rounded-2xl border border-gray-200 bg-white p-4 sm:p-5 shadow-sm">
        <form method="GET" action="{{ route('admin.employee-management.index') }}">
            <div class="flex flex-col gap-3 sm:flex-row sm:items-end">
                <div class="flex-1">
                    <label class="mb-1 block text-xs sm:text-sm font-medium text-gray-700">Cari Karyawan</label>
                    <div class="relative mt-1">
                        <span class="pointer-events-none absolute inset-y-0 left-3.5 flex items-center text-gray-400">
                            <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-4.35-4.35m0 0A7.5 7.5 0 1110.5 3a7.5 7.5 0 016.15 12.65z" />
                            </svg>
                        </span>
                        <input
                            x-data="{ _t: null }"
                            @input="clearTimeout(_t); _t = setTimeout(() => $el.closest('form').submit(), 400)"
                            type="text" name="search" value="{{ $search ?? '' }}" placeholder="Cari nama, divisi, atau jabatan..." class="w-full rounded-2xl border border-gray-300 bg-white py-2.5 sm:py-3 pl-10 pr-4 text-xs sm:text-sm text-gray-900 shadow-sm focus:border-primary focus:outline-none focus:ring-1 focus:ring-primary" />
                    </div>
                </div>

                <div class="flex items-center">
                    @if($canExportPegawai)
                        <button type="button" @click.prevent="openExport()" class="w-full sm:w-auto inline-flex items-center justify-center gap-2 rounded-2xl border border-gray-200 bg-white px-4 py-2.5 sm:py-3 text-xs sm:text-sm font-medium text-gray-700 shadow-sm transition hover:bg-gray-50 whitespace-nowrap">
                            <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4 text-gray-500" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4" />
                            </svg>
                            <span>Export</span>
                        </button>
                    @else
                        <button type="button" disabled title="Anda tidak memiliki hak akses untuk mengekspor data pegawai" class="w-full sm:w-auto inline-flex items-center justify-center gap-2 rounded-2xl border border-gray-200 bg-gray-100 px-4 py-2.5 sm:py-3 text-xs sm:text-sm font-medium text-gray-400 opacity-60 cursor-not-allowed select-none whitespace-nowrap">
                            <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4 text-gray-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4" />
                            </svg>
                            <span>Export</span>
                        </button>
                    @endif
                </div>
            </div>
        </form>
    </div>

    {{-- ======================================================== --}}
    {{-- 1. TAMPILAN MOBILE: CARD LIST (Tampil di Layar HP) --}}
    {{-- ======================================================== --}}
    <div class="block sm:hidden space-y-3.5 mb-6">
        @forelse ($employees as $employee)
            <div class="rounded-2xl border border-slate-200 bg-white p-4 shadow-sm space-y-3">
                {{-- Header Card: Foto + Nama + NIP + Status --}}
                <div class="flex items-center justify-between gap-3 border-b border-slate-100 pb-3">
                    <div class="flex items-center gap-3 min-w-0">
                        @php $photoUrl = supabase_public_url($employee->foto_profile); @endphp
                        <div class="relative flex h-10 w-10 flex-shrink-0 items-center justify-center">
                            @if ($photoUrl)
                                <img src="{{ $photoUrl }}"
                                     alt="{{ $employee->nama_pegawai }}"
                                     class="h-10 w-10 rounded-full object-cover shadow-sm border border-slate-200"
                                     loading="lazy"
                                     onerror="this.style.display='none'; if(this.nextElementSibling){this.nextElementSibling.classList.remove('hidden');}" />
                            @endif
                            <div class="flex h-10 w-10 items-center justify-center rounded-full bg-slate-100 text-xs font-semibold text-slate-700 border border-slate-200 shadow-sm {{ $photoUrl ? 'hidden' : '' }}">
                                {{ getInitials($employee->nama_pegawai) }}
                            </div>
                        </div>
                        <div class="min-w-0">
                            <h3 class="truncate text-sm font-bold text-slate-900">{{ $employee->nama_pegawai }}</h3>
                            <p class="text-xs text-slate-500">NIP: {{ $employee->nip ?? '-' }}</p>
                        </div>
                    </div>
                    <div class="flex-shrink-0">
                        @php
                            $statusText = $employee->status ?? 'Aktif';
                            $isAktif = strcasecmp(trim($statusText), 'Aktif') === 0;
                        @endphp
                        <span class="inline-flex items-center rounded-full px-2.5 py-1 text-xs font-semibold {{ $isAktif ? 'bg-emerald-50 border border-emerald-200 text-emerald-700' : 'bg-rose-50 border border-rose-200 text-rose-700' }}">
                            {{ $statusText }}
                        </span>
                    </div>
                </div>

                {{-- Grid Department & Role --}}
                <div class="grid grid-cols-2 gap-2.5 text-xs">
                    <div class="rounded-xl bg-slate-50 p-2.5">
                        <p class="text-slate-400 text-[10px] uppercase font-semibold">Divisi</p>
                        <p class="font-medium text-slate-800 mt-0.5 truncate">{{ $employee->masterDivisi->nama_divisi ?? '-' }}</p>
                    </div>

                    <div class="rounded-xl bg-slate-50 p-2.5">
                        <p class="text-slate-400 text-[10px] uppercase font-semibold">Jabatan</p>
                        <p class="font-medium text-slate-800 mt-0.5 truncate">{{ $employee->masterJabatan->nama_jabatan ?? '-' }}</p>
                    </div>
                </div>

                {{-- Action Buttons --}}
                <div class="flex items-center gap-2 pt-1 border-t border-slate-100">
                    <button type="button" @click.prevent="openDetail($event)"
                            data-employee="{{ json_encode([
                                'pegawai_id' => $employee->pegawai_id,
                                'nama_pegawai' => $employee->nama_pegawai,
                                'nip' => $employee->nip,
                                'email' => $employee->email,
                                'divisi_id' => $employee->divisi_id,
                                'divisi_name' => $employee->masterDivisi->nama_divisi ?? '-',
                                'jabatan_id' => $employee->jabatan_id,
                                'jabatan_name' => $employee->masterJabatan->nama_jabatan ?? '-',
                                'status' => $employee->status ?? 'Aktif',
                                'no_handphone' => $employee->no_handphone,
                                'nfc_id' => $employee->nfc->nfc_serial_number ?? '-',
                                'foto_profile_path' => $employee->foto_profile ?? '',
                                'foto_profile' => $employee->foto_profile ? supabase_public_url($employee->foto_profile) : '',
                                'username' => $employee->akun->username ?? '-',
                            ], JSON_HEX_APOS | JSON_HEX_QUOT) }}"
                            class="flex-1 inline-flex items-center justify-center gap-1.5 rounded-xl border border-slate-200 bg-white py-2 text-xs font-semibold text-slate-700 transition hover:bg-slate-50">
                        <i class="fa-solid fa-eye text-slate-500 text-xs"></i>
                        <span>Detail</span>
                    </button>

                    @if($canEditPegawai)
                        <button type="button" @click.prevent="openEdit($event)"
                                data-employee="{{ json_encode([
                                    'pegawai_id' => $employee->pegawai_id,
                                    'nama_pegawai' => $employee->nama_pegawai,
                                    'nip' => $employee->nip,
                                    'email' => $employee->email,
                                    'divisi_id' => $employee->divisi_id,
                                    'jabatan_id' => $employee->jabatan_id,
                                    'status' => $employee->status ?? 'Aktif',
                                    'no_handphone' => $employee->no_handphone,
                                    'nfc_id' => $employee->nfc->nfc_serial_number ?? '',
                                    'foto_profile_path' => $employee->foto_profile ?? '',
                                    'foto_profile' => $employee->foto_profile ? supabase_public_url($employee->foto_profile) : '',
                                    'username' => $employee->akun->username ?? '',
                                    'role_id' => $employee->akun->role_id ?? '',
                                    'role' => $employee->akun->roleAkses->nama_role ?? $employee->akun->role ?? 'Pegawai'
                                ], JSON_HEX_APOS | JSON_HEX_QUOT) }}"
                                class="flex-1 inline-flex items-center justify-center gap-1.5 rounded-xl bg-blue-50 py-2 text-xs font-semibold text-primary transition hover:bg-blue-100">
                            <i class="fa-solid fa-pen-to-square text-primary text-xs"></i>
                            <span>Edit</span>
                        </button>
                    @else
                        <button type="button" disabled title="Anda tidak memiliki hak akses untuk mengubah data pegawai"
                                class="flex-1 inline-flex items-center justify-center gap-1.5 rounded-xl bg-slate-100 py-2 text-xs font-semibold text-slate-400 opacity-60 cursor-not-allowed select-none">
                            <i class="fa-solid fa-pen-to-square text-slate-400 text-xs"></i>
                            <span>Edit</span>
                        </button>
                    @endif
                </div>
            </div>
        @empty
            <div class="rounded-2xl border border-slate-200 bg-white p-8 text-center text-xs text-slate-500">
                Belum ada data pegawai.
            </div>
        @endforelse
    </div>

    {{-- ======================================================== --}}
    {{-- 2. TAMPILAN DESKTOP: FULL TABLE (Tampil di Laptop/Tablet) --}}
    {{-- ======================================================== --}}
    <div class="hidden sm:block overflow-hidden rounded-2xl border border-gray-200 bg-white shadow-sm mb-6">
        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-gray-200">
                <thead class="bg-gray-50/80">
                    <tr>
                        <th class="whitespace-nowrap px-4 py-3.5 text-left text-xs font-semibold uppercase tracking-wider text-gray-600">Photo</th>
                        <th class="whitespace-nowrap px-4 py-3.5 text-left text-xs font-semibold uppercase tracking-wider text-gray-600">Nama</th>
                        <th class="whitespace-nowrap px-4 py-3.5 text-left text-xs font-semibold uppercase tracking-wider text-gray-600">NIP</th>
                        <th class="whitespace-nowrap px-4 py-3.5 text-left text-xs font-semibold uppercase tracking-wider text-gray-600">Divisi</th>
                        <th class="whitespace-nowrap px-4 py-3.5 text-left text-xs font-semibold uppercase tracking-wider text-gray-600">Jabatan</th>
                        <th class="whitespace-nowrap px-4 py-3.5 text-left text-xs font-semibold uppercase tracking-wider text-gray-600">Status</th>
                        <th class="whitespace-nowrap px-4 py-3.5 text-left text-xs font-semibold uppercase tracking-wider text-gray-600">Action</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-200 bg-white">
                    @forelse ($employees as $employee)
                        <tr class="transition hover:bg-gray-50/80">
                            <td class="whitespace-nowrap px-4 py-3 text-sm text-gray-900">
                                <div class="flex items-center gap-3">
                                    @php $photoUrl = supabase_public_url($employee->foto_profile); @endphp
                                    <div class="relative flex h-10 w-10 flex-shrink-0 items-center justify-center">
                                        @if ($photoUrl)
                                            <img src="{{ $photoUrl }}"
                                                 alt="{{ $employee->nama_pegawai }}"
                                                 class="h-10 w-10 rounded-full object-cover shadow-sm border border-slate-200"
                                                 loading="lazy"
                                                 onerror="this.style.display='none'; if(this.nextElementSibling){this.nextElementSibling.classList.remove('hidden');}" />
                                        @endif
                                        <div class="flex h-10 w-10 items-center justify-center rounded-full bg-slate-100 text-sm font-semibold text-slate-700 border border-slate-200 shadow-sm {{ $photoUrl ? 'hidden' : '' }}">
                                            {{ getInitials($employee->nama_pegawai) }}
                                        </div>
                                    </div>
                                </div>
                            </td>
                            <td class="whitespace-nowrap px-4 py-3 text-sm font-semibold text-gray-900">{{ $employee->nama_pegawai }}</td>
                            <td class="whitespace-nowrap px-4 py-3 text-sm text-gray-700">{{ $employee->nip ?? '-' }}</td>
                            <td class="whitespace-nowrap px-4 py-3 text-sm text-gray-700">{{ $employee->masterDivisi->nama_divisi ?? '-' }}</td>
                            <td class="whitespace-nowrap px-4 py-3 text-sm text-gray-700">{{ $employee->masterJabatan->nama_jabatan ?? '-' }}</td>
                            <td class="whitespace-nowrap px-4 py-3 text-sm">
                                @php
                                    $statusText = $employee->status ?? 'Aktif';
                                    $isAktif = strcasecmp(trim($statusText), 'Aktif') === 0;
                                @endphp
                                <span class="inline-flex items-center rounded-full px-2.5 py-1 text-xs font-semibold {{ $isAktif ? 'bg-emerald-50 border border-emerald-200 text-emerald-700' : 'bg-rose-50 border border-rose-200 text-rose-700' }}">
                                    {{ $statusText }}
                                </span>
                            </td>
                            <td class="whitespace-nowrap px-4 py-3 text-sm">
                                <button type="button" @click.prevent="openDetail($event)"
                                        data-employee="{{ json_encode([
                                            'pegawai_id' => $employee->pegawai_id,
                                            'nama_pegawai' => $employee->nama_pegawai,
                                            'nip' => $employee->nip,
                                            'email' => $employee->email,
                                            'divisi_id' => $employee->divisi_id,
                                            'divisi_name' => $employee->masterDivisi->nama_divisi ?? '-',
                                            'jabatan_id' => $employee->jabatan_id,
                                            'jabatan_name' => $employee->masterJabatan->nama_jabatan ?? '-',
                                            'status' => $employee->status ?? 'Aktif',
                                            'no_handphone' => $employee->no_handphone,
                                            'nfc_id' => $employee->nfc->nfc_serial_number ?? '-',
                                            'foto_profile_path' => $employee->foto_profile ?? '',
                                            'foto_profile' => $employee->foto_profile ? supabase_public_url($employee->foto_profile) : '',
                                            'username' => $employee->akun->username ?? '-',
                                        ], JSON_HEX_APOS | JSON_HEX_QUOT) }}"
                                        class="mr-2 inline-flex h-9 w-9 items-center justify-center rounded-full border border-slate-200 bg-white text-slate-500 transition hover:bg-slate-100 hover:text-slate-700 shadow-sm"
                                        aria-label="Lihat detail pegawai">
                                    <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                        <path d="M1 12s4-7 11-7 11 7 11 7-4 7-11 7-11-7-11-7z" />
                                        <circle cx="12" cy="12" r="3" />
                                    </svg>
                                </button>
                                @if($canEditPegawai)
                                    <button type="button" @click.prevent="openEdit($event)"
                                            data-employee="{{ json_encode([
                                                'pegawai_id' => $employee->pegawai_id,
                                                'nama_pegawai' => $employee->nama_pegawai,
                                                'nip' => $employee->nip,
                                                'email' => $employee->email,
                                                'divisi_id' => $employee->divisi_id,
                                                'jabatan_id' => $employee->jabatan_id,
                                                'status' => $employee->status ?? 'Aktif',
                                                'no_handphone' => $employee->no_handphone,
                                                'nfc_id' => $employee->nfc->nfc_serial_number ?? '',
                                                'foto_profile_path' => $employee->foto_profile ?? '',
                                                'foto_profile' => $employee->foto_profile ? supabase_public_url($employee->foto_profile) : '',
                                                'username' => $employee->akun->username ?? '',
                                                'role_id' => $employee->akun->role_id ?? '',
                                                'role' => $employee->akun->roleAkses->nama_role ?? $employee->akun->role ?? 'Pegawai'
                                            ], JSON_HEX_APOS | JSON_HEX_QUOT) }}"
                                            class="inline-flex h-9 w-9 items-center justify-center rounded-full border border-slate-200 bg-white text-primary transition hover:bg-blue-50 shadow-sm"
                                            aria-label="Edit pegawai">
                                        <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 20h9" />
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16.5 3.5a2.121 2.121 0 113 3L7 19l-4 1 1-4L16.5 3.5z" />
                                        </svg>
                                    </button>
                                @else
                                    <button type="button" disabled title="Anda tidak memiliki hak akses untuk mengubah data pegawai"
                                            class="inline-flex h-9 w-9 items-center justify-center rounded-full border border-slate-200 bg-slate-100 text-slate-300 opacity-60 cursor-not-allowed select-none shadow-none"
                                            aria-label="Edit pegawai dinonaktifkan">
                                        <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4 text-slate-300" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 20h9" />
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16.5 3.5a2.121 2.121 0 113 3L7 19l-4 1 1-4L16.5 3.5z" />
                                        </svg>
                                    </button>
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="7" class="px-4 py-8 text-center text-sm text-gray-500">Belum ada data pegawai.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    {{-- ======================================================== --}}
    {{-- 3. PAGINATION (Responsif untuk Mobile & Desktop) --}}
    {{-- ======================================================== --}}
    <div class="flex flex-col gap-3.5 border-t border-slate-200 bg-white p-4 sm:p-5 rounded-2xl shadow-sm sm:flex-row sm:items-center sm:justify-between mb-6">
        @php
            $from = $employees->firstItem() ?: 0;
            $to = $employees->lastItem() ?: 0;
            $total = $employees->total();
            $currentPage = $employees->currentPage();
            $lastPage = $employees->lastPage();
        @endphp

        <p class="text-xs sm:text-sm text-slate-500 text-center sm:text-left">
            Menampilkan <span class="font-semibold text-slate-700">{{ $from }} - {{ $to }}</span> dari <span class="font-semibold text-slate-700">{{ $total }}</span> data
        </p>

        <div class="overflow-x-auto pb-1 sm:pb-0 flex justify-center sm:justify-end">
            <nav class="inline-flex overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm flex-shrink-0" aria-label="Pagination">
                {{-- Previous Page Link --}}
                <a
                    class="inline-flex h-9 sm:h-11 w-9 sm:w-11 items-center justify-center border-r border-slate-200 text-xs sm:text-sm font-medium transition {{ $employees->onFirstPage() ? 'cursor-not-allowed pointer-events-none bg-slate-100 text-slate-300' : 'text-slate-700 hover:bg-slate-100 hover:text-slate-900 active:bg-slate-200' }}"
                    href="{{ $employees->onFirstPage() ? '#' : $employees->previousPageUrl() }}"
                    aria-disabled="{{ $employees->onFirstPage() ? 'true' : 'false' }}"
                >
                    <i class="fa-solid fa-chevron-left text-xs"></i>
                </a>

                @php
                    $paginationElements = [];

                    if ($lastPage <= 7) {
                        for ($i = 1; $i <= $lastPage; $i++) {
                            $paginationElements[] = $i;
                        }
                    } else {
                        if ($currentPage <= 4) {
                            for ($i = 1; $i <= 5; $i++) {
                                $paginationElements[] = $i;
                            }
                            $paginationElements[] = '...';
                            $paginationElements[] = $lastPage;
                        } elseif ($currentPage >= $lastPage - 3) {
                            $paginationElements[] = 1;
                            $paginationElements[] = '...';
                            for ($i = $lastPage - 4; $i <= $lastPage; $i++) {
                                $paginationElements[] = $i;
                            }
                        } else {
                            $paginationElements[] = 1;
                            $paginationElements[] = '...';
                            for ($i = $currentPage - 1; $i <= $currentPage + 1; $i++) {
                                $paginationElements[] = $i;
                            }
                            $paginationElements[] = '...';
                            $paginationElements[] = $lastPage;
                        }
                    }
                @endphp

                @foreach ($paginationElements as $element)
                    @if ($element === '...')
                        <span class="inline-flex h-9 sm:h-11 min-w-[32px] sm:min-w-[44px] items-center justify-center border-r border-slate-200 px-2 text-xs sm:text-sm font-medium text-slate-400 bg-white select-none">
                            ...
                        </span>
                    @else
                        <a
                            class="inline-flex h-9 sm:h-11 min-w-[32px] sm:min-w-[44px] items-center justify-center border-r border-slate-200 px-2 sm:px-3.5 text-xs sm:text-sm font-semibold transition {{ $element === $currentPage ? 'bg-primary text-white hover:bg-primary-hover' : 'bg-white text-slate-700 hover:bg-slate-100 hover:text-slate-900 active:bg-slate-200' }}"
                            href="{{ $employees->url($element) }}"
                        >
                            {{ $element }}
                        </a>
                    @endif
                @endforeach

                {{-- Next Page Link --}}
                <a
                    class="inline-flex h-9 sm:h-11 w-9 sm:w-11 items-center justify-center text-xs sm:text-sm font-medium transition {{ ! $employees->hasMorePages() ? 'cursor-not-allowed pointer-events-none bg-slate-100 text-slate-300' : 'text-slate-700 hover:bg-slate-100 hover:text-slate-900 active:bg-slate-200' }}"
                    href="{{ $employees->hasMorePages() ? $employees->nextPageUrl() : '#' }}"
                    aria-disabled="{{ ! $employees->hasMorePages() ? 'true' : 'false' }}"
                >
                    <i class="fa-solid fa-chevron-right text-xs"></i>
                </a>
            </nav>
        </div>
    </div>

        {{-- DETAIL MODAL PEGAWAI --}}
    <div x-show="detailModalOpen" x-cloak class="fixed inset-0 z-50 flex items-center justify-center bg-slate-900/40 backdrop-blur-sm p-4" @click.self="closeDetail()">
        <div class="relative w-full max-w-lg max-h-[90vh] overflow-y-auto rounded-3xl sm:rounded-[28px] bg-white shadow-2xl ring-1 ring-slate-200" @click.stop>
            {{-- Header --}}
            <div class="border-b border-slate-200 px-5 sm:px-6 py-3.5 flex items-center justify-between">
                <div>
                    <h2 class="text-base sm:text-lg font-bold text-slate-900">Detail Pegawai</h2>
                    <p class="mt-0.5 text-xs text-slate-500">Informasi lengkap data pegawai dan akun.</p>
                </div>
                <button type="button" class="rounded-full border border-slate-200 bg-white p-2 text-slate-500 transition hover:bg-slate-100 hover:text-slate-700"
                    @click="closeDetail()" aria-label="Tutup modal">
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                    </svg>
                </button>
            </div>

            {{-- Body --}}
            <div class="p-4 sm:p-5 space-y-3.5">
                {{-- Profile Header: Foto di KIRI, Nama & NIP di KANAN --}}
                <div class="flex items-center gap-3.5 sm:gap-4 bg-slate-50 p-3.5 sm:p-4 rounded-2xl border border-slate-100">
                    <template x-if="detailData.foto_profile">
                        <img :src="detailData.foto_profile" alt="Foto Pegawai" class="h-14 w-14 sm:h-16 sm:w-16 rounded-full object-cover shadow-sm border-2 border-white flex-shrink-0" x-on:error="detailData.foto_profile = ''" />
                    </template>
                    <template x-if="!detailData.foto_profile">
                        <div class="flex h-14 w-14 sm:h-16 sm:w-16 items-center justify-center rounded-full bg-slate-200 text-lg sm:text-xl font-bold text-slate-700 flex-shrink-0 border-2 border-white shadow-sm">
                            <span x-text="detailData.nama_pegawai ? detailData.nama_pegawai.substring(0, 1).toUpperCase() : 'U'"></span>
                        </div>
                    </template>
                    <div class="min-w-0 flex-1">
                        <h3 class="text-base sm:text-lg font-bold text-slate-900 truncate" x-text="detailData.nama_pegawai"></h3>
                        <p class="text-xs text-slate-500 mt-0.5">NIP: <span class="font-semibold text-slate-700" x-text="detailData.nip || '-'"></span></p>
                        <div class="mt-1">
                            <span 
                                :class="(detailData.status && detailData.status.toLowerCase() === 'aktif') 
                                    ? 'bg-emerald-50 border-emerald-200 text-emerald-700' 
                                    : 'bg-rose-50 border-rose-200 text-rose-700'"
                                class="inline-flex items-center rounded-full border px-2.5 py-0.5 text-[11px] font-semibold" 
                                x-text="detailData.status || 'Aktif'"
                            ></span>
                        </div>
                    </div>
                </div>

                {{-- Detail Grid --}}
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-2.5 text-xs sm:text-sm">
                    <div class="rounded-2xl border border-slate-100 bg-white p-3 shadow-sm">
                        <p class="text-slate-400 text-[10px] sm:text-[11px] uppercase font-semibold">Divisi</p>
                        <p class="font-semibold text-slate-800 mt-0.5 truncate" x-text="detailData.divisi_name || '-'"></p>
                    </div>

                    <div class="rounded-2xl border border-slate-100 bg-white p-3 shadow-sm">
                        <p class="text-slate-400 text-[10px] sm:text-[11px] uppercase font-semibold">Jabatan</p>
                        <p class="font-semibold text-slate-800 mt-0.5 truncate" x-text="detailData.jabatan_name || '-'"></p>
                    </div>

                    <div class="rounded-2xl border border-slate-100 bg-white p-3 shadow-sm">
                        <p class="text-slate-400 text-[10px] sm:text-[11px] uppercase font-semibold">Email</p>
                        <p class="font-semibold text-slate-800 mt-0.5 truncate" x-text="detailData.email || '-'"></p>
                    </div>

                    <div class="rounded-2xl border border-slate-100 bg-white p-3 shadow-sm">
                        <p class="text-slate-400 text-[10px] sm:text-[11px] uppercase font-semibold">No. Telepon</p>
                        <p class="font-semibold text-slate-800 mt-0.5" x-text="detailData.no_handphone || '-'"></p>
                    </div>

                    <div class="rounded-2xl border border-slate-100 bg-white p-3 shadow-sm">
                        <p class="text-slate-400 text-[10px] sm:text-[11px] uppercase font-semibold">Username</p>
                        <p class="font-semibold text-slate-800 mt-0.5" x-text="detailData.username || '-'"></p>
                    </div>

                    <div class="rounded-2xl border border-slate-100 bg-white p-3 shadow-sm">
                        <p class="text-slate-400 text-[10px] sm:text-[11px] uppercase font-semibold">Kartu NFC ID</p>
                        <p class="font-semibold text-slate-800 mt-0.5" x-text="detailData.nfc_id || '-'"></p>
                    </div>
                </div>
            </div>

        </div>
    </div>

    @include('admin.employee-management._employee-form-modal')
    @include('admin.employee-management._export-modal')

    <div x-show="divisionModalOpen" x-cloak class="fixed inset-0 z-50 flex items-center justify-center bg-slate-900/40 backdrop-blur-sm p-4" @click.self="closeDivisionModal()">
        <div class="relative w-full max-w-md max-h-[90vh] overflow-y-auto rounded-3xl sm:rounded-[24px] bg-white shadow-[0_35px_100px_rgba(15,23,42,0.16)] ring-1 ring-slate-200" @click.stop>
            <div class="border-b border-slate-200 px-5 sm:px-6 py-4">
                <div class="flex items-start justify-between gap-4">
                    <div>
                        <h2 class="text-lg sm:text-xl font-semibold text-slate-900">Tambah Divisi</h2>
                        <p class="mt-1 text-xs sm:text-sm text-slate-500">Tambahkan divisi baru tanpa meninggalkan halaman ini.</p>
                    </div>
                    <button type="button" class="rounded-full border border-slate-200 bg-white p-2 text-slate-500 transition hover:bg-slate-100 hover:text-slate-700"
                        @click="closeDivisionModal()" aria-label="Tutup modal">
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                        </svg>
                    </button>
                </div>
            </div>
            <div class="px-5 sm:px-6 py-5">
                <div class="space-y-3">
                    <div>
                        <label class="mb-1 block text-xs sm:text-sm font-medium text-slate-700">Nama Divisi</label>
                        <input type="text" x-model="newDivisionName" placeholder="Contoh: HR"
                            class="w-full rounded-2xl border border-slate-300 bg-white px-4 py-3 text-sm text-slate-900 outline-none transition focus:border-primary focus:ring-1 focus:ring-primary" />
                        <template x-if="divisionError">
                            <p class="mt-1 text-xs text-red-600" x-text="divisionError"></p>
                        </template>
                    </div>
                </div>
            </div>
            <div class="border-t border-slate-200 bg-white px-5 sm:px-6 py-4 flex flex-col-reverse sm:flex-row justify-end gap-3">
                <button type="button" @click="closeDivisionModal()"
                    class="w-full sm:w-auto inline-flex items-center justify-center rounded-2xl border border-slate-300 bg-slate-100 px-5 py-2.5 text-xs sm:text-sm font-semibold text-slate-800 transition hover:bg-slate-200">
                    Batal
                </button>
                <button type="button" @click="saveDivision()"
                    class="w-full sm:w-auto inline-flex items-center justify-center rounded-2xl bg-primary px-5 py-2.5 text-xs sm:text-sm font-semibold text-white transition hover:bg-primary-hover"
                    x-bind:disabled="isSavingDivision"
                    x-text="isSavingDivision ? 'Menyimpan...' : 'Simpan'"></button>
            </div>
        </div>
    </div>

    <div x-show="roleModalOpen" x-cloak class="fixed inset-0 z-50 flex items-center justify-center bg-slate-900/40 backdrop-blur-sm p-4" @click.self="closeRoleModal()">
        <div class="relative w-full max-w-md max-h-[90vh] overflow-y-auto rounded-3xl sm:rounded-[24px] bg-white shadow-[0_35px_100px_rgba(15,23,42,0.16)] ring-1 ring-slate-200" @click.stop>
            <div class="border-b border-slate-200 px-5 sm:px-6 py-4">
                <div class="flex items-start justify-between gap-4">
                    <div>
                        <h2 class="text-lg sm:text-xl font-semibold text-slate-900">Tambah Jabatan</h2>
                        <p class="mt-1 text-xs sm:text-sm text-slate-500">Tambahkan jabatan baru tanpa meninggalkan halaman ini.</p>
                    </div>
                    <button type="button" class="rounded-full border border-slate-200 bg-white p-2 text-slate-500 transition hover:bg-slate-100 hover:text-slate-700"
                        @click="closeRoleModal()" aria-label="Tutup modal">
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                        </svg>
                    </button>
                </div>
            </div>
            <div class="px-5 sm:px-6 py-5">
                <div class="space-y-3">
                    <div>
                        <label class="mb-1 block text-xs sm:text-sm font-medium text-slate-700">Nama Jabatan</label>
                        <input type="text" x-model="newJabatanName" placeholder="Contoh: Staff IT"
                            class="w-full rounded-2xl border border-slate-300 bg-white px-4 py-3 text-sm text-slate-900 outline-none transition focus:border-primary focus:ring-1 focus:ring-primary" />
                        <template x-if="roleError">
                            <p class="mt-1 text-xs text-red-600" x-text="roleError"></p>
                        </template>
                    </div>
                </div>
            </div>
            <div class="border-t border-slate-200 bg-white px-5 sm:px-6 py-4 flex flex-col-reverse sm:flex-row justify-end gap-3">
                <button type="button" @click="closeRoleModal()"
                    class="w-full sm:w-auto inline-flex items-center justify-center rounded-2xl border border-slate-300 bg-slate-100 px-5 py-2.5 text-xs sm:text-sm font-semibold text-slate-800 transition hover:bg-slate-200">
                    Batal
                </button>
                <button type="button" @click="saveRole()"
                    class="w-full sm:w-auto inline-flex items-center justify-center rounded-2xl bg-primary px-5 py-2.5 text-xs sm:text-sm font-semibold text-white transition hover:bg-primary-hover"
                    x-bind:disabled="isSavingRole"
                    x-text="isSavingRole ? 'Menyimpan...' : 'Simpan'"></button>
            </div>
        </div>
    </div>

    <script>
        function employeeModal() {
            return {
                modalOpen: {{ $errors->any() ? 'true' : 'false' }},
                isEdit: {{ old('form_mode') === 'edit' || old('_method') === 'PUT' ? 'true' : 'false' }},
                formAction: '{{ old("form_mode") === "edit" && old("pegawai_id") ? route("admin.employee-management.update", old("pegawai_id")) : route("admin.employee-management.store") }}',
                divisionModalOpen: false,
                roleModalOpen: false,
                newDivisionName: '',
                newJabatanName: '',
                divisionError: '',
                divisionSuccess: '',
                roleError: '',
                roleSuccess: '',
                isSavingDivision: false,
                isSavingRole: false,
                isSavingEmployee: false,
                photoError: '',
                exportSuccessMessage: '',
                exportErrorMessage: '',
                detailModalOpen: false,
                detailData: {
                    pegawai_id: '',
                    nama_pegawai: '',
                    nip: '',
                    email: '',
                    no_handphone: '',
                    divisi_name: '-',
                    jabatan_name: '-',
                    status: 'Aktif',
                    username: '-',
                    nfc_id: '-',
                    foto_profile: '',
                },
                form: {
                    pegawai_id: '{{ old('pegawai_id', '') }}',
                    nama_pegawai: '{{ old('nama_pegawai', '') }}',
                    nip: '{{ old('nip', '') }}',
                    email: '{{ old('email', '') }}',
                    no_handphone: '{{ old('no_handphone', '') }}',
                    divisi_id: '{{ old('divisi_id', '') }}',
                    jabatan_id: '{{ old('jabatan_id', '') }}',
                    role: '{{ old('role', 'Anggota') }}',
                    status: '{{ old('status', 'Aktif') }}',
                    nfc_id: '{{ old('nfc_id', '') }}',
                    username: '{{ old('username', '') }}',
                    password: '',
                    password_confirmation: '',
                    photoPreview: '',
                },
                previewObjectUrl: null,
                csrfToken: '{{ csrf_token() }}',
                init() {
                    this.$watch('modalOpen', value => {
                        if (!value) {
                            if (this.previewObjectUrl) {
                                URL.revokeObjectURL(this.previewObjectUrl);
                                this.previewObjectUrl = null;
                            }
                            this.form.photoPreview = '';
                            this.clearErrors();
                        }
                    });
                },
                clearErrors() {
                    const form = document.getElementById('employeeForm');
                    if (form) {
                        // Hapus semua pesan error (client-side dan server-side)
                        form.querySelectorAll('.client-error, p.text-red-600').forEach(el => el.remove());

                        // Reset styling error pada input / container
                        form.querySelectorAll('.border-red-400, .border-red-300, .bg-red-50\\/20, .ring-red-300, .border-client-error').forEach(el => {
                            el.classList.remove('border-red-400', 'border-red-300', 'bg-red-50/20', 'ring-1', 'ring-red-300', 'border-client-error');
                            if (el.tagName === 'INPUT' || el.tagName === 'SELECT') {
                                el.classList.add('border-slate-300', 'bg-white');
                            } else {
                                el.classList.add('border-slate-200', 'bg-slate-50');
                            }
                        });

                        // Reset file input
                        const photoInput = document.getElementById('photoInput');
                        if (photoInput) {
                            photoInput.value = '';
                        }
                    }

                    // Reset pesan alert/status divisi & jabatan
                    this.divisionError = '';
                    this.divisionSuccess = '';
                    this.roleError = '';
                    this.roleSuccess = '';
                    this.photoError = '';
                },
                openCreate() {
                    this.isEdit = false;
                    this.formAction = "{{ route('admin.employee-management.store') }}";

                    this.form = {
                        pegawai_id: '',
                        nama_pegawai: '',
                        nip: '',
                        email: '',
                        no_handphone: '',
                        divisi_id: '',
                        jabatan_id: '',
                        role: 'Anggota',
                        status: 'Aktif',
                        nfc_id: '',
                        username: '',
                        password: '',
                        password_confirmation: '',
                        photoPreview: '',
                    };

                    if (this.previewObjectUrl) {
                        URL.revokeObjectURL(this.previewObjectUrl);
                        this.previewObjectUrl = null;
                    }

                    this.isSavingEmployee = false;
                    this.clearErrors();
                    this.modalOpen = true;
                },
                openEdit(event) {
                    const button = event.currentTarget;
                    const employee = JSON.parse(button.getAttribute('data-employee'));

                    this.isEdit = true;
                    this.formAction = `/admin/employee-management/${employee.pegawai_id}`;

                    let rawRole = employee.role || 'Anggota';
                    let mappedRole = rawRole;
                    if (rawRole.toLowerCase() === 'admin') {
                        mappedRole = 'Admin';
                    } else if (rawRole.toLowerCase() === 'pegawai' || rawRole.toLowerCase() === 'karyawan') {
                        mappedRole = 'Pegawai';
                    } else if (rawRole.toLowerCase() === 'hr' || rawRole.toLowerCase() === 'hrd' || rawRole.toLowerCase() === 'hr / hrd') {
                        mappedRole = 'HR / HRD';
                    } else if (rawRole.toLowerCase() === 'direktur') {
                        mappedRole = 'Direktur';
                    }

                    this.form = {
                        pegawai_id: employee.pegawai_id || '',
                        nama_pegawai: employee.nama_pegawai || '',
                        nip: employee.nip || '',
                        email: employee.email || '',
                        no_handphone: employee.no_handphone || '',
                        divisi_id: employee.divisi_id || '',
                        jabatan_id: employee.jabatan_id || '',
                        role: mappedRole,
                        status: employee.status || 'Aktif',
                        nfc_id: employee.nfc_id || '',
                        username: employee.username || '',
                        password: '',
                        password_confirmation: '',
                        photoPreview: employee.foto_profile || '',
                    };

                    if (this.previewObjectUrl) {
                        URL.revokeObjectURL(this.previewObjectUrl);
                        this.previewObjectUrl = null;
                    }

                    this.isSavingEmployee = false;
                    this.clearErrors();
                    this.modalOpen = true;
                },
                closeModal() {
                    this.isSavingEmployee = false;
                    this.clearErrors();
                    this.modalOpen = false;
                },
                /**
                 * Validasi client-side form Tambah/Edit Pegawai.
                 * Memeriksa field wajib dan menampilkan pesan error inline
                 * agar user langsung mengetahui field mana yang harus diisi
                 * sebelum data dikirim ke server.
                 */
                validateForm() {
                    // Hapus semua error client-side yang sudah ada sebelumnya
                    const form = document.getElementById('employeeForm');
                    if (!form) return true;

                    form.querySelectorAll('.client-error').forEach(el => el.remove());
                    form.querySelectorAll('.border-client-error').forEach(el => {
                        el.classList.remove('border-red-400', 'ring-1', 'ring-red-300', 'border-client-error');
                    });

                    let isValid = true;

                    const showError = (inputEl, message) => {
                        if (!inputEl) return;
                        inputEl.classList.add('border-red-400', 'ring-1', 'ring-red-300', 'border-client-error');
                        const existing = inputEl.parentElement.querySelector('.client-error');
                        if (!existing) {
                            const p = document.createElement('p');
                            p.className = 'client-error mt-1.5 text-xs text-red-600 font-medium flex items-center gap-1.5';
                            p.innerHTML = '<i class="fa-solid fa-circle-exclamation text-xs"></i> <span>' + message + '</span>';
                            inputEl.parentElement.appendChild(p);
                        }
                        isValid = false;
                    };

                    // Nama Lengkap — wajib untuk tambah dan edit
                    const namaInput = form.querySelector('input[name="nama_pegawai"]');
                    if (namaInput && !namaInput.value.trim()) {
                        showError(namaInput, 'Nama lengkap wajib diisi.');
                    }

                    // NIP — wajib untuk tambah baru
                    if (!this.isEdit) {
                        const nipInput = form.querySelector('input[name="nip"]');
                        if (nipInput && !nipInput.value.trim()) {
                            showError(nipInput, 'NIP wajib diisi.');
                        }
                    }

                    // Email / Username — minimal salah satu wajib diisi (tambah baru maupun edit)
                    // karena login menggunakan email atau username.
                    {
                        const emailInput    = form.querySelector('input[name="email"]');
                        const usernameInput = form.querySelector('input[name="username"]');
                        const emailVal      = emailInput    ? emailInput.value.trim()    : '';
                        const usernameVal   = usernameInput ? usernameInput.value.trim() : '';
                        if (!emailVal && !usernameVal) {
                            showError(emailInput,    'Email atau Username wajib diisi (minimal salah satu) agar pegawai dapat login.');
                            showError(usernameInput, 'Email atau Username wajib diisi (minimal salah satu) agar pegawai dapat login.');
                        }
                    }

                    // No. Telepon — format angka jika diisi
                    const phoneInput = form.querySelector('input[name="no_handphone"]');
                    if (phoneInput && phoneInput.value.trim()) {
                        if (!/^[0-9]+$/.test(phoneInput.value.trim())) {
                            showError(phoneInput, 'Format nomor handphone tidak valid.');
                        }
                    }

                    // Password — wajib untuk tambah baru, opsional untuk edit
                    if (!this.isEdit) {
                        const passInput = form.querySelector('input[name="password"]');
                        if (passInput && !passInput.value.trim()) {
                            showError(passInput, 'Password wajib diisi.');
                        } else if (passInput && passInput.value.trim().length < 6) {
                            showError(passInput, 'Password minimal 6 karakter.');
                        } else {
                            // Validasi konfirmasi password
                            const passConfirm = form.querySelector('input[name="password_confirmation"]');
                            if (passInput && passConfirm && passInput.value !== passConfirm.value) {
                                showError(passConfirm, 'Konfirmasi password tidak cocok.');
                            }
                        }
                    } else {
                        // Edit: jika password diisi, konfirmasi harus cocok
                        const passInput = form.querySelector('input[name="password"]');
                        const passConfirm = form.querySelector('input[name="password_confirmation"]');
                        if (passInput && passInput.value.trim()) {
                            if (passInput.value.trim().length < 6) {
                                showError(passInput, 'Password minimal 6 karakter.');
                            } else if (passConfirm && passInput.value !== passConfirm.value) {
                                showError(passConfirm, 'Konfirmasi password tidak cocok.');
                            }
                        }
                    }

                    // Role Akses — wajib dipilih untuk tambah baru
                    if (!this.isEdit) {
                        const roleSelect = form.querySelector('select[name="role"]');
                        if (roleSelect && !roleSelect.value.trim()) {
                            showError(roleSelect, 'Role Akses wajib dipilih.');
                        }
                    }

                    // Scroll ke error pertama jika ada
                    if (!isValid) {
                        const firstError = form.querySelector('.client-error');
                        if (firstError) {
                            firstError.scrollIntoView({ behavior: 'smooth', block: 'center' });
                        }
                    }

                    return isValid;
                },

                openDetail(event) {
                    const button = event.currentTarget;
                    const employee = JSON.parse(button.getAttribute('data-employee'));
                    this.detailData = {
                        pegawai_id: employee.pegawai_id || '',
                        nama_pegawai: employee.nama_pegawai || '',
                        nip: employee.nip || '',
                        email: employee.email || '',
                        no_handphone: employee.no_handphone || '',
                        divisi_name: employee.divisi_name || employee.divisi_id || '-',
                        jabatan_name: employee.jabatan_name || employee.jabatan_id || '-',
                        status: employee.status || 'Aktif',
                        username: employee.username || '-',
                        nfc_id: employee.nfc_id || '-',
                        foto_profile: employee.foto_profile || '',
                    };
                    this.detailModalOpen = true;
                },
                closeDetail() {
                    this.detailModalOpen = false;
                },
                previewPhoto(event) {
                    this.photoError = '';
                    const file = event.target.files[0];
                    if (!file) {
                        if (this.previewObjectUrl) {
                            URL.revokeObjectURL(this.previewObjectUrl);
                            this.previewObjectUrl = null;
                        }
                        this.form.photoPreview = this.isEdit ? (this.form.foto_profile_existing || '') : '';
                        return;
                    }

                    // Validasi tipe file
                    const validTypes = ['image/jpeg', 'image/png', 'image/jpg'];
                    const isValidType = validTypes.includes(file.type) || file.name.match(/\.(jpg|jpeg|png)$/i);
                    if (!isValidType) {
                        this.photoError = 'Format file tidak didukung. Harap pilih file JPG, JPEG, atau PNG.';
                        event.target.value = '';
                        if (this.previewObjectUrl) {
                            URL.revokeObjectURL(this.previewObjectUrl);
                            this.previewObjectUrl = null;
                        }
                        this.form.photoPreview = this.isEdit ? (this.form.foto_profile_existing || '') : '';
                        return;
                    }

                    // Validasi ukuran file (2MB = 2 * 1024 * 1024 bytes)
                    if (file.size > 2 * 1024 * 1024) {
                        const fileSizeMb = (file.size / (1024 * 1024)).toFixed(2);
                        this.photoError = `Ukuran foto (${fileSizeMb} MB) melebihi batas maksimal 2MB. Silakan pilih foto lain.`;
                        event.target.value = '';
                        if (this.previewObjectUrl) {
                            URL.revokeObjectURL(this.previewObjectUrl);
                            this.previewObjectUrl = null;
                        }
                        this.form.photoPreview = this.isEdit ? (this.form.foto_profile_existing || '') : '';
                        return;
                    }

                    if (this.previewObjectUrl) {
                        URL.revokeObjectURL(this.previewObjectUrl);
                    }

                    this.previewObjectUrl = URL.createObjectURL(file);
                    this.form.photoPreview = this.previewObjectUrl;
                },
                openDivisionModal() {
                    this.divisionModalOpen = true;
                    this.newDivisionName = '';
                    this.divisionError = '';
                    this.divisionSuccess = '';
                },
                closeDivisionModal() {
                    this.divisionModalOpen = false;
                    this.newDivisionName = '';
                    this.divisionError = '';
                },
                openRoleModal() {
                    this.roleModalOpen = true;
                    this.newJabatanName = '';
                    this.roleError = '';
                    this.roleSuccess = '';
                    this.photoError = '';
                },
                closeRoleModal() {
                    this.roleModalOpen = false;
                    this.newJabatanName = '';
                    this.roleError = '';
                },
                exportModalOpen: false,
                exportIsLoading: false,
                openExport() {
                    this.exportIsLoading = false;
                    this.exportErrorMessage = '';
                    this.exportModalOpen = true;
                },
                closeExport() {
                    this.exportIsLoading = false;
                    this.exportErrorMessage = '';
                    this.exportModalOpen = false;
                    this.$refs.exportForm.reset();
                },
                async submitExport(event) {
                    if (this.exportIsLoading) {
                        return;
                    }

                    this.exportIsLoading = true;

                    const form = event.target;
                    const formData = new FormData(form);

                    try {
                        const response = await fetch(form.action, {
                            method: 'POST',
                            body: formData,
                            headers: { 'X-Requested-With': 'XMLHttpRequest' },
                        });

                        const contentType = response.headers.get('Content-Type') || '';

                        // Jika server mengembalikan file (bukan HTML redirect/error)
                        if (response.ok && !contentType.includes('text/html')) {
                            const blob = await response.blob();
                            const disposition = response.headers.get('Content-Disposition') || '';
                            const fileMatch = disposition.match(/filename[^;=\n]*=(['"]?)([^'"\n;]*)\1/);
                            const filename = fileMatch ? fileMatch[2].trim() : 'export';

                            const url = URL.createObjectURL(blob);
                            const a = document.createElement('a');
                            a.href = url;
                            a.download = filename;
                            document.body.appendChild(a);
                            a.click();
                            a.remove();
                            URL.revokeObjectURL(url);

                            this.closeExport();
                            this.exportSuccessMessage = 'Data berhasil diekspor.';
                        } else {
                            // Gagal/tidak ada data: tampilkan pesan, modal tetap terbuka
                            this.exportErrorMessage = 'Tidak ada data yang dapat diekspor.';
                            this.exportIsLoading = false;
                        }
                    } catch (e) {
                        // Network error atau error tak terduga
                        this.exportIsLoading = false;
                    }
                },
                async saveDivision() {
                    this.divisionError = '';
                    this.divisionSuccess = '';
                    const namaDivisi = (this.newDivisionName || '').trim();

                    if (!namaDivisi) {
                        this.divisionError = 'Nama divisi wajib diisi.';
                        return;
                    }

                    this.isSavingDivision = true;

                    try {
                        const response = await fetch('{{ route('admin.employee-management.storeDivision') }}', {
                            method: 'POST',
                            headers: {
                                'Content-Type': 'application/json',
                                'X-CSRF-TOKEN': this.csrfToken,
                                'Accept': 'application/json',
                            },
                            body: JSON.stringify({ nama_divisi: namaDivisi }),
                        });

                        const payload = await response.json();

                        if (!response.ok) {
                            if (payload.errors?.nama_divisi?.[0]) {
                                this.divisionError = payload.errors.nama_divisi[0];
                            } else {
                                this.divisionError = payload.message || 'Gagal menambahkan divisi.';
                            }
                            return;
                        }

                        const select = document.querySelector('select[name="divisi_id"]');
                        if (select) {
                            const option = document.createElement('option');
                            option.value = payload.division.divisi_id;
                            option.textContent = payload.division.nama_divisi;
                            select.appendChild(option);
                        }

                        this.form.divisi_id = payload.division.divisi_id;
                        this.divisionSuccess = payload.message || 'Divisi berhasil ditambahkan.';
                        this.closeDivisionModal();
                    } catch (error) {
                        this.divisionError = 'Terjadi kesalahan saat menyimpan divisi.';
                    } finally {
                        this.isSavingDivision = false;
                    }
                },
                async saveRole() {
                    this.roleError = '';
                    this.roleSuccess = '';
                    this.photoError = '';
                    const namaJabatan = (this.newJabatanName || '').trim();

                    if (!namaJabatan) {
                        this.roleError = 'Nama jabatan wajib diisi.';
                        return;
                    }

                    this.isSavingRole = true;

                    try {
                        const response = await fetch('{{ route('admin.employee-management.storeRole') }}', {
                            method: 'POST',
                            headers: {
                                'Content-Type': 'application/json',
                                'X-CSRF-TOKEN': this.csrfToken,
                                'Accept': 'application/json',
                            },
                            body: JSON.stringify({ nama_jabatan: namaJabatan }),
                        });

                        const payload = await response.json();

                        if (!response.ok) {
                            if (payload.errors?.nama_jabatan?.[0]) {
                                this.roleError = payload.errors.nama_jabatan[0];
                            } else {
                                this.roleError = payload.message || 'Gagal menambahkan jabatan.';
                            }
                            return;
                        }

                        const select = document.querySelector('select[name="jabatan_id"]');
                        if (select) {
                            const option = document.createElement('option');
                            option.value = payload.role.jabatan_id;
                            option.textContent = payload.role.nama_jabatan;
                            select.appendChild(option);
                        }

                        this.form.jabatan_id = payload.role.jabatan_id;
                        this.roleSuccess = payload.message || 'Jabatan berhasil ditambahkan.';
                        this.closeRoleModal();
                    } catch (error) {
                        this.roleError = 'Terjadi kesalahan saat menyimpan jabatan.';
                    } finally {
                        this.isSavingRole = false;
                    }
                }
            };
        }
    </script>
</div>
@endsection