<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('role', function (Blueprint $table) {
            $table->dropUnique('role_nama_role_unique');
            $table->unique(['organization_id', 'nama_role'], 'role_org_nama_unique');
        });

        $globalRoles = DB::table('role')
            ->whereNull('organization_id')
            ->where('nama_role', '!=', 'Super Admin')
            ->orderBy('role_id')
            ->get();

        foreach (DB::table('organizations')->get() as $organization) {
            foreach ($globalRoles as $globalRole) {
                $localRole = DB::table('role')
                    ->where('organization_id', $organization->organization_id)
                    ->where('nama_role', $globalRole->nama_role)
                    ->first();

                if (! $localRole) {
                    $localRoleId = DB::table('role')->insertGetId([
                        'nama_role' => $globalRole->nama_role,
                        'deskripsi' => $globalRole->deskripsi ?? null,
                        'organization_id' => $organization->organization_id,
                        'created_at' => now(),
                        'updated_at' => now(),
                    ], 'role_id');

                    $privilegeRows = DB::table('role_privilege')
                        ->where('role_id', $globalRole->role_id)
                        ->get(['privilege_id'])
                        ->map(fn ($row) => [
                            'role_id' => $localRoleId,
                            'privilege_id' => $row->privilege_id,
                            'created_at' => now(),
                            'updated_at' => now(),
                        ])
                        ->all();

                    if ($privilegeRows) {
                        DB::table('role_privilege')->insert($privilegeRows);
                    }
                } else {
                    $localRoleId = $localRole->role_id;
                }

                DB::table('akun')
                    ->where('role_id', $globalRole->role_id)
                    ->whereIn('pegawai_id', function ($query) use ($organization) {
                        $query->select('pegawai_id')
                            ->from('pegawai')
                            ->where('organization_id', $organization->organization_id);
                    })
                    ->update(['role_id' => $localRoleId]);
            }
        }
    }

    public function down(): void
    {
        $localRoles = DB::table('role')->whereNotNull('organization_id')->get();

        foreach ($localRoles as $localRole) {
            $globalRole = DB::table('role')
                ->whereNull('organization_id')
                ->where('nama_role', $localRole->nama_role)
                ->first();

            if (! $globalRole || $globalRole->deskripsi !== $localRole->deskripsi) {
                throw new RuntimeException('Organization-specific roles must be reconciled before this migration can be rolled back.');
            }

            $localPrivileges = DB::table('role_privilege')
                ->where('role_id', $localRole->role_id)
                ->orderBy('privilege_id')
                ->pluck('privilege_id')
                ->all();
            $globalPrivileges = DB::table('role_privilege')
                ->where('role_id', $globalRole->role_id)
                ->orderBy('privilege_id')
                ->pluck('privilege_id')
                ->all();

            if ($localPrivileges !== $globalPrivileges) {
                throw new RuntimeException('Organization-specific role privileges must be reconciled before this migration can be rolled back.');
            }
        }

        foreach ($localRoles as $localRole) {
            $globalRoleId = DB::table('role')
                ->whereNull('organization_id')
                ->where('nama_role', $localRole->nama_role)
                ->value('role_id');

            DB::table('akun')
                ->where('role_id', $localRole->role_id)
                ->update(['role_id' => $globalRoleId]);
            DB::table('role_privilege')->where('role_id', $localRole->role_id)->delete();
            DB::table('role')->where('role_id', $localRole->role_id)->delete();
        }

        Schema::table('role', function (Blueprint $table) {
            $table->dropUnique('role_org_nama_unique');
            $table->unique('nama_role', 'role_nama_role_unique');
        });
    }
};