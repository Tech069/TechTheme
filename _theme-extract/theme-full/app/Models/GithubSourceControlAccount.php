<?php

namespace Pterodactyl\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;

class GithubSourceControlAccount extends Model
{
    protected $table = 'github_source_control_accounts';

    protected $fillable = [
        'user_id',
        'github_id',
        'access_token',
        'username',
    ];

    protected $guarded = ['id', self::CREATED_AT, self::UPDATED_AT];

    protected $hidden = ['access_token'];

    public static array $validationRules = [
        'user_id' => 'required|integer|exists:users,id',
        'github_id' => 'required|string|max:191',
        'access_token' => 'required|string|max:191',
        'username' => 'required|string|max:191',
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
