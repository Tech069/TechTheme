<?php

namespace Pterodactyl\Models\DGEN;

use Pterodactyl\Models\Model;
use Pterodactyl\Models\Server;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ServerSplitterWhitelist extends Model
{
    protected $table = 'server_splitter_whitelists';

    protected $fillable = [
        'server_id',
        'max_children',
        'is_active',
    ];

    protected $guarded = ['id', self::CREATED_AT, self::UPDATED_AT];

    public static array $validationRules = [
        'server_id' => 'required|integer|exists:servers,id',
        'max_children' => 'required|integer|min:0',
        'is_active' => 'sometimes|boolean',
    ];

    protected $casts = [
        'server_id' => 'integer',
        'max_children' => 'integer',
        'is_active' => 'boolean',
        self::CREATED_AT => 'datetime',
        self::UPDATED_AT => 'datetime',
    ];

    public function server(): BelongsTo
    {
        return $this->belongsTo(Server::class);
    }
}
