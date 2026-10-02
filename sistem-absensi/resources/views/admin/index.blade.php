@extends('layouts.admin.app')

@section('title', 'Dashboard Kehadiran Anggota')

@section('content')

<div class="space-y-8" x-data="{ editJamOpen: false }">

    {{-- ===================================================== --}}
    {{-- HEADER --}}
    {{-- ===================================================== --}}
    @php
        $canKelolaJadwal = Auth::user()?->roleAkses?->hasPrivilege('kelola_jadwal_kerja') ?? false;
    @endphp

    <section class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">

        <div>

            <h1 class="text-2xl sm:text-3xl lg:text-[34px] font-bold text-slate-900 leading-tight">
                Dashboard Kehadiran {{ \App\Helpers\OrganizationHelper::term('member', 'Anggota') }}
            </h1>

            <p class="mt-1.5 sm:mt-2 text-sm sm:text-[15px] text-slate-500">
                Monitoring kehadiran {{ strtolower(\App\Helpers\OrganizationHelper::term('member', 'anggota')) }} secara real-time.
            </p>

        </div>

        @hasfeature('schedule')
            @if($canKelolaJadwal)
                <button
                    @click="editJamOpen = true"
                    class="w-full sm:w-auto inline-flex items-center justify-center gap-2 rounded-xl bg-primary px-5 sm:px-6 py-3 text-sm font-semibold text-white transition duration-300 hover:bg-primary-hover flex-shrink-0 shadow-sm">

                    <svg xmlns="http://www.w3.org/2000/svg"
                        class="h-5 w-5"
                        fill="none"
                        viewBox="0 0 24 24"
                        stroke="currentColor">

                        <path stroke-linecap="round"
                            stroke-linejoin="round"
                            stroke-width="2"
                            d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z" />

                    </svg>

                    Edit Jam Masuk

                </button>
            @else
                <button
                    type="button"
                    disabled
                    title="Anda tidak memiliki hak akses untuk mengelola jadwal kerja"
                    class="w-full sm:w-auto inline-flex items-center justify-center gap-2 rounded-xl bg-slate-200 px-5 sm:px-6 py-3 text-sm font-semibold text-slate-400 opacity-60 cursor-not-allowed select-none flex-shrink-0 shadow-none">

                    <svg xmlns="http://www.w3.org/2000/svg"
                        class="h-5 w-5 text-slate-400"
                        fill="none"
                        viewBox="0 0 24 24"
                        stroke="currentColor">

                        <path stroke-linecap="round"
                            stroke-linejoin="round"
                            stroke-width="2"
                            d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z" />

                    </svg>

                    Edit Jam Masuk

                </button>
            @endif
        @endhasfeature

    </section>



    {{-- ===================================================== --}}
    {{-- FLASH NOTIFICATION --}}
    {{-- ===================================================== --}}
    @if(session('success'))
    <div
        x-data="{ show: true }"
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

    @if(session('error') || (isset($errors) && $errors->any()))
    <div
        x-data="{ show: true }"
        x-show="show"
        x-init="setTimeout(() => show = false, 6000)"
        x-transition:leave="transition ease-in duration-300"
        x-transition:leave-start="opacity-100"
        x-transition:leave-end="opacity-0"
        class="flex items-center gap-3 rounded-2xl border border-red-200 bg-red-50 px-5 py-4 text-sm text-red-800 shadow-sm">
        <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 flex-shrink-0 text-red-500" fill="none" viewBox="0 0 24 24" stroke="currentColor">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
        </svg>
        <div>
            @if(session('error'))
                <p>{{ session('error') }}</p>
            @endif
            @if(isset($errors) && $errors->any())
                @foreach($errors->all() as $err)
                    <p>{{ $err }}</p>
                @endforeach
            @endif
        </div>
    </div>
    @endif

    {{-- ===================================================== --}}
    {{-- SUMMARY CARD --}}
    {{-- ===================================================== --}}

    <section class="grid grid-cols-1 sm:grid-cols-2 {{ $hasWfoWfh ? 'xl:grid-cols-4' : 'xl:grid-cols-2' }} gap-4 sm:gap-6">

        {{-- Total Anggota --}}
        <div class="rounded-3xl bg-blue-50 p-5 sm:p-6 shadow-sm border border-blue-100/50">

            <div class="flex items-start justify-between">
                <p class="text-sm font-medium text-blue-600">
                    Total {{ \App\Helpers\OrganizationHelper::term('member', 'Anggota') }}
                </p>
                <div class="text-blue-500">
                    <i class="fa-solid fa-users fa-lg"></i>
                </div>
            </div>

            <div class="mt-4 text-center">
                <h2 class="text-3xl sm:text-[36px] lg:text-[40px] leading-none font-bold text-primary">
                    {{ $totalPegawai }}
                </h2>
                <p class="mt-2 text-xs font-medium text-blue-400">
                    Total {{ strtolower(\App\Helpers\OrganizationHelper::term('member', 'anggota')) }} aktif
                </p>
            </div>

        </div>

        {{-- Hadir Hari Ini --}}
        <div class="rounded-3xl bg-green-50 p-5 sm:p-6 shadow-sm border border-green-100/50">

            <div class="flex items-start justify-between">
                <p class="text-sm font-medium text-green-700">
                    Hadir Hari Ini
                </p>
                <div class="text-green-600">
                    <i class="fa-solid fa-circle-check fa-lg"></i>
                </div>
            </div>

            <div class="mt-4 text-center">
                <h2 class="text-3xl sm:text-[36px] lg:text-[40px] leading-none font-bold text-green-600">
                    {{ $hadirHariIni }}
                </h2>
                <p class="mt-2 text-xs font-medium text-green-500">
                    {{ \App\Helpers\OrganizationHelper::term('member', 'Anggota') }} sudah check in
                </p>
            </div>

        </div>

        @if($hasWfoWfh)
            {{-- WFO --}}
            <div class="rounded-3xl bg-white p-5 sm:p-6 shadow-card">

                <div class="flex items-start justify-between">
                    <p class="text-sm font-medium text-slate-700">
                        WFO
                    </p>
                    <div class="text-slate-400">
                        <i class="fa-solid fa-building fa-lg"></i>
                    </div>
                </div>

                <div class="mt-4 text-center">
                    <h2 class="text-3xl sm:text-[36px] lg:text-[40px] leading-none font-bold text-slate-800">
                        {{ $wfoCount }}
                    </h2>
                    <p class="mt-2 text-xs font-medium text-slate-400">
                        Work From Office
                    </p>
                </div>

            </div>

            {{-- WFH/WFC --}}
            <div class="rounded-3xl bg-orange-50 p-5 sm:p-6 shadow-sm border border-orange-100/50">

                <div class="flex items-start justify-between">
                    <p class="text-sm font-medium text-orange-600">
                        WFH / WFC
                    </p>
                    <div class="text-orange-500">
                        <i class="fa-solid fa-house-laptop fa-lg"></i>
                    </div>
                </div>

                <div class="mt-4 text-center">
                    <h2 class="text-3xl sm:text-[36px] lg:text-[40px] leading-none font-bold text-orange-600">
                        {{ $wfhWfcCount }}
                    </h2>
                    <p class="mt-2 text-xs font-medium text-orange-400">
                        Remote Working
                    </p>
                </div>

            </div>
        @endif

    </section>
        {{-- ===================================================== --}}
    {{-- STATISTIK & LIVE CHECK IN --}}
    {{-- ===================================================== --}}
    <section class="grid grid-cols-1 xl:grid-cols-3 gap-4 sm:gap-6">

        {{-- ============================= --}}
        {{-- STATISTIK --}}
        {{-- ============================= --}}
        <div class="xl:col-span-2 rounded-3xl bg-white p-5 sm:p-6 shadow-card">

            <div class="mb-6 flex flex-wrap items-center justify-between gap-3">

                <div>

                    <h2 class="text-lg sm:text-xl font-semibold text-slate-900">
                        Statistik Kehadiran
                    </h2>

                    <p id="chart-subtitle" class="mt-0.5 sm:mt-1 text-xs sm:text-sm text-slate-500">
                        7 hari terakhir
                    </p>

                </div>

                {{-- Filter Dropdown --}}
                <div class="relative" x-data="{ open: false }">

                    <button
                        @click="open = !open"
                        class="inline-flex items-center gap-2 rounded-xl bg-primary px-3.5 sm:px-4 py-2 text-xs sm:text-sm font-semibold text-white shadow-sm transition hover:bg-primary-hover">
                        <span id="filter-label">Pilih Filter</span>
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4 flex-shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/>
                        </svg>
                    </button>

                    <div
                        x-show="open"
                        @click.outside="open = false"
                        x-transition
                        class="absolute right-0 mt-2 w-36 sm:w-40 rounded-xl border border-slate-100 bg-white py-1 shadow-lg z-20">

                        <button onclick="loadChart('minggu')" @click="open=false"
                            class="w-full px-4 py-2 text-left text-xs sm:text-sm text-slate-700 hover:bg-slate-50">
                            Per Minggu
                        </button>
                        <button onclick="loadChart('bulan')" @click="open=false"
                            class="w-full px-4 py-2 text-left text-xs sm:text-sm text-slate-700 hover:bg-slate-50">
                            Per Bulan
                        </button>
                        <button onclick="loadChart('tahun')" @click="open=false"
                            class="w-full px-4 py-2 text-left text-xs sm:text-sm text-slate-700 hover:bg-slate-50">
                            Per Tahun
                        </button>

                    </div>

                </div>

            </div>

            {{-- Chart Canvas --}}
            <div class="relative h-[250px] sm:h-[300px] md:h-[320px] w-full">
                <canvas id="attendanceChart"></canvas>
            </div>

            {{-- Legend --}}
            <div class="mt-4 flex flex-wrap items-center gap-x-4 sm:gap-x-5 gap-y-2 justify-center">
                @php
                    $legendItems = $hasWfoWfh
                        ? [
                            ['WFO',      '#FB923C'],
                            ['WFH/WFC',  '#A78BFA'],
                            ['Izin',     '#34D399'],
                            ['Alfa',     '#F87171'],
                            ['Dinas',    '#60A5FA'],
                        ]
                        : [
                            ['Hadir',    '#34D399'],
                            ['Izin',     '#60A5FA'],
                            ['Alfa',     '#F87171'],
                        ];
                @endphp
                @foreach($legendItems as [$label, $color])
                    <span class="flex items-center gap-1.5 text-xs text-slate-600">
                        <span class="inline-block h-3 w-3 rounded-sm" style="background:{{ $color }}"></span>
                        {{ $label }}
                    </span>
                @endforeach
            </div>

        </div>




        {{-- ============================= --}}
        {{-- LIVE CHECK IN --}}
        {{-- ============================= --}}
        <div class="rounded-3xl bg-white p-5 sm:p-6 shadow-card flex flex-col h-[380px] sm:h-[400px]">

            <div class="mb-4 sm:mb-5 text-center">
                <h2 class="text-base sm:text-lg font-bold text-slate-900">
                    Live Check In
                </h2>
                <p class="mt-0.5 sm:mt-1 text-[11px] text-slate-500">
                    Aktivitas absensi hari ini
                </p>
                <hr class="mt-3 border-slate-200">
            </div>

            <div class="space-y-4 overflow-y-auto flex-1 pr-2 custom-scrollbar">

                @foreach($liveCheckIns as $item)

                    <div class="flex items-center justify-between gap-3">
                        <div class="flex items-center gap-3 min-w-0 flex-1">
                            <div class="h-10 w-10 overflow-hidden rounded-full bg-slate-100 flex-shrink-0">
                                @if($item['foto'])
                                    <img src="{{ $item['foto'] }}" alt="{{ $item['nama'] }}" class="h-full w-full object-cover">
                                @else
                                    <div class="flex h-full w-full items-center justify-center bg-[#123D91] text-xs font-bold text-white">
                                        {{ getInitials($item['nama']) }}
                                    </div>
                                @endif
                            </div>
                            <div class="min-w-0 flex-1">
                                <h3 class="text-sm font-semibold text-slate-900 truncate">
                                    {{ $item['nama'] }}
                                </h3>
                                <p class="text-[11px] font-medium text-slate-500 truncate">
                                    Check in - <span class="text-green-500">{{ $item['status'] }}</span>
                                </p>
                            </div>
                        </div>
                        <div class="text-right flex-shrink-0">
                            <p class="text-xs font-semibold text-slate-400">
                                {{ $item['jam'] }} WIB
                            </p>
                        </div>
                    </div>

                @endforeach

            </div>

            <div class="mt-4 sm:mt-5 pt-3 sm:pt-4 space-y-2">
                <a href="{{ route('admin.log-aktivitas') }}" class="block w-full text-center rounded-full border border-slate-200 py-2 text-[13px] font-semibold text-slate-700 transition hover:bg-slate-50">
                    Lihat Semua Aktivitas
                </a>
                
                @php
                    $activeOrgToken = \App\Models\Organization::find(\App\Helpers\OrganizationHelper::requireActiveOrganization())?->display_token;
                @endphp
                @if($activeOrgToken)
                <a href="{{ route('tv.dashboard', ['display_token' => $activeOrgToken]) }}" target="_blank" class="flex items-center justify-center gap-2 w-full text-center rounded-full bg-primary/10 border border-primary/20 py-2 text-[13px] font-semibold text-primary transition hover:bg-primary/20">
                    <i class="fa-solid fa-tv"></i>
                    Buka Monitoring TV
                </a>
                @endif
            </div>

        </div>

    </section>
        {{-- ===================================================== --}}
    {{-- APPROVAL --}}
    {{-- ===================================================== --}}
    @hasfeature('approval')
    <section class="grid gap-6">

        {{-- ======================================== --}}
        {{-- MENUNGGU PERSETUJUAN --}}
        {{-- ======================================== --}}
        <div class="rounded-3xl bg-white p-5 sm:p-6 shadow-card">

            <div class="mb-6 flex flex-wrap items-center justify-between gap-3">
                <h2 class="text-lg sm:text-xl font-semibold text-slate-900">
                    Menunggu Persetujuan
                </h2>
                <div class="flex items-center gap-2 rounded-full bg-primary px-3 py-1.5 text-xs font-semibold text-white flex-shrink-0">
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z" />
                    </svg>
                    {{ $pendingApprovalsCount }} Menunggu
                </div>
            </div>

            <div class="space-y-0">
                <div class="flex items-center justify-between border-b border-slate-100 py-3">
                    <div class="flex items-center gap-3 text-sm font-medium text-slate-700">
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 text-slate-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 0 0 2-2V7a2 2 0 0 0-2-2H5a2 2 0 0 0-2 2v12a2 2 0 0 0 2 2z" />
                        </svg>
                        Cuti
                    </div>
                    <span class="text-sm font-bold text-slate-900">{{ $approvalBreakdown['Cuti'] ?? 0 }}</span>
                </div>

                <div class="flex items-center justify-between border-b border-slate-100 py-3">
                    <div class="flex items-center gap-3 text-sm font-medium text-slate-700">
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 text-slate-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2" />
                        </svg>
                        Izin
                    </div>
                    <span class="text-sm font-bold text-slate-900">{{ $approvalBreakdown['Izin'] ?? 0 }}</span>
                </div>

                <div class="flex items-center justify-between border-b border-slate-100 py-3">
                    <div class="flex items-center gap-3 text-sm font-medium text-slate-700">
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 text-slate-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4" />
                        </svg>
                        Sakit
                    </div>
                    <span class="text-sm font-bold text-slate-900">{{ $approvalBreakdown['Sakit'] ?? 0 }}</span>
                </div>

                @if($hasWfoWfh)
                <div class="flex items-center justify-between border-b border-slate-100 py-3">
                    <div class="flex items-center gap-3 text-sm font-medium text-slate-700">
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 text-slate-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6" />
                        </svg>
                        WFH
                    </div>
                    <span class="text-sm font-bold text-slate-900">{{ $approvalBreakdown['WFH'] ?? 0 }}</span>
                </div>
                @endif
            </div>

            <div class="mt-6 flex justify-end">
                <a href="{{ route('admin.persetujuan') }}" class="inline-flex items-center gap-2 text-sm font-medium text-slate-600 hover:text-primary transition">
                    Lihat Semua
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 7l5 5m0 0l-5 5m5-5H6" />
                    </svg>
                </a>
            </div>

        </div>
    </section>
    @endhasfeature


    {{-- ===================================================== --}}
    {{-- MODAL: EDIT JAM MASUK --}}
    {{-- ===================================================== --}}
    @hasfeature('schedule')
    <div
        x-show="editJamOpen"
        x-cloak
        class="fixed inset-0 z-50 flex items-center justify-center p-4"
        aria-modal="true"
        role="dialog">

        {{-- Backdrop --}}
        <div
            class="absolute inset-0 bg-slate-900/40 backdrop-blur-sm"
            @click="editJamOpen = false">
        </div>

        {{-- Dialog --}}
        <div
            x-show="editJamOpen"
            x-transition:enter="transition ease-out duration-200"
            x-transition:enter-start="opacity-0 scale-95"
            x-transition:enter-end="opacity-100 scale-100"
            x-transition:leave="transition ease-in duration-150"
            x-transition:leave-start="opacity-100 scale-100"
            x-transition:leave-end="opacity-0 scale-95"
            class="relative w-full max-w-md rounded-3xl bg-white p-6 sm:p-8 shadow-2xl">

            {{-- Title --}}
            <h2 class="text-xl sm:text-2xl font-bold text-slate-900">
                Edit Jam Masuk
            </h2>

            <p class="mt-1.5 sm:mt-2 text-xs sm:text-sm text-slate-500">
                Atur jam kerja standar untuk perhitungan keterlambatan.
            </p>

            {{-- Form --}}
            <form method="POST" action="{{ route('admin.jam-kerja.simpan') }}" class="mt-6 space-y-4">
                @csrf

                {{-- Jam Masuk --}}
                <div>
                    <label class="mb-1 block text-sm text-slate-600" for="jam_masuk">
                        Jam Masuk
                    </label>
                    <div class="relative">
                        <input
                            id="jam_masuk"
                            name="jam_masuk"
                            type="time"
                            value="{{ old('jam_masuk', $jamMasuk ?? '08:00') }}"
                            required
                            class="w-full rounded-2xl border border-slate-200 bg-white px-4 py-3 pr-12 text-sm text-slate-800 outline-none ring-0 transition focus:border-primary focus:ring-2 focus:ring-primary/20"
                            placeholder="00:00">
                        <span class="pointer-events-none absolute right-4 top-1/2 -translate-y-1/2 text-slate-400">
                            <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <circle cx="12" cy="12" r="10"/>
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6v6l4 2"/>
                            </svg>
                        </span>
                    </div>
                </div>

                {{-- Jam Pulang --}}
                <div>
                    <label class="mb-1 block text-sm text-slate-600" for="jam_pulang">
                        Jam Pulang
                    </label>
                    <div class="relative">
                        <input
                            id="jam_pulang"
                            name="jam_pulang"
                            type="time"
                            value="{{ old('jam_pulang', $jamPulang ?? '17:00') }}"
                            required
                            class="w-full rounded-2xl border border-slate-200 bg-white px-4 py-3 pr-12 text-sm text-slate-800 outline-none ring-0 transition focus:border-primary focus:ring-2 focus:ring-primary/20"
                            placeholder="00:00">
                        <span class="pointer-events-none absolute right-4 top-1/2 -translate-y-1/2 text-slate-400">
                            <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <circle cx="12" cy="12" r="10"/>
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6v6l4 2"/>
                            </svg>
                        </span>
                    </div>
                </div>

                {{-- Actions --}}
                <div class="mt-6 flex items-center justify-end gap-3">

                    <button
                        type="button"
                        @click="editJamOpen = false"
                        class="rounded-2xl border border-slate-200 px-6 py-3 text-sm font-semibold text-slate-700 transition hover:bg-slate-50">
                        Batal
                    </button>

                    <button
                        type="submit"
                        class="rounded-2xl bg-primary px-6 py-3 text-sm font-semibold text-white transition hover:bg-primary-hover">
                        Simpan
                    </button>

                </div>

            </form>

        </div>

    </div>
    @endhasfeature

</div>

@endsection

@push('scripts')
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.3/dist/chart.umd.min.js"></script>
<script>
(function () {
    const COLORS = {
        'WFO':     { bg: 'rgba(251,146, 60,0.85)', border: '#FB923C' },
        'WFH/WFC': { bg: 'rgba(167,139,250,0.85)', border: '#A78BFA' },
        'Izin':    { bg: 'rgba( 52,211,153,0.85)', border: '#34D399' },
        'Alfa':    { bg: 'rgba(248,113,113,0.85)', border: '#F87171' },
        'Dinas':   { bg: 'rgba( 96,165,250,0.85)', border: '#60A5FA' },
    };

    const FILTER_LABELS = {
        minggu: 'Per Minggu',
        bulan:  'Per Bulan',
        tahun:  'Per Tahun',
    };

    let chart = null;

    const ctx = document.getElementById('attendanceChart').getContext('2d');

    window.loadChart = function (filter) {
        document.getElementById('filter-label').textContent = FILTER_LABELS[filter] || 'Pilih Filter';

        fetch(`{{ route('admin.chart-statistik') }}?filter=${filter}`, {
            headers: { 'X-Requested-With': 'XMLHttpRequest' }
        })
        .then(r => r.json())
        .then(json => {
            document.getElementById('chart-subtitle').textContent = json.subtitle ?? '';

            const datasets = json.datasets.map(ds => ({
                label:           ds.label,
                data:            ds.data,
                backgroundColor: COLORS[ds.label]?.bg     ?? 'rgba(100,116,139,0.7)',
                borderColor:     COLORS[ds.label]?.border ?? '#64748b',
                borderWidth:     1.5,
                borderRadius:    4,
            }));

            if (chart) {
                chart.data.labels   = json.labels;
                chart.data.datasets = datasets;
                chart.update('active');
            } else {
                chart = new Chart(ctx, {
                    type: 'bar',
                    data: { labels: json.labels, datasets },
                    options: {
                        responsive:          true,
                        maintainAspectRatio: false,
                        plugins: {
                            legend: { display: false },
                            tooltip: {
                                callbacks: {
                                    label: ctx => ` ${ctx.dataset.label}: ${ctx.parsed.y}`
                                }
                            },
                        },
                        scales: {
                            x: {
                                grid: { display: false },
                                ticks: { color: '#64748b', font: { size: 12 } },
                            },
                            y: {
                                beginAtZero: true,
                                grid: { color: '#f1f5f9' },
                                ticks: {
                                    color: '#94a3b8',
                                    font: { size: 11 },
                                    stepSize: 1,
                                    precision: 0,
                                },
                            },
                        },
                    },
                });
            }
        })
        .catch(err => console.error('Chart load error:', err));
    };

    // Load default on page ready
    loadChart('minggu');
})();
</script>
@endpush
