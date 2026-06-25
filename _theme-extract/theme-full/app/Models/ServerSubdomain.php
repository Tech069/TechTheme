<?php

namespace Pterodactyl\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ServerSubdomain extends Model
{
    protected $table = 'server_subdomains';

    protected $guarded = ['id', self::CREATED_AT, self::UPDATED_AT];

    public static array $validationRules = [
        'server_id' => 'required|integer|exists:servers,id',
        'subdomain' => 'required|string|max:253',
        'domain' => 'required|string|max:253',
        'record_id' => 'nullable|string|max:100',
        'type' => 'nullable|string|max:10',
        'target' => 'nullable|string|max:253',
    ];

    protected $casts = [
        'server_id' => 'integer',
        self::CREATED_AT => 'datetime',
        self::UPDATED_AT => 'datetime',
    ];

    public function server(): BelongsTo
    {
        return $this->belongsTo(Server::class);
    }
}
