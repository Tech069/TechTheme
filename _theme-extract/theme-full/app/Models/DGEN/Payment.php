<?php

namespace Pterodactyl\Models\DGEN;

use Pterodactyl\Models\Model;
use Pterodactyl\Models\User;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Payment extends Model
{
    protected $table = 'payments';

    protected $fillable = [
        'user_id',
        'amount',
        'currency',
        'status',
        'gateway',
        'transaction_id',
    ];

    protected $guarded = ['id', self::CREATED_AT, self::UPDATED_AT];

    public static array $validationRules = [
        'user_id' => 'required|integer|exists:users,id',
        'amount' => 'required|numeric|min:0',
        'currency' => 'required|string|max:3',
        'status' => 'required|string|max:191',
        'gateway' => 'required|string|max:191',
        'transaction_id' => 'nullable|string|max:191',
    ];

    protected $casts = [
        'user_id' => 'integer',
        'amount' => 'float',
        self::CREATED_AT => 'datetime',
        self::UPDATED_AT => 'datetime',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
