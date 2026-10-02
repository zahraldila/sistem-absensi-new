<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class OrganizationFeature extends Model
{
    protected $table = 'organization_features';

    protected $fillable = [
        'organization_id',
        'feature_id',
        'is_enabled',
        'configuration',
    ];

    protected $casts = [
        'is_enabled'    => 'boolean',
        'configuration' => 'array',
    ];

    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class, 'organization_id', 'organization_id');
    }

    public function feature(): BelongsTo
    {
        return $this->belongsTo(Feature::class, 'feature_id', 'id');
    }
}
