<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        if (Schema::hasTable('lokasi_kantor')) {
            // Bersihkan data sampah/kosong jika ada dari pengetesan sebelumnya
            DB::table('lokasi_kantor')
                ->whereNull('nama_kantor')
                ->orWhereRaw("TRIM(nama_kantor) = ''")
                ->delete();

            // Isi created_at dan updated_at yang masih null
            DB::table('lokasi_kantor')
                ->whereNull('created_at')
                ->update([
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);

            Schema::table('lokasi_kantor', function (Blueprint $table) {
                if (! Schema::hasColumn('lokasi_kantor', 'created_at')) {
                    $table->timestamps();
                }
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // No reverse needed for data cleanup
    }
};
