@extends('layouts.admin.app')

@section('title', 'Katalog Fitur Global — Sistem Multi-Organisasi')

@section('content')
<div class="space-y-6" x-data="{ createModalOpen: false, editModalOpen: false, editFeature: {} }">

    {{-- System Navigation Tabs --}}
    @include('admin.system._nav')

    {{-- Header --}}
    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
        <div>
            <h1 class="text-2xl sm:text-3xl font-bold text-slate-900 leading-tight">
                Katalog Fitur Global
            </h1>
            <p class="mt-1 text-sm text-slate-500">
                Daftar kapabilitas/kemampuan sistem yang dapat diaktifkan atau dinonaktifkan pada tiap organisasi secara independen.
            </p>
        </div>
        <div>
            <button type="button" @click="createModalOpen = true"
                    class="inline-flex items-center gap-2 rounded-xl bg-primary px-4 py-2.5 text-sm font-semibold text-white shadow-sm transition hover:bg-primary-hover">
                <i class="fa-solid fa-plus"></i>
                Tambah Fitur Baru
            </button>
        </div>
    </div>

    {{-- Flash Notifications --}}
    @if(session('success'))
    <div class="flex items-center gap-3 rounded-2xl border border-green-200 bg-green-50 px-5 py-4 text-sm text-green-800 shadow-sm">
        <i class="fa-solid fa-circle-check text-green-500"></i>
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

    {{-- Features List Grouped by Category Group --}}
    <div class="space-y-6">
        @foreach($features as $group => $items)
        <div class="rounded-3xl bg-white p-6 shadow-sm border border-slate-200 overflow-hidden">
            <div class="flex items-center gap-3 pb-4 mb-4 border-b border-slate-100">
                <span class="inline-flex h-8 w-8 items-center justify-center rounded-xl bg-blue-50 text-primary font-bold text-sm uppercase">
                    {{ substr($group, 0, 1) }}
                </span>
                <div>
                    <h2 class="text-base font-bold text-slate-800 capitalize">
                        Grup: {{ $group }}
                    </h2>
                    <p class="text-xs text-slate-400">Total: {{ $items->count() }} kapabilitas</p>
                </div>
            </div>

            <div class="overflow-x-auto">
                <table class="w-full text-left text-sm text-slate-600">
                    <thead class="bg-slate-50 text-xs font-semibold uppercase tracking-wider text-slate-500 border-b border-slate-200">
                        <tr>
                            <th class="px-6 py-3">Key Fitur</th>
                            <th class="px-6 py-3">Nama Fitur</th>
                            <th class="px-6 py-3">Deskripsi</th>
                            <th class="px-6 py-3 text-center">Status</th>
                            <th class="px-6 py-3 text-right">Aksi</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                        @foreach($items as $feature)
                        <tr class="hover:bg-slate-50/50 transition">
                            <td class="px-6 py-3 font-mono text-xs font-bold text-primary">
                                {{ $feature->key }}
                            </td>
                            <td class="px-6 py-3 font-semibold text-slate-900">
                                {{ $feature->name }}
                            </td>
                            <td class="px-6 py-3 text-slate-500 max-w-sm truncate" title="{{ $feature->description }}">
                                {{ $feature->description ?: '-' }}
                            </td>
                            <td class="px-6 py-3 text-center">
                                @if($feature->status === 'active')
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
                            <td class="px-6 py-3 text-right space-x-2 whitespace-nowrap">
                                <button type="button"
                                        @click="editFeature = {{ json_encode($feature) }}; editModalOpen = true"
                                        class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-lg border border-slate-200 text-xs font-semibold text-slate-700 hover:bg-slate-100 transition">
                                    <i class="fa-solid fa-pen-to-square text-xs"></i>
                                    Edit
                                </button>

                                <form method="POST" action="{{ route('admin.system.features.toggle', $feature->id) }}" class="inline-block">
                                    @csrf
                                    <button type="submit"
                                            class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-lg border text-xs font-semibold transition {{ $feature->status === 'active' ? 'border-amber-200 text-amber-700 hover:bg-amber-50' : 'border-emerald-200 text-emerald-700 hover:bg-emerald-50' }}"
                                            onclick="return confirm('Ubah status fitur ini?')">
                                        <i class="fa-solid {{ $feature->status === 'active' ? 'fa-ban' : 'fa-check' }} text-xs"></i>
                                        {{ $feature->status === 'active' ? 'Nonaktifkan' : 'Aktifkan' }}
                                    </button>
                                </form>
                            </td>
                        </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>
        @endforeach
    </div>

    {{-- MODAL TAMBAH FITUR --}}
    <div x-show="createModalOpen" x-cloak class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-slate-900/50 backdrop-blur-sm">
        <div @click.away="createModalOpen = false" class="w-full max-w-lg rounded-3xl bg-white p-6 sm:p-8 shadow-xl border border-slate-200">
            <div class="flex items-center justify-between pb-4 border-b border-slate-100">
                <h3 class="text-lg font-bold text-slate-900">Tambah Fitur Global Baru</h3>
                <button type="button" @click="createModalOpen = false" class="text-slate-400 hover:text-slate-600">
                    <i class="fa-solid fa-xmark text-lg"></i>
                </button>
            </div>

            <form method="POST" action="{{ route('admin.system.features.store') }}" class="mt-6 space-y-4">
                @csrf
                <div>
                    <label class="block text-sm font-semibold text-slate-700 mb-1.5">Key Fitur (Identifier Unik) <span class="text-red-500">*</span></label>
                    <input type="text" name="key" required placeholder="Contoh: billing, payroll, notification"
                           class="w-full rounded-xl border border-slate-300 px-4 py-2.5 text-sm font-mono text-slate-800 focus:border-primary focus:ring-1 focus:ring-primary outline-none">
                    <p class="mt-1 text-xs text-slate-400">Digunakan dalam hasFeature('key'). Huruf kecil, angka, strip, garis bawah.</p>
                </div>

                <div>
                    <label class="block text-sm font-semibold text-slate-700 mb-1.5">Nama Fitur <span class="text-red-500">*</span></label>
                    <input type="text" name="name" required placeholder="Contoh: Penggajian & Payroll"
                           class="w-full rounded-xl border border-slate-300 px-4 py-2.5 text-sm text-slate-800 focus:border-primary focus:ring-1 focus:ring-primary outline-none">
                </div>

                <div>
                    <label class="block text-sm font-semibold text-slate-700 mb-1.5">Grup Kategori <span class="text-red-500">*</span></label>
                    <input type="text" name="category_group" required placeholder="Contoh: core, method, corporate, academic, custom"
                           class="w-full rounded-xl border border-slate-300 px-4 py-2.5 text-sm text-slate-800 focus:border-primary focus:ring-1 focus:ring-primary outline-none">
                </div>

                <div>
                    <label class="block text-sm font-semibold text-slate-700 mb-1.5">Deskripsi</label>
                    <textarea name="description" rows="3" placeholder="Jelaskan kegunaan fitur ini..."
                              class="w-full rounded-xl border border-slate-300 px-4 py-2.5 text-sm text-slate-800 focus:border-primary focus:ring-1 focus:ring-primary outline-none"></textarea>
                </div>

                <div>
                    <label class="block text-sm font-semibold text-slate-700 mb-1.5">Status <span class="text-red-500">*</span></label>
                    <select name="status" class="w-full rounded-xl border border-slate-300 px-4 py-2.5 text-sm text-slate-800 focus:border-primary focus:ring-1 focus:ring-primary outline-none bg-white">
                        <option value="active">Aktif</option>
                        <option value="inactive">Nonaktif</option>
                    </select>
                </div>

                <div class="pt-4 flex justify-end gap-3 border-t border-slate-100">
                    <button type="button" @click="createModalOpen = false" class="px-4 py-2 rounded-xl text-sm font-semibold text-slate-600 hover:bg-slate-100 transition">
                        Batal
                    </button>
                    <button type="submit" class="px-5 py-2 rounded-xl text-sm font-semibold bg-primary text-white hover:bg-primary-hover shadow-sm transition">
                        Simpan Fitur
                    </button>
                </div>
            </form>
        </div>
    </div>

    {{-- MODAL EDIT FITUR --}}
    <div x-show="editModalOpen" x-cloak class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-slate-900/50 backdrop-blur-sm">
        <div @click.away="editModalOpen = false" class="w-full max-w-lg rounded-3xl bg-white p-6 sm:p-8 shadow-xl border border-slate-200">
            <div class="flex items-center justify-between pb-4 border-b border-slate-100">
                <h3 class="text-lg font-bold text-slate-900">Edit Fitur Global</h3>
                <button type="button" @click="editModalOpen = false" class="text-slate-400 hover:text-slate-600">
                    <i class="fa-solid fa-xmark text-lg"></i>
                </button>
            </div>

            <form :action="'{{ url('admin/system/features') }}/' + editFeature.id" method="POST" class="mt-6 space-y-4">
                @csrf
                @method('PUT')
                <div>
                    <label class="block text-sm font-semibold text-slate-700 mb-1.5">Key Fitur <span class="text-red-500">*</span></label>
                    <input type="text" name="key" x-model="editFeature.key" required
                           class="w-full rounded-xl border border-slate-300 px-4 py-2.5 text-sm font-mono text-slate-800 focus:border-primary focus:ring-1 focus:ring-primary outline-none">
                </div>

                <div>
                    <label class="block text-sm font-semibold text-slate-700 mb-1.5">Nama Fitur <span class="text-red-500">*</span></label>
                    <input type="text" name="name" x-model="editFeature.name" required
                           class="w-full rounded-xl border border-slate-300 px-4 py-2.5 text-sm text-slate-800 focus:border-primary focus:ring-1 focus:ring-primary outline-none">
                </div>

                <div>
                    <label class="block text-sm font-semibold text-slate-700 mb-1.5">Grup Kategori <span class="text-red-500">*</span></label>
                    <input type="text" name="category_group" x-model="editFeature.category_group" required
                           class="w-full rounded-xl border border-slate-300 px-4 py-2.5 text-sm text-slate-800 focus:border-primary focus:ring-1 focus:ring-primary outline-none">
                </div>

                <div>
                    <label class="block text-sm font-semibold text-slate-700 mb-1.5">Deskripsi</label>
                    <textarea name="description" x-model="editFeature.description" rows="3"
                              class="w-full rounded-xl border border-slate-300 px-4 py-2.5 text-sm text-slate-800 focus:border-primary focus:ring-1 focus:ring-primary outline-none"></textarea>
                </div>

                <div>
                    <label class="block text-sm font-semibold text-slate-700 mb-1.5">Status <span class="text-red-500">*</span></label>
                    <select name="status" x-model="editFeature.status" class="w-full rounded-xl border border-slate-300 px-4 py-2.5 text-sm text-slate-800 focus:border-primary focus:ring-1 focus:ring-primary outline-none bg-white">
                        <option value="active">Aktif</option>
                        <option value="inactive">Nonaktif</option>
                    </select>
                </div>

                <div class="pt-4 flex justify-end gap-3 border-t border-slate-100">
                    <button type="button" @click="editModalOpen = false" class="px-4 py-2 rounded-xl text-sm font-semibold text-slate-600 hover:bg-slate-100 transition">
                        Batal
                    </button>
                    <button type="submit" class="px-5 py-2 rounded-xl text-sm font-semibold bg-primary text-white hover:bg-primary-hover shadow-sm transition">
                        Perbarui Fitur
                    </button>
                </div>
            </form>
        </div>
    </div>

</div>
@endsection
