<?php

namespace Pterodactyl\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ReverseProxy extends Model
{
    protected $table = 'reverse_proxies';

    protected $guarded = ['id', self::CREATED_AT, self::UPDATED_AT];

    public static array $validationRules = [
        'server_id' => 'required|integer|exists:servers,id',
        'hostname' => 'required|string|max:253',
        'target_port' => 'required|integer|between:1,65535',
        'ssl_cert_path' => 'nullable|string',
        'ssl_key_path' => 'nullable|string',
        'config_file' => 'nullable|string',
        'status' => 'nullable|string|max:50',
    ];

    protected $casts = [
        'server_id' => 'integer',
        'target_port' => 'integer',
        self::CREATED_AT => 'datetime',
        self::UPDATED_AT => 'datetime',
    ];

    public function server(): BelongsTo
    {
        return $this->belongsTo(Server::class);
    }
}
