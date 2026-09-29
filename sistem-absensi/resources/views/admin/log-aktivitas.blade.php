@extends('layouts.admin.app')

@section('title', 'Log Aktivitas')

@section('content')
<div class="w-full">
    {{-- 1. Header Section --}}
    <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between mb-5 sm:mb-6">
        <div>
            <h1 class="text-xl sm:text-2xl font-bold text-gray-900 leading-tight">Log Aktivitas</h1>
            <p class="mt-1 text-xs sm:text-sm text-gray-600">Pantau seluruh aktivitas admin dan anggota secara real-time.</p>
        </div>
    </div>

    {{-- 2. Statistik Cards --}}
    <div class="grid grid-cols-1 sm:grid-cols-2 xl:grid-cols-4 gap-3.5 sm:gap-5 mb-5 sm:mb-6">
        {{-- Card 1: Total Anggota --}}
        <div class="rounded-2xl border border-gray-200 bg-white p-4 sm:p-5 shadow-sm flex items-center gap-4">
            <div class="flex h-12 w-12 items-center justify-center rounded-2xl bg-blue-50 text-primary flex-shrink-0">
                <svg class="h-6 w-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 7h8m0 0v8m0-8l-8 8-4-4-6 6"></path>
                </svg>
            </div>
            <div class="min-w-0">
                <p class="text-xs sm:text-sm font-medium text-gray-500">Total Anggota</p>
                <h3 class="text-xl sm:text-2xl font-bold text-gray-900 mt-0.5">{{ $totalPegawai }}</h3>
                <p class="text-[11px] text-gray-400">Total terdaftar di sistem</p>
            </div>
        </div>

        {{-- Card 2: Hadir Hari Ini --}}
        <div class="rounded-2xl border border-gray-200 bg-white p-4 sm:p-5 shadow-sm flex items-center gap-4">
            <div class="flex h-12 w-12 items-center justify-center rounded-2xl bg-green-50 text-green-600 flex-shrink-0">
                <svg class="h-6 w-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 16l-4-4m0 0l4-4m-4 4h14m-5 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h7a3 3 0 013 3v1"></path>
                </svg>
            </div>
            <div class="min-w-0">
                <p class="text-xs sm:text-sm font-medium text-gray-500">Hadir Hari Ini</p>
                <h3 class="text-xl sm:text-2xl font-bold text-gray-900 mt-0.5">{{ $hadirHariIni }}</h3>
                <p class="text-[11px] text-gray-400">Anggota sudah check-in</p>
            </div>
        </div>

        {{-- Card 3: Skema Kerja --}}
        <div class="rounded-2xl border border-gray-200 bg-white p-4 sm:p-5 shadow-sm flex items-center gap-4">
            <div class="flex h-12 w-12 items-center justify-center rounded-2xl bg-orange-50 text-orange-500 flex-shrink-0">
                <svg class="h-6 w-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4"></path>
                </svg>
            </div>
            <div class="min-w-0">
                <p class="text-xs sm:text-sm font-medium text-gray-500">Skema Kerja</p>
                <h3 class="text-base sm:text-lg font-bold text-gray-900 mt-0.5">{{ $wfoCount }} WFO / {{ $wfhWfcCount }} WFH</h3>
                <p class="text-[11px] text-gray-400">Distribusi absensi hari ini</p>
            </div>
        </div>

        {{-- Card 4: Status Sistem --}}
        <div class="rounded-2xl border border-gray-200 bg-white p-4 sm:p-5 shadow-sm flex items-center gap-4">
            <div class="flex h-12 w-12 items-center justify-center rounded-2xl bg-indigo-50 text-indigo-500 flex-shrink-0">
                <i class="fa-solid fa-signal text-lg"></i>
            </div>
            <div class="min-w-0">
                <p class="text-xs sm:text-sm font-medium text-gray-500">Status Sistem</p>
                <h3 class="text-xl sm:text-2xl font-bold text-green-600 mt-0.5">Aktif</h3>
                <p class="text-[11px] text-gray-400">Sistem berjalan normal</p>
            </div>
        </div>
    </div>

    {{-- 3. Content Area: Log List & Side Widget --}}
    <div class="flex flex-col gap-5 sm:gap-6">
        {{-- Kolom Kiri: Log Aktivitas --}}
        <div class="w-full">

            {{-- 3a. Mobile Card View (Tampil di Layar HP < 640px) --}}
            <div class="block sm:hidden space-y-3 mb-6">
                @forelse ($logs as $log)
                    <div class="rounded-2xl border border-slate-200 bg-white p-4 shadow-sm space-y-2.5">
                        <div class="flex items-center justify-between gap-3 border-b border-slate-100 pb-2.5">
                            <div class="min-w-0">
                                <h3 class="truncate text-sm font-bold text-slate-900">{{ $log->nama_pegawai ?? $log->username }}</h3>
                            </div>
                            <div class="flex-shrink-0">
                                <span class="rounded-full {{ strtolower($log->access_role_display) === 'admin' ? 'bg-blue-50 text-primary' : 'bg-slate-100 text-slate-600' }} px-2.5 py-0.5 text-xs font-semibold">{{ $log->access_role_display }}</span>
                            </div>
                        </div>

                        <p class="text-xs sm:text-sm text-slate-700 leading-relaxed font-medium">
                            {{ $log->aktivitas_display }}
                        </p>

                        <div class="text-[11px] text-slate-400 flex items-center gap-1.5 pt-1 border-t border-slate-100">
                            <i class="fa-regular fa-clock text-slate-400 text-xs"></i>
                            <span>{{ \Carbon\Carbon::parse($log->waktu_log)->translatedFormat('d M Y') }} &bull; {{ \Carbon\Carbon::parse($log->waktu_log)->format('H:i') }} WIB</span>
                        </div>
                    </div>
                @empty
                    <div class="rounded-2xl border border-slate-200 bg-white p-8 text-center text-xs text-slate-500">
                        Belum ada aktivitas yang tercatat.
                    </div>
                @endforelse
            </div>

            <div class="hidden sm:block rounded-2xl border border-gray-200 bg-white shadow-sm overflow-hidden">
                <div class="overflow-x-auto w-full">
                    <table class="w-full min-w-[650px] divide-y divide-gray-200 text-left text-sm">
                        <thead class="bg-gray-50/80">
                            <tr>
                                <th class="px-5 py-3.5 text-left text-xs font-semibold uppercase tracking-wider text-gray-600 whitespace-nowrap">Waktu</th>
                                <th class="px-5 py-3.5 text-left text-xs font-semibold uppercase tracking-wider text-gray-600 whitespace-nowrap">Pengguna</th>
                                <th class="px-5 py-3.5 text-left text-xs font-semibold uppercase tracking-wider text-gray-600 whitespace-nowrap">Role</th>
                                <th class="px-5 py-3.5 text-left text-xs font-semibold uppercase tracking-wider text-gray-600">Aktivitas</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-200 bg-white">
                            @forelse ($logs as $log)
                                <tr class="transition hover:bg-gray-50/80">
                                    <td class="whitespace-nowrap px-5 py-3.5 text-sm">
                                        <div class="font-semibold text-gray-900">
                                            {{ \Carbon\Carbon::parse($log->waktu_log)->format('H:i') }} WIB
                                        </div>
                                        <div class="text-xs text-gray-500">
                                            {{ \Carbon\Carbon::parse($log->waktu_log)->translatedFormat('d M Y') }}
                                        </div>
                                    </td>
                                    <td class="whitespace-nowrap px-5 py-3.5 text-sm font-semibold text-gray-900">
                                        {{ $log->nama_pegawai ?? $log->username }}
                                    </td>
                                    <td class="whitespace-nowrap px-5 py-3.5 text-sm">
                                        <span class="rounded-full {{ strtolower($log->access_role_display) === 'admin' ? 'bg-blue-50 text-primary' : 'bg-gray-100 text-gray-600' }} px-2 py-1 text-xs font-semibold">{{ $log->access_role_display }}</span>
                                    </td>
                                    <td class="px-5 py-3.5 text-sm text-gray-700">
                                        {{ $log->aktivitas_display }}
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="4" class="px-5 py-12 text-center text-sm text-gray-500">
                                        Belum ada aktivitas yang tercatat.
                                    </td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
                
                {{-- Custom Pagination --}}
                <div class="flex flex-col gap-3.5 border-t border-slate-200 bg-white p-4 sm:p-5 rounded-b-2xl shadow-sm sm:flex-row sm:items-center sm:justify-between">
                    @php
                        $from = $logs->firstItem() ?: 0;
                        $to = $logs->lastItem() ?: 0;
                        $total = $logs->total();
                        $currentPage = $logs->currentPage();
                        $lastPage = $logs->lastPage();
                    @endphp

                    <p class="text-xs sm:text-sm text-slate-500 text-center sm:text-left">
                        Menampilkan <span class="font-semibold text-slate-700">{{ $from }} - {{ $to }}</span> dari <span class="font-semibold text-slate-700">{{ $total }}</span> data
                    </p>

                    <div class="overflow-x-auto pb-1 sm:pb-0 flex justify-center sm:justify-end">
                        <nav class="inline-flex overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm flex-shrink-0" aria-label="Pagination">
                            {{-- Previous Page Link --}}
                            <a
                                class="inline-flex h-9 sm:h-11 w-9 sm:w-11 items-center justify-center border-r border-slate-200 text-xs sm:text-sm font-medium transition {{ $logs->onFirstPage() ? 'cursor-not-allowed pointer-events-none bg-slate-100 text-slate-300' : 'text-slate-700 hover:bg-slate-100 hover:text-slate-900 active:bg-slate-200' }}"
                                href="{{ $logs->onFirstPage() ? '#' : $logs->previousPageUrl() }}"
                                aria-disabled="{{ $logs->onFirstPage() ? 'true' : 'false' }}"
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
                                        href="{{ $logs->url($element) }}"
                                    >
                                        {{ $element }}
                                    </a>
                                @endif
                            @endforeach

                            {{-- Next Page Link --}}
                            <a
                                class="inline-flex h-9 sm:h-11 w-9 sm:w-11 items-center justify-center text-xs sm:text-sm font-medium transition {{ ! $logs->hasMorePages() ? 'cursor-not-allowed pointer-events-none bg-slate-100 text-slate-300' : 'text-slate-700 hover:bg-slate-100 hover:text-slate-900 active:bg-slate-200' }}"
                                href="{{ $logs->hasMorePages() ? $logs->nextPageUrl() : '#' }}"
                                aria-disabled="{{ ! $logs->hasMorePages() ? 'true' : 'false' }}"
                            >
                                <i class="fa-solid fa-chevron-right text-xs"></i>
                            </a>
                        </nav>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection