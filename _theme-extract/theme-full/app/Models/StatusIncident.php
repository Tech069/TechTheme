<?php

namespace Pterodactyl\Models;

class StatusIncident extends Model
{
    protected $table = 'status_incidents';

    protected $fillable = [
        'title',
        'message',
        'severity',
        'status',
        'impact',
    ];

    protected $guarded = ['id', self::CREATED_AT, self::UPDATED_AT];

    public static array $validationRules = [
        'title' => 'required|string|max:191',
        'message' => 'required|string',
        'severity' => 'required|string|max:191',
        'status' => 'required|string|max:191',
        'impact' => 'nullable|array',
    ];

    protected $casts = [
        'impact' => 'array',
        self::CREATED_AT => 'datetime',
        self::UPDATED_AT => 'datetime',
    ];
}
