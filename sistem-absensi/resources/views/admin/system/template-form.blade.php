@extends('layouts.admin.app')

@section('title', ($isEdit ? 'Edit Template: ' . $template->name : 'Buat Template Baru') . ' — Sistem Multi-Organisasi')

@section('content')
<div class="max-w-4xl mx-auto space-y-6">

    {{-- Breadcrumb / Back --}}
    <div>
        <a href="{{ route('admin.system.templates') }}" class="inline-flex items-center gap-2 text-sm font-semibold text-slate-500 hover:text-primary transition">
            <i class="fa-solid fa-arrow-left"></i>
            Kembali ke Daftar Template
        </a>
    </div>

    {{-- Header --}}
    <div>
        <h1 class="text-2xl sm:text-3xl font-bold text-slate-900 leading-tight">
            {{ $isEdit ? 'Edit Template: ' . $template->name : 'Buat Template Kategori Baru' }}
        </h1>
        <p class="mt-1 text-sm text-slate-500">
            Tentukan kategori dan pilih fitur-fitur yang aktif secara default untuk template ini.
        </p>
    </div>

    {{-- Flash Notifications --}}
    @if(session('error') || (isset($errors) && $errors->any()))
    <div class="flex items-center gap-3 rounded-2xl border border-red-200 bg-red-50 px-5 py-4 text-sm text-red-800 shadow-sm">
        <i class="fa-solid fa-circle-exclamation text-red-500 flex-shrink-0"></i>
        <div>
            @if(session('error')) <p>{{ session('error') }}</p> @endif
            @if(isset($errors) && $errors->any())
                @foreach($errors->all() as $err) <p>{{ $err }}</p> @endforeach
            @endif
        </div>
    </div>
    @endif

    {{-- Form --}}
    <form method="POST" action="{{ $isEdit ? route('admin.system.templates.update', $template->id) : route('admin.system.templates.store') }}"
          class="space-y-6">
        @csrf
        @if($isEdit)
            @method('PUT')
        @endif

        {{-- Basic Information Card --}}
        <div class="rounded-3xl bg-white p-6 sm:p-8 shadow-sm border border-slate-200 space-y-4">
            <h2 class="text-base font-bold text-slate-900 border-b border-slate-100 pb-3">
                Informasi Dasar Template
            </h2>

            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                <div>
                    <label class="block text-sm font-semibold text-slate-700 mb-1.5">Nama Template <span class="text-red-500">*</span></label>
                    <input type="text" name="name" value="{{ old('name', $template->name) }}" required placeholder="Contoh: Bimbel Standard, Perusahaan Full"
                           class="w-full rounded-xl border border-slate-300 px-4 py-2.5 text-sm text-slate-800 focus:border-primary focus:ring-1 focus:ring-primary outline-none">
                </div>

                <div>
                    <label class="block text-sm font-semibold text-slate-700 mb-1.5">Kategori Organisasi <span class="text-red-500">*</span></label>
                    <select name="category_id" required class="w-full rounded-xl border border-slate-300 px-4 py-2.5 text-sm text-slate-800 focus:border-primary focus:ring-1 focus:ring-primary outline-none bg-white">
                        <option value="">-- Pilih Kategori --</option>
                        @foreach($categories as $category)
                            <option value="{{ $category->id }}" {{ old('category_id', $template->category_id) == $category->id ? 'selected' : '' }}>
                                {{ $category->name }}
                            </option>
                        @endforeach
                    </select>
                </div>
            </div>

            <div>
                <label class="block text-sm font-semibold text-slate-700 mb-1.5">Deskripsi Template</label>
                <textarea name="description" rows="2" placeholder="Jelaskan peruntukan template ini..."
                          class="w-full rounded-xl border border-slate-300 px-4 py-2.5 text-sm text-slate-800 focus:border-primary focus:ring-1 focus:ring-primary outline-none">{{ old('description', $template->description) }}</textarea>
            </div>

            <div class="pt-2">
                <label class="inline-flex items-center gap-2.5 cursor-pointer">
                    <input type="checkbox" name="is_default" value="1" {{ old('is_default', $template->is_default) ? 'checked' : '' }}
                           class="h-4 w-4 rounded border-slate-300 text-primary focus:ring-primary">
                    <span class="text-sm font-medium text-slate-700">Jadikan template default untuk kategori ini</span>
                </label>
                <p class="text-xs text-slate-400 pl-6">Organisasi baru di kategori ini akan langsung mengadopsi fitur-fitur dari template ini.</p>
            </div>
        </div>

        {{-- Feature Selection Card --}}
        <div class="rounded-3xl bg-white p-6 sm:p-8 shadow-sm border border-slate-200 space-y-6">
            <div class="flex items-center justify-between border-b border-slate-100 pb-3">
                <div>
                    <h2 class="text-base font-bold text-slate-900">
                        Pilihan Fitur Aktif
                    </h2>
                    <p class="text-xs text-slate-500">Centang fitur-fitur yang ingin diaktifkan untuk template ini.</p>
                </div>
            </div>

            @php
                $groupedFeatures = $features->groupBy('category_group');
            @endphp

            <div class="space-y-6">
                @foreach($groupedFeatures as $group => $items)
                <div>
                    <h3 class="text-xs font-bold uppercase tracking-wider text-slate-400 mb-3 flex items-center gap-2">
                        <span class="h-1.5 w-1.5 rounded-full bg-primary"></span>
                        Grup {{ ucfirst($group) }}
                    </h3>
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-3">
                        @foreach($items as $feature)
                        @php
                            $isEnabled = false;
                            if (isset($templateFeaturesMap)) {
                                $isEnabled = !empty($templateFeaturesMap[$feature->id]);
                            } elseif (is_array(old('features'))) {
                                $isEnabled = array_key_exists($feature->id, old('features'));
                            }
                        @endphp
                        <label class="flex items-start gap-3 p-3.5 rounded-2xl border border-slate-200 hover:border-primary/50 transition cursor-pointer bg-slate-50/50">
                            <input type="checkbox" name="features[{{ $feature->id }}]" value="1" {{ $isEnabled ? 'checked' : '' }}
                                   class="h-4 w-4 rounded border-slate-300 text-primary focus:ring-primary mt-0.5">
                            <div class="text-xs">
                                <span class="font-bold text-slate-800">{{ $feature->name }}</span>
                                <span class="ml-1 text-[11px] font-mono text-slate-400">({{ $feature->key }})</span>
                                @if($feature->description)
                                    <p class="text-slate-500 mt-0.5 leading-relaxed">{{ $feature->description }}</p>
                                @endif
                            </div>
                        </label>
                        @endforeach
                    </div>
                </div>
                @endforeach
            </div>
        </div>

        {{-- Actions --}}
        <div class="flex items-center justify-end gap-3 pt-2">
            <a href="{{ route('admin.system.templates') }}" class="px-5 py-2.5 rounded-xl border border-slate-200 text-sm font-semibold text-slate-600 hover:bg-slate-50 transition">
                Batal
            </a>
            <button type="submit" class="px-6 py-2.5 rounded-xl bg-primary text-white text-sm font-bold hover:bg-primary-hover shadow-sm transition">
                <i class="fa-solid fa-save mr-1.5"></i>
                Simpan Template
            </button>
        </div>
    </form>

</div>
@endsection
