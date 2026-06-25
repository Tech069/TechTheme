<?php

namespace Pterodactyl\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AdminAuditLog extends Model
{
    protected $table = 'admin_audit_logs';

    protected $guarded = ['id', self::CREATED_AT, self::UPDATED_AT];

    public static array $validationRules = [
        'user_id' => 'required|integer|exists:users,id',
        'action' => 'required|string|max:191',
        'data' => 'nullable|array',
        'ip_address' => 'nullable|string|max:45',
    ];

    protected $casts = [
        'user_id' => 'integer',
        'data' => 'array',
        self::CREATED_AT => 'datetime',
        self::UPDATED_AT => 'datetime',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
