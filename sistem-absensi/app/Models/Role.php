<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Role extends Model
{
    protected $table = 'role';
    protected $primaryKey = 'role_id';
    public $incrementing = true;
    protected $keyType = 'int';
    public $timestamps = true;

    protected $fillable = [
        'nama_role',
        'deskripsi',
        'organization_id',
    ];

    public function akun(): HasMany
    {
        return $this->hasMany(Akun::class, 'role_id', 'role_id');
    }

    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class, 'organization_id', 'organization_id');
    }

    public function privileges(): BelongsToMany
    {
        return $this->belongsToMany(Privilege::class, 'role_privilege', 'role_id', 'privilege_id')
            ->withTimestamps();
    }

    public function hasPrivilege(string $namaPrivilege): bool
    {
        if ($this->isGlobalSuperAdmin()) {
            return true;
        }

        return $this->privileges->contains('nama_privilege', $namaPrivilege);
    }

    public function isGlobalSuperAdmin(): bool
    {
        return strtolower($this->nama_role) === 'super admin'
            && is_null($this->organization_id);
    }

    public function hasAnyPrivilege(): bool
    {
        return $this->isGlobalSuperAdmin() || $this->privileges()->exists();
    }
}
