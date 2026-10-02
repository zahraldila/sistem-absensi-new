<!DOCTYPE html>
<html lang="id" class="h-full">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Tambah Organisasi Baru | Sistem Absensi</title>
    <!-- FontAwesome 6 CDN for Icons -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.2/css/all.min.css" integrity="sha512-z3gLpd7yknf1YoNbCzqRKc4qyor8gaKU1qmn+CShxbuBusANI9QpRohGBreCFkKxLhei6S9CQXFEbbKuqLg0DA==" crossorigin="anonymous" referrerpolicy="no-referrer" />
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    <style>
        :root {
            @php
                $primaryColor = \App\Models\Setting::get('primary_color', '#123D91');
            @endphp
            --color-primary: {{ $primaryColor }};
            --color-primary-hover: color-mix(in srgb, {{ $primaryColor }} 80%, black);
        }
    </style>
</head>
<body class="bg-[#F5F7FB] text-gray-800 min-h-screen flex flex-col antialiased">
    
    {{-- Header with Logout --}}
    <header class="bg-white border-b border-slate-200 px-6 py-4 flex justify-end shadow-sm">
        <form method="POST" action="{{ route('logout') }}">
            @csrf
            <button type="submit" class="inline-flex items-center gap-2 text-sm font-semibold text-slate-600 hover:text-red-600 transition">
                <i class="fa-solid fa-arrow-right-from-bracket"></i>
                Logout
            </button>
        </form>
    </header>

    {{-- Main Content --}}
    <main class="flex-1 flex flex-col max-w-2xl mx-auto w-full px-4 sm:px-6 lg:px-8 py-10">
        
        <div class="mb-6">
            <a href="{{ route('admin.organization.select') }}" class="inline-flex items-center gap-2 text-sm font-semibold text-slate-500 hover:text-primary transition">
                <i class="fa-solid fa-arrow-left"></i>
                Kembali
            </a>
        </div>

        <div class="space-y-6">
            <section>
                <h1 class="text-2xl sm:text-3xl font-bold text-slate-900 leading-tight">
                    Tambah Organisasi
                </h1>
                <p class="mt-1.5 text-sm text-slate-500">
                    Masukkan detail organisasi baru yang akan dikelola.
                </p>
            </section>

            {{-- Flash Notifications --}}
            @if(session('error') || (isset($errors) && $errors->any()))
            <div class="flex items-center gap-3 rounded-2xl border border-red-200 bg-red-50 px-5 py-4 text-sm text-red-800 shadow-sm">
                <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 flex-shrink-0 text-red-500" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
                </svg>
                <div>
                    @if(session('error'))
                        <p>{{ session('error') }}</p>
                    @endif
                    @if(isset($errors) && $errors->any())
                        @foreach($errors->all() as $err)
                            <p>{{ $err }}</p>
                        @endforeach
                    @endif
                </div>
            </div>
            @endif

            <div class="rounded-3xl bg-white p-6 sm:p-8 shadow-sm border border-slate-200">
                <form method="POST" action="{{ route('admin.organization.storeNew') }}" class="space-y-6">
                    @csrf
                    
                    <div>
                        <label for="nama_organisasi" class="block text-sm font-semibold text-slate-700 mb-2">Nama Organisasi <span class="text-red-500">*</span></label>
                        <input type="text" id="nama_organisasi" name="nama_organisasi" value="{{ old('nama_organisasi') }}" required placeholder="Contoh: PT Teknologi Maju"
                            class="w-full rounded-xl border border-slate-300 px-4 py-3 text-sm text-slate-800 focus:border-primary focus:ring-1 focus:ring-primary outline-none transition">
                    </div>

                    <div>
                        <label for="kode_organisasi" class="block text-sm font-semibold text-slate-700 mb-2">Kode Organisasi <span class="text-red-500">*</span></label>
                        <input type="text" id="kode_organisasi" name="kode_organisasi" value="{{ old('kode_organisasi') }}" required placeholder="Contoh: TECH01"
                            class="w-full rounded-xl border border-slate-300 px-4 py-3 text-sm text-slate-800 focus:border-primary focus:ring-1 focus:ring-primary outline-none transition">
                        <p class="mt-1.5 text-xs text-slate-500">Kode unik yang merepresentasikan organisasi.</p>
                    </div>

                    <div>
                        <label for="category_id" class="block text-sm font-semibold text-slate-700 mb-2">Kategori Organisasi <span class="text-red-500">*</span></label>
                        <select id="category_id" name="category_id" required
                            class="w-full rounded-xl border border-slate-300 px-4 py-3 text-sm text-slate-800 focus:border-primary focus:ring-1 focus:ring-primary outline-none transition bg-white">
                            <option value="">-- Pilih Kategori --</option>
                            @foreach($categories as $category)
                                <option value="{{ $category->id }}" {{ old('category_id') == $category->id ? 'selected' : ($category->slug === 'perusahaan' ? 'selected' : '') }}>
                                    {{ $category->name }} @if($category->description) — {{ Str::limit($category->description, 60) }} @endif
                                </option>
                            @endforeach
                        </select>
                        <p class="mt-1.5 text-xs text-slate-500">Fitur default organisasi akan di-provisioning otomatis berdasarkan template kategori ini.</p>
                    </div>

                    <div>
                        <label for="alamat" class="block text-sm font-semibold text-slate-700 mb-2">Alamat (Opsional)</label>
                        <textarea id="alamat" name="alamat" rows="3" placeholder="Alamat lengkap organisasi..."
                            class="w-full rounded-xl border border-slate-300 px-4 py-3 text-sm text-slate-800 focus:border-primary focus:ring-1 focus:ring-primary outline-none transition">{{ old('alamat') }}</textarea>
                    </div>

                    <div class="pt-2">
                        <button type="submit" class="w-full inline-flex items-center justify-center gap-2 rounded-xl bg-primary px-5 py-3 text-sm font-bold text-white shadow-sm transition hover:bg-primary-hover">
                            <i class="fa-solid fa-save"></i>
                            Simpan Organisasi
                        </button>
                    </div>
                </form>
            </div>

        </div>

    </main>

</body>
</html>
