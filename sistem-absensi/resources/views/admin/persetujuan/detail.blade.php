@extends('layouts.admin.app')

@section('title', 'Detail Persetujuan Pengajuan')

@section('content')
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

<div class="space-y-6 max-w-4xl mx-auto">
    {{-- Header --}}
    <div class="flex items-center justify-between">
        <div>
            <h1 class="text-2xl sm:text-3xl font-bold text-slate-900">Detail Pengajuan</h1>
            <p class="text-sm text-slate-500">Informasi lengkap permohonan {{ strtolower(\App\Helpers\OrganizationHelper::term('member', 'anggota')) }}</p>
        </div>
        <a href="{{ route('admin.persetujuan') }}" class="inline-flex items-center gap-2 rounded-2xl border border-slate-200 bg-white px-4 py-2.5 text-sm font-semibold text-slate-700 shadow-sm transition hover:bg-slate-50">
            <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18" />
            </svg>
            Kembali
        </a>
    </div>

    {{-- Content Card --}}
    <div class="overflow-hidden rounded-3xl bg-white border border-slate-200 shadow-sm p-6 sm:p-8">
        <div class="flex flex-col sm:flex-row items-center sm:items-start gap-6 border-b border-slate-100 pb-6">
            @if($photoUrl)
                <img src="{{ $photoUrl }}" alt="{{ $approval->pegawai?->nama_pegawai }}" class="h-24 w-24 rounded-full object-cover shadow-sm border border-slate-200" />
            @else
                <div class="flex h-24 w-24 items-center justify-center rounded-full bg-slate-100 text-2xl font-bold text-slate-700 border border-slate-200">
                    {{ strtoupper(substr($approval->pegawai?->nama_pegawai ?? 'U', 0, 1)) }}
                </div>
            @endif

            <div class="text-center sm:text-left flex-1">
                <h2 class="text-xl font-bold text-slate-900">{{ $approval->pegawai?->nama_pegawai ?? '-' }}</h2>
                @if(!empty($hasDivision))
                    <p class="text-sm text-slate-500">{{ $approval->pegawai?->masterDivisi?->nama_divisi ?? '-' }}</p>
                @endif
                <div class="mt-3">
                    <span class="inline-flex items-center rounded-full px-3 py-1 text-xs font-semibold {{ $statusClass }}">
                        {{ $displayStatus }}
                    </span>
                </div>
            </div>
        </div>

        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4 mt-6">
            <div class="p-4 rounded-2xl bg-slate-50 border border-slate-100">
                <p class="text-xs font-semibold uppercase tracking-wider text-slate-400">Jenis Pengajuan</p>
                <p class="text-base font-medium text-slate-800 mt-1">{{ $approval->jenis_pengajuan ?? '-' }}</p>
            </div>

            <div class="p-4 rounded-2xl bg-slate-50 border border-slate-100">
                <p class="text-xs font-semibold uppercase tracking-wider text-slate-400">Tanggal Pengajuan</p>
                <p class="text-base font-medium text-slate-800 mt-1">
                    {{ $approval->tanggal_pengajuan ? \Carbon\Carbon::parse($approval->tanggal_pengajuan)->translatedFormat('d F Y') : '-' }}
                </p>
            </div>

            @if(!empty($hasDivision))
            <div class="p-4 rounded-2xl bg-slate-50 border border-slate-100">
                <p class="text-xs font-semibold uppercase tracking-wider text-slate-400">{{ \App\Helpers\OrganizationHelper::term('division', 'Divisi') }}</p>
                <p class="text-base font-medium text-slate-800 mt-1">{{ $approval->pegawai?->masterDivisi?->nama_divisi ?? '-' }}</p>
            </div>
            @endif

            <div class="p-4 rounded-2xl bg-slate-50 border border-slate-100">
                <p class="text-xs font-semibold uppercase tracking-wider text-slate-400">Status</p>
                <p class="text-base font-medium text-slate-800 mt-1">{{ $displayStatus }}</p>
            </div>
        </div>

        <div class="mt-6 p-4 rounded-2xl bg-slate-50 border border-slate-100">
            <p class="text-xs font-semibold uppercase tracking-wider text-slate-400">Keterangan / Alasan</p>
            <p class="text-sm text-slate-700 mt-1 whitespace-pre-line">{{ $approval->keterangan ?? 'Tidak ada keterangan.' }}</p>
        </div>

        @if($approval->lampiran)
        <div class="mt-6 p-4 rounded-2xl bg-slate-50 border border-slate-100">
            <p class="text-xs font-semibold uppercase tracking-wider text-slate-400 mb-2">Lampiran Dokumen</p>
            <a href="{{ supabase_submission_url($approval->lampiran) }}" target="_blank" class="inline-flex items-center gap-2 text-sm text-primary hover:underline font-medium">
                <i class="fa-solid fa-paperclip"></i>
                Lihat / Unduh Lampiran
            </a>
        </div>
        @endif
    </div>
</div>
@endsection
