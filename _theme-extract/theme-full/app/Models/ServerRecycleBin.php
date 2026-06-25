<?php

namespace Pterodactyl\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ServerRecycleBin extends Model
{
    protected $table = 'server_recycle_bins';

    protected $guarded = ['id', self::CREATED_AT, self::UPDATED_AT];

    public static array $validationRules = [
        'server_id' => 'required|integer|exists:servers,id',
        'user_id' => 'required|integer|exists:users,id',
        'data' => 'nullable|array',
        'deleted_at' => 'required|date',
        'restore_until' => 'nullable|date',
    ];

    protected $casts = [
        'server_id' => 'integer',
        'user_id' => 'integer',
        'data' => 'array',
        'deleted_at' => 'datetime',
        'restore_until' => 'datetime',
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
