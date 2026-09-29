<div x-show="exportModalOpen"
    x-cloak
    class="fixed inset-0 z-50 flex items-center justify-center bg-slate-900/40 backdrop-blur-sm p-4"
    @click.self="closeExport()">
    <div class="relative w-full max-w-2xl max-h-[90vh] overflow-y-auto rounded-3xl sm:rounded-[28px] bg-white shadow-2xl" @click.stop>
        <form x-ref="exportForm" method="POST" action="{{ route('admin.employee-management.export') }}" @submit.prevent="submitExport($event)">
            @csrf
            <div class="flex items-center justify-between border-b border-slate-200 px-5 sm:px-8 py-5 sm:py-6">
                <div>
                    <h2 class="text-base sm:text-lg font-bold text-slate-900">Export Data Akun</h2>
                    <p class="mt-1 text-xs sm:text-sm text-slate-500">Pilih format dan filter untuk unduh data akun anggota.</p>
                </div>
                <button type="button" class="flex items-center justify-center rounded-full p-2 text-slate-400 transition hover:bg-slate-100 hover:text-slate-600" @click="closeExport()" aria-label="Tutup modal">
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                    </svg>
                </button>
            </div>

            <div class="space-y-5 sm:space-y-6 px-5 sm:px-8 py-5 sm:py-6">
                <div class="grid gap-5 sm:gap-6 grid-cols-1 md:grid-cols-2">
                    <div class="space-y-3 sm:space-y-4">
                        <p class="text-xs sm:text-sm font-semibold text-slate-900">Format Export</p>
                        <div class="space-y-3">
                            <label class="flex cursor-pointer items-start gap-3 rounded-2xl border border-slate-200 bg-white p-3.5 sm:p-4 transition hover:border-slate-300 hover:bg-slate-50">
                                <input type="radio" name="format" value="xlsx" class="mt-1 h-4 w-4 text-primary focus:ring-primary" checked />
                                <div class="flex-1">
                                    <p class="text-sm font-medium text-slate-900">Excel (.xlsx)</p>
                                    <p class="text-xs text-slate-500">Unduh file Excel dengan data akun anggota.</p>
                                </div>
                            </label>

                            <label class="flex cursor-pointer items-start gap-3 rounded-2xl border border-slate-200 bg-white p-3.5 sm:p-4 transition hover:border-slate-300 hover:bg-slate-50">
                                <input type="radio" name="format" value="csv" class="mt-1 h-4 w-4 text-primary focus:ring-primary" />
                                <div class="flex-1">
                                    <p class="text-sm font-medium text-slate-900">CSV</p>
                                    <p class="text-xs text-slate-500">Unduh file CSV yang mudah diolah.</p>
                                </div>
                            </label>

                            <label class="flex cursor-pointer items-start gap-3 rounded-2xl border border-slate-200 bg-white p-3.5 sm:p-4 transition hover:border-slate-300 hover:bg-slate-50">
                                <input type="radio" name="format" value="pdf" class="mt-1 h-4 w-4 text-primary focus:ring-primary" />
                                <div class="flex-1">
                                    <p class="text-sm font-medium text-slate-900">PDF</p>
                                    <p class="text-xs text-slate-500">Unduh file PDF yang siap dicetak.</p>
                                </div>
                            </label>
                        </div>
                    </div>

                    <div class="space-y-3 sm:space-y-4">
                        <p class="text-xs sm:text-sm font-semibold text-slate-900">Filter Export</p>
                        <div class="grid gap-3 sm:gap-4">
                            <div>
                                <label class="mb-1 block text-xs sm:text-sm font-medium text-slate-700">Status</label>
                                <select name="status" class="w-full rounded-2xl border border-slate-300 bg-white px-4 py-3 text-sm text-slate-900 focus:border-primary focus:ring-primary">
                                    <option value="">Semua</option>
                                    <option value="Aktif">Aktif</option>
                                    <option value="Nonaktif">Nonaktif</option>
                                </select>
                            </div>
                            <div>
                                <label class="mb-1 block text-xs sm:text-sm font-medium text-slate-700">Divisi</label>
                                <select name="divisi_id" class="w-full rounded-2xl border border-slate-300 bg-white px-4 py-3 text-sm text-slate-900 focus:border-primary focus:ring-primary">
                                    <option value="">Semua</option>
                                    @foreach($filters['divisions'] as $div)
                                        <option value="{{ $div->divisi_id }}">{{ $div->nama_divisi }}</option>
                                    @endforeach
                                </select>
                            </div>
                            <div>
                                <label class="mb-1 block text-xs sm:text-sm font-medium text-slate-700">Jabatan</label>
                                <select name="jabatan_id" class="w-full rounded-2xl border border-slate-300 bg-white px-4 py-3 text-sm text-slate-900 focus:border-primary focus:ring-primary">
                                    <option value="">Semua</option>
                                    @foreach($filters['roles'] as $r)
                                        <option value="{{ $r->jabatan_id }}">{{ $r->nama_jabatan }}</option>
                                    @endforeach
                                </select>
                            </div>
                        </div>
                    </div>
                </div>

                <template x-if="exportErrorMessage">
                    <div class="rounded-xl border border-red-200 bg-red-50 px-4 py-3 text-xs sm:text-sm text-red-700" x-text="exportErrorMessage"></div>
                </template>

                <div class="flex flex-col-reverse gap-3 border-t border-slate-200 pt-5 sm:pt-6 sm:flex-row sm:justify-end">
                    <button type="button" @click="closeExport()" class="w-full sm:w-auto rounded-2xl border border-slate-200 bg-white px-6 py-3 text-xs sm:text-sm font-semibold text-slate-700 transition hover:bg-slate-50">Batal</button>
                    <button type="submit" x-bind:disabled="exportIsLoading" class="w-full sm:w-auto rounded-2xl bg-primary px-6 py-3 text-xs sm:text-sm font-semibold text-white transition hover:bg-primary-hover shadow-sm" x-text="exportIsLoading ? 'Menyiapkan...' : 'Unduh'"></button>
                </div>
            </div>
        </form>
    </div>
</div>