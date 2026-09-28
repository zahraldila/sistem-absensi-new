<?php

namespace App\Helpers;

use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class logHelpers {
    // Untuk mencatat aktivitas user ke tabel audit_log
    public static function record($akunId, $aktivitas) {
        $user = \Illuminate\Support\Facades\Auth::user();
        if ($user && isset($user->id)) {
            $akunId = $user->id;
        } elseif ($user && isset($user->akun_id)) {
            $akunId = $user->akun_id;
        }

        // Truncate string to max 95 characters with ellipsis to strictly prevent PostgreSQL SQLSTATE[22001]
        $safeAktivitas = mb_strimwidth($aktivitas, 0, 95, '...');

        DB::table('audit_log')->insert([
            'akun_id'   => $akunId,
            'aktivitas' => $safeAktivitas,
            'waktu_log' => Carbon::now(),
        ]);
    }
}