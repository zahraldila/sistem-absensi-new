<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class CategoryTemplate extends Model
{
    protected $table = 'category_templates';

    protected $fillable = [
        'category_id',
        'name',
        'description',
        'is_default',
    ];

    protected $casts = [
        'is_default' => 'boolean',
    ];

    public function category(): BelongsTo
    {
        return $this->belongsTo(OrganizationCategory::class, 'category_id', 'id');
    }

    public function templateFeatures(): HasMany
    {
        return $this->hasMany(CategoryTemplateFeature::class, 'template_id', 'id');
    }

    public function features(): BelongsToMany
    {
        return $this->belongsToMany(Feature::class, 'category_template_features', 'template_id', 'feature_id')
            ->withPivot('is_enabled', 'default_config')
            ->withTimestamps();
    }
}
