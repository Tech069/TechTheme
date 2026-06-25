<?php

namespace Pterodactyl\Models\DGEN;

use Pterodactyl\Models\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Game extends Model
{
    protected $table = 'games';

    protected $fillable = [
        'name',
        'slug',
        'category_id',
        'icon',
        'available',
    ];

    protected $guarded = ['id', self::CREATED_AT, self::UPDATED_AT];

    public static array $validationRules = [
        'name' => 'required|string|max:191',
        'slug' => 'required|string|max:191|unique:games,slug',
        'category_id' => 'required|integer|exists:game_categories,id',
        'icon' => 'nullable|string|max:191',
        'available' => 'sometimes|boolean',
    ];

    protected $casts = [
        'category_id' => 'integer',
        'available' => 'boolean',
        self::CREATED_AT => 'datetime',
        self::UPDATED_AT => 'datetime',
    ];

    public function category(): BelongsTo
    {
        return $this->belongsTo(GameCategory::class, 'category_id');
    }

    public function subcategories(): HasMany
    {
        return $this->hasMany(Subcategory::class, 'category_id');
    }
}
