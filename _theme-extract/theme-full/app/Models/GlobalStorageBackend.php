<?php

namespace Pterodactyl\Models;

class GlobalStorageBackend extends Model
{
    protected $table = 'global_storage_backends';

    protected $fillable = [
        'name',
        'driver',
        'config',
        'is_default',
    ];

    protected $guarded = ['id', self::CREATED_AT, self::UPDATED_AT];

    public static array $validationRules = [
        'name' => 'required|string|max:191',
        'driver' => 'required|string|max:191',
        'config' => 'nullable|array',
        'is_default' => 'sometimes|boolean',
    ];

    protected $casts = [
        'config' => 'array',
        'is_default' => 'boolean',
        self::CREATED_AT => 'datetime',
        self::UPDATED_AT => 'datetime',
    ];
}
