<?php

namespace Pterodactyl\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ServerImport extends Model
{
    protected $table = 'server_imports';

    protected $guarded = ['id', self::CREATED_AT, self::UPDATED_AT];

    public static array $validationRules = [
        'user_id' => 'required|integer|exists:users,id',
        'server_id' => 'nullable|integer|exists:servers,id',
        'source_type' => 'required|string|max:191',
        'status' => 'required|string|max:191',
    ];

    protected $casts = [
        'user_id' => 'integer',
        'server_id' => 'integer',
        self::CREATED_AT => 'datetime',
        self::UPDATED_AT => 'datetime',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function server(): BelongsTo
    {
        return $this->belongsTo(Server::class);
    }
}
