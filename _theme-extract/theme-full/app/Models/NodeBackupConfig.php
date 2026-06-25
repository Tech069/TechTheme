<?php

namespace Pterodactyl\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;

class NodeBackupConfig extends Model
{
    protected $table = 'node_backup_configs';

    protected $guarded = ['id', self::CREATED_AT, self::UPDATED_AT];

    public static array $validationRules = [
        'node_id' => 'required|integer|exists:nodes,id',
        'max_backups' => 'required|integer|min:0',
        'retention_days' => 'required|integer|min:0',
    ];

    protected $casts = [
        'node_id' => 'integer',
        'max_backups' => 'integer',
        'retention_days' => 'integer',
        self::CREATED_AT => 'datetime',
        self::UPDATED_AT => 'datetime',
    ];

    public function node(): BelongsTo
    {
        return $this->belongsTo(Node::class);
    }
}
