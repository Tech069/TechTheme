<?php

namespace Pterodactyl\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;

class UserIntegration extends Model
{
    protected $table = 'user_integrations';

    protected $fillable = [
        'user_id',
        'provider',
        'provider_id',
        'access_token',
        'refresh_token',
    ];

    protected $guarded = ['id', self::CREATED_AT, self::UPDATED_AT];

    protected $hidden = ['access_token', 'refresh_token'];

    public static array $validationRules = [
        'user_id' => 'required|integer|exists:users,id',
        'provider' => 'required|string|max:191',
        'provider_id' => 'required|string|max:191',
        'access_token' => 'required|string|max:191',
        'refresh_token' => 'nullable|string|max:191',
    ];

    protected $casts = [
        'user_id' => 'integer',
        self::CREATED_AT => 'datetime',
        self::UPDATED_AT => 'datetime',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
