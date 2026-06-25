<?php

namespace Pterodactyl\Models\DGEN;

use Pterodactyl\Models\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Subcategory extends Model
{
    protected $table = 'subcategories';

    protected $fillable = [
        'category_id',
        'name',
        'slug',
    ];

    protected $guarded = ['id', self::CREATED_AT, self::UPDATED_AT];

    public static array $validationRules = [
        'category_id' => 'required|integer|exists:game_categories,id',
        'name' => 'required|string|max:191',
        'slug' => 'required|string|max:191|unique:subcategories,slug',
    ];

    protected $casts = [
        'category_id' => 'integer',
        self::CREATED_AT => 'datetime',
        self::UPDATED_AT => 'datetime',
    ];

    public function category(): BelongsTo
    {
        return $this->belongsTo(GameCategory::class, 'category_id');
    }
}
