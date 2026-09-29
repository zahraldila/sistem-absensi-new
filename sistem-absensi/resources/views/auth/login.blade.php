@extends('layouts.auth.app')

@section('title', 'Login | Sistem Absensi')

@section('content')
<style>
    [x-cloak] { display: none !important; }
    /* Override browser autofill background & text colors */
    input:-webkit-autofill,
    input:-webkit-autofill:hover, 
    input:-webkit-autofill:focus, 
    input:-webkit-autofill:active {
        -webkit-box-shadow: 0 0 0 1000px #f8fafc inset !important;
        -webkit-text-fill-color: #1e293b !important;
        transition: background-color 5000s ease-in-out 0s;
    }
</style>
<div class="rounded-3xl sm:rounded-[40px] border border-slate-100 bg-white px-5 py-7 sm:px-8 sm:py-10 shadow-2xl">

    {{-- Brand --}}
    <div class="mb-5 sm:mb-6 flex flex-col justify-center items-center">
        <h1 class="text-center text-2xl sm:text-3xl font-extrabold tracking-tight text-slate-900">Sistem Absensi</h1>
        <p class="mt-1.5 sm:mt-2 text-center text-sm sm:text-base font-medium text-slate-500">Integrated Attendance System</p>
    </div>

    {{-- Status Alert (e.g. Password reset success) --}}
    @if (session('status'))
        <div class="mt-4 sm:mt-5 flex items-center gap-2 rounded-2xl border border-green-200 bg-green-50 px-4 py-3 text-xs sm:text-sm text-green-700">
            <i class="fa-solid fa-circle-check text-green-500"></i>
            <span>{{ session('status') }}</span>
        </div>
    @endif

    {{-- Error Banner (General login error / Offline error) --}}
    <div id="login-error-banner" @if (!session('error') && !$errors->has('message')) style="display: none;" @endif class="mt-4 sm:mt-5 flex items-center gap-2 rounded-2xl border border-red-200 bg-red-50 px-4 py-3 text-xs sm:text-sm text-red-700">
        <i class="fa-solid fa-circle-exclamation text-red-500"></i>
        <span id="login-error-message">{{ session('error') ?? $errors->first('message') }}</span>
    </div>

    <form method="POST" action="{{ url('/login') }}" novalidate class="mt-6 sm:mt-8 space-y-4 sm:space-y-5" x-data="{ showPassword: false, isSubmitting: false }" @submit="if (!navigator.onLine) { $event.preventDefault(); isSubmitting = false; return false; } if (isSubmitting) { $event.preventDefault(); return false; } isSubmitting = true;">
        @csrf

        {{-- Username / Email --}}
        <div>
            <label for="email" class="mb-1.5 block text-xs sm:text-sm font-semibold text-slate-700">Username atau Email</label>
            <div class="h-12 sm:h-[50px] flex items-center gap-2.5 sm:gap-3 rounded-2xl border @error('email') border-red-400 bg-red-50/30 ring-1 ring-red-300 @else border-slate-300 bg-slate-50 focus-within:border-primary focus-within:ring-1 focus-within:ring-primary @enderror px-3.5 sm:px-4 transition-all">
                <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 shrink-0 @error('email') text-red-400 @else text-slate-400 @enderror" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z" />
                </svg>
                <input
                    type="text"
                    name="email"
                    id="email"
                    value="{{ old('email') }}"
                    placeholder="Masukkan username atau email"
                    autofocus
                    class="w-full border-none bg-transparent p-0 text-sm text-slate-800 placeholder-slate-400 outline-none focus:outline-none focus:ring-0" />
            </div>
            @error('email')
                <p class="mt-1.5 text-xs text-red-600 font-medium flex items-center gap-1.5">
                    <i class="fa-solid fa-circle-exclamation text-xs"></i>
                    <span>{{ $message }}</span>
                </p>
            @enderror
        </div>

        {{-- Password --}}
        <div>
            <label for="password" class="mb-1.5 block text-xs sm:text-sm font-semibold text-slate-700">Password</label>
            <div class="h-12 sm:h-[50px] flex items-center gap-2.5 sm:gap-3 rounded-2xl border @error('password') border-red-400 bg-red-50/30 ring-1 ring-red-300 @else border-slate-300 bg-slate-50 focus-within:border-primary focus-within:ring-1 focus-within:ring-primary @enderror px-3.5 sm:px-4 transition-all">
                <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 shrink-0 @error('password') text-red-400 @else text-slate-400 @enderror" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <rect x="4" y="11" width="16" height="9" rx="2" stroke-width="2" />
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 11V7a4 4 0 0 1 8 0v4" />
                </svg>
                <input
                    :type="showPassword ? 'text' : 'password'"
                    name="password"
                    id="password"
                    placeholder="Masukkan password akun"
                    class="w-full border-none bg-transparent p-0 text-sm text-slate-800 placeholder-slate-400 outline-none focus:outline-none focus:ring-0" />
                <button type="button" @click="showPassword = !showPassword" class="shrink-0 flex items-center justify-center p-0.5 text-slate-400 hover:text-slate-600 focus:outline-none transition-colors" :title="showPassword ? 'Sembunyikan password' : 'Tampilkan password'" aria-label="Toggle password visibility">
                    {{-- Eye Icon (ketika password tersembunyi) --}}
                    <svg x-show="!showPassword" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
                        <path stroke-linecap="round" stroke-linejoin="round" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z" />
                    </svg>
                    {{-- Eye Off Icon (ketika password terlihat) --}}
                    <svg x-show="showPassword" x-cloak class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M13.875 18.825A10.05 10.05 0 0112 19c-4.478 0-8.268-2.943-9.543-7a9.97 9.97 0 011.563-3.029m5.858.908a3 3 0 114.243 4.243M9.878 9.878l4.242 4.242M9.88 9.88l-3.29-3.29m7.532 7.532l3.29 3.29M3 3l18 18" />
                    </svg>
                </button>
            </div>
            @error('password')
                <p class="mt-1.5 text-xs text-red-600 font-medium flex items-center gap-1.5">
                    <i class="fa-solid fa-circle-exclamation text-xs"></i>
                    <span>{{ $message }}</span>
                </p>
            @enderror
        </div>

        {{-- Remember Me --}}
        <div class="flex items-center text-xs sm:text-sm">
            <label class="flex items-center gap-2 text-slate-600 cursor-pointer select-none">
                <input type="checkbox" name="remember" class="h-4 w-4 rounded border-slate-300 text-primary focus:ring-primary" />
                <span>Ingat Saya</span>
            </label>
        </div>

        {{-- Submit --}}
        <button
            type="submit"
            :disabled="isSubmitting"
            :class="isSubmitting ? 'opacity-75 cursor-not-allowed' : 'hover:bg-primary-hover active:scale-95'"
            class="w-full flex items-center justify-center gap-2 rounded-2xl bg-primary py-3.5 sm:py-4 text-sm sm:text-base font-bold text-white shadow-lg transition">
            <svg x-show="isSubmitting" x-cloak class="h-5 w-5 animate-spin text-white" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
            </svg>
            <span x-text="isSubmitting ? 'Memproses...' : 'Masuk'">Masuk</span>
        </button>

        {{-- Help --}}
        <p class="text-center text-xs sm:text-sm text-slate-500 pt-1">
            Butuh bantuan?
            <a href="#" class="font-semibold text-primary hover:underline">Hubungi IT Support</a>
        </p>
    </form>
</div>

<script>
    document.addEventListener('DOMContentLoaded', function () {
        var form = document.querySelector('form');
        if (form) {
            form.addEventListener('submit', function (e) {
                if (!navigator.onLine) {
                    e.preventDefault();
                    var banner = document.getElementById('login-error-banner');
                    var msg = document.getElementById('login-error-message');
                    if (banner && msg) {
                        msg.textContent = 'Gagal terhubung ke server. Silakan periksa koneksi internet Anda dan coba lagi.';
                        banner.style.display = 'flex';
                    }
                    if (form._x_dataStack && form._x_dataStack[0]) {
                        form._x_dataStack[0].isSubmitting = false;
                    }
                }
            });
        }
    });

    window.addEventListener('pageshow', function (event) {
        if (event.persisted) {
            window.location.reload();
            return;
        }
        var form = document.querySelector('form');
        if (form && form._x_dataStack && form._x_dataStack[0]) {
            form._x_dataStack[0].isSubmitting = false;
        }
    });
</script>
@endsection