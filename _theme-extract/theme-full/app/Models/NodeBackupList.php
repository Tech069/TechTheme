<?php

namespace Pterodactyl\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;

class NodeBackupList extends Model
{
    protected $table = 'node_backup_lists';

    protected $guarded = ['id', self::CREATED_AT, self::UPDATED_AT];

    public static array $validationRules = [
        'backup_id' => 'required|integer|exists:node_backups,id',
        'server_id' => 'required|integer|exists:servers,id',
        'size' => 'nullable|integer|min:0',
        'status' => 'required|string|max:191',
    ];

    protected $casts = [
        'backup_id' => 'integer',
        'server_id' => 'integer',
        'size' => 'integer',
        self::CREATED_AT => 'datetime',
        self::UPDATED_AT => 'datetime',
    ];

    public function backup(): BelongsTo
    {
        return $this->belongsTo(NodeBackup::class);
    }

    public function server(): BelongsTo
    {
        return $this->belongsTo(Server::class);
    }
}
