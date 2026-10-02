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
        if (! Schema::hasTable('category_templates')) {
            Schema::create('category_templates', function (Blueprint $table) {
                $table->id();
                $table->foreignId('category_id')
                    ->constrained('organization_categories')
                    ->cascadeOnDelete();
                $table->string('name', 100);
                $table->text('description')->nullable();
                $table->boolean('is_default')->default(true);
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('category_template_features')) {
            Schema::create('category_template_features', function (Blueprint $table) {
                $table->id();
                $table->foreignId('template_id')
                    ->constrained('category_templates')
                    ->cascadeOnDelete();
                $table->foreignId('feature_id')
                    ->constrained('features')
                    ->cascadeOnDelete();
                $table->boolean('is_enabled')->default(true);
                $table->json('default_config')->nullable();
                $table->timestamps();

                $table->unique(['template_id', 'feature_id'], 'cat_tmpl_feat_unique');
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('category_template_features');
        Schema::dropIfExists('category_templates');
    }
};
