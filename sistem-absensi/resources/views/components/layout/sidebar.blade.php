<div class="flex h-full w-full flex-col bg-white overflow-y-auto">
    {{-- ========================= --}}
    {{-- HEADER --}}
    {{-- ========================= --}}
    <div class="px-5 pt-5">
        @php
            $activeOrgId = \App\Helpers\OrganizationHelper::getActiveOrganizationId();
            $activeOrg = $activeOrgId ? \App\Models\Organization::find($activeOrgId) : null;
            $isSuperAdmin = Auth::user()?->isSuperAdmin() ?? false;
            $isSuperAdminGlobal = $isSuperAdmin && (! $activeOrgId || request()->routeIs('admin.organization.*', 'admin.system.*'));

            $companyName = $activeOrg ? $activeOrg->nama_organisasi : ($isSuperAdmin ? 'Super Admin' : 'Nama Organisasi');
            $companyInitials = $activeOrg ? getInitials($companyName) : ($isSuperAdmin ? 'SA' : null);
            $companyLogo = $activeOrg ? company_logo_url() : null;
            $userRoleDisplay = $isSuperAdmin ? ($activeOrg ? 'Super Admin (' . $activeOrg->nama_organisasi . ')' : 'Super Admin') : (Auth::user()?->roleAkses?->nama_role ?? Auth::user()?->role ?? 'User');
        @endphp
        <div class="flex items-center gap-3">
            {{-- Logo --}}
            <div class="h-10 w-10 flex-shrink-0 overflow-hidden rounded-full bg-white flex items-center justify-center border border-slate-100 shadow-sm">
                @if($companyLogo)
                    <img src="{{ $companyLogo }}" alt="Logo {{ $companyName }}" class="h-full w-full object-cover" onerror="this.hidden=true;this.nextElementSibling.hidden=false;">
                @endif
                <span @if($companyLogo) hidden @endif class="h-full w-full rounded-full bg-primary text-white flex items-center justify-center text-xs font-bold">
                    @if($companyInitials)
                        {{ $companyInitials }}
                    @else
                        <i class="fa-solid fa-building" aria-hidden="true"></i>
                    @endif
                </span>
            </div>

            {{-- Company --}}
            <div class="flex flex-col">
                <h2 class="text-[16px] font-semibold text-slate-900 leading-tight">
                    {{ $companyName }}
                </h2>
                <p class="text-[12px] text-slate-500 font-medium">
                    {{ $userRoleDisplay }}
                </p>
            </div>
        </div>
    </div>


    {{-- Divider --}}
    <div class="mx-5 mt-5 border-b border-slate-200"></div>


    {{-- ========================= --}}
    {{-- MENU --}}
    {{-- ========================= --}}
    @php
        /**
         * Tahap 4A: Muat privilege user yang sedang login sekali saja.
         * Alur: Auth::user() → Akun → roleAkses (Role) → hasPrivilege()
         * Jika user tidak login atau tidak punya relasi role, semua menu disabled.
         * Super Admin selalu mendapat akses penuh (ditangani di Role::hasPrivilege()).
         */
        $sidebarRole = Auth::user()?->roleAkses ?? null;

        // Fungsi helper lokal untuk cek privilege di Blade
        $canAccess = function (string $privilege) use ($sidebarRole): bool {
            if ($sidebarRole === null) {
                return false;
            }
            return $sidebarRole->hasPrivilege($privilege);
        };

        // Feature active checks for active organization
        $hasAttendanceFeature = \App\Helpers\OrganizationHelper::canAccessFeature('attendance');
        $hasEmployeeFeature   = \App\Helpers\OrganizationHelper::canAccessFeature('employee');
        $hasApprovalFeature   = \App\Helpers\OrganizationHelper::canAccessFeature('approval');

        // Combined access rule: ACCESS = PRIVILEGE && FEATURE
        $canDashboard       = $canAccess('lihat_dashboard');
        $canLaporan         = $canAccess('lihat_laporan_kehadiran') && $hasAttendanceFeature;
        $canManajemenAkun   = $canAccess('lihat_manajemen_akun') && $hasEmployeeFeature;
        $canPersetujuan     = $canAccess('lihat_persetujuan') && $hasApprovalFeature;
        $canLogAktivitas    = $canAccess('lihat_log_aktivitas');

        // Settings: aktif jika memiliki setidaknya satu privilege Settings
        $canSettings = $canAccess('kelola_tampilan_branding')
                    || $canAccess('kelola_lokasi_cabang')
                    || $canAccess('kelola_wifi_kantor')
                    || $canAccess('kelola_role_hak_akses');

        $isSuperAdmin = Auth::user()?->isSuperAdmin() ?? false;
    @endphp

    <nav class="mt-4 flex-1 px-3 pb-6">

        <p class="mb-3 px-3 text-sm font-medium text-slate-500">
            Menu
        </p>

        <div class="space-y-2">
        @if($isSuperAdminGlobal)
            {{-- Menu Super Admin: 1. Organisasi --}}
            <a href="{{ route('admin.organization.select') }}"
                class="relative flex items-center gap-3 rounded-2xl px-4 py-3 {{ request()->routeIs('admin.organization.*') ? 'bg-blue-50 text-primary' : 'text-slate-600 hover:bg-slate-100 hover:text-primary' }} transition">
                <i class="fa-solid fa-building fa-fw text-lg"></i>
                <span class="text-sm font-semibold">Organisasi</span>
                @if(request()->routeIs('admin.organization.*'))
                <span class="absolute right-0 top-2 bottom-2 w-1 rounded-full bg-primary"></span>
                @endif
            </a>

            {{-- Menu Super Admin: 2. Log Aktivitas --}}
            <a href="{{ route('admin.log-aktivitas') }}"
                class="relative flex items-center gap-3 rounded-2xl px-4 py-3 {{ request()->routeIs('admin.log-aktivitas*') ? 'bg-blue-50 text-primary' : 'text-slate-600 hover:bg-slate-100 hover:text-primary' }} transition">
                <i class="fa-solid fa-clock-rotate-left fa-fw text-lg"></i>
                <span class="text-sm font-semibold">Log Aktivitas</span>
                @if(request()->routeIs('admin.log-aktivitas*'))
                <span class="absolute right-0 top-2 bottom-2 w-1 rounded-full bg-primary"></span>
                @endif
            </a>

            {{-- Menu Super Admin: 3. Sistem & Fitur --}}
            <a href="{{ route('admin.system.categories') }}"
                class="relative flex items-center gap-3 rounded-2xl px-4 py-3 {{ request()->routeIs('admin.system.*', 'admin.organization.features*') ? 'bg-blue-50 text-primary' : 'text-slate-600 hover:bg-slate-100 hover:text-primary' }} transition">
                <i class="fa-solid fa-shapes fa-fw text-lg"></i>
                <span class="text-sm font-semibold">Sistem & Fitur</span>
                @if(request()->routeIs('admin.system.*', 'admin.organization.features*'))
                <span class="absolute right-0 top-2 bottom-2 w-1 rounded-full bg-primary"></span>
                @endif
            </a>
        @else
            {{-- Dashboard (Core) --}}
            @if($canDashboard)
                <a href="{{ route('admin.dashboard') }}"
                    class="relative flex items-center gap-3 rounded-2xl {{ request()->routeIs('admin.dashboard', 'admin.dashboard.index') ? 'bg-blue-50 text-primary' : 'text-slate-600 hover:bg-slate-100 hover:text-primary' }} px-4 py-3 transition">

                    <i class="fa-solid fa-house fa-fw text-lg"></i>

                    <span class="text-sm font-semibold">
                        Dashboard
                    </span>

                    @if(request()->routeIs('admin.dashboard', 'admin.dashboard.index'))
                    <span
                        class="absolute right-0 top-2 bottom-2 w-1 rounded-full bg-primary">
                    </span>
                    @endif

                </a>
            @endif

            {{-- Laporan Kehadiran (Feature: attendance) --}}
            @if($hasAttendanceFeature)
                @if($canLaporan)
                    <a href="{{ route('admin.laporan-kehadiran') }}"
                        class="relative flex items-center gap-3 rounded-2xl px-4 py-3 {{ request()->routeIs('admin.laporan-kehadiran') ? 'bg-blue-50 text-primary' : 'text-slate-600 hover:bg-slate-100 hover:text-primary' }} transition">

                        <i class="fa-solid fa-clipboard-user fa-fw text-lg"></i>

                        <span class="text-sm font-semibold">
                            Laporan Kehadiran
                        </span>

                        @if(request()->routeIs('admin.laporan-kehadiran'))
                        <span class="absolute right-0 top-2 bottom-2 w-1 rounded-full bg-[#123D91]"></span>
                        @endif

                    </a>
                @else
                    <span title="Anda tidak memiliki akses ke Laporan Kehadiran"
                        class="relative flex items-center gap-3 rounded-2xl px-4 py-3 text-slate-300 opacity-50 cursor-not-allowed select-none">

                        <i class="fa-solid fa-clipboard-user fa-fw text-lg"></i>

                        <span class="text-sm font-semibold">
                            Laporan Kehadiran
                        </span>

                    </span>
                @endif
            @endif

            {{-- Manajemen Akun / Data Pegawai (Feature: employee) --}}
            @if($hasEmployeeFeature)
                @if($canManajemenAkun)
                    <a href="{{ route('admin.manajemen-akun') }}"
                        class="relative flex items-center gap-3 rounded-2xl px-4 py-3 {{ request()->routeIs('admin.manajemen-akun', 'admin.employee-management.*') ? 'bg-blue-50 text-primary' : 'text-slate-600 hover:bg-slate-100 hover:text-primary' }} transition">

                        <i class="fa-solid fa-users-gear fa-fw text-lg"></i>

                        <span class="text-sm font-semibold">
                            {{ \App\Helpers\OrganizationHelper::term('member_management', 'Manajemen Akun') }}
                        </span>

                        @if(request()->routeIs('admin.manajemen-akun', 'admin.employee-management.*'))
                        <span class="absolute right-0 top-2 bottom-2 w-1 rounded-full bg-[#123D91]"></span>
                        @endif

                    </a>
                @else
                    <span title="Anda tidak memiliki akses ke Manajemen Akun"
                        class="relative flex items-center gap-3 rounded-2xl px-4 py-3 text-slate-300 opacity-50 cursor-not-allowed select-none">

                        <i class="fa-solid fa-users-gear fa-fw text-lg"></i>

                        <span class="text-sm font-semibold">
                            {{ \App\Helpers\OrganizationHelper::term('member_management', 'Manajemen Akun') }}
                        </span>

                    </span>
                @endif
            @endif

            {{-- Persetujuan (Feature: approval) --}}
            @if($hasApprovalFeature)
                @if($canPersetujuan)
                    <a href="{{ route('admin.persetujuan') }}"
                        class="relative flex items-center gap-3 rounded-2xl px-4 py-3 {{ request()->routeIs('admin.persetujuan', 'admin.persetujuan.detail') ? 'bg-blue-50 text-primary' : 'text-slate-600 hover:bg-slate-100 hover:text-primary' }} transition">

                        <i class="fa-solid fa-clipboard-check fa-fw text-lg"></i>

                        <span class="text-sm font-semibold">
                            Persetujuan
                        </span>

                        @if(request()->routeIs('admin.persetujuan', 'admin.persetujuan.detail'))
                        <span class="absolute right-0 top-2 bottom-2 w-1 rounded-full bg-[#123D91]"></span>
                        @endif

                    </a>
                @else
                    <span title="Anda tidak memiliki akses ke Persetujuan"
                        class="relative flex items-center gap-3 rounded-2xl px-4 py-3 text-slate-300 opacity-50 cursor-not-allowed select-none">

                        <i class="fa-solid fa-clipboard-check fa-fw text-lg"></i>

                        <span class="text-sm font-semibold">
                            Persetujuan
                        </span>

                    </span>
                @endif
            @endif

            {{-- Log Aktivitas (Privilege-only) --}}
            @if($canLogAktivitas)
                <a href="{{ route('admin.log-aktivitas') }}"
                    class="relative flex items-center gap-3 rounded-2xl px-4 py-3 {{ request()->routeIs('admin.log-aktivitas') ? 'bg-blue-50 text-primary' : 'text-slate-600 hover:bg-slate-100 hover:text-primary' }} transition">

                    <i class="fa-solid fa-clock-rotate-left fa-fw text-lg"></i>

                    <span class="text-sm font-semibold">
                        Log Aktivitas
                    </span>

                    @if(request()->routeIs('admin.log-aktivitas'))
                    <span class="absolute right-0 top-2 bottom-2 w-1 rounded-full bg-[#123D91]"></span>
                    @endif

                </a>
            @else
                <span title="Anda tidak memiliki akses ke Log Aktivitas"
                    class="relative flex items-center gap-3 rounded-2xl px-4 py-3 text-slate-300 opacity-50 cursor-not-allowed select-none">

                    <i class="fa-solid fa-clock-rotate-left fa-fw text-lg"></i>

                    <span class="text-sm font-semibold">
                        Log Aktivitas
                    </span>

                </span>
            @endif

            {{-- Settings (Privilege-only) --}}
            @if($canSettings)
                <a href="{{ route('admin.tampilan-branding') }}"
                    class="relative flex items-center gap-3 rounded-2xl px-4 py-3 {{ request()->routeIs('admin.tampilan-branding') ? 'bg-blue-50 text-primary' : 'text-slate-600 hover:bg-slate-100 hover:text-primary' }} transition">

                    <i class="fa-solid fa-gear fa-fw text-lg"></i>

                    <span class="text-sm font-semibold">
                        Settings
                    </span>

                    @if(request()->routeIs('admin.tampilan-branding'))
                    <span class="absolute right-0 top-2 bottom-2 w-1 rounded-full bg-primary"></span>
                    @endif

                </a>
            @else
                <span title="Anda tidak memiliki akses ke Settings"
                    class="relative flex items-center gap-3 rounded-2xl px-4 py-3 text-slate-300 opacity-50 cursor-not-allowed select-none">

                    <i class="fa-solid fa-gear fa-fw text-lg"></i>

                    <span class="text-sm font-semibold">
                        Settings
                    </span>

                </span>
            @endif

            {{-- Sistem Multi-Org & Fitur (Khusus Super Admin di dalam konteks organisasi) --}}
            @if($isSuperAdmin)
                <div class="pt-2 mt-2 border-t border-slate-100 space-y-1">
                    <a href="{{ route('admin.organization.select') }}"
                        class="relative flex items-center gap-3 rounded-2xl px-4 py-2.5 text-slate-600 hover:bg-slate-100 hover:text-primary transition">
                        <i class="fa-solid fa-building fa-fw text-base"></i>
                        <span class="text-xs font-semibold">Daftar Organisasi</span>
                    </a>
                    <a href="{{ route('admin.system.categories') }}"
                        class="relative flex items-center gap-3 rounded-2xl px-4 py-2.5 text-slate-600 hover:bg-slate-100 hover:text-primary transition">
                        <i class="fa-solid fa-shapes fa-fw text-base"></i>
                        <span class="text-xs font-semibold">Sistem & Fitur</span>
                    </a>
                </div>
            @endif
        @endif
        </div>

    </nav>


    {{-- Divider --}}
    <div class="mx-5 border-t border-slate-200"></div>


    {{-- ========================= --}}
    {{-- LOGOUT --}}
    {{-- ========================= --}}
    <div class="p-5" x-data="{ logoutOpen: false }">

        {{-- Hidden logout form --}}
        <form id="logout-form" method="POST" action="{{ route('logout') }}">
            @csrf
        </form>

        {{-- Trigger button --}}
        <button
            type="button"
            @click="logoutOpen = true"
            class="flex w-full items-center justify-center gap-2 rounded-2xl bg-primary py-3 text-sm font-medium text-white transition hover:bg-primary-hover">

            <i class="fa-solid fa-right-from-bracket fa-fw text-lg"></i>

            Logout

        </button>

        {{-- Confirmation Modal --}}
        <div
            x-show="logoutOpen"
            x-cloak
            class="fixed inset-0 z-[60] flex items-center justify-center"
            aria-modal="true"
            role="dialog">

            {{-- Backdrop --}}
            <div
                class="absolute inset-0 bg-white/70 backdrop-blur-sm"
                @click="logoutOpen = false">
            </div>

            {{-- Dialog Card --}}
            <div
                x-show="logoutOpen"
                x-transition:enter="transition ease-out duration-200"
                x-transition:enter-start="opacity-0 scale-95"
                x-transition:enter-end="opacity-100 scale-100"
                x-transition:leave="transition ease-in duration-150"
                x-transition:leave-start="opacity-100 scale-100"
                x-transition:leave-end="opacity-0 scale-95"
                class="relative w-full max-w-sm rounded-3xl bg-white px-10 py-10 shadow-2xl text-center">
                {{-- Warning Icon --}}
                <div class="flex justify-center mb-6">
                    <div class="flex h-24 w-24 items-center justify-center rounded-full bg-red-500">
                        <svg xmlns="http://www.w3.org/2000/svg"
                            class="h-12 w-12 text-white"
                            fill="currentColor"
                            viewBox="0 0 24 24">
                            <path d="M12 2C6.477 2 2 6.477 2 12s4.477 10 10 10 10-4.477 10-10S17.523 2 12 2zm0 5a1 1 0 0 1 1 1v4a1 1 0 1 1-2 0V8a1 1 0 0 1 1-1zm0 10a1.25 1.25 0 1 1 0-2.5A1.25 1.25 0 0 1 12 17z"/>
                        </svg>
                    </div>
                </div>

                {{-- Message --}}
                <p class="mb-8 text-base font-medium text-slate-800">
                    Apakah anda ingin keluar?
                </p>

                {{-- Actions --}}
                <div class="flex items-center justify-center gap-4">

                    <button
                        type="button"
                        @click="logoutOpen = false"
                        class="rounded-full border border-red-300 bg-red-50 px-8 py-2.5 text-sm font-semibold text-red-500 transition hover:bg-red-100">
                        Tidak
                    </button>

                    <button
                        type="button"
                        @click="document.getElementById('logout-form').submit()"
                        class="rounded-full bg-red-500 px-8 py-2.5 text-sm font-semibold text-white transition hover:bg-red-600">
                        Ya
                    </button>

                </div>

            </div>

        </div>

    </div>

</div>
