<?php

namespace Pterodactyl\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;

class WipeSchedule extends Model
{
    protected $table = 'wipe_schedules';

    protected $guarded = ['id', self::CREATED_AT, self::UPDATED_AT];

    public static array $validationRules = [
        'server_id' => 'required|integer|exists:servers,id',
        'execute_at' => 'required|date',
        'options' => 'nullable|array',
        'status' => 'required|string|in:pending,completed,failed,cancelled',
    ];

    protected $casts = [
        'server_id' => 'integer',
        'execute_at' => 'datetime',
        'options' => 'array',
        self::CREATED_AT => 'datetime',
        self::UPDATED_AT => 'datetime',
    ];

    public function server(): BelongsTo
    {
        return $this->belongsTo(Server::class);
    }
}
