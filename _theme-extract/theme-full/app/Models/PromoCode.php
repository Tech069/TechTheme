<?php

namespace Pterodactyl\Models;

class PromoCode extends Model
{
    protected $table = 'promo_codes';

    protected $fillable = [
        'code',
        'discount_percent',
        'max_uses',
        'expires_at',
    ];

    protected $guarded = ['id', self::CREATED_AT, self::UPDATED_AT];

    public static array $validationRules = [
        'code' => 'required|string|max:191|unique:promo_codes,code',
        'discount_percent' => 'required|numeric|min:1|max:100',
        'max_uses' => 'nullable|integer|min:1',
        'expires_at' => 'nullable|date',
    ];

    protected $casts = [
        'discount_percent' => 'float',
        'max_uses' => 'integer',
        'expires_at' => 'datetime',
        self::CREATED_AT => 'datetime',
        self::UPDATED_AT => 'datetime',
    ];
}
