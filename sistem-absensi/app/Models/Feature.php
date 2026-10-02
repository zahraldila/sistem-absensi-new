<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class Feature extends Model
{
    protected $table = 'features';

    protected $fillable = [
        'key',
        'name',
        'description',
        'category_group',
        'status',
    ];

    public function templateFeatures(): HasMany
    {
        return $this->hasMany(CategoryTemplateFeature::class, 'feature_id', 'id');
    }

    public function organizationFeatures(): HasMany
    {
        return $this->hasMany(OrganizationFeature::class, 'feature_id', 'id');
    }

    public function organizations(): BelongsToMany
    {
        return $this->belongsToMany(Organization::class, 'organization_features', 'feature_id', 'organization_id')
            ->withPivot('is_enabled', 'configuration')
            ->withTimestamps();
    }
}
