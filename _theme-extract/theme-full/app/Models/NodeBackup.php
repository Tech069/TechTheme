<?php

namespace Pterodactyl\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;

class NodeBackup extends Model
{
    protected $table = 'node_backups';

    protected $guarded = ['id', self::CREATED_AT, self::UPDATED_AT];

    public static array $validationRules = [
        'node_id' => 'required|integer|exists:nodes,id',
        'location_id' => 'required|integer|exists:locations,id',
        'backup_path' => 'required|string|max:191',
        'schedule' => 'nullable|string|max:191',
    ];

    protected $casts = [
        'node_id' => 'integer',
        'location_id' => 'integer',
        self::CREATED_AT => 'datetime',
        self::UPDATED_AT => 'datetime',
    ];

    public function node(): BelongsTo
    {
        return $this->belongsTo(Node::class);
    }

    public function location(): BelongsTo
    {
        return $this->belongsTo(Location::class);
    }
}
