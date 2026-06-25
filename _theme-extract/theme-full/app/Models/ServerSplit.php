<?php

namespace Pterodactyl\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ServerSplit extends Model
{
    protected $table = 'server_splits';

    protected $guarded = ['id', self::CREATED_AT, self::UPDATED_AT];

    public static array $validationRules = [
        'parent_server_id' => 'required|integer|exists:servers,id',
        'child_server_id' => 'required|integer|exists:servers,id',
        'split_index' => 'required|integer|min:0',
        'original_resource' => 'nullable|string',
    ];

    protected $casts = [
        'parent_server_id' => 'integer',
        'child_server_id' => 'integer',
        'split_index' => 'integer',
        self::CREATED_AT => 'datetime',
        self::UPDATED_AT => 'datetime',
    ];

    public function parentServer(): BelongsTo
    {
        return $this->belongsTo(Server::class, 'parent_server_id');
    }

    public function childServer(): BelongsTo
    {
        return $this->belongsTo(Server::class, 'child_server_id');
    }
}
