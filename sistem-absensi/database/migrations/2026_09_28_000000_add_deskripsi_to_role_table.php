<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        if (Schema::hasTable('role')) {
            if (! Schema::hasColumn('role', 'deskripsi')) {
                Schema::table('role', function (Blueprint $table) {
                    $table->string('deskripsi', 255)->nullable()->after('nama_role');
                });
            }
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (Schema::hasTable('role') && Schema::hasColumn('role', 'deskripsi')) {
            Schema::table('role', function (Blueprint $table) {
                $table->dropColumn('deskripsi');
            });
        }
    }
};
