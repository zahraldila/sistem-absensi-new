<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class WorkLocation extends Model
{
    protected $table = 'lokasi_kantor';
    protected $primaryKey = 'lokasi_id';
    public $incrementing = true;
    protected $keyType = 'int';
    public $timestamps = true;

    protected $fillable = [
        'nama_kantor',
        'latitude',
        'longitude',
        'radius_meter',
        'organization_id',
    ];

    public function absensis(): HasMany
    {
        return $this->hasMany(Attendance::class, 'lokasi_id', 'lokasi_id');
    }
}
