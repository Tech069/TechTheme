<?php

namespace Pterodactyl\Models\DGEN;

use Pterodactyl\Models\Model;

class SubdomainManagerWhitelist extends Model
{
    protected $table = 'subdomain_manager_whitelists';

    protected $fillable = [
        'domain',
        'max_subdomains',
        'is_active',
    ];

    protected $guarded = ['id', self::CREATED_AT, self::UPDATED_AT];

    public static array $validationRules = [
        'domain' => 'required|string|max:191|unique:subdomain_manager_whitelists,domain',
        'max_subdomains' => 'required|integer|min:0',
        'is_active' => 'sometimes|boolean',
    ];

    protected $casts = [
        'max_subdomains' => 'integer',
        'is_active' => 'boolean',
        self::CREATED_AT => 'datetime',
        self::UPDATED_AT => 'datetime',
    ];
}
