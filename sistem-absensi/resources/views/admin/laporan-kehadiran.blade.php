@extends('layouts.admin.app')

@section('title', 'Laporan Kehadiran')

@section('content')
<div x-data="attendanceReport()">

    {{-- Header --}}
    <div class="mb-5 sm:mb-6">
        <h1 class="text-xl sm:text-2xl font-bold text-gray-900 leading-tight">Laporan Kehadiran</h1>
        <p class="mt-1 text-xs sm:text-sm text-gray-600">Ringkasan laporan kehadiran anggota secara menyeluruh.</p>
    </div>

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

    {{-- Search & Filter Card --}}
    <div class="mb-5 sm:mb-6 rounded-2xl border border-gray-200 bg-white p-4 sm:p-5 shadow-sm">
        <form method="GET" action="{{ route('admin.laporan-kehadiran') }}">
            <div class="flex flex-col gap-3 sm:flex-row sm:items-end">
                {{-- Search Input --}}
                <div class="flex-1 min-w-0">
                    <label class="mb-1 block text-xs sm:text-sm font-medium text-gray-700">Cari {{ \App\Helpers\OrganizationHelper::term('member', 'Anggota') }}</label>
                    <div class="relative mt-1">
                        <span class="pointer-events-none absolute inset-y-0 left-3.5 flex items-center text-gray-400">
                            <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-4.35-4.35m0 0A7.5 7.5 0 1110.5 3a7.5 7.5 0 016.15 12.65z" />
                            </svg>
                        </span>
                        <input
                            x-data="{ _t: null }"
                            @input="clearTimeout(_t); _t = setTimeout(() => $el.closest('form').submit(), 400)"
                            id="search"
                            name="search"
                            type="text"
                            value="{{ request('search') }}"
                            placeholder="Cari nama, status, atau lokasi..."
                            class="w-full rounded-2xl border border-gray-300 bg-white py-2.5 sm:py-3 pl-10 pr-4 text-xs sm:text-sm text-gray-900 shadow-sm focus:border-primary focus:outline-none focus:ring-1 focus:ring-primary" />
                    </div>
                </div>

                {{-- Action Buttons: Filter & Export --}}
                <div class="grid grid-cols-2 gap-2.5 sm:flex sm:items-center sm:gap-3 flex-shrink-0">
                    {{-- Filter Dropdown Trigger --}}
                    <div class="relative" @click.outside="filterOpen = false">
                        <button
                            type="button"
                            @click="filterOpen = !filterOpen"
                            class="w-full sm:w-auto inline-flex items-center justify-center gap-2 rounded-2xl border border-gray-200 bg-white px-4 py-2.5 sm:py-3 text-xs sm:text-sm font-medium text-gray-700 shadow-sm transition hover:bg-gray-50 whitespace-nowrap">
                            <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4 flex-shrink-0 text-gray-500" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 4a1 1 0 011-1h16a1 1 0 011 1v2.586a1 1 0 01-.293.707l-6.414 6.414a1 1 0 00-.293.707V17l-4 4v-6.586a1 1 0 00-.293-.707L3.293 7.293A1 1 0 013 6.586V4z" />
                            </svg>
                            <span>Filter</span>
                        </button>

                        {{-- Filter Popover --}}
                        <div x-show="filterOpen" x-transition x-cloak class="absolute left-0 sm:left-auto sm:right-0 top-full z-30 mt-2 w-[calc(100vw-2.5rem)] sm:w-[340px] max-w-[340px] rounded-2xl border border-gray-200 bg-white p-4 shadow-xl">
                            <div class="space-y-3">
                                <x-forms.select name="status" label="Status">
                                    <option value="Semua" {{ request('status') === 'Semua' ? 'selected' : '' }}>Semua</option>
                                    <option value="Hadir" {{ request('status') === 'Hadir' ? 'selected' : '' }}>Hadir (Semua)</option>
                                    <option value="Tepat Waktu" {{ request('status') === 'Tepat Waktu' ? 'selected' : '' }}>Tepat Waktu</option>
                                    <option value="Terlambat" {{ request('status') === 'Terlambat' ? 'selected' : '' }}>Terlambat</option>
                                    <option value="Tidak Hadir" {{ request('status') === 'Tidak Hadir' ? 'selected' : '' }}>Tidak Hadir</option>
                                </x-forms.select>

                                @hasfeature('division')
                                <x-forms.select name="divisi_id" :label="\App\Helpers\OrganizationHelper::term('division', 'Divisi')">
                                    <option value="Semua" {{ request('divisi_id') === 'Semua' ? 'selected' : '' }}>Semua</option>
                                    @foreach($divisions as $division)
                                        <option value="{{ $division->divisi_id }}" {{ (string) request('divisi_id') === (string) $division->divisi_id ? 'selected' : '' }}>{{ $division->nama_divisi }}</option>
                                    @endforeach
                                </x-forms.select>
                                @endhasfeature

                                <div class="grid gap-3 grid-cols-1 sm:grid-cols-2">
                                    <x-forms.date-picker name="start_date" label="Tanggal Awal" value="{{ request('start_date') }}" />
                                    <x-forms.date-picker name="end_date" label="Tanggal Akhir" value="{{ request('end_date') }}" />
                                </div>

                                @hasfeature('wfo_wfh')
                                <x-forms.select name="mode_kerja" label="Mode Kerja">
                                    <option value="Semua" {{ request('mode_kerja') === 'Semua' ? 'selected' : '' }}>Semua</option>
                                    <option value="WFO" {{ request('mode_kerja') === 'WFO' ? 'selected' : '' }}>WFO</option>
                                    <option value="WFH" {{ request('mode_kerja') === 'WFH' ? 'selected' : '' }}>WFH</option>
                                </x-forms.select>
                                @endhasfeature
                            </div>

                            <div class="mt-4 flex items-center justify-end gap-2 border-t border-slate-200 pt-3">
                                <button
                                    type="button"
                                    @click="window.location.href = '{{ route('admin.laporan-kehadiran') }}'"
                                    class="rounded-2xl border border-gray-200 bg-white px-4 py-2 text-xs sm:text-sm font-semibold text-gray-700 transition hover:bg-gray-50">
                                    Reset
                                </button>
                                <button
                                    type="submit"
                                    class="rounded-2xl bg-primary px-4 py-2 text-xs sm:text-sm font-semibold text-white transition hover:bg-primary-hover">
                                    Terapkan
                                </button>
                            </div>
                        </div>
                    </div>

                    {{-- Export Button --}}
                    @php
                        $canExportLaporan = Auth::user()?->roleAkses?->hasPrivilege('export_laporan_kehadiran') ?? false;
                    @endphp
                    <div>
                        @if($canExportLaporan)
                            <button
                                type="button"
                                @click="openExport()"
                                class="w-full sm:w-auto inline-flex items-center justify-center gap-2 rounded-2xl border border-gray-200 bg-white px-4 py-2.5 sm:py-3 text-xs sm:text-sm font-medium text-gray-700 shadow-sm transition hover:bg-gray-50 whitespace-nowrap">
                                <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4 text-gray-500" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4" />
                                </svg>
                                <span>Export</span>
                            </button>
                        @else
                            <button
                                type="button"
                                disabled
                                title="Anda tidak memiliki hak akses untuk mengekspor data laporan"
                                class="w-full sm:w-auto inline-flex items-center justify-center gap-2 rounded-2xl border border-gray-200 bg-gray-100 px-4 py-2.5 sm:py-3 text-xs sm:text-sm font-medium text-gray-400 opacity-60 cursor-not-allowed select-none whitespace-nowrap">
                                <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4 text-gray-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4" />
                                </svg>
                                <span>Export</span>
                            </button>
                        @endif
                    </div>
                </div>
            </div>
        </form>
    </div>

    {{-- ======================================================== --}}
    {{-- 1. TAMPILAN MOBILE: CARD LIST (Layar HP < 640px) --}}
    {{-- ======================================================== --}}
    <div class="block md:hidden space-y-3.5 mb-6">
        @forelse($attendances as $attendance)
            <div class="rounded-2xl border border-slate-200 bg-white p-4 shadow-sm space-y-3">
                {{-- Header Card: Foto + Nama + Status --}}
                <div class="flex items-center justify-between gap-3 border-b border-slate-100 pb-3">
                    <div class="flex items-center gap-3 min-w-0">
                        @php $photoUrl = supabase_public_url($attendance->pegawai?->foto_profile); @endphp
                        @if ($photoUrl)
                            <img src="{{ $photoUrl }}" alt="{{ $attendance->pegawai?->nama_pegawai ?? '-' }}" class="h-10 w-10 rounded-full object-cover flex-shrink-0 shadow-sm" />
                        @else
                            <div class="flex h-10 w-10 items-center justify-center rounded-full bg-slate-100 text-xs font-semibold text-slate-700 flex-shrink-0 border border-slate-200">
                                {{ getInitials($attendance->pegawai?->nama_pegawai) }}
                            </div>
                        @endif
                        <div class="min-w-0">
                            <h3 class="truncate text-sm font-bold text-slate-900">{{ $attendance->pegawai?->nama_pegawai ?? '-' }}</h3>
                            @hasfeature('division')
                            <p class="text-xs text-slate-500">{{ $attendance->pegawai?->masterDivisi?->nama_divisi ?? '-' }}</p>
                            @endhasfeature
                        </div>
                    </div>
                    <div class="flex-shrink-0">
                        <x-status-badge :status="$attendance->status_kehadiran ?? '-'" />
                    </div>
                </div>

                {{-- Grid Detail Absensi --}}
                <div class="grid grid-cols-2 gap-2.5 text-xs">
                    <div class="rounded-xl bg-slate-50 p-2.5">
                        <p class="text-slate-400 text-[10px] uppercase font-semibold">Tanggal</p>
                        <p class="font-medium text-slate-800 mt-0.5">{{ \Carbon\Carbon::parse($attendance->tanggal_absensi)->translatedFormat('d M Y') }}</p>
                    </div>

                    @hasfeature('wfo_wfh')
                    <div class="rounded-xl bg-slate-50 p-2.5">
                        <p class="text-slate-400 text-[10px] uppercase font-semibold">Mode Kerja</p>
                        <p class="font-medium text-slate-800 mt-0.5">{{ $attendance->skema_kerja ?? '-' }}</p>
                    </div>
                    @endhasfeature

                    <div class="rounded-xl bg-slate-50 p-2.5">
                        <p class="text-slate-400 text-[10px] uppercase font-semibold">Jam Masuk / Pulang</p>
                        <p class="font-medium text-slate-800 mt-0.5">
                            {{ $attendance->jam_checkin ? \Carbon\Carbon::parse($attendance->jam_checkin)->format('H:i') : '-' }} - 
                            {{ $attendance->jam_checkout ? \Carbon\Carbon::parse($attendance->jam_checkout)->format('H:i') : '-' }}
                        </p>
                    </div>

                    <div class="rounded-xl bg-slate-50 p-2.5">
                        <p class="text-slate-400 text-[10px] uppercase font-semibold">Durasi Kerja</p>
                        <p class="font-medium text-slate-800 mt-0.5">
                            @php
                                if ($attendance->jam_checkin && $attendance->jam_checkout) {
                                    $durationMinutes = \Carbon\Carbon::parse($attendance->jam_checkin)->diffInMinutes($attendance->jam_checkout);
                                    $hours = intdiv($durationMinutes, 60);
                                    $minutes = $durationMinutes % 60;
                                    echo trim(($hours ? $hours . 'j' : '') . ($minutes ? ' ' . $minutes . 'm' : '')) ?: '0m';
                                } else {
                                    echo '-';
                                }
                            @endphp
                        </p>
                    </div>
                </div>

                @if($attendance->latitude !== null && $attendance->longitude !== null)
                    <div class="text-[11px] text-slate-400 flex items-center gap-1.5 pt-1">
                        <i class="fa-solid fa-location-dot text-slate-400"></i>
                        <span class="truncate">{{ $attendance->latitude }}, {{ $attendance->longitude }}</span>
                    </div>
                @endif
            </div>
        @empty
            <div class="rounded-2xl border border-slate-200 bg-white p-8 text-center text-xs text-slate-500">
                Tidak ada data kehadiran.
            </div>
        @endforelse
    </div>

    {{-- ======================================================== --}}
    {{-- 2. TAMPILAN DESKTOP & TABLET: FULL TABLE (Scrollable Halus) --}}
    {{-- ======================================================== --}}
    <section class="hidden md:block rounded-2xl border border-gray-200 bg-white shadow-sm mb-6 overflow-hidden">
        <div class="overflow-x-auto w-full">
            <table class="w-full min-w-[950px] divide-y divide-gray-200 text-left text-sm">
                <thead class="bg-gray-50/80">
                    <tr>
                        <th class="whitespace-nowrap px-4 py-3.5 text-left text-xs font-semibold uppercase tracking-wider text-gray-600">{{ \App\Helpers\OrganizationHelper::term('member', 'Anggota') }}</th>
                        @hasfeature('division')
                        <th class="whitespace-nowrap px-4 py-3.5 text-left text-xs font-semibold uppercase tracking-wider text-gray-600">{{ \App\Helpers\OrganizationHelper::term('division', 'Divisi') }}</th>
                        @endhasfeature
                        <th class="whitespace-nowrap px-4 py-3.5 text-left text-xs font-semibold uppercase tracking-wider text-gray-600">Tanggal</th>
                        <th class="whitespace-nowrap px-4 py-3.5 text-left text-xs font-semibold uppercase tracking-wider text-gray-600">Masuk</th>
                        <th class="whitespace-nowrap px-4 py-3.5 text-left text-xs font-semibold uppercase tracking-wider text-gray-600">Keluar</th>
                        <th class="whitespace-nowrap px-4 py-3.5 text-left text-xs font-semibold uppercase tracking-wider text-gray-600">Durasi</th>
                        @hasfeature('wfo_wfh')
                        <th class="whitespace-nowrap px-4 py-3.5 text-left text-xs font-semibold uppercase tracking-wider text-gray-600">Mode</th>
                        @endhasfeature
                        <th class="whitespace-nowrap px-4 py-3.5 text-left text-xs font-semibold uppercase tracking-wider text-gray-600">Lokasi</th>
                        <th class="whitespace-nowrap px-4 py-3.5 text-left text-xs font-semibold uppercase tracking-wider text-gray-600">Status</th>
                    </tr>
                </thead>

                <tbody class="divide-y divide-gray-200 bg-white">
                    @forelse($attendances as $attendance)
                        <tr class="transition hover:bg-gray-50/80">
                            <td class="whitespace-nowrap px-4 py-3">
                                <div class="flex items-center gap-3">
                                    @php $photoUrl = supabase_public_url($attendance->pegawai?->foto_profile); @endphp
                                    @if ($photoUrl)
                                        <img src="{{ $photoUrl }}" alt="{{ $attendance->pegawai?->nama_pegawai ?? '-' }}" class="h-10 w-10 rounded-full object-cover flex-shrink-0 shadow-sm" />
                                    @else
                                        <div class="flex h-10 w-10 items-center justify-center rounded-full bg-slate-100 text-sm font-semibold text-slate-700 flex-shrink-0 border border-slate-200">
                                            {{ getInitials($attendance->pegawai?->nama_pegawai) }}
                                        </div>
                                    @endif
                                    <div class="min-w-0">
                                        <p class="truncate text-sm font-semibold text-gray-900">{{ $attendance->pegawai?->nama_pegawai ?? '-' }}</p>
                                    </div>
                                </div>
                            </td>

                            @hasfeature('division')
                            <td class="whitespace-nowrap px-4 py-3 text-sm text-gray-700">
                                {{ $attendance->pegawai?->masterDivisi?->nama_divisi ?? '-' }}
                            </td>
                            @endhasfeature

                            <td class="whitespace-nowrap px-4 py-3 text-sm text-gray-700">{{ \Carbon\Carbon::parse($attendance->tanggal_absensi)->translatedFormat('d F Y') }}</td>
                            <td class="whitespace-nowrap px-4 py-3 text-sm font-medium text-gray-800">{{ $attendance->jam_checkin ? \Carbon\Carbon::parse($attendance->jam_checkin)->format('H:i') : '-' }}</td>
                            <td class="whitespace-nowrap px-4 py-3 text-sm font-medium text-gray-800">{{ $attendance->jam_checkout ? \Carbon\Carbon::parse($attendance->jam_checkout)->format('H:i') : '-' }}</td>
                            <td class="whitespace-nowrap px-4 py-3 text-sm text-gray-700">
                                @php
                                    if ($attendance->jam_checkin && $attendance->jam_checkout) {
                                        $durationMinutes = \Carbon\Carbon::parse($attendance->jam_checkin)->diffInMinutes($attendance->jam_checkout);
                                        $hours = intdiv($durationMinutes, 60);
                                        $minutes = $durationMinutes % 60;
                                        echo trim(($hours ? $hours . ' Jam' : '') . ($minutes ? ' ' . $minutes . ' Menit' : '')) ?: '0 Menit';
                                    } else {
                                        echo '-';
                                    }
                                @endphp
                            </td>
                            @hasfeature('wfo_wfh')
                            <td class="whitespace-nowrap px-4 py-3 text-sm text-gray-700">
                                <span class="inline-flex items-center rounded-lg bg-slate-100 px-2.5 py-1 text-xs font-medium text-slate-700">
                                    {{ $attendance->skema_kerja ?? '-' }}
                                </span>
                            </td>
                            @endhasfeature
                            <td class="whitespace-nowrap px-4 py-3 text-sm text-gray-700">{{ $attendance->latitude !== null && $attendance->longitude !== null ? $attendance->latitude . ', ' . $attendance->longitude : '-' }}</td>
                            <td class="whitespace-nowrap px-4 py-3 text-sm">
                                <x-status-badge :status="$attendance->status_kehadiran ?? '-'" />
                            </td>
                        </tr>
                    @empty
                        @php
                            $tableColspan = 7 + (!empty($hasDivision) ? 1 : 0) + (!empty($hasWfoWfh) ? 1 : 0);
                        @endphp
                        <tr>
                            <td colspan="{{ $tableColspan }}" class="px-4 py-12 text-center text-sm text-gray-500">
                                Tidak ada data kehadiran.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </section>

    {{-- ======================================================== --}}
    {{-- 3. PAGINATION (Responsif untuk Mobile, Tablet, & Desktop) --}}
    {{-- ======================================================== --}}
    <div class="flex flex-col gap-3.5 border-t border-slate-200 bg-white p-4 sm:p-5 rounded-2xl shadow-sm sm:flex-row sm:items-center sm:justify-between mb-6">
        <p class="text-xs sm:text-sm text-slate-500 text-center sm:text-left">
            Menampilkan <span class="font-semibold text-slate-700">{{ $attendances->firstItem() ?? 0 }} - {{ $attendances->lastItem() ?? 0 }}</span> dari <span class="font-semibold text-slate-700">{{ $attendances->total() }}</span> data
        </p>

        <div class="overflow-x-auto pb-1 sm:pb-0 flex justify-center sm:justify-end">
            <nav class="inline-flex overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm flex-shrink-0" aria-label="Pagination">
                {{-- Previous Page Link --}}
                <a
                    class="inline-flex h-9 sm:h-11 w-9 sm:w-11 items-center justify-center border-r border-slate-200 text-xs sm:text-sm font-medium transition {{ $attendances->onFirstPage() ? 'cursor-not-allowed pointer-events-none bg-slate-100 text-slate-300' : 'text-slate-700 hover:bg-slate-100 hover:text-slate-900 active:bg-slate-200' }}"
                    href="{{ $attendances->onFirstPage() ? '#' : $attendances->withQueryString()->previousPageUrl() }}"
                    aria-disabled="{{ $attendances->onFirstPage() ? 'true' : 'false' }}">
                    <i class="fa-solid fa-chevron-left text-xs"></i>
                </a>

                @php
                    $currentPage = $attendances->currentPage();
                    $lastPage = $attendances->lastPage();
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
                        <span class="inline-flex h-9 sm:h-11 min-w-[32px] sm:min-w-[44px] items-center justify-center border-r border-slate-200 px-2 text-xs sm:text-sm font-medium text-slate-400">
                            ...
                        </span>
                    @else
                        <a
                            class="inline-flex h-9 sm:h-11 min-w-[32px] sm:min-w-[44px] items-center justify-center border-r border-slate-200 px-2 sm:px-3.5 text-xs sm:text-sm font-semibold transition {{ $element === $currentPage ? 'bg-primary text-white hover:bg-primary-hover' : 'bg-white text-slate-700 hover:bg-slate-100 hover:text-slate-900 active:bg-slate-200' }}"
                            href="{{ $attendances->url($element) }}">
                            {{ $element }}
                        </a>
                    @endif
                @endforeach

                {{-- Next Page Link --}}
                <a
                    class="inline-flex h-9 sm:h-11 w-9 sm:w-11 items-center justify-center text-xs sm:text-sm font-medium transition {{ ! $attendances->hasMorePages() ? 'cursor-not-allowed pointer-events-none bg-slate-100 text-slate-300' : 'text-slate-700 hover:bg-slate-100 hover:text-slate-900 active:bg-slate-200' }}"
                    href="{{ $attendances->hasMorePages() ? $attendances->withQueryString()->nextPageUrl() : '#' }}"
                    aria-disabled="{{ ! $attendances->hasMorePages() ? 'true' : 'false' }}">
                    <i class="fa-solid fa-chevron-right text-xs"></i>
                </a>
            </nav>
        </div>
    </div>

    {{-- ======================================================== --}}
    {{-- 4. EXPORT MODAL --}}
    {{-- ======================================================== --}}
    <div x-show="exportModalOpen"
        x-cloak
        class="fixed inset-0 z-50 flex items-center justify-center bg-slate-900/40 backdrop-blur-sm p-4"
        @click.self="closeExport()">
        <div class="relative w-full max-w-2xl max-h-[90vh] overflow-visible rounded-3xl sm:rounded-[28px] bg-white shadow-2xl" @click.stop>
            <form x-ref="exportForm" @submit.prevent="submitExport($event)">
                <div class="flex items-center justify-between border-b border-slate-200 px-5 sm:px-8 py-5 sm:py-6">
                    <div>
                        <h2 class="text-base sm:text-lg font-bold text-slate-900">Export Laporan</h2>
                        <p class="mt-1 text-xs sm:text-sm text-slate-500">Pilih format dan rentang filter untuk unduh laporan.</p>
                    </div>
                    <button type="button" @click="closeExport()" class="flex items-center justify-center rounded-full p-2 text-slate-400 transition hover:bg-slate-100 hover:text-slate-600" aria-label="Tutup modal">
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                        </svg>
                    </button>
                </div>

                <div class="space-y-5 sm:space-y-6 px-5 sm:px-8 py-5 sm:py-6">
                    <input type="hidden" name="search" value="{{ request('search') }}" />
                    @hasfeature('wfo_wfh')
                    <input type="hidden" name="mode_kerja" value="{{ request('mode_kerja') }}" />
                    @endhasfeature

                    <div class="grid gap-5 sm:gap-6 grid-cols-1 md:grid-cols-2">
                        <div class="space-y-3 sm:space-y-4">
                            <p class="text-xs sm:text-sm font-semibold text-slate-900">Format Export</p>

                            <div class="space-y-3">
                                <label class="flex cursor-pointer items-start gap-3 rounded-2xl border border-slate-200 bg-white p-3.5 sm:p-4 transition hover:border-slate-300 hover:bg-slate-50">
                                    <input type="radio" name="format" value="xlsx" class="mt-1 h-4 w-4 text-primary focus:ring-primary" x-model="exportFormat" checked />
                                    <div class="flex-1">
                                        <p class="text-sm font-medium text-slate-900">Excel (.xlsx)</p>
                                        <p class="text-xs text-slate-500">Unduh file Excel dengan data laporan kehadiran.</p>
                                    </div>
                                </label>

                                <label class="flex cursor-pointer items-start gap-3 rounded-2xl border border-slate-200 bg-white p-3.5 sm:p-4 transition hover:border-slate-300 hover:bg-slate-50">
                                    <input type="radio" name="format" value="csv" class="mt-1 h-4 w-4 text-primary focus:ring-primary" x-model="exportFormat" />
                                    <div class="flex-1">
                                        <p class="text-sm font-medium text-slate-900">CSV</p>
                                        <p class="text-xs text-slate-500">Unduh file CSV yang mudah diolah.</p>
                                    </div>
                                </label>

                                <label class="flex cursor-pointer items-start gap-3 rounded-2xl border border-slate-200 bg-white p-3.5 sm:p-4 transition hover:border-slate-300 hover:bg-slate-50">
                                    <input type="radio" name="format" value="pdf" class="mt-1 h-4 w-4 text-primary focus:ring-primary" x-model="exportFormat" />
                                    <div class="flex-1">
                                        <p class="text-sm font-medium text-slate-900">PDF</p>
                                        <p class="text-xs text-slate-500">Unduh file PDF yang siap dicetak.</p>
                                    </div>
                                </label>
                            </div>
                        </div>

                        <div class="space-y-3 sm:space-y-4">
                            <p class="text-xs sm:text-sm font-semibold text-slate-900">Rentang Tanggal & Filter</p>
                            <div class="space-y-3">
                                <x-forms.date-picker name="start_date" label="Tanggal Awal" value="{{ request('start_date') }}" />
                                <x-forms.date-picker name="end_date" label="Tanggal Akhir" value="{{ request('end_date') }}" />
                            </div>
                        </div>
                    </div>

                    <div class="grid gap-3 sm:gap-4 border-t border-slate-200 pt-5 sm:pt-6 grid-cols-1 sm:grid-cols-2 md:grid-cols-3">
                        <x-forms.select name="status" label="Status">
                            <option value="Semua" {{ request('status') === 'Semua' ? 'selected' : '' }}>Semua</option>
                            <option value="Hadir" {{ request('status') === 'Hadir' ? 'selected' : '' }}>Hadir (Semua)</option>
                            <option value="Tepat Waktu" {{ request('status') === 'Tepat Waktu' ? 'selected' : '' }}>Tepat Waktu</option>
                            <option value="Terlambat" {{ request('status') === 'Terlambat' ? 'selected' : '' }}>Terlambat</option>
                            <option value="Tidak Hadir" {{ request('status') === 'Tidak Hadir' ? 'selected' : '' }}>Tidak Hadir</option>
                        </x-forms.select>

                        @hasfeature('division')
                        <x-forms.select name="divisi_id" :label="\App\Helpers\OrganizationHelper::term('division', 'Divisi')">
                            <option value="Semua" {{ request('divisi_id') === 'Semua' ? 'selected' : '' }}>Semua</option>
                            @foreach($divisions as $division)
                                <option value="{{ $division->divisi_id }}" {{ (string) request('divisi_id') === (string) $division->divisi_id ? 'selected' : '' }}>{{ $division->nama_divisi }}</option>
                            @endforeach
                        </x-forms.select>
                        @endhasfeature

                        @php
                            $pegawaiOptions = $pegawaiList->map(function($p) {
                                return ['value' => $p->pegawai_id, 'text' => $p->nama_pegawai];
                            })->toArray();
                        @endphp
                        <x-forms.searchable-select name="pegawai_id" :label="\App\Helpers\OrganizationHelper::term('member', 'Anggota')" :options="$pegawaiOptions"
                            class="w-full rounded-3xl border border-slate-200 bg-white pl-4 pr-4 py-3 text-sm text-slate-900 focus:border-slate-400 focus:outline-none focus:ring-2 focus:ring-slate-200" 
                            selected="{{ request('pegawai_id', '') }}" />
                    </div>

                    <template x-if="exportErrorMessage">
                        <div class="rounded-xl border border-red-200 bg-red-50 px-4 py-3 text-xs sm:text-sm text-red-700" x-text="exportErrorMessage"></div>
                    </template>

                    <div class="flex flex-col-reverse gap-3 border-t border-slate-200 pt-5 sm:pt-6 sm:flex-row sm:justify-end">
                        <button type="button" @click="closeExport()" class="w-full sm:w-auto rounded-2xl border border-slate-200 bg-white px-6 py-3 text-xs sm:text-sm font-semibold text-slate-700 transition hover:bg-slate-50">
                            Batal
                        </button>
                        <button
                            type="submit"
                            x-bind:disabled="exportIsLoading"
                            class="w-full sm:w-auto rounded-2xl bg-primary px-6 py-3 text-xs sm:text-sm font-semibold text-white transition hover:bg-primary-hover shadow-sm"
                            x-text="exportIsLoading ? 'Menyiapkan...' : 'Unduh'">
                        </button>
                    </div>
                </div>
            </form>
        </div>
    </div>

    <script>
        function attendanceReport() {
            return {
                filterOpen: false,
                exportModalOpen: false,
                exportFormat: 'xlsx',
                exportIsLoading: false,
                exportSuccessMessage: '',
                exportErrorMessage: '',
                openExport() {
                    this.exportIsLoading = false;
                    this.resetExport();
                    this.exportModalOpen = true;
                },
                closeExport() {
                    this.exportIsLoading = false;
                    this.exportModalOpen = false;
                    this.resetExport();
                },
                resetExport() {
                    this.exportFormat = 'xlsx';
                    this.exportErrorMessage = '';
                    if (this.$refs.exportForm) {
                        this.$refs.exportForm.reset();

                        const startDateInput = this.$refs.exportForm.querySelector('input[name="start_date"]');
                        if (startDateInput) startDateInput.value = '{{ request('start_date', '') }}';

                        const endDateInput = this.$refs.exportForm.querySelector('input[name="end_date"]');
                        if (endDateInput) endDateInput.value = '{{ request('end_date', '') }}';

                        const statusSelect = this.$refs.exportForm.querySelector('select[name="status"]');
                        if (statusSelect) statusSelect.value = '{{ request('status', 'Semua') }}';

                        @hasfeature('division')
                        const divisiSelect = this.$refs.exportForm.querySelector('select[name="divisi_id"]');
                        if (divisiSelect) divisiSelect.value = '{{ request('divisi_id', 'Semua') }}';
                        @endhasfeature

                        const searchableSelects = this.$refs.exportForm.querySelectorAll('[x-data]');
                        searchableSelects.forEach(el => {
                            if (el._x_dataStack && el._x_dataStack[0]) {
                                el._x_dataStack[0].selected = '{{ request('pegawai_id', '') }}';
                                el._x_dataStack[0].search = '';
                                el._x_dataStack[0].open = false;
                            }
                        });
                        const pegawaiInput = this.$refs.exportForm.querySelector('input[name="pegawai_id"]');
                        if (pegawaiInput) pegawaiInput.value = '{{ request('pegawai_id', '') }}';
                    }
                },
                async submitExport(event) {
                    if (this.exportIsLoading) {
                        return;
                    }

                    this.exportIsLoading = true;
                    this.exportErrorMessage = '';

                    const form = this.$refs.exportForm;
                    const formData = new FormData(form);
                    const startDate = formData.get('start_date');
                    const endDate = formData.get('end_date');
                    if (startDate && endDate && startDate > endDate) {
                        this.exportErrorMessage = 'Tanggal awal tidak boleh lebih besar dari tanggal akhir.';
                        this.exportIsLoading = false;
                        return;
                    }
                    const params = new URLSearchParams(formData);

                    let exportUrl = '{{ route('admin.laporan-kehadiran.export.excel') }}';
                    if (this.exportFormat === 'csv') {
                        exportUrl = '{{ route('admin.laporan-kehadiran.export.csv') }}';
                    } else if (this.exportFormat === 'pdf') {
                        exportUrl = '{{ route('admin.laporan-kehadiran.export.pdf') }}';
                    }

                    try {
                        const response = await fetch(`${exportUrl}?${params.toString()}`, {
                            method: 'GET',
                            headers: { 'X-Requested-With': 'XMLHttpRequest' },
                        });

                        const contentType = response.headers.get('Content-Type') || '';

                        // Jika server mengembalikan file (bukan HTML redirect/error)
                        if (response.ok && !contentType.includes('text/html')) {
                            const blob = await response.blob();
                            const disposition = response.headers.get('Content-Disposition') || '';
                            const fileMatch = disposition.match(/filename[^;=\n]*=(['"]?)([^'"\n;]*)\1/);
                            const filename = fileMatch ? fileMatch[2].trim() : ('laporan-kehadiran.' + this.exportFormat);

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
                        this.exportErrorMessage = 'Terjadi kesalahan saat mengekspor data.';
                        this.exportIsLoading = false;
                    }
                }
            };
        }
    </script>
</div>
@endsection