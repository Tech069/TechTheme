<?php

namespace Pterodactyl\Models\DGEN;

use Pterodactyl\Models\Model;

class ReverseProxyWhitelist extends Model
{
    protected $table = 'reverse_proxy_whitelists';

    protected $fillable = [
        'domain',
        'max_proxies',
        'is_active',
    ];

    protected $guarded = ['id', self::CREATED_AT, self::UPDATED_AT];

    public static array $validationRules = [
        'domain' => 'required|string|max:191|unique:reverse_proxy_whitelists,domain',
        'max_proxies' => 'required|integer|min:0',
        'is_active' => 'sometimes|boolean',
    ];

    protected $casts = [
        'max_proxies' => 'integer',
        'is_active' => 'boolean',
        self::CREATED_AT => 'datetime',
        self::UPDATED_AT => 'datetime',
    ];
}
