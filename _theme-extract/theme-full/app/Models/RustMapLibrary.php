<?php

namespace Pterodactyl\Models;

class RustMapLibrary extends Model
{
    protected $table = 'rust_map_libraries';

    protected $fillable = [
        'name',
        'url',
        'size',
        'workshop_id',
    ];

    protected $guarded = ['id', self::CREATED_AT, self::UPDATED_AT];

    public static array $validationRules = [
        'name' => 'required|string|max:191',
        'url' => 'required|url|max:191',
        'size' => 'nullable|integer|min:0',
        'workshop_id' => 'nullable|string|max:191',
    ];

    protected $casts = [
        'size' => 'integer',
        self::CREATED_AT => 'datetime',
        self::UPDATED_AT => 'datetime',
    ];
}
