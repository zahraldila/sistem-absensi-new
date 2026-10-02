@php
    $pageTitle = 'Live TV Dashboard - ' . ($organizationName ?? 'Organisasi');
@endphp
<!DOCTYPE html>
<html lang="id" class="h-full bg-slate-50">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{{ $pageTitle }}</title>

    <!-- Google Fonts: Plus Jakarta Sans -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800;900&display=swap" rel="stylesheet">

    <!-- FontAwesome Icons -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">

    <!-- Styles & Scripts (Tailwind & Alpine via Vite) -->
    @vite(['resources/css/app.css', 'resources/js/app.js'])

    <style>
        body {
            font-family: 'Plus Jakarta Sans', sans-serif;
            background-color: #F8FAFC;
        }

        /* Custom subtle scrollbar */
        .custom-scrollbar::-webkit-scrollbar {
            width: 4px;
        }
        .custom-scrollbar::-webkit-scrollbar-track {
            background: transparent;
        }
        .custom-scrollbar::-webkit-scrollbar-thumb {
            background: #E2E8F0;
            border-radius: 9999px;
        }
        .custom-scrollbar::-webkit-scrollbar-thumb:hover {
            background: #CBD5E1;
        }

        [x-cloak] { display: none !important; }
    </style>
</head>
<body class="h-full antialiased text-slate-800 flex flex-col justify-between overflow-x-hidden selection:bg-primary selection:text-white"
    x-data="tvDashboard('{{ $selectedDate }}')"
    x-cloak
>

    <!-- Background Decoration Grid -->
    <div class="fixed inset-0 pointer-events-none opacity-40 z-0 bg-[radial-gradient(#CBD5E1_1px,transparent_1px)] [background-size:24px_24px]"></div>

    <!-- Header Section (Company Branding + Clock) -->
    <header class="relative z-10 w-full px-4 sm:px-6 lg:px-10 py-3.5 bg-white/95 backdrop-blur-md border-b border-slate-200/80 shadow-xs flex-shrink-0">
        <div class="max-w-[1920px] mx-auto flex items-center justify-between gap-4">
            
            <!-- Left: Logo & Company Name -->
            <div class="flex items-center gap-3">
                <div class="w-10 h-10 sm:w-11 sm:h-11 rounded-xl sm:rounded-2xl bg-white border border-slate-200 shadow-xs flex items-center justify-center p-1.5 overflow-hidden flex-shrink-0">
                    @if($logoUrl)
                        <img src="{{ $logoUrl }}" alt="Logo {{ $organizationName }}" class="w-full h-full object-contain" onerror="this.style.display='none'; this.nextElementSibling.classList.remove('hidden');">
                    @endif
                    <span class="w-full h-full rounded-lg bg-primary text-white flex items-center justify-center text-xs font-bold {{ $logoUrl ? 'hidden' : '' }}">
                        @if($organizationInitials)
                            {{ $organizationInitials }}
                        @else
                            <i class="fa-solid fa-building" aria-hidden="true"></i>
                        @endif
                    </span>
                </div>
                <div>
                    <h1 class="text-base sm:text-lg font-black text-slate-900 tracking-tight leading-tight">
                        {{ $organizationName }}
                    </h1>
                    <p class="text-[10px] sm:text-xs font-bold text-slate-400 uppercase tracking-widest mt-0.5">
                        Live Attendance Dashboard
                    </p>
                </div>
            </div>

            <!-- Right: Clock (Desktop & Mobile) -->
            <div class="text-right">
                <div class="text-xl sm:text-2xl lg:text-3xl font-black text-slate-900 tracking-tight leading-none" x-text="clockTime">
                    00:00:00
                </div>
                <div class="text-[11px] sm:text-xs font-semibold text-slate-400 mt-1" x-text="clockDate">
                    ...
                </div>
            </div>

        </div>
    </header>

    <!-- Main Content Area: Dynamic Multi-Branch Responsive Grid with Avatar Card Grid -->
    <main class="relative z-10 flex-1 max-w-[1920px] w-full mx-auto p-4 sm:p-6 lg:p-8 flex flex-col justify-between">
        
        @if(empty($hasAttendance))
        <div class="flex-1 flex items-center justify-center my-auto">
            <div class="bg-white rounded-3xl p-8 sm:p-12 border border-slate-200 text-center max-w-lg shadow-sm">
                <div class="w-16 h-16 bg-slate-100 rounded-2xl flex items-center justify-center mx-auto mb-4 text-slate-400 text-2xl">
                    <i class="fa-solid fa-calendar-xmark"></i>
                </div>
                <h2 class="text-xl font-bold text-slate-800">Presensi Kehadiran Nonaktif</h2>
                <p class="text-sm text-slate-500 mt-2">Fitur presensi kehadiran tidak aktif pada organisasi ini.</p>
            </div>
        </div>
        @else
        <div 
            :class="gridColsClass"
            class="grid gap-5 lg:gap-8 items-stretch h-[calc(100vh-8.5rem)] transition-all duration-300"
        >
            
            <!-- Dynamic Branch Columns from Database -->
            <template x-for="branch in branchCards" :key="branch.lokasi_id">
                <section class="bg-white rounded-3xl p-5 sm:p-6 border border-slate-200/80 shadow-sm flex flex-col h-full overflow-hidden transition-all">
                    
                    <!-- Header Kolom Cabang -->
                    <div class="flex items-center justify-between pb-3.5 border-b border-slate-100 flex-shrink-0">
                        <div class="flex items-center gap-3 min-w-0">
                            <div :class="branch.is_hq ? 'bg-blue-50 text-primary border-blue-100' : 'bg-emerald-50 text-emerald-600 border-emerald-100'"
                                class="w-10 h-10 sm:w-11 sm:h-11 rounded-2xl flex items-center justify-center text-lg sm:text-xl shadow-xs border flex-shrink-0">
                                <i :class="branch.is_hq ? 'fa-solid fa-building-circle-check' : 'fa-solid fa-building'"></i>
                            </div>
                            <div class="min-w-0">
                                <div class="flex items-center gap-2">
                                    <h2 class="text-base sm:text-lg lg:text-xl font-black text-slate-900 tracking-tight truncate" x-text="branch.nama_kantor">
                                    </h2>
                                    <span :class="branch.is_hq ? 'bg-primary/10 text-primary border-primary/20' : 'bg-emerald-50 text-emerald-700 border-emerald-200'"
                                        class="text-[9px] sm:text-[10px] font-extrabold px-2 sm:px-2.5 py-0.5 rounded-full uppercase tracking-wider border flex-shrink-0"
                                        x-text="branch.is_hq ? 'Kantor Pusat' : 'Kantor Cabang'">
                                    </span>
                                </div>
                            </div>
                        </div>

                        <!-- Badge Total Hadir Cabang -->
                        <div class="text-right flex-shrink-0 pl-2">
                            <div class="text-2xl sm:text-3xl font-black text-[#12B76A] leading-none" x-text="branch.total_hadir"></div>
                            <div class="text-[9px] sm:text-[10px] font-bold text-slate-400 uppercase tracking-wider mt-0.5">{{ $memberTerm ?? 'Anggota' }} Hadir</div>
                        </div>
                    </div>

                    <!-- Mini Stats Bar Cabang -->
                    <div class="grid {{ !empty($hasWfoWfh) ? 'grid-cols-3' : 'grid-cols-2' }} gap-2 my-3 flex-shrink-0">
                        <div class="bg-emerald-50/70 p-2 sm:p-2.5 rounded-2xl border border-emerald-100 flex items-center justify-between">
                            <div class="flex items-center gap-1.5 text-[10px] sm:text-[11px] font-bold text-emerald-800">
                                <span class="w-2 h-2 rounded-full bg-emerald-500 animate-pulse"></span>
                                <span>Kerja</span>
                            </div>
                            <span class="text-sm sm:text-base font-black text-emerald-700" x-text="branch.working_count"></span>
                        </div>

                        <div class="bg-slate-50 p-2 sm:p-2.5 rounded-2xl border border-slate-100 flex items-center justify-between">
                            <div class="flex items-center gap-1.5 text-[10px] sm:text-[11px] font-bold text-slate-600">
                                <span class="w-2 h-2 rounded-full bg-slate-400"></span>
                                <span>Pulang</span>
                            </div>
                            <span class="text-sm sm:text-base font-black text-slate-700" x-text="branch.checkout_count"></span>
                        </div>

                        @if(!empty($hasWfoWfh))
                        <div class="bg-blue-50/70 p-2 sm:p-2.5 rounded-2xl border border-blue-100 flex items-center justify-between">
                            <div class="flex items-center gap-1.5 text-[10px] sm:text-[11px] font-bold text-blue-800">
                                <i class="fa-solid fa-laptop-house text-xs text-blue-600"></i>
                                <span>WFO</span>
                            </div>
                            <span class="text-sm sm:text-base font-black text-blue-700" x-text="branch.total_hadir"></span>
                        </div>
                        @endif
                    </div>

                    <!-- List Pegawai Hadir di Cabang (Avatar Card Grid Layout) -->
                    <div :id="'branch-list-' + branch.lokasi_id" class="flex-1 overflow-y-auto pr-1 custom-scrollbar">
                        
                        <!-- Grid Kartu Karyawan (Fokus Foto + Nama) -->
                        <div class="grid grid-cols-2 sm:grid-cols-3 md:grid-cols-3 xl:grid-cols-4 2xl:grid-cols-5 gap-2.5 sm:gap-3 p-1">
                            <template x-for="item in branch.attendances" :key="item.id">
                                <div 
                                    :class="item.has_checkout 
                                        ? 'bg-slate-50/90 border-slate-200/60 opacity-80' 
                                        : 'bg-white border-slate-200/90 shadow-xs hover:shadow-sm hover:border-emerald-300'"
                                    class="flex flex-col items-center justify-center p-2.5 sm:p-3 rounded-2xl border transition-all duration-200 text-center relative group"
                                >
                                    <!-- Avatar with Ring & Dot Status -->
                                    <div class="relative mb-2 flex-shrink-0">
                                        <template x-if="item.foto_profile">
                                            <img 
                                                :src="item.foto_profile" 
                                                :alt="item.nama" 
                                                x-on:error="item.foto_profile = null"
                                                :class="item.has_checkout 
                                                    ? 'ring-2 ring-slate-300 grayscale-[25%]' 
                                                    : 'ring-2 ring-emerald-500 ring-offset-1'"
                                                class="w-12 h-12 sm:w-14 sm:h-14 rounded-full object-cover p-0.5 bg-white shadow-xs transition"
                                            >
                                        </template>
                                        <template x-if="!item.foto_profile">
                                            <div 
                                                :class="item.has_checkout 
                                                    ? 'bg-slate-400 ring-2 ring-slate-300' 
                                                    : 'bg-primary ring-2 ring-emerald-500 ring-offset-1'"
                                                class="w-12 h-12 sm:w-14 sm:h-14 rounded-full text-white flex items-center justify-center font-black text-sm sm:text-base shadow-xs transition" 
                                                x-text="item.nama.substring(0, 1).toUpperCase()">
                                            </div>
                                        </template>

                                        <!-- Dot status badge -->
                                        <span class="absolute bottom-0 right-0 flex h-3.5 w-3.5">
                                            <template x-if="!item.has_checkout">
                                                <span class="relative inline-flex rounded-full h-3.5 w-3.5 bg-emerald-500 border-2 border-white"></span>
                                            </template>
                                            <template x-if="item.has_checkout">
                                                <span class="relative inline-flex rounded-full h-3.5 w-3.5 bg-slate-400 border-2 border-white"></span>
                                            </template>
                                        </span>
                                    </div>

                                    <!-- Nama Pegawai (Fokus Utama) -->
                                    <h4 
                                        :class="item.has_checkout ? 'text-slate-600 font-semibold' : 'text-slate-900 font-bold'" 
                                        class="text-xs sm:text-sm leading-tight line-clamp-1 w-full px-1 tracking-tight" 
                                        x-text="item.nama"
                                        :title="item.nama"
                                    ></h4>

                                    <!-- Mini Badge Waktu Check In / Out -->
                                    <div class="mt-1 flex items-center justify-center">
                                        <span 
                                            :class="item.has_checkout 
                                                ? 'text-slate-500 bg-slate-100' 
                                                : (item.status_kehadiran === 'Terlambat' ? 'text-[#B42318] bg-[#FFF4F2]' : 'text-[#027A48] bg-[#ECFDF3]')"
                                            class="text-[10px] font-bold px-1.5 py-0.5 rounded-md leading-none" 
                                            x-text="item.waktu + ' WIB'"
                                        ></span>
                                    </div>
                                </div>
                            </template>
                        </div>

                        <!-- Empty State -->
                        <div x-show="branch.attendances.length === 0" class="flex flex-col items-center justify-center h-48 text-center px-4 py-8">
                            <div class="w-11 h-11 bg-slate-50 border border-slate-100 rounded-full flex items-center justify-center mb-2.5 text-slate-300">
                                <i class="fa-regular fa-building text-lg"></i>
                            </div>
                            <h5 class="text-xs sm:text-sm font-bold text-slate-600">Belum ada {{ strtolower($memberTerm ?? 'anggota') }} hadir</h5>
                            <p class="text-[11px] text-slate-400 mt-0.5" x-text="'Belum ada aktivitas presensi di ' + branch.nama_kantor + ' hari ini.'"></p>
                        </div>

                    </div>

                </section>
            </template>

        </div>
        @endif

    </main>

    {{-- ===================================================== --}}
    {{-- POPUP MODAL (CHECK IN / CHECK OUT LIVE ALERT) --}}
    {{-- ===================================================== --}}
    <div 
        x-show="showModal" 
        x-transition.opacity.duration.300ms
        class="fixed inset-0 z-50 flex items-center justify-center bg-black/60 backdrop-blur-sm p-3 sm:p-4"
        style="display: none;"
    >
        <div 
            x-show="showModal" 
            x-transition:enter="transition ease-out duration-300 transform"
            x-transition:enter-start="opacity-0 scale-95 translate-y-4"
            x-transition:enter-end="opacity-100 scale-100 translate-y-0"
            x-transition:leave="transition ease-in duration-200 transform"
            x-transition:leave-start="opacity-100 scale-100 translate-y-0"
            x-transition:leave-end="opacity-0 scale-95 translate-y-4"
            @click.away="closeModalAndProceed()"
            class="bg-white rounded-3xl sm:rounded-[2.5rem] shadow-[0_25px_60px_-12px_rgba(0,0,0,0.3)] max-w-3xl lg:max-w-4xl w-full mx-3 sm:mx-4 p-6 sm:p-8 md:p-10 flex flex-col md:flex-row gap-6 sm:gap-8 lg:gap-10 relative overflow-y-auto max-h-[92vh] border border-slate-100"
        >
            <!-- Close Button -->
            <button @click="closeModalAndProceed()" class="absolute top-4 sm:top-6 right-4 sm:right-6 text-slate-400 hover:text-slate-600 transition-colors p-2 rounded-full hover:bg-slate-50" aria-label="Tutup alert">
                <i class="fa-solid fa-xmark text-lg"></i>
            </button>

            <!-- Modal Left Side -->
            <div class="flex-1 flex flex-col items-center justify-center border-b md:border-b-0 md:border-r border-slate-100 pb-5 md:pb-0 md:pr-6 lg:pr-8">
                
                <!-- Circular Icon -->
                <div :class="modalData.tipe === 'checkout' ? 'bg-slate-100 text-slate-700' : 'bg-[#EBFDF2] text-[#12B76A]'"
                    class="w-24 h-24 sm:w-28 sm:h-28 lg:w-32 lg:h-32 rounded-full flex items-center justify-center relative shadow-xs">
                    <template x-if="modalData.tipe !== 'checkout'">
                        <i class="fa-solid fa-check text-4xl sm:text-5xl"></i>
                    </template>
                    <template x-if="modalData.tipe === 'checkout'">
                        <i class="fa-solid fa-door-open text-4xl sm:text-5xl text-slate-600"></i>
                    </template>
                </div>
                
                <h3 class="text-xl sm:text-2xl font-black text-slate-900 text-center mt-5 sm:mt-6" x-text="modalData.tipe === 'checkout' ? 'Check Out Berhasil' : 'Check In Berhasil'">
                </h3>
                <p class="text-slate-500 text-xs sm:text-sm text-center mt-1 font-medium leading-relaxed">
                    <span x-text="modalData.tipe === 'checkout' ? 'Terima kasih atas kerja kerasnya hari ini, ' : 'Selamat datang kembali, '"></span>
                    <span class="text-slate-800 font-extrabold" x-text="modalData.nama"></span>!
                </p>

                <!-- Status Badge with Emoticon -->
                <div class="mt-4 sm:mt-5 flex items-center gap-2 flex-wrap justify-center">
                    <template x-if="modalData.tipe !== 'checkout'">
                        <div>
                            <template x-if="modalData.status_kehadiran === 'Terlambat'">
                                <span class="inline-flex items-center gap-1.5 bg-[#FFF4F2] text-[#B42318] border border-[#FECDCA] font-bold px-4 py-1.5 rounded-full text-xs shadow-xs">
                                    <span>Terlambat</span>
                                    <i class="fa-regular fa-face-frown text-sm"></i>
                                </span>
                            </template>
                            <template x-if="modalData.status_kehadiran !== 'Terlambat'">
                                <span class="inline-flex items-center gap-1.5 bg-[#ECFDF3] text-[#027A48] border border-[#A6F4C5] font-bold px-4 py-1.5 rounded-full text-xs shadow-xs">
                                    <span>Tepat Waktu</span>
                                    <i class="fa-regular fa-face-smile text-sm"></i>
                                </span>
                            </template>
                        </div>
                    </template>
                    <template x-if="modalData.tipe === 'checkout'">
                        <span class="inline-flex items-center gap-1.5 bg-slate-100 text-slate-700 border border-slate-200 font-bold px-4 py-1.5 rounded-full text-xs shadow-xs">
                            <i class="fa-solid fa-circle-check text-slate-500"></i>
                            <span>Sudah Pulang</span>
                        </span>
                    </template>
                </div>
            </div>

            <!-- Modal Right Side -->
            <div class="flex-[1.2] flex flex-col justify-center">
                <div class="flex items-center gap-3.5 sm:gap-4">
                    <template x-if="modalData.foto_profile">
                        <img 
                            :src="modalData.foto_profile" 
                            :alt="modalData.nama" 
                            x-on:error="modalData.foto_profile = null"
                            class="w-14 h-14 sm:w-16 sm:h-16 lg:w-18 lg:h-18 rounded-full object-cover border-2 border-slate-200 shadow-sm flex-shrink-0"
                        >
                    </template>
                    <template x-if="!modalData.foto_profile">
                        <div class="w-14 h-14 sm:w-16 sm:h-16 lg:w-18 lg:h-18 rounded-full bg-primary text-white flex items-center justify-center font-black text-xl sm:text-2xl shadow-sm border-2 border-slate-100 flex-shrink-0" x-text="modalData.nama ? modalData.nama.substring(0, 1).toUpperCase() : ''">
                        </div>
                    </template>
                    
                    <div class="min-w-0">
                        <h4 class="text-lg sm:text-xl lg:text-2xl font-black text-slate-900 tracking-tight truncate" x-text="modalData.nama"></h4>
                        <div class="flex items-center gap-2 mt-1">
                            <span :class="modalData.tipe === 'checkout' ? 'bg-slate-100 text-slate-700 border-slate-200' : 'bg-[#ECFDF3] text-[#027A48] border-[#A6F4C5]'"
                                class="text-[10px] sm:text-[11px] font-bold px-2 py-0.5 rounded-md border uppercase tracking-wider" x-text="modalData.tipe === 'checkout' ? 'Check Out' : 'Check In'">
                            </span>
                            @if(!empty($hasWfoWfh))
                            <span class="text-xs font-medium text-slate-400 truncate" x-text="modalData.skema_label"></span>
                            @endif
                        </div>
                    </div>
                </div>

                <!-- Table details -->
                <div class="mt-4 sm:mt-5 bg-slate-50/70 border border-slate-200/70 rounded-2xl overflow-hidden divide-y divide-slate-100 text-xs sm:text-sm">
                    
                    <div class="flex items-center justify-between p-3 sm:p-3.5">
                        <div class="text-slate-500 font-medium flex items-center gap-2">
                            <i class="fa-regular fa-clock text-slate-400"></i>
                            <span x-text="modalData.tipe === 'checkout' ? 'Waktu Check Out' : 'Waktu Check In'"></span>
                        </div>
                        <div class="text-slate-900 font-bold" x-text="modalData.waktu ? modalData.waktu + ' WIB' : '-'"></div>
                    </div>

                    <template x-if="modalData.tipe === 'checkout'">
                        <div class="flex items-center justify-between p-3 sm:p-3.5">
                            <div class="text-slate-500 font-medium flex items-center gap-2">
                                <i class="fa-solid fa-business-time text-slate-400"></i>
                                Total Durasi Kerja
                            </div>
                            <div class="text-slate-700 font-bold" x-text="modalData.durasi || '-'"></div>
                        </div>
                    </template>

                    <div class="flex items-center justify-between p-3 sm:p-3.5">
                        <div class="text-slate-500 font-medium flex items-center gap-2">
                            <i class="fa-solid fa-location-dot text-slate-400"></i>
                            Lokasi
                        </div>
                        <div class="text-slate-900 font-bold" x-text="modalData.lokasi || '-'"></div>
                    </div>

                    @if(!empty($hasDivision))
                    <div class="flex items-center justify-between p-3 sm:p-3.5">
                        <div class="text-slate-500 font-medium flex items-center gap-2">
                            <i class="fa-solid fa-building-user text-slate-400"></i>
                            {{ $divisionTerm ?? 'Divisi' }}
                        </div>
                        <div class="text-slate-900 font-bold" x-text="modalData.divisi || '-'"></div>
                    </div>
                    @endif

                    @if(!empty($hasPosition))
                    <div class="flex items-center justify-between p-3 sm:p-3.5">
                        <div class="text-slate-500 font-medium flex items-center gap-2">
                            <i class="fa-solid fa-user-tag text-slate-400"></i>
                            {{ $positionTerm ?? 'Jabatan' }}
                        </div>
                        <div class="text-slate-900 font-bold" x-text="modalData.jabatan || '-'"></div>
                    </div>
                    @endif

                    @if(!empty($hasSchedule))
                    <div class="flex items-center justify-between p-3 sm:p-3.5">
                        <div class="text-slate-500 font-medium flex items-center gap-2">
                            <i class="fa-regular fa-calendar-check text-slate-400"></i>
                            Jam Kerja
                        </div>
                        <div class="text-slate-900 font-bold" x-text="modalData.jam_kerja || '-'"></div>
                    </div>
                    @endif

                </div>
            </div>
        </div>
    </div>

    {{-- ===================================================== --}}
    {{-- SCRIPTS (ALPINE.JS LOGIC & REAL-TIME POLLING) --}}
    {{-- ===================================================== --}}
    <script>
        document.addEventListener('alpine:init', () => {
            Alpine.data('tvDashboard', (selectedDate) => ({
                date: selectedDate,
                branches: @json($branches) || [],
                branchCards: @json($branchCards) || [],
                totalPegawai: {{ $totalPegawai }},
                totalHadir: {{ $totalHadir }},
                sedangBekerja: {{ $sedangBekerja }},
                sudahCheckOut: {{ $sudahCheckOut }},
                wfoCount: {{ $wfoCount }},
                wfhCount: {{ $wfhCount }},
                sakitCount: {{ $sakitCount }},
                belumHadir: {{ $belumHadir }},

                clockTime: '00:00:00',
                clockDate: '...',

                // Modal popup queue states
                showModal: false,
                modalData: {},
                modalQueue: [],
                isProcessingQueue: false,
                knownActivityKeys: new Set(),
                modalTimer: null,

                get gridColsClass() {
                    const count = this.branchCards.length;
                    if (count <= 1) return 'grid-cols-1';
                    if (count === 2) return 'grid-cols-1 lg:grid-cols-2';
                    if (count === 3) return 'grid-cols-1 md:grid-cols-2 lg:grid-cols-3';
                    return 'grid-cols-1 md:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4';
                },

                init() {
                    this.updateClock();
                    setInterval(() => this.updateClock(), 1000);

                    // Initialize known activity keys on initial load
                    this.branchCards.forEach(b => {
                        (b.attendances || []).forEach(item => {
                            this.knownActivityKeys.add(this.getActivityKey(item));
                        });
                    });

                    // Auto-poll stats every 5 seconds
                    setInterval(() => this.fetchStats(), 5000);

                    // Start smooth auto-scroll for all active branch columns
                    this.initAllAutoScrolls();
                },

                getActivityKey(item) {
                    return item.id + '_' + (item.has_checkout ? 'out_' + item.jam_checkout : 'in_' + item.jam_checkin);
                },

                initAllAutoScrolls() {
                    this.$nextTick(() => {
                        this.branchCards.forEach(b => {
                            this.initAutoScroll('branch-list-' + b.lokasi_id);
                        });
                    });
                },

                initAutoScroll(containerId) {
                    const container = document.getElementById(containerId);
                    if (!container || container.dataset.scrollInit) return;
                    container.dataset.scrollInit = 'true';

                    let scrollDirection = 1;
                    let isPaused = false;

                    setInterval(() => {
                        if (isPaused) return;

                        if (container.scrollHeight > container.clientHeight) {
                            if (container.scrollTop + container.clientHeight >= container.scrollHeight - 5) {
                                scrollDirection = -1;
                                isPaused = true;
                                setTimeout(() => { isPaused = false; }, 2500);
                            } else if (container.scrollTop <= 5 && scrollDirection === -1) {
                                scrollDirection = 1;
                                isPaused = true;
                                setTimeout(() => { isPaused = false; }, 2500);
                            }

                            container.scrollBy({
                                top: scrollDirection * 45,
                                behavior: 'smooth'
                            });
                        }
                    }, 2000);
                },

                updateClock() {
                    const days = ['Minggu', 'Senin', 'Selasa', 'Rabu', 'Kamis', 'Jumat', 'Sabtu'];
                    const months = [
                        'Januari', 'Februari', 'Maret', 'April', 'Mei', 'Juni', 
                        'Juli', 'Agustus', 'September', 'Oktober', 'November', 'Desember'
                    ];

                    const d = new Date();
                    
                    const hours = String(d.getHours()).padStart(2, '0');
                    const minutes = String(d.getMinutes()).padStart(2, '0');
                    const seconds = String(d.getSeconds()).padStart(2, '0');
                    this.clockTime = `${hours}:${minutes}:${seconds}`;

                    const dayName = days[d.getDay()];
                    const dateNum = d.getDate();
                    const monthName = months[d.getMonth()];
                    const year = d.getFullYear();
                    this.clockDate = `${dayName}, ${dateNum} ${monthName} ${year}`;
                },

                async fetchStats() {
                    try {
                        const response = await fetch(`/api/tv/{{ $displayToken }}/stats?date=${this.date}`);
                        if (response.ok) {
                            const text = await response.text();
                            const cleanJson = text.replace(/^\uFEFF/, '').trim();
                            const data = JSON.parse(cleanJson);

                            this.totalPegawai = data.totalPegawai;
                            this.totalHadir = data.totalHadir;
                            this.sedangBekerja = data.sedangBekerja;
                            this.sudahCheckOut = data.sudahCheckOut;
                            this.wfoCount = data.wfoCount;
                            this.wfhCount = data.wfhCount;
                            this.sakitCount = data.sakitCount;
                            this.belumHadir = data.belumHadir;
                            
                            const prevLength = this.branchCards.length;
                            this.branches = data.branches || [];
                            this.branchCards = data.branchCards || [];

                            if (this.branchCards.length !== prevLength) {
                                this.initAllAutoScrolls();
                            }

                            // Trigger live modal queue if new attendance occurs
                            const newActivities = [];
                            this.branchCards.forEach(b => {
                                (b.attendances || []).forEach(item => {
                                    const key = this.getActivityKey(item);
                                    if (!this.knownActivityKeys.has(key)) {
                                        this.knownActivityKeys.add(key);
                                        newActivities.push(item);
                                    }
                                });
                            });

                            if (newActivities.length > 0) {
                                newActivities.reverse().forEach(item => {
                                    this.modalQueue.push(item);
                                });
                                this.processModalQueue();
                            }
                        }
                    } catch (error) {
                        console.error('Failed to fetch stats:', error);
                    }
                },

                processModalQueue() {
                    if (this.isProcessingQueue || this.modalQueue.length === 0) {
                        return;
                    }

                    this.isProcessingQueue = true;
                    const nextItem = this.modalQueue.shift();
                    this.modalData = nextItem;
                    this.showModal = true;

                    if (this.modalTimer) {
                        clearTimeout(this.modalTimer);
                    }

                    this.modalTimer = setTimeout(() => {
                        this.closeModalAndProceed();
                    }, 4000);
                },

                closeModalAndProceed() {
                    if (this.modalTimer) {
                        clearTimeout(this.modalTimer);
                        this.modalTimer = null;
                    }

                    this.showModal = false;

                    setTimeout(() => {
                        this.isProcessingQueue = false;
                        if (this.modalQueue.length > 0) {
                            this.processModalQueue();
                        }
                    }, 350);
                }
            }));
        });
    </script>
</body>
</html>