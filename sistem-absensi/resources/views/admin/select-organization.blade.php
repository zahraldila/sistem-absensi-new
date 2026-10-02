@extends('layouts.admin.app')

@section('title', 'Organisasi — Super Admin')

@section('content')
<div class="space-y-6" x-data="{
    featureModalOpen: false,
    currentOrg: { id: null, name: '' },
    selectedFeatures: {},
    openFeatureModal(org, featuresMap) {
        this.currentOrg = { id: org.organization_id, name: org.nama_organisasi };
        this.selectedFeatures = Object.assign({}, featuresMap);
        this.featureModalOpen = true;
    }
}">

    {{-- Header Section --}}
    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
        <div>
            <h1 class="text-2xl sm:text-3xl font-bold text-slate-900 leading-tight">
                Organisasi
            </h1>
            <p class="mt-1 text-sm text-slate-500">
                Kelola seluruh organisasi yang terdaftar dalam sistem.
            </p>
        </div>
        <div class="flex items-center gap-2.5">
            <a href="{{ route('admin.organization.create') }}"
               class="inline-flex items-center gap-2 rounded-xl bg-primary px-4 py-2.5 text-sm font-semibold text-white shadow-sm transition hover:bg-primary-hover">
                <i class="fa-solid fa-plus text-xs"></i>
                Tambah Organisasi
            </a>
        </div>
    </div>

    {{-- Flash Notifications --}}
    @if(session('success'))
    <div class="flex items-center gap-3 rounded-2xl border border-green-200 bg-green-50 px-5 py-4 text-sm text-green-800 shadow-sm">
        <i class="fa-solid fa-circle-check text-green-500 flex-shrink-0"></i>
        <span>{{ session('success') }}</span>
    </div>
    @endif

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

    {{-- Table Organisasi --}}
    <div class="rounded-3xl bg-white shadow-sm border border-slate-200 overflow-hidden">
        <div class="overflow-x-auto">
            <table class="w-full text-left text-sm text-slate-600">
                <thead class="bg-slate-50 text-xs font-semibold uppercase tracking-wider text-slate-500 border-b border-slate-200">
                    <tr>
                        <th class="px-6 py-4">Nama Organisasi</th>
                        <th class="px-6 py-4">Kategori</th>
                        <th class="px-6 py-4">Alamat</th>
                        <th class="px-6 py-4 text-center">Status</th>
                        <th class="px-6 py-4 text-right">Aksi</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100 bg-white">
                    @forelse($organizations as $org)
                    @php
                        $orgFeatureMap = $org->organizationFeatures->pluck('is_enabled', 'feature_id')->toArray();
                    @endphp
                    <tr class="hover:bg-slate-50/70 transition">
                        {{-- Nama Organisasi --}}
                        <td class="px-6 py-4">
                            <div class="flex items-center gap-3">
                                <div class="h-9 w-9 rounded-xl bg-blue-50 text-primary flex items-center justify-center font-bold text-xs flex-shrink-0 border border-blue-100">
                                    {{ getInitials($org->nama_organisasi) ?: substr($org->nama_organisasi, 0, 2) }}
                                </div>
                                <div class="min-w-0">
                                    <div class="font-bold text-slate-900 leading-tight">
                                        {{ $org->nama_organisasi }}
                                    </div>
                                    <div class="mt-0.5">
                                        <span class="inline-block text-[11px] font-mono font-semibold text-primary bg-blue-50 px-2 py-0.5 rounded">
                                            {{ $org->kode_organisasi }}
                                        </span>
                                    </div>
                                </div>
                            </div>
                        </td>

                        {{-- Kategori --}}
                        <td class="px-6 py-4 whitespace-nowrap">
                            @if($org->category)
                                <span class="inline-flex items-center px-2.5 py-1 rounded-md text-xs font-medium bg-slate-100 text-slate-700">
                                    {{ $org->category->name }}
                                </span>
                            @else
                                <span class="text-xs text-slate-400 italic">
                                    Tanpa Kategori
                                </span>
                            @endif
                        </td>

                        {{-- Alamat --}}
                        <td class="px-6 py-4 text-slate-600 max-w-sm">
                            <span class="line-clamp-2 text-xs sm:text-sm" title="{{ $org->alamat }}">
                                {{ $org->alamat ?: 'Tidak ada alamat' }}
                            </span>
                        </td>

                        {{-- Status --}}
                        <td class="px-6 py-4 text-center whitespace-nowrap">
                            @if(strtolower($org->status) === 'active')
                                <span class="inline-flex items-center gap-1.5 px-3 py-1 rounded-full text-xs font-semibold bg-emerald-50 text-emerald-700 border border-emerald-200">
                                    <span class="h-1.5 w-1.5 rounded-full bg-emerald-500"></span>
                                    Aktif
                                </span>
                            @else
                                <span class="inline-flex items-center gap-1.5 px-3 py-1 rounded-full text-xs font-semibold bg-slate-100 text-slate-600 border border-slate-200">
                                    <span class="h-1.5 w-1.5 rounded-full bg-slate-400"></span>
                                    Nonaktif
                                </span>
                            @endif
                        </td>

                        {{-- Aksi --}}
                        <td class="px-6 py-4 text-right space-x-2 whitespace-nowrap">
                            {{-- Tombol Masuk --}}
                            @if(strtolower($org->status) === 'active')
                                <form method="POST" action="{{ route('admin.organization.store') }}" class="inline-block">
                                    @csrf
                                    <input type="hidden" name="organization_id" value="{{ $org->organization_id }}">
                                    <button type="submit"
                                            class="inline-flex items-center gap-1.5 px-3.5 py-1.5 rounded-xl bg-primary text-xs font-semibold text-white hover:bg-primary-hover shadow-sm transition">
                                        <span>Masuk</span>
                                        <i class="fa-solid fa-arrow-right text-[10px]"></i>
                                    </button>
                                </form>
                            @else
                                <button type="button"
                                        disabled
                                        title="Organisasi nonaktif tidak dapat dimasuki"
                                        class="inline-flex items-center gap-1.5 px-3.5 py-1.5 rounded-xl bg-slate-100 text-xs font-semibold text-slate-400 cursor-not-allowed">
                                    <span>Masuk</span>
                                    <i class="fa-solid fa-arrow-right text-[10px]"></i>
                                </button>
                            @endif

                            {{-- Tombol Kelola Fitur (Membuka Modal) --}}
                            <button type="button"
                                    @click="openFeatureModal({{ json_encode(['organization_id' => $org->organization_id, 'nama_organisasi' => $org->nama_organisasi]) }}, {{ json_encode($orgFeatureMap) }})"
                                    class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-xl border border-slate-200 bg-white text-xs font-semibold text-slate-700 hover:bg-slate-50 hover:border-slate-300 transition">
                                <i class="fa-solid fa-sliders text-xs text-slate-500"></i>
                                <span>Kelola Fitur</span>
                            </button>
                        </td>
                    </tr>
                    @empty
                    <tr>
                        <td colspan="5" class="px-6 py-12 text-center text-slate-400">
                            <i class="fa-solid fa-building text-3xl mb-2 text-slate-300 block"></i>
                            Belum ada organisasi yang terdaftar dalam sistem.
                        </td>
                    </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    {{-- MODAL KELOLA FITUR ORGANISASI --}}
    <div x-show="featureModalOpen"
         x-cloak
         class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-slate-900/50 backdrop-blur-sm"
         aria-modal="true"
         role="dialog">

        <div @click.away="featureModalOpen = false"
             x-show="featureModalOpen"
             x-transition:enter="transition ease-out duration-200"
             x-transition:enter-start="opacity-0 scale-95"
             x-transition:enter-end="opacity-100 scale-100"
             x-transition:leave="transition ease-in duration-150"
             x-transition:leave-start="opacity-100 scale-100"
             x-transition:leave-end="opacity-0 scale-95"
             class="w-full max-w-xl rounded-3xl bg-white p-6 sm:p-7 shadow-2xl border border-slate-200 flex flex-col max-h-[90vh]">

            {{-- Modal Header --}}
            <div class="flex items-start justify-between pb-4 border-b border-slate-100">
                <div>
                    <h3 class="text-lg font-bold text-slate-900">
                        Kelola Fitur Organisasi
                    </h3>
                    <p class="text-sm font-semibold text-primary mt-0.5" x-text="currentOrg.name"></p>
                    <p class="text-xs text-slate-500 mt-1">
                        Pilih fitur yang dapat digunakan oleh organisasi ini.
                    </p>
                </div>
                <button type="button"
                        @click="featureModalOpen = false"
                        class="text-slate-400 hover:text-slate-600 transition p-1">
                    <i class="fa-solid fa-xmark text-lg"></i>
                </button>
            </div>

            {{-- Modal Form --}}
            <form :action="'{{ url('admin/organizations') }}/' + currentOrg.id + '/features'"
                  method="POST"
                  class="flex-1 overflow-y-auto mt-4 space-y-4 pr-1">
                @csrf
                @method('PUT')
                <input type="hidden" name="redirect_to" value="{{ route('admin.organization.select') }}">

                {{-- Features List Grid --}}
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-2.5">
                    @foreach($allFeatures as $feature)
                    <label class="flex items-start gap-3 p-3 rounded-2xl border border-slate-200 hover:border-blue-300 hover:bg-blue-50/30 cursor-pointer transition select-none">
                        <input type="checkbox"
                               name="features[{{ $feature->id }}]"
                               value="1"
                               :checked="Boolean(selectedFeatures[{{ $feature->id }}])"
                               @change="selectedFeatures[{{ $feature->id }}] = $event.target.checked"
                               class="mt-1 h-4 w-4 rounded border-slate-300 text-primary focus:ring-primary">
                        <div class="min-w-0">
                            <span class="text-sm font-semibold text-slate-800 block">
                                {{ $feature->name }}
                            </span>
                            @if($feature->description)
                            <span class="text-xs text-slate-500 block line-clamp-2 mt-0.5">
                                {{ $feature->description }}
                            </span>
                            @endif
                        </div>
                    </label>
                    @endforeach
                </div>

                {{-- Modal Footer Actions --}}
                <div class="pt-4 flex items-center justify-end gap-3 border-t border-slate-100 sticky bottom-0 bg-white">
                    <button type="button"
                            @click="featureModalOpen = false"
                            class="px-4 py-2.5 rounded-xl border border-slate-200 text-sm font-semibold text-slate-600 hover:bg-slate-100 transition">
                        Batal
                    </button>
                    <button type="submit"
                            class="px-5 py-2.5 rounded-xl bg-primary text-sm font-semibold text-white hover:bg-primary-hover shadow-sm transition">
                        Simpan
                    </button>
                </div>
            </form>

        </div>
    </div>

</div>
@endsection
