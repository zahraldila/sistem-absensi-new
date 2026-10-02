<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class OrganizationCategory extends Model
{
    protected $table = 'organization_categories';

    protected $fillable = [
        'name',
        'slug',
        'description',
        'status',
    ];

    public function organizations(): HasMany
    {
        return $this->hasMany(Organization::class, 'category_id', 'id');
    }

    public function templates(): HasMany
    {
        return $this->hasMany(CategoryTemplate::class, 'category_id', 'id');
    }

    public function defaultTemplate(): HasOne
    {
        return $this->hasOne(CategoryTemplate::class, 'category_id', 'id')
            ->where('is_default', true);
    }
}
