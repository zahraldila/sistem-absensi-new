<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CategoryTemplateFeature extends Model
{
    protected $table = 'category_template_features';

    protected $fillable = [
        'template_id',
        'feature_id',
        'is_enabled',
        'default_config',
    ];

    protected $casts = [
        'is_enabled'     => 'boolean',
        'default_config' => 'array',
    ];

    public function template(): BelongsTo
    {
        return $this->belongsTo(CategoryTemplate::class, 'template_id', 'id');
    }

    public function feature(): BelongsTo
    {
        return $this->belongsTo(Feature::class, 'feature_id', 'id');
    }
}
