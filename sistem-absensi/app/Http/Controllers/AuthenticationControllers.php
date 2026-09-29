<?php

namespace App\Http\Controllers;

use App\Http\Requests\LoginRequest;
use App\Models\Akun;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use App\Helpers\logHelpers;

class AuthenticationControllers extends Controller
{
    /**
     * Proses login admin / user.
     * - Validasi ke tabel akun (username / email pegawai)
     * - Verifikasi password
     * - Login via Auth::login
     * - Regenerate session & simpan info session (akun_id, pegawai_id, role, nama_pegawai)
     * - Redirect ke dashboard admin
     *
     * @param LoginRequest $request
     * @return \Illuminate\Http\RedirectResponse
     */
    public function login(LoginRequest $request)
    {
        try {
            $loginInput = $request->input('email');
            $password = $request->input('password');

            // Mencari akun berdasarkan username atau email pegawai
            $akun = Akun::where('username', $loginInput)
                ->orWhereHas('pegawai', function ($query) use ($loginInput) {
                    $query->where('email', $loginInput);
                })
                ->first();

            $validPassword = false;
            if ($akun) {
                $storedPassword = (string) $akun->password;

                // Preferensi: selalu coba Hash::check terlebih dahulu (untuk hashed passwords)
                if (Hash::check($password, $storedPassword)) {
                    $validPassword = true;
                } else {
                    // Fallback untuk akun legacy yang menyimpan password plaintext.
                    // Jika cocok, migrasikan ke hash secara aman.
                    if (hash_equals($storedPassword, (string) $password)) {
                        $validPassword = true;
                        $akun->password = Hash::make($password);
                        $akun->save();
                    }
                }
            }

            if (! $akun || ! $validPassword) {
                return back()
                    ->withErrors(['email' => 'Username/Email atau password yang Anda masukkan salah.'])
                    ->onlyInput('email');
            }

                        // Cek status akun & pegawai — tolak jika status bernilai 'Tidak Aktif'.
            $isAccountInactive = ($akun->status && strcasecmp(trim($akun->status), 'Tidak Aktif') === 0)
                || ($akun->pegawai && strcasecmp(trim($akun->pegawai->status), 'Tidak Aktif') === 0);

            if ($isAccountInactive) {
                $pesanTidakAktif = 'Akun Anda tidak aktif. Silakan hubungi Admin.';

                if ($request->expectsJson()) {
                    return response()->json([
                        'status'  => 'error',
                        'message' => $pesanTidakAktif,
                    ], 403);
                }

                return back()
                    ->withErrors(['email' => $pesanTidakAktif])
                    ->onlyInput('email');
            }

            $remember = $request->boolean('remember');

            // Login user dengan remember-token DB jika "Ingat Saya" dicentang.
            Auth::login($akun, $remember);

            // Regenerate session untuk keamanan
            $request->session()->regenerate();

            // Jika "Ingat Saya" TIDAK dicentang, set lastActivityTime agar SessionTimeout aktif.
            // Jika "Ingat Saya" dicentang, lastActivityTime tidak di-set sehingga sesi tidak expire.
            if (! $remember) {
                $request->session()->put('lastActivityTime', time());
            }

            // Menyimpan informasi user/pegawai ke dalam session Laravel
            $pegawai = $akun->pegawai;
            session([
                'akun_id' => $akun->akun_id,
                'pegawai_id' => $akun->pegawai_id,
                'role' => $akun->role,
                'nama_pegawai' => $pegawai ? $pegawai->nama_pegawai : null,
                'email_pegawai' => $pegawai ? $pegawai->email : null,
                'remember_me' => $remember,
            ]);

            // ---------------------------------------------------------
            // INJEKSI LOG ACTIVITY: Mencatat bahwa user berhasil login
            // ---------------------------------------------------------
            $roleName = ucfirst($akun->role); // Membuat huruf pertama kapital (misal: 'Admin' atau 'Pegawai')
            logHelpers::record($akun->akun_id, "{$roleName} berhasil login ke dalam sistem");
            // ---------------------------------------------------------

            return redirect()->intended(route('admin.dashboard'));
        } catch (\Throwable $e) {
            \Illuminate\Support\Facades\Log::error('Login failed due to server/connection error: ' . $e->getMessage());

            return back()
                ->with('error', 'Gagal terhubung ke server. Silakan periksa koneksi internet Anda dan coba lagi.')
                ->onlyInput('email');
        }
    }

    /**
     * Proses logout.
     * - Logout dari guard 'web'
     * - Invalidate session & regenerate CSRF token
     * - Redirect ke halaman login
     *
     * @param Request $request
     * @return \Illuminate\Http\RedirectResponse
     */
    public function logout(Request $request)
    {
        // ---------------------------------------------------------
        // INJEKSI LOG ACTIVITY: Mencatat bahwa user melakukan logout
        // Pastikan kita ambil ID sebelum user benar-benar di-logout
        // ---------------------------------------------------------
        if (Auth::check()) {
            $akunId = Auth::user()->akun_id; 
            // Atau bisa juga menggunakan ID dari session: $request->session()->get('akun_id');
            
            $roleName = ucfirst(Auth::user()->role);
            logHelpers::record($akunId, "{$roleName} melakukan logout dari sistem");
        }
        // ---------------------------------------------------------

        Auth::guard('web')->logout();

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('login')
            ->header('Cache-Control', 'no-cache, no-store, max-age=0, must-revalidate')
            ->header('Pragma', 'no-cache')
            ->header('Expires', 'Sat, 01 Jan 2000 00:00:00 GMT');
    }

    // register/forgot/reset masih placeholder, belum masuk scope sprint ini
    public function register(Request $request)
    {
        //
    }

    public function forgot(Request $request)
    {
        //
    }

    public function reset(Request $request)
    {
        //
    }
}