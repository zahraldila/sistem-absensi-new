@extends('layouts.admin.app')

@section('title', 'Template Kategori — Sistem Multi-Organisasi')

@section('content')
<div class="space-y-6">

    {{-- System Navigation Tabs --}}
    @include('admin.system._nav')

    {{-- Header --}}
    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
        <div>
            <h1 class="text-2xl sm:text-3xl font-bold text-slate-900 leading-tight">
                Template Kategori
            </h1>
            <p class="mt-1 text-sm text-slate-500">
                Kumpulan konfigurasi fitur awal untuk tiap kategori. Saat organisasi baru dibuat, fitur akan otomatis disalin dari template default kategori tersebut.
            </p>
        </div>
        <div>
            <a href="{{ route('admin.system.templates.create') }}"
               class="inline-flex items-center gap-2 rounded-xl bg-primary px-4 py-2.5 text-sm font-semibold text-white shadow-sm transition hover:bg-primary-hover">
                <i class="fa-solid fa-plus"></i>
                Buat Template Baru
            </a>
        </div>
    </div>

    {{-- Flash Notifications --}}
    @if(session('success'))
    <div class="flex items-center gap-3 rounded-2xl border border-green-200 bg-green-50 px-5 py-4 text-sm text-green-800 shadow-sm">
        <i class="fa-solid fa-circle-check text-green-500"></i>
        <span>{{ session('success') }}</span>
    </div>
    @endif

    {{-- Templates List --}}
    <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
        @forelse($templates as $template)
        <div class="rounded-3xl bg-white p-6 shadow-sm border border-slate-200 flex flex-col justify-between">
            <div>
                <div class="flex items-start justify-between gap-2 mb-3">
                    <div>
                        <div class="inline-block bg-blue-50 text-primary text-xs font-bold px-2.5 py-1 rounded-md mb-2">
                            Kategori: {{ $template->category?->name ?? 'Tidak Terkait' }}
                        </div>
                        <h2 class="text-lg font-bold text-slate-900 leading-tight">
                            {{ $template->name }}
                        </h2>
                    </div>
                    @if($template->is_default)
                        <span class="inline-flex items-center gap-1.5 px-3 py-1 rounded-full text-xs font-semibold bg-emerald-50 text-emerald-700 border border-emerald-200 whitespace-nowrap">
                            <i class="fa-solid fa-star text-amber-500 text-xs"></i>
                            Template Default
                        </span>
                    @endif
                </div>

                <p class="text-sm text-slate-500 mb-4">
                    {{ $template->description ?: 'Tidak ada deskripsi.' }}
                </p>

                {{-- Feature Badges --}}
                <div class="border-t border-slate-100 pt-4 mb-4">
                    <p class="text-xs font-semibold uppercase tracking-wider text-slate-400 mb-2">
                        Fitur Aktif ({{ $template->templateFeatures->where('is_enabled', true)->count() }} / {{ $template->templateFeatures->count() }}):
                    </p>
                    <div class="flex flex-wrap gap-1.5 max-h-32 overflow-y-auto">
                        @foreach($template->templateFeatures as $tmplFeat)
                            @if($tmplFeat->is_enabled)
                                <span class="inline-block bg-emerald-50 text-emerald-700 border border-emerald-200 text-xs px-2 py-0.5 rounded-md font-medium">
                                    {{ $tmplFeat->feature?->name ?? $tmplFeat->feature_id }}
                                </span>
                            @else
                                <span class="inline-block bg-slate-50 text-slate-400 border border-slate-200 text-xs px-2 py-0.5 rounded-md line-through">
                                    {{ $tmplFeat->feature?->name ?? $tmplFeat->feature_id }}
                                </span>
                            @endif
                        @endforeach
                    </div>
                </div>
            </div>

            <div class="border-t border-slate-100 pt-4 flex items-center justify-between gap-3">
                @if(!$template->is_default)
                <form method="POST" action="{{ route('admin.system.templates.default', $template->id) }}">
                    @csrf
                    <button type="submit" class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-xl border border-slate-200 text-xs font-semibold text-slate-700 hover:bg-slate-50 transition">
                        <i class="fa-regular fa-star text-amber-500"></i>
                        Jadikan Default
                    </button>
                </form>
                @else
                <span class="text-xs text-slate-400 italic">Digunakan otomatis untuk organisasi baru</span>
                @endif

                <a href="{{ route('admin.system.templates.edit', $template->id) }}"
                   class="inline-flex items-center gap-1.5 px-4 py-2 rounded-xl bg-slate-100 text-slate-800 text-xs font-bold hover:bg-primary hover:text-white transition">
                    <i class="fa-solid fa-sliders"></i>
                    Atur & Edit Fitur
                </a>
            </div>
        </div>
        @empty
        <div class="col-span-full rounded-3xl bg-white p-12 text-center border border-slate-200">
            <i class="fa-solid fa-layer-group text-slate-300 text-4xl mb-3"></i>
            <h3 class="text-base font-bold text-slate-800">Belum ada template yang dibuat</h3>
            <p class="text-sm text-slate-500 mt-1">Buat template pertama untuk menentukan konfigurasi fitur awal suatu kategori.</p>
        </div>
        @endforelse
    </div>

</div>
@endsection
