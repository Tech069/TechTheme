<?php

namespace Pterodactyl\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CustomMonitor extends Model
{
    protected $table = 'custom_monitors';

    protected $guarded = ['id', self::CREATED_AT, self::UPDATED_AT];

    public static array $validationRules = [
        'server_id' => 'required|integer|exists:servers,id',
        'type' => 'required|string|max:191',
        'regex' => 'nullable|string|max:191',
        'threshold' => 'nullable|numeric',
    ];

    protected $casts = [
        'server_id' => 'integer',
        'threshold' => 'float',
        self::CREATED_AT => 'datetime',
        self::UPDATED_AT => 'datetime',
    ];

    public function server(): BelongsTo
    {
        return $this->belongsTo(Server::class);
    }
}
