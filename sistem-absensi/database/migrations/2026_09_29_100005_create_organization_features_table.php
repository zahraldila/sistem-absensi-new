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
        if (! Schema::hasTable('organization_features')) {
            Schema::create('organization_features', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('organization_id');
                $table->foreign('organization_id')
                    ->references('organization_id')
                    ->on('organizations')
                    ->cascadeOnDelete();
                $table->foreignId('feature_id')
                    ->constrained('features')
                    ->cascadeOnDelete();
                $table->boolean('is_enabled')->default(true);
                $table->json('configuration')->nullable();
                $table->timestamps();

                $table->unique(['organization_id', 'feature_id'], 'org_feat_unique');
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('organization_features');
    }
};
