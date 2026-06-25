<?php

namespace Pterodactyl\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;

class HyperCommandHistory extends Model
{
    protected $table = 'hyper_command_histories';

    protected $guarded = ['id', self::CREATED_AT, self::UPDATED_AT];

    public static array $validationRules = [
        'server_id' => 'required|integer|exists:servers,id',
        'user_id' => 'required|integer|exists:users,id',
        'command' => 'required|string|max:191',
        'output' => 'nullable|string',
    ];

    protected $casts = [
        'server_id' => 'integer',
        'user_id' => 'integer',
        self::CREATED_AT => 'datetime',
        self::UPDATED_AT => 'datetime',
    ];

    public function server(): BelongsTo
    {
        return $this->belongsTo(Server::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
