<div x-show="modalOpen"
    x-cloak
    class="fixed inset-0 z-50 flex items-center justify-center bg-slate-900/40 backdrop-blur-sm p-3 sm:p-4"
    @click.self="closeModal()">

    <div class="relative w-full max-w-xl max-h-[90vh] flex flex-col overflow-hidden rounded-3xl sm:rounded-[24px] bg-white shadow-2xl ring-1 ring-slate-200"
        @click.stop>

        {{-- Modal Header --}}
        <div class="border-b border-slate-200 px-5 sm:px-6 py-4 flex-shrink-0 bg-white">
            <div class="flex items-start justify-between gap-4">
                <div>
                    <h2 class="text-lg sm:text-xl font-bold text-slate-900" x-text="isEdit ? 'Edit Pegawai' : 'Tambah Pegawai'">Tambah Pegawai</h2>
                    <p class="mt-0.5 text-xs sm:text-sm text-slate-500">Isi informasi pegawai untuk membuat atau mengubah akun</p>
                </div>
                <button type="button" class="rounded-full border border-slate-200 bg-white p-2 text-slate-500 transition hover:bg-slate-100 hover:text-slate-700"
                    @click="closeModal()" aria-label="Tutup modal">
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                    </svg>
                </button>
            </div>
        </div>

        {{-- Modal Body (Scrollable) --}}
        <div class="flex-1 min-h-0 overflow-y-auto px-5 sm:px-6 py-4 space-y-4">
            <form id="employeeForm" novalidate :action="formAction" method="POST" enctype="multipart/form-data" class="space-y-4">
                @csrf
                <input type="hidden" name="form_mode" :value="isEdit ? 'edit' : 'create'">
                <input type="hidden" name="pegawai_id" x-model="form.pegawai_id">
                <input type="hidden" name="foto_profile_existing" x-model="form.foto_profile_existing">
                <template x-if="isEdit">
                    <input type="hidden" name="_method" value="PUT">
                </template>

                {{-- Photo Upload --}}
                <div class="rounded-2xl border transition-all p-3.5 sm:p-4"
                     :class="photoError ? 'border-red-300 bg-red-50/20 ring-1 ring-red-200' : (@error('foto_profile') 'border-red-300 bg-red-50/20' @else 'border-slate-200 bg-slate-50' @enderror)">
                    <label for="photoInput" class="flex cursor-pointer flex-col items-center justify-center gap-2 rounded-2xl bg-white p-3 text-center border border-dashed transition"
                           :class="photoError ? 'border-red-300 hover:bg-red-50/20' : 'border-slate-300 hover:bg-slate-50'">
                        <div class="flex h-[80px] w-[80px] sm:h-[90px] sm:w-[90px] items-center justify-center rounded-2xl text-slate-400 overflow-hidden shadow-inner"
                             :class="photoError ? 'bg-red-50 text-red-400' : 'bg-slate-100 text-slate-400'">
                            <template x-if="!form.photoPreview">
                                <svg xmlns="http://www.w3.org/2000/svg" class="h-8 w-8" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1M8 12a4 4 0 118 0M12 8v8" />
                                </svg>
                            </template>
                            <template x-if="form.photoPreview">
                                <img :src="form.photoPreview" alt="Preview foto" class="h-full w-full object-cover" x-on:error="form.photoPreview = ''" />
                            </template>
                        </div>
                        <div class="space-y-0.5">
                            <p class="text-xs sm:text-sm font-semibold text-slate-900" x-text="form.photoPreview ? 'Ganti Foto' : 'Klik untuk Upload Foto'">Klik untuk Upload Foto</p>
                            <p class="text-[11px] text-slate-500">PNG, JPG maksimal 2MB</p>
                        </div>

                        {{-- Real-time validation warning message --}}
                        <template x-if="photoError">
                            <div class="mt-2 flex items-center justify-center gap-1.5 text-xs text-red-600 font-medium">
                                <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4 flex-shrink-0 text-red-500" viewBox="0 0 20 20" fill="currentColor">
                                    <path fill-rule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7 4a1 1 0 11-2 0 1 1 0 012 0zm-1-9a1 1 0 00-1 1v4a1 1 0 102 0V6a1 1 0 00-1-1z" clip-rule="evenodd" />
                                </svg>
                                <span x-text="photoError"></span>
                            </div>
                        </template>
                    </label>
                    <input id="photoInput" type="file" name="foto_profile" accept="image/png,image/jpeg,image/jpg" @change="previewPhoto($event)" class="hidden">

                    @error('foto_profile')
                        <p x-show="!photoError" class="mt-1.5 text-xs text-red-600 font-medium flex items-center justify-center gap-1.5">
                            <i class="fa-solid fa-circle-exclamation text-xs text-red-500"></i>
                            <span>{{ $message }}</span>
                        </p>
                    @enderror
                </div>

                <div class="space-y-3.5">
                    {{-- Nama Lengkap --}}
                    <div>
                        <label class="mb-1 block text-xs sm:text-sm font-medium text-slate-700">Nama Lengkap <span class="text-red-500">*</span></label>
                        <input type="text" name="nama_pegawai" x-model="form.nama_pegawai" placeholder="Masukkan Nama Lengkap Pegawai"
                            class="w-full rounded-2xl border @error('nama_pegawai') border-red-400 bg-red-50/20 ring-1 ring-red-300 @else border-slate-300 bg-white focus:border-primary focus:ring-1 focus:ring-primary @enderror px-3.5 py-2.5 sm:px-4 sm:py-3 text-xs sm:text-sm text-slate-900 outline-none transition" />
                        @error('nama_pegawai')
                            <p class="mt-1.5 text-xs text-red-600 font-medium flex items-center gap-1.5">
                                <i class="fa-solid fa-circle-exclamation text-xs"></i>
                                <span>{{ $message }}</span>
                            </p>
                        @enderror
                    </div>

                    {{-- NIP & NFC --}}
                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-3.5">
                        <div>
                            <label class="mb-1 block text-xs sm:text-sm font-medium text-slate-700">NIP <span class="text-red-500">*</span></label>
                            <input type="text" name="nip" x-model="form.nip" placeholder="Contoh : 12345678"
                                class="w-full rounded-2xl border @error('nip') border-red-400 bg-red-50/20 ring-1 ring-red-300 @else border-slate-300 bg-white focus:border-primary focus:ring-1 focus:ring-primary @enderror px-3.5 py-2.5 sm:px-4 sm:py-3 text-xs sm:text-sm text-slate-900 outline-none transition" />
                            @error('nip')
                                <p class="mt-1.5 text-xs text-red-600 font-medium flex items-center gap-1.5">
                                    <i class="fa-solid fa-circle-exclamation text-xs"></i>
                                    <span>{{ $message }}</span>
                                </p>
                            @enderror
                        </div>

                        <div>
                            <label class="mb-1 block text-xs sm:text-sm font-medium text-slate-700">Kartu NFC (opsional)</label>
                            <input type="text" name="nfc_id" x-model="form.nfc_id" placeholder="UID NFC Pegawai"
                                class="w-full rounded-2xl border border-slate-300 bg-white px-3.5 py-2.5 sm:px-4 sm:py-3 text-xs sm:text-sm text-slate-900 outline-none transition focus:border-primary focus:ring-1 focus:ring-primary" />
                            @error('nfc_id')
                                <p class="mt-1.5 text-xs text-red-600 font-medium flex items-center gap-1.5">
                                    <i class="fa-solid fa-circle-exclamation text-xs"></i>
                                    <span>{{ $message }}</span>
                                </p>
                            @enderror
                        </div>
                    </div>

                    {{-- Divisi --}}
                    <div>
                        <label class="mb-1 block text-xs sm:text-sm font-medium text-slate-700">Divisi</label>
                        <select name="divisi_id" x-model="form.divisi_id"
                            class="w-full rounded-2xl border border-slate-300 bg-white px-3.5 py-2.5 sm:px-4 sm:py-3 text-xs sm:text-sm text-slate-900 outline-none transition focus:border-primary focus:ring-1 focus:ring-primary">
                            <option value="">Pilih Divisi</option>
                            @foreach ($filters['divisions'] ?? [] as $division)
                                <option value="{{ $division['divisi_id'] }}">{{ $division['nama_divisi'] }}</option>
                            @endforeach
                        </select>
                        <button type="button" @click.prevent="openDivisionModal()"
                            class="mt-1.5 inline-flex items-center gap-1.5 text-xs sm:text-sm font-semibold text-primary hover:underline">
                            <i class="fa-solid fa-plus text-xs"></i>
                            <span>Tambah Divisi Baru</span>
                        </button>
                        <template x-if="divisionSuccess">
                            <p class="mt-1 text-xs text-green-600" x-text="divisionSuccess"></p>
                        </template>
                        @error('divisi_id')
                            <p class="mt-1.5 text-xs text-red-600 font-medium flex items-center gap-1.5">
                                <i class="fa-solid fa-circle-exclamation text-xs"></i>
                                <span>{{ $message }}</span>
                            </p>
                        @enderror
                    </div>

                    {{-- Jabatan --}}
                    <div>
                        <label class="mb-1 block text-xs sm:text-sm font-medium text-slate-700">Jabatan</label>
                        <select name="jabatan_id" x-model="form.jabatan_id"
                            class="w-full rounded-2xl border border-slate-300 bg-white px-3.5 py-2.5 sm:px-4 sm:py-3 text-xs sm:text-sm text-slate-900 outline-none transition focus:border-primary focus:ring-1 focus:ring-primary">
                            <option value="">Pilih Jabatan</option>
                            @foreach ($filters['roles'] ?? [] as $role)
                                <option value="{{ $role['jabatan_id'] }}">{{ $role['nama_jabatan'] }}</option>
                            @endforeach
                        </select>
                        <button type="button" @click.prevent="openRoleModal()"
                            class="mt-1.5 inline-flex items-center gap-1.5 text-xs sm:text-sm font-semibold text-primary hover:underline">
                            <i class="fa-solid fa-plus text-xs"></i>
                            <span>Tambah Jabatan Baru</span>
                        </button>
                        <template x-if="roleSuccess">
                            <p class="mt-1 text-xs text-green-600" x-text="roleSuccess"></p>
                        </template>
                        @error('jabatan_id')
                            <p class="mt-1.5 text-xs text-red-600 font-medium flex items-center gap-1.5">
                                <i class="fa-solid fa-circle-exclamation text-xs"></i>
                                <span>{{ $message }}</span>
                            </p>
                        @enderror
                    </div>

                    {{-- Role Akses --}}
                    <div>
                        <label class="mb-1 block text-xs sm:text-sm font-medium text-slate-700">Role Akses <span class="text-red-500">*</span></label>
                        <select name="role" x-model="form.role"
                            class="w-full rounded-2xl border @error('role') border-red-400 bg-red-50/20 ring-1 ring-red-300 @else border-slate-300 bg-white focus:border-primary focus:ring-1 focus:ring-primary @enderror px-3.5 py-2.5 sm:px-4 sm:py-3 text-xs sm:text-sm text-slate-900 outline-none transition">
                            <option value="">Pilih Role Akses</option>
                            @if (!empty($filters['master_roles']) && count($filters['master_roles']) > 0)
                                @foreach ($filters['master_roles'] as $mr)
                                    <option value="{{ $mr->nama_role }}">{{ $mr->nama_role }}</option>
                                @endforeach
                            @else
                                <option value="HR / HRD">HR / HRD</option>
                                <option value="Direktur">Direktur</option>
                                <option value="Pegawai">Pegawai</option>
                            @endif
                        </select>
                        @error('role')
                            <p class="mt-1.5 text-xs text-red-600 font-medium flex items-center gap-1.5">
                                <i class="fa-solid fa-circle-exclamation text-xs"></i>
                                <span>{{ $message }}</span>
                            </p>
                        @enderror
                    </div>

                    {{-- Email & Username --}}
                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-3.5">
                        <div>
                            <label class="mb-1 block text-xs sm:text-sm font-medium text-slate-700">Email</label>
                            <input type="email" name="email" x-model="form.email" placeholder="admin@spi.co.id"
                                class="w-full rounded-2xl border @error('email') border-red-400 bg-red-50/20 ring-1 ring-red-300 @else border-slate-300 bg-white focus:border-primary focus:ring-1 focus:ring-primary @enderror px-3.5 py-2.5 sm:px-4 sm:py-3 text-xs sm:text-sm text-slate-900 outline-none transition" />
                            @error('email')
                                <p class="mt-1.5 text-xs text-red-600 font-medium flex items-center gap-1.5">
                                    <i class="fa-solid fa-circle-exclamation text-xs"></i>
                                    <span>{{ $message }}</span>
                                </p>
                            @enderror
                        </div>

                        <div>
                            <label class="mb-1 block text-xs sm:text-sm font-medium text-slate-700">Username (opsional)</label>
                            <input type="text" name="username" x-model="form.username" placeholder="jdoe"
                                class="w-full rounded-2xl border @error('username') border-red-400 bg-red-50/20 ring-1 ring-red-300 @else border-slate-300 bg-white focus:border-primary focus:ring-1 focus:ring-primary @enderror px-3.5 py-2.5 sm:px-4 sm:py-3 text-xs sm:text-sm text-slate-900 outline-none transition" />
                            @error('username')
                                <p class="mt-1.5 text-xs text-red-600 font-medium flex items-center gap-1.5">
                                    <i class="fa-solid fa-circle-exclamation text-xs"></i>
                                    <span>{{ $message }}</span>
                                </p>
                            @enderror
                        </div>
                    </div>

                    {{-- No Telepon & Status --}}
                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-3.5">
                        <div>
                            <label class="mb-1 block text-xs sm:text-sm font-medium text-slate-700">No. Telepon</label>
                            <input type="text" name="no_handphone" x-model="form.no_handphone" placeholder="08123456789"
                                class="w-full rounded-2xl border @error('no_handphone') border-red-400 bg-red-50/20 ring-1 ring-red-300 @else border-slate-300 bg-white focus:border-primary focus:ring-1 focus:ring-primary @enderror px-3.5 py-2.5 sm:px-4 sm:py-3 text-xs sm:text-sm text-slate-900 outline-none transition" />
                            @error('no_handphone')
                                <p class="mt-1.5 text-xs text-red-600 font-medium flex items-center gap-1.5">
                                    <i class="fa-solid fa-circle-exclamation text-xs"></i>
                                    <span>{{ $message }}</span>
                                </p>
                            @enderror
                        </div>

                        <div>
                            <label class="mb-1 block text-xs sm:text-sm font-medium text-slate-700">Status Akun</label>
                            <select name="status" x-model="form.status"
                                class="w-full rounded-2xl border border-slate-300 bg-white px-3.5 py-2.5 sm:px-4 sm:py-3 text-xs sm:text-sm text-slate-900 outline-none transition focus:border-primary focus:ring-1 focus:ring-primary">
                                <option value="Aktif">Aktif</option>
                                <option value="Tidak Aktif">Tidak Aktif</option>
                            </select>
                            @error('status')
                                <p class="mt-1.5 text-xs text-red-600 font-medium flex items-center gap-1.5">
                                    <i class="fa-solid fa-circle-exclamation text-xs"></i>
                                    <span>{{ $message }}</span>
                                </p>
                            @enderror
                        </div>
                    </div>

                    {{-- Password & Konfirmasi Password --}}
                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-3.5">
                        <div x-data="{ showPass: false }">
                            <label class="mb-1 block text-xs sm:text-sm font-medium text-slate-700">Password <span class="text-red-500" x-show="!isEdit">*</span></label>
                            <div class="relative">
                                <input :type="showPass ? 'text' : 'password'" name="password" x-model="form.password"
                                    placeholder="Minimal 6 karakter"
                                    class="w-full rounded-2xl border @error('password') border-red-400 bg-red-50/20 ring-1 ring-red-300 @else border-slate-300 bg-white focus:border-primary focus:ring-1 focus:ring-primary @enderror px-3.5 py-2.5 sm:px-4 sm:py-3 pr-10 text-xs sm:text-sm text-slate-900 outline-none transition" />
                                <button type="button" @click="showPass = !showPass"
                                    class="absolute inset-y-0 right-3 inline-flex items-center text-slate-400 transition hover:text-slate-600 focus:outline-none"
                                    aria-label="Toggle password visibility">
                                    <i :class="showPass ? 'fa-regular fa-eye-slash' : 'fa-regular fa-eye'" class="text-sm"></i>
                                </button>
                            </div>
                            @error('password')
                                <p class="mt-1.5 text-xs text-red-600 font-medium flex items-center gap-1.5">
                                    <i class="fa-solid fa-circle-exclamation text-xs"></i>
                                    <span>{{ $message }}</span>
                                </p>
                            @enderror
                        </div>

                        <div x-data="{ showPassConfirm: false }">
                            <label class="mb-1 block text-xs sm:text-sm font-medium text-slate-700">Konfirmasi Password <span class="text-red-500" x-show="!isEdit">*</span></label>
                            <div class="relative">
                                <input :type="showPassConfirm ? 'text' : 'password'" name="password_confirmation" x-model="form.password_confirmation"
                                    placeholder="Ketik ulang password"
                                    class="w-full rounded-2xl border border-slate-300 bg-white px-3.5 py-2.5 sm:px-4 sm:py-3 pr-10 text-xs sm:text-sm text-slate-900 outline-none transition focus:border-primary focus:ring-1 focus:ring-primary" />
                                <button type="button" @click="showPassConfirm = !showPassConfirm"
                                    class="absolute inset-y-0 right-3 inline-flex items-center text-slate-400 transition hover:text-slate-600 focus:outline-none"
                                    aria-label="Toggle konfirmasi password visibility">
                                    <i :class="showPassConfirm ? 'fa-regular fa-eye-slash' : 'fa-regular fa-eye'" class="text-sm"></i>
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
            </form>
        </div>

        {{-- Modal Footer --}}
        <div class="border-t border-slate-200 bg-white px-5 sm:px-6 py-4 flex-shrink-0">
            <div class="grid grid-cols-2 gap-3 sm:flex sm:justify-end sm:items-center">
                <button type="button" @click="closeModal()"
                    class="w-full sm:w-auto inline-flex items-center justify-center rounded-2xl border border-slate-300 bg-white px-5 py-2.5 text-xs sm:text-sm font-semibold text-slate-700 transition hover:bg-slate-50">
                    Batal
                </button>
                <button type="button"
                    x-bind:disabled="isSavingEmployee"
                    @click.prevent="if (isSavingEmployee) return; if (validateForm()) { isSavingEmployee = true; document.getElementById('employeeForm').submit(); } else { isSavingEmployee = false; }"
                    class="w-full sm:w-auto inline-flex items-center justify-center rounded-2xl bg-primary px-6 py-2.5 text-xs sm:text-sm font-semibold text-white transition hover:bg-primary-hover shadow-sm min-w-[100px]"
                    x-text="isSavingEmployee ? 'Menyimpan...' : (isEdit ? 'Simpan Perubahan' : 'Simpan')">Simpan</button>
            </div>
        </div>
    </div>
</div>