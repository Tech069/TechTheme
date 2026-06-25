<?php

namespace Pterodactyl\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;

class WipeExecution extends Model
{
    protected $table = 'wipe_executions';

    protected $guarded = ['id', self::CREATED_AT, self::UPDATED_AT];

    public static array $validationRules = [
        'server_id' => 'required|integer|exists:servers,id',
        'wipe_files' => 'required|boolean',
        'wipe_databases' => 'required|boolean',
        'wipe_backups' => 'required|boolean',
        'wipe_allocations' => 'required|boolean',
        'result' => 'nullable|array',
        'executed_at' => 'required|date',
    ];

    protected $casts = [
        'server_id' => 'integer',
        'wipe_files' => 'boolean',
        'wipe_databases' => 'boolean',
        'wipe_backups' => 'boolean',
        'wipe_allocations' => 'boolean',
        'result' => 'array',
        'executed_at' => 'datetime',
        self::CREATED_AT => 'datetime',
        self::UPDATED_AT => 'datetime',
    ];

    public function server(): BelongsTo
    {
        return $this->belongsTo(Server::class);
    }
}
