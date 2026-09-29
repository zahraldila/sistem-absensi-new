{{-- ======================================== --}}
{{-- 1. TAMPILAN MOBILE: CARD LIST (HP) --}}
{{-- ======================================== --}}
<div class="block sm:hidden space-y-3.5 mb-6">
    @forelse($approvals as $approval)
        @php
            $displayStatus = in_array($approval->status_pengajuan, ['Pending', 'Menunggu'], true) ? 'Pending' : ($approval->status_pengajuan ?? '-');
            $statusClass = match($displayStatus) {
                'Pending' => 'bg-yellow-100 text-yellow-700',
                'Disetujui' => 'bg-green-100 text-green-700',
                'Ditolak' => 'bg-red-100 text-red-700',
                default => 'bg-slate-100 text-slate-700',
            };
            $photoUrl = supabase_public_url($approval->pegawai?->foto_profile);
        @endphp
        <div class="rounded-2xl border border-slate-200 bg-white p-4 shadow-sm space-y-3">
            {{-- Header: Foto + Nama + Status --}}
            <div class="flex items-center justify-between gap-3 border-b border-slate-100 pb-3">
                <div class="flex items-center gap-3 min-w-0">
                    @if($photoUrl)
                        <img src="{{ $photoUrl }}" alt="{{ $approval->pegawai?->nama_pegawai }}" class="h-10 w-10 rounded-full object-cover flex-shrink-0 shadow-sm" />
                    @else
                        <div class="flex h-10 w-10 items-center justify-center rounded-full bg-slate-100 text-xs font-semibold text-slate-700 flex-shrink-0 border border-slate-200">
                            {{ strtoupper(substr($approval->pegawai?->nama_pegawai ?? 'U', 0, 1)) }}
                        </div>
                    @endif
                    <div class="min-w-0">
                        <h3 class="truncate text-sm font-bold text-slate-900">{{ $approval->pegawai?->nama_pegawai ?? '-' }}</h3>
                        <p class="text-xs text-slate-500">{{ $approval->pegawai?->masterDivisi?->nama_divisi ?? '-' }}</p>
                    </div>
                </div>
                <div class="flex-shrink-0">
                    <span class="rounded-full px-2.5 py-1 text-xs font-semibold {{ $statusClass }}">
                        {{ $displayStatus }}
                    </span>
                </div>
            </div>

            {{-- Grid Info --}}
            <div class="grid grid-cols-2 gap-2.5 text-xs">
                <div class="rounded-xl bg-slate-50 p-2.5">
                    <p class="text-slate-400 text-[10px] uppercase font-semibold">Jenis Pengajuan</p>
                    <p class="font-medium text-slate-800 mt-0.5">{{ $approval->jenis_pengajuan ?? '-' }}</p>
                </div>

                <div class="rounded-xl bg-slate-50 p-2.5">
                    <p class="text-slate-400 text-[10px] uppercase font-semibold">Tanggal Pengajuan</p>
                    <p class="font-medium text-slate-800 mt-0.5">
                        {{ $approval->tanggal_pengajuan ? \Carbon\Carbon::parse($approval->tanggal_pengajuan)->translatedFormat('d M Y') : '-' }}
                    </p>
                </div>
            </div>

            {{-- Action Button --}}
            <div class="pt-1 border-t border-slate-100">
                <button type="button" @click.prevent="openApproval($event)" data-approval="{{ json_encode([
                    'pengajuan_id' => $approval->pengajuan_id,
                    'approval_id' => $approval->pengajuan_id,
                    'nama_pegawai' => $approval->pegawai->nama_pegawai ?? '-',
                    'divisi_name' => $approval->pegawai->masterDivisi->nama_divisi ?? '-',
                    'jenis_pengajuan' => $approval->jenis_pengajuan ?? '-',
                    'tanggal_pengajuan' => $approval->tanggal_pengajuan ? \Carbon\Carbon::parse($approval->tanggal_pengajuan)->format('d F Y') : '-',
                    'status_pengajuan' => $displayStatus,
                    'keterangan' => $approval->keterangan ?? '-',
                    'lampiran_path' => $approval->lampiran ?? null,
                    'lampiran_url' => $approval->lampiran ? supabase_submission_url($approval->lampiran) : null,
                    'lampiran_name' => $approval->lampiran ? basename($approval->lampiran) : null,
                    'foto_profile' => $photoUrl
                ], JSON_HEX_APOS | JSON_HEX_QUOT) }}" class="w-full inline-flex items-center justify-center gap-1.5 rounded-xl border border-slate-200 bg-white py-2 text-xs font-semibold text-slate-700 transition hover:bg-slate-50 shadow-sm">
                    <i class="fa-solid fa-eye text-slate-500 text-xs"></i>
                    <span>Lihat Detail Pengajuan</span>
                </button>
            </div>
        </div>
    @empty
        <div class="rounded-2xl border border-slate-200 bg-white p-8 text-center text-xs text-slate-500">
            Belum ada data pengajuan.
        </div>
    @endforelse
</div>

{{-- ======================================== --}}
{{-- 2. TAMPILAN DESKTOP: FULL TABLE --}}
{{-- ======================================== --}}
<div class="hidden sm:block overflow-hidden rounded-2xl border border-gray-200 bg-white shadow-sm mb-6">
    <div class="overflow-x-auto">
        <table class="min-w-full divide-y divide-gray-200 text-left text-sm">
            <thead class="bg-gray-50/80">
                <tr>
                    <th class="px-4 py-3.5 text-left text-xs font-semibold uppercase tracking-wider text-gray-600">Anggota</th>
                    <th class="px-4 py-3.5 text-left text-xs font-semibold uppercase tracking-wider text-gray-600">Jenis Pengajuan</th>
                    <th class="px-4 py-3.5 text-left text-xs font-semibold uppercase tracking-wider text-gray-600">Tanggal Pengajuan</th>
                    <th class="px-4 py-3.5 text-left text-xs font-semibold uppercase tracking-wider text-gray-600">Status</th>
                    <th class="px-4 py-3.5 text-center text-xs font-semibold uppercase tracking-wider text-gray-600">Aksi</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-200 bg-white">
                @forelse($approvals as $approval)
                    @php
                        $displayStatus = in_array($approval->status_pengajuan, ['Pending', 'Menunggu'], true) ? 'Pending' : ($approval->status_pengajuan ?? '-');
                        $statusClass = match($displayStatus) {
                            'Pending' => 'bg-yellow-100 text-yellow-700',
                            'Disetujui' => 'bg-green-100 text-green-700',
                            'Ditolak' => 'bg-red-100 text-red-700',
                            default => 'bg-slate-100 text-slate-700',
                        };
                        $photoUrl = supabase_public_url($approval->pegawai?->foto_profile);
                    @endphp
                    <tr class="transition hover:bg-gray-50/80">
                        <td class="px-4 py-3 text-sm text-gray-900">
                            <div class="flex items-center gap-3.5">
                                @if($photoUrl)
                                    <img src="{{ $photoUrl }}" alt="{{ $approval->pegawai?->nama_pegawai }}" class="h-10 w-10 rounded-full object-cover shadow-sm flex-shrink-0" />
                                @else
                                    <div class="flex h-10 w-10 items-center justify-center rounded-full bg-slate-100 text-sm font-semibold text-slate-700 flex-shrink-0 border border-slate-200">
                                        {{ strtoupper(substr($approval->pegawai?->nama_pegawai ?? 'U', 0, 1)) }}
                                    </div>
                                @endif
                                <div class="min-w-0">
                                    <p class="font-semibold text-slate-900">{{ $approval->pegawai?->nama_pegawai ?? '-' }}</p>
                                    <p class="text-xs text-slate-500">{{ $approval->pegawai?->masterDivisi?->nama_divisi ?? '-' }}</p>
                                </div>
                            </div>
                        </td>
                        <td class="px-4 py-3 text-sm text-gray-700">
                            <span class="font-medium text-slate-800">{{ $approval->jenis_pengajuan ?? '-' }}</span>
                        </td>
                        <td class="px-4 py-3 text-sm text-gray-700">
                            @if($approval->tanggal_pengajuan)
                                {{ \Carbon\Carbon::parse($approval->tanggal_pengajuan)->translatedFormat('d F Y') }}
                            @else
                                -
                            @endif
                        </td>
                        <td class="px-4 py-3 text-sm">
                            <span class="rounded-full px-3 py-1 text-xs font-semibold {{ $statusClass }}">{{ $displayStatus }}</span>
                        </td>
                        <td class="px-4 py-3 text-center">
                            <div class="flex items-center justify-center gap-2">
                                <button type="button" @click.prevent="openApproval($event)" data-approval="{{ json_encode([
                                    'pengajuan_id' => $approval->pengajuan_id,
                                    'approval_id' => $approval->pengajuan_id,
                                    'nama_pegawai' => $approval->pegawai->nama_pegawai ?? '-',
                                    'divisi_name' => $approval->pegawai->masterDivisi->nama_divisi ?? '-',
                                    'jenis_pengajuan' => $approval->jenis_pengajuan ?? '-',
                                    'tanggal_pengajuan' => $approval->tanggal_pengajuan ? \Carbon\Carbon::parse($approval->tanggal_pengajuan)->format('d F Y') : '-',
                                    'status_pengajuan' => $displayStatus,
                                    'keterangan' => $approval->keterangan ?? '-',
                                    'lampiran_path' => $approval->lampiran ?? null,
                                    'lampiran_url' => $approval->lampiran ? supabase_submission_url($approval->lampiran) : null,
                                    'lampiran_name' => $approval->lampiran ? basename($approval->lampiran) : null,
                                    'foto_profile' => $photoUrl
                                ], JSON_HEX_APOS | JSON_HEX_QUOT) }}" class="inline-flex items-center rounded-xl bg-slate-100 px-3.5 py-1.5 text-xs font-semibold text-slate-700 transition hover:bg-slate-200">Detail</button>
                            </div>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="5" class="py-12 text-center">
                            <p class="text-sm text-slate-400">Belum ada data pengajuan.</p>
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>

{{-- ======================================== --}}
{{-- 3. PAGINATION (Mobile & Desktop) --}}
{{-- ======================================== --}}
<div class="flex flex-col gap-3.5 border-t border-slate-200 bg-white p-4 sm:p-5 rounded-2xl shadow-sm sm:flex-row sm:items-center sm:justify-between mb-6">
    <p class="text-xs sm:text-sm text-slate-500 text-center sm:text-left">
        Menampilkan <span class="font-semibold text-slate-700">{{ $approvals->firstItem() ?? 0 }} - {{ $approvals->lastItem() ?? 0 }}</span> dari <span class="font-semibold text-slate-700">{{ $approvals->total() }}</span> data
    </p>

    <div class="overflow-x-auto pb-1 sm:pb-0 flex justify-center sm:justify-end">
        <nav class="inline-flex overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm flex-shrink-0" aria-label="Pagination">
            {{-- Previous Page Link --}}
            <a
                class="inline-flex h-9 sm:h-11 w-9 sm:w-11 items-center justify-center border-r border-slate-200 text-xs sm:text-sm font-medium transition {{ $approvals->onFirstPage() ? 'cursor-not-allowed pointer-events-none bg-slate-100 text-slate-300' : 'text-slate-700 hover:bg-slate-100 hover:text-slate-900 active:bg-slate-200' }}"
                href="{{ $approvals->onFirstPage() ? '#' : $approvals->previousPageUrl() }}"
                aria-disabled="{{ $approvals->onFirstPage() ? 'true' : 'false' }}"
            >
                <i class="fa-solid fa-chevron-left text-xs"></i>
            </a>

            @php
                $currentPage = $approvals->currentPage();
                $lastPage = $approvals->lastPage();
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
                        href="{{ $approvals->url($element) }}"
                    >
                        {{ $element }}
                    </a>
                @endif
            @endforeach

            {{-- Next Page Link --}}
            <a
                class="inline-flex h-9 sm:h-11 w-9 sm:w-11 items-center justify-center text-xs sm:text-sm font-medium transition {{ ! $approvals->hasMorePages() ? 'cursor-not-allowed pointer-events-none bg-slate-100 text-slate-300' : 'text-slate-700 hover:bg-slate-100 hover:text-slate-900 active:bg-slate-200' }}"
                href="{{ $approvals->hasMorePages() ? $approvals->nextPageUrl() : '#' }}"
                aria-disabled="{{ ! $approvals->hasMorePages() ? 'true' : 'false' }}"
            >
                <i class="fa-solid fa-chevron-right text-xs"></i>
            </a>
        </nav>
    </div>
</div>