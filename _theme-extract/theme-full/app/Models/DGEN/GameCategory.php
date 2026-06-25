<?php

namespace Pterodactyl\Models\DGEN;

use Pterodactyl\Models\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class GameCategory extends Model
{
    protected $table = 'game_categories';

    protected $fillable = [
        'name',
        'slug',
        'icon',
        'position',
    ];

    protected $guarded = ['id', self::CREATED_AT, self::UPDATED_AT];

    public static array $validationRules = [
        'name' => 'required|string|max:191',
        'slug' => 'required|string|max:191|unique:game_categories,slug',
        'icon' => 'nullable|string|max:191',
        'position' => 'required|integer|min:0',
    ];

    protected $casts = [
        'position' => 'integer',
        self::CREATED_AT => 'datetime',
        self::UPDATED_AT => 'datetime',
    ];

    public function games(): HasMany
    {
        return $this->hasMany(Game::class, 'category_id');
    }

    public function subcategories(): HasMany
    {
        return $this->hasMany(Subcategory::class, 'category_id');
    }
}
