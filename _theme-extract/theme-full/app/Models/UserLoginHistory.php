<?php

namespace Pterodactyl\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;

class UserLoginHistory extends Model
{
    protected $table = 'user_login_histories';

    protected $guarded = ['id', self::CREATED_AT, self::UPDATED_AT];

    public static array $validationRules = [
        'user_id' => 'required|integer|exists:users,id',
        'ip_address' => 'required|string|max:45',
        'user_agent' => 'nullable|string|max:191',
        'success' => 'required|boolean',
        'location' => 'nullable|string|max:191',
    ];

    protected $casts = [
        'user_id' => 'integer',
        'success' => 'boolean',
        self::CREATED_AT => 'datetime',
        self::UPDATED_AT => 'datetime',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
