<?php

namespace Pterodactyl\Models\DGEN;

use Pterodactyl\Models\Model;

class PermissionRole extends Model
{
    protected $table = 'permission_roles';

    protected $fillable = [
        'name',
        'permissions',
        'is_default',
    ];

    protected $guarded = ['id', self::CREATED_AT, self::UPDATED_AT];

    public static array $validationRules = [
        'name' => 'required|string|max:191|unique:permission_roles,name',
        'permissions' => 'nullable|array',
        'is_default' => 'sometimes|boolean',
    ];

    protected $casts = [
        'permissions' => 'array',
        'is_default' => 'boolean',
        self::CREATED_AT => 'datetime',
        self::UPDATED_AT => 'datetime',
    ];
}
