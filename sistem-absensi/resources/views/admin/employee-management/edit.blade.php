@extends('layouts.admin.app')

@section('content')
<div class="p-6">
    <div class="mb-6">
        <h1 class="text-2xl font-semibold text-gray-900">Edit Akun {{ \App\Helpers\OrganizationHelper::term('member') }}</h1>
        <p class="text-sm text-gray-600">Perbarui detail akun dan data {{ strtolower(\App\Helpers\OrganizationHelper::term('member')) }}.</p>
    </div>

    <div class="rounded-xl border border-gray-200 bg-white p-6 shadow-sm">
        <form method="POST" action="{{ route('admin.employee-management.update', $employee->pegawai_id) }}" enctype="multipart/form-data" class="grid gap-4 md:grid-cols-2">
            @csrf
            @method('PUT')
            <div>
                <label class="mb-1 block text-sm font-medium text-gray-700">Nama {{ \App\Helpers\OrganizationHelper::term('member') }}</label>
                <input type="text" name="nama_pegawai" value="{{ old('nama_pegawai', $employee->nama_pegawai) }}" required class="w-full rounded-md border border-gray-300 px-3 py-2 text-sm focus:border-indigo-500 focus:outline-none">
            </div>
            <div>
                <label class="mb-1 block text-sm font-medium text-gray-700">{{ \App\Helpers\OrganizationHelper::term('member_id') }}</label>
                <input type="text" name="nip" value="{{ old('nip', $employee->nip) }}" class="w-full rounded-md border border-gray-300 px-3 py-2 text-sm focus:border-indigo-500 focus:outline-none">
            </div>
            <div>
                <label class="mb-1 block text-sm font-medium text-gray-700">NFC ID</label>
                <input type="text" name="nfc_id" value="{{ old('nfc_id', $employee->nfc->nfc_serial_number ?? '') }}" class="w-full rounded-md border border-gray-300 px-3 py-2 text-sm focus:border-indigo-500 focus:outline-none">
                @error('nfc_id')<p class="mt-1 text-xs text-red-600">{{ $message }}</p>@enderror
            </div>
            <div>
                <label class="mb-1 block text-sm font-medium text-gray-700">Email</label>
                <input type="email" name="email" value="{{ old('email', $employee->email) }}" class="w-full rounded-md border border-gray-300 px-3 py-2 text-sm focus:border-indigo-500 focus:outline-none">
            </div>
            <div>
                <label class="mb-1 block text-sm font-medium text-gray-700">No. Telepon</label>
                <input type="text" name="no_handphone" value="{{ old('no_handphone', $employee->no_handphone) }}" placeholder="Contoh : 081234567890" class="w-full rounded-md border border-gray-300 px-3 py-2 text-sm focus:border-indigo-500 focus:outline-none">
                @error('no_handphone')<p class="mt-1 text-xs text-red-600">{{ $message }}</p>@enderror
            </div>
            <div>
                <label class="mb-1 block text-sm font-medium text-gray-700">Username</label>
                <input type="text" name="username" value="{{ old('username', $employee->akun->username ?? '') }}" class="w-full rounded-md border border-gray-300 px-3 py-2 text-sm focus:border-indigo-500 focus:outline-none">
            </div>
            @hasfeature('division')
            <div>
                <label class="mb-1 block text-sm font-medium text-gray-700">{{ \App\Helpers\OrganizationHelper::term('division') }}</label>
                <select name="divisi_id" class="w-full rounded-md border border-gray-300 px-3 py-2 text-sm focus:border-indigo-500 focus:outline-none">
                    <option value="">Pilih {{ \App\Helpers\OrganizationHelper::term('division') }}</option>
                    @foreach ($filters['divisions'] ?? [] as $division)
                        <option value="{{ $division->divisi_id }}" {{ old('divisi_id', $employee->divisi_id) == $division->divisi_id ? 'selected' : '' }}>{{ $division->nama_divisi }}</option>
                    @endforeach
                </select>
                @error('divisi_id')<p class="mt-1 text-xs text-red-600">{{ $message }}</p>@enderror
            </div>
            @endhasfeature
            @hasfeature('position')
            <div>
                <label class="mb-1 block text-sm font-medium text-gray-700">{{ \App\Helpers\OrganizationHelper::term('position') }}</label>
                <select name="jabatan_id" class="w-full rounded-md border border-gray-300 px-3 py-2 text-sm focus:border-indigo-500 focus:outline-none">
                    <option value="">Pilih {{ \App\Helpers\OrganizationHelper::term('position') }}</option>
                    @foreach ($filters['roles'] ?? [] as $role)
                        <option value="{{ $role->jabatan_id }}" {{ old('jabatan_id', $employee->jabatan_id) == $role->jabatan_id ? 'selected' : '' }}>{{ $role->nama_jabatan }}</option>
                    @endforeach
                </select>
                @error('jabatan_id')<p class="mt-1 text-xs text-red-600">{{ $message }}</p>@enderror
            </div>
            @endhasfeature
            <div>
                <label class="mb-1 block text-sm font-medium text-gray-700">Role Akses</label>
                @php
                    $currentRole = old('role', $employee->akun->roleAkses->nama_role ?? 'Anggota');
                    if (strtolower($currentRole) === 'admin') $currentRole = 'Admin';
                    elseif (strtolower($currentRole) === 'pegawai' || strtolower($currentRole) === 'karyawan') $currentRole = 'Anggota';
                    elseif (strtolower($currentRole) === 'hr' || strtolower($currentRole) === 'hrd' || strtolower($currentRole) === 'hr / hrd') $currentRole = 'HR / HRD';
                    elseif (strtolower($currentRole) === 'direktur') $currentRole = 'Direktur';
                @endphp
                <select name="role" class="w-full rounded-md border border-gray-300 px-3 py-2 text-sm focus:border-indigo-500 focus:outline-none">
                    <option value="">Pilih Role Akses</option>
                    @if (!empty($filters['master_roles']) && count($filters['master_roles']) > 0)
                        @foreach ($filters['master_roles'] as $mr)
                            <option value="{{ $mr->nama_role }}" {{ $currentRole == $mr->nama_role ? 'selected' : '' }}>{{ $mr->nama_role }}</option>
                        @endforeach
                    @else
                        <option value="HR / HRD" {{ $currentRole == 'HR / HRD' ? 'selected' : '' }}>HR / HRD</option>
                        <option value="Direktur" {{ $currentRole == 'Direktur' ? 'selected' : '' }}>Direktur</option>
                        <option value="Anggota" {{ $currentRole == 'Anggota' ? 'selected' : '' }}>{{ \App\Helpers\OrganizationHelper::term('member') }}</option>
                    @endif
                </select>
                @error('role')<p class="mt-1 text-xs text-red-600">{{ $message }}</p>@enderror
            </div>
            <div>
                <label class="mb-1 block text-sm font-medium text-gray-700">Password</label>
                <input type="password" name="password" class="w-full rounded-md border border-gray-300 px-3 py-2 text-sm focus:border-indigo-500 focus:outline-none">
            </div>
            <div>
                <label class="mb-1 block text-sm font-medium text-gray-700">Konfirmasi Password</label>
                <input type="password" name="password_confirmation" class="w-full rounded-md border border-gray-300 px-3 py-2 text-sm focus:border-indigo-500 focus:outline-none">
            </div>
            <div>
                <label class="mb-1 block text-sm font-medium text-gray-700">Status</label>
                <select name="status" class="w-full rounded-md border border-gray-300 px-3 py-2 text-sm focus:border-indigo-500 focus:outline-none">
                    <option value="Aktif" {{ old('status', $employee->status) == 'Aktif' ? 'selected' : '' }}>Aktif</option>
                    <option value="Tidak Aktif" {{ old('status', $employee->status) == 'Tidak Aktif' ? 'selected' : '' }}>Tidak Aktif</option>
                </select>
                @error('status')<p class="mt-1 text-xs text-red-600">{{ $message }}</p>@enderror
            </div>
            <div x-data="{ photoError: '' }">
                <div class="flex items-center justify-between mb-1">
                    <label class="block text-sm font-medium text-gray-700">Foto Profil (opsional)</label>
                    <span class="text-[11px] text-gray-400">Maks. 2MB (JPG, JPEG, PNG)</span>
                </div>
                <input type="file" name="foto_profile" accept=".jpg,.jpeg,.png,image/jpeg,image/png"
                       @change="
                           photoError = '';
                           const file = $event.target.files[0];
                           if (file) {
                               if (file.size > 2 * 1024 * 1024) {
                                   photoError = 'Ukuran foto melebihi batas maksimal 2MB (' + (file.size / (1024 * 1024)).toFixed(2) + ' MB). Silakan pilih foto lain.';
                                   $event.target.value = '';
                                   return;
                               }
                               const validTypes = ['image/jpeg', 'image/png', 'image/jpg'];
                               if (!validTypes.includes(file.type) && !file.name.match(/\.(jpg|jpeg|png)$/i)) {
                                   photoError = 'Format file tidak didukung. Hanya file JPG, JPEG, atau PNG yang diperbolehkan.';
                                   $event.target.value = '';
                                   return;
                               }
                           }
                       "
                       class="w-full text-sm rounded-md border border-gray-300 px-3 py-1.5 focus:border-indigo-500 focus:outline-none file:mr-3 file:py-1 file:px-3 file:rounded-md file:border-0 file:text-xs file:font-semibold file:bg-indigo-50 file:text-indigo-700 hover:file:bg-indigo-100">
                @if(!empty($employee->foto_profile))
                    <p class="mt-1 text-xs text-gray-500">Foto saat ini: <a href="{{ supabase_public_url($employee->foto_profile) }}" target="_blank" class="text-indigo-600 underline">Lihat Foto</a></p>
                @endif
                <p x-show="photoError" x-text="photoError" x-cloak class="mt-1 text-xs text-red-600 font-medium"></p>
                @error('foto_profile')<p class="mt-1 text-xs text-red-600 font-medium">{{ $message }}</p>@enderror
            </div>
            <div class="md:col-span-2 flex gap-3">
                <button type="submit" class="rounded-md bg-indigo-600 px-4 py-2 text-sm font-medium text-white hover:bg-indigo-700">Perbarui</button>
                <a href="{{ route('admin.employee-management.index') }}" class="rounded-md border border-gray-300 px-4 py-2 text-sm font-medium text-gray-700 hover:bg-gray-50">Batal</a>
            </div>
        </form>
    </div>
</div>
@endsection
