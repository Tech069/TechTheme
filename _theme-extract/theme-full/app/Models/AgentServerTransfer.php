<?php

namespace Pterodactyl\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AgentServerTransfer extends Model
{
    protected $table = 'agent_server_transfers';

    protected $guarded = ['id', self::CREATED_AT, self::UPDATED_AT];

    public static array $validationRules = [
        'server_id' => 'required|integer|exists:servers,id',
        'from_node_id' => 'required|integer|exists:nodes,id',
        'to_node_id' => 'required|integer|exists:nodes,id',
        'status' => 'required|string|max:191',
    ];

    protected $casts = [
        'server_id' => 'integer',
        'from_node_id' => 'integer',
        'to_node_id' => 'integer',
        self::CREATED_AT => 'datetime',
        self::UPDATED_AT => 'datetime',
    ];

    public function server(): BelongsTo
    {
        return $this->belongsTo(Server::class);
    }

    public function fromNode(): BelongsTo
    {
        return $this->belongsTo(Node::class, 'from_node_id');
    }

    public function toNode(): BelongsTo
    {
        return $this->belongsTo(Node::class, 'to_node_id');
    }
}
