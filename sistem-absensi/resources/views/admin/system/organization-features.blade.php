@extends('layouts.admin.app')

@section('title', 'Kelola Fitur Organisasi: ' . $organization->nama_organisasi . ' — Sistem Multi-Organisasi')

@section('content')
<div class="max-w-5xl mx-auto space-y-6">

    {{-- Breadcrumb / Back --}}
    <div class="flex items-center justify-between">
        <a href="{{ route('admin.organization.select') }}" class="inline-flex items-center gap-2 text-sm font-semibold text-slate-500 hover:text-primary transition">
            <i class="fa-solid fa-arrow-left"></i>
            Kembali ke Daftar Organisasi
        </a>
        <span class="text-xs text-slate-400">
            ID Organisasi: #{{ $organization->organization_id }}
        </span>
    </div>

    {{-- Header --}}
    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
        <div>
            <div class="flex items-center gap-2.5 mb-1.5">
                <span class="inline-block bg-blue-50 text-primary text-xs font-bold px-2.5 py-1 rounded-md">
                    {{ $organization->kode_organisasi }}
                </span>
                <span class="inline-block bg-slate-100 text-slate-700 text-xs font-semibold px-2.5 py-1 rounded-md">
                    Kategori: {{ $organization->category?->name ?? 'Belum Ditentukan' }}
                </span>
            </div>
            <h1 class="text-2xl sm:text-3xl font-bold text-slate-900 leading-tight">
                {{ $organization->nama_organisasi }}
            </h1>
            <p class="mt-1 text-sm text-slate-500">
                Atur kapabilitas aktif untuk organisasi ini. Mengubah switch di bawah akan menimpa (override) konfigurasi template tanpa mengubah kategori organisasi.
            </p>
        </div>
    </div>

    {{-- Flash Notifications --}}
    @if(session('success'))
    <div class="flex items-center gap-3 rounded-2xl border border-green-200 bg-green-50 px-5 py-4 text-sm text-green-800 shadow-sm">
        <i class="fa-solid fa-circle-check text-green-500"></i>
        <span>{{ session('success') }}</span>
    </div>
    @endif

    {{-- Form Feature Overrides --}}
    <form method="POST" action="{{ route('admin.organization.features.update', $organization->organization_id) }}" class="space-y-6">
        @csrf
        @method('PUT')

        @foreach($features as $group => $items)
        <div class="rounded-3xl bg-white p-6 sm:p-8 shadow-sm border border-slate-200">
            <div class="flex items-center justify-between border-b border-slate-100 pb-3 mb-4">
                <div class="flex items-center gap-2">
                    <span class="h-2 w-2 rounded-full bg-primary"></span>
                    <h2 class="text-sm font-bold uppercase tracking-wider text-slate-700">
                        Fitur Grup: {{ ucfirst($group) }}
                    </h2>
                </div>
                <span class="text-xs text-slate-400">{{ $items->count() }} fitur</span>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                @foreach($items as $feature)
                @php
                    $isCurrentlyActive = !empty($currentFeaturesMap[$feature->id]);
                @endphp
                <div class="p-4 rounded-2xl border transition flex items-start justify-between gap-3 {{ $isCurrentlyActive ? 'border-primary/40 bg-blue-50/20' : 'border-slate-200 bg-slate-50/30' }}">
                    <div class="flex-1">
                        <div class="flex items-center gap-2 mb-1">
                            <span class="text-sm font-bold text-slate-900">{{ $feature->name }}</span>
                            <span class="font-mono text-[11px] text-slate-400">({{ $feature->key }})</span>
                        </div>
                        <p class="text-xs text-slate-500 line-clamp-2">
                            {{ $feature->description ?: 'Tidak ada deskripsi.' }}
                        </p>
                    </div>

                    {{-- Switch Toggle --}}
                    <label class="relative inline-flex items-center cursor-pointer flex-shrink-0 mt-0.5">
                        <input type="checkbox" name="features[{{ $feature->id }}]" value="1" {{ $isCurrentlyActive ? 'checked' : '' }}
                               class="sr-only peer">
                        <div class="w-11 h-6 bg-slate-200 peer-focus:outline-none rounded-full peer peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:border-slate-300 after:border after:rounded-full after:h-5 after:w-5 after:transition-all peer-checked:bg-primary"></div>
                    </label>
                </div>
                @endforeach
            </div>
        </div>
        @endforeach

        {{-- Floating / Bottom Action Bar --}}
        <div class="sticky bottom-4 rounded-2xl bg-white p-4 shadow-lg border border-slate-200 flex items-center justify-between gap-4">
            <span class="text-xs text-slate-500 hidden sm:inline">
                Perubahan fitur akan langsung diterapkan pada instans organisasi secara aman (cache busting otomatis).
            </span>
            <div class="flex items-center gap-3 ml-auto">
                <a href="{{ route('admin.organization.select') }}" class="px-4 py-2 rounded-xl text-sm font-semibold text-slate-600 hover:bg-slate-100 transition">
                    Kembali
                </a>
                <button type="submit" class="inline-flex items-center gap-2 px-6 py-2.5 rounded-xl bg-primary text-white text-sm font-bold hover:bg-primary-hover shadow-sm transition">
                    <i class="fa-solid fa-check"></i>
                    Simpan Perubahan Fitur
                </button>
            </div>
        </div>
    </form>

</div>
@endsection
