<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        foreach (DB::table('organizations')->get(['organization_id']) as $organization) {
            $memberRole = DB::table('role')
                ->where('organization_id', $organization->organization_id)
                ->whereRaw('LOWER(nama_role) = ?', ['anggota'])
                ->first();

            if (! $memberRole) {
                $memberRoleId = DB::table('role')->insertGetId([
                    'nama_role' => 'Anggota',
                    'deskripsi' => 'Akun anggota untuk aplikasi mobile dan presensi.',
                    'organization_id' => $organization->organization_id,
                    'created_at' => now(),
                    'updated_at' => now(),
                ], 'role_id');
            } else {
                $memberRoleId = $memberRole->role_id;
            }

            DB::table('role_privilege')->where('role_id', $memberRoleId)->delete();

            DB::table('akun')
                ->whereNull('role_id')
                ->whereRaw("TRIM(COALESCE(role, '')) = ''")
                ->whereIn('pegawai_id', function ($query) use ($organization) {
                    $query->select('pegawai_id')
                        ->from('pegawai')
                        ->where('organization_id', $organization->organization_id);
                })
                ->update([
                    'role_id' => $memberRoleId,
                    'role' => 'Anggota',
                ]);
        }
    }

    public function down(): void
    {
        foreach (DB::table('role')->where('nama_role', 'Anggota')->whereNotNull('organization_id')->get() as $memberRole) {
            DB::table('akun')
                ->where('role_id', $memberRole->role_id)
                ->where('role', 'Anggota')
                ->update(['role_id' => null, 'role' => null]);

            DB::table('role_privilege')->where('role_id', $memberRole->role_id)->delete();
            DB::table('role')->where('role_id', $memberRole->role_id)->delete();
        }
    }
};
