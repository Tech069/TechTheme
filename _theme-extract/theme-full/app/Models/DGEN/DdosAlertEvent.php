<?php

namespace Pterodactyl\Models\DGEN;

use Pterodactyl\Models\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Pterodactyl\Models\Node;
use Pterodactyl\Models\Server;

class DdosAlertEvent extends Model
{
    protected $table = 'ddos_alert_events';

    protected $guarded = ['id', self::CREATED_AT, self::UPDATED_AT];

    public static array $validationRules = [
        'node_id' => 'required|integer|exists:nodes,id',
        'server_id' => 'nullable|integer|exists:servers,id',
        'severity' => 'required|string|max:191',
        'detected_at' => 'required|date',
        'resolved_at' => 'nullable|date',
        'details' => 'nullable|array',
    ];

    protected $casts = [
        'node_id' => 'integer',
        'server_id' => 'integer',
        'details' => 'array',
        'detected_at' => 'datetime',
        'resolved_at' => 'datetime',
        self::CREATED_AT => 'datetime',
        self::UPDATED_AT => 'datetime',
    ];

    public function node(): BelongsTo
    {
        return $this->belongsTo(Node::class);
    }

    public function server(): BelongsTo
    {
        return $this->belongsTo(Server::class);
    }
}
