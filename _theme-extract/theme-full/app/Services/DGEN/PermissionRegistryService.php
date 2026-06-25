<?php

namespace Pterodactyl\Services\DGEN;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

class PermissionRegistryService
{
    private const CACHE_KEY = 'dgen:permission_registry';

    private const CACHE_TTL = 3600;

    private const DGEN_PERMISSIONS = [
        'server-split' => [
            'description' => 'Split servers and create child instances.',
            'group' => 'server_management',
        ],
        'server-wipe' => [
            'description' => 'Wipe server data and files.',
            'group' => 'server_management',
        ],
        'fastdl-manage' => [
            'description' => 'Manage FastDL configurations.',
            'group' => 'network',
        ],
        'subdomain-manage' => [
            'description' => 'Manage subdomain assignments.',
            'group' => 'network',
        ],
        'reverse-proxy-manage' => [
            'description' => 'Manage reverse proxy configurations.',
            'group' => 'network',
        ],
        'auto-suspend-manage' => [
            'description' => 'Configure auto-suspension rules.',
            'group' => 'server_management',
        ],
        'ddos-alerts' => [
            'description' => 'View and manage DDoS alerts.',
            'group' => 'security',
        ],
        'billing-read' => [
            'description' => 'View billing information.',
            'group' => 'billing',
        ],
        'billing-manage' => [
            'description' => 'Manage billing and orders.',
            'group' => 'billing',
        ],
        'store-manage' => [
            'description' => 'Manage the store and products.',
            'group' => 'billing',
        ],
        'discord-manage' => [
            'description' => 'Manage Discord bot integration.',
            'group' => 'integrations',
        ],
        'node-status' => [
            'description' => 'View node status and metrics.',
            'group' => 'monitoring',
        ],
        'command-history' => [
            'description' => 'View command history.',
            'group' => 'monitoring',
        ],
        'player-manager' => [
            'description' => 'Manage Minecraft players.',
            'group' => 'server_management',
        ],
        'security-alerts' => [
            'description' => 'View and manage security alerts.',
            'group' => 'security',
        ],
        'curseforge-access' => [
            'description' => 'Access CurseForge API for mod searches.',
            'group' => 'integrations',
        ],
        'nbt-editor' => [
            'description' => 'Read and write NBT data files.',
            'group' => 'server_management',
        ],
        'fivem-manage' => [
            'description' => 'Manage FiveM server utilities.',
            'group' => 'server_management',
        ],
    ];

    public function __construct()
    {
    }

    /**
     * Get all DGEN permissions.
     */
    public function getAll(): array
    {
        return Cache::remember(self::CACHE_KEY, self::CACHE_TTL, fn () => self::DGEN_PERMISSIONS);
    }

    /**
     * Get permissions grouped by category.
     */
    public function getGrouped(): array
    {
        $all = $this->getAll();
        $grouped = [];

        foreach ($all as $key => $data) {
            $group = $data['group'] ?? 'other';
            $grouped[$group][$key] = $data;
        }

        return $grouped;
    }

    /**
     * Check if a permission exists.
     */
    public function exists(string $permission): bool
    {
        return isset(self::DGEN_PERMISSIONS[$permission]);
    }

    /**
     * Get permission details.
     */
    public function get(string $permission): ?array
    {
        return self::DGEN_PERMISSIONS[$permission] ?? null;
    }

    /**
     * Get all permission keys as a flat array.
     */
    public function getKeys(): array
    {
        return array_keys(self::DGEN_PERMISSIONS);
    }

    /**
     * Get all unique groups.
     */
    public function getGroups(): array
    {
        return array_unique(array_column(self::DGEN_PERMISSIONS, 'group'));
    }

    /**
     * Refresh the cache.
     */
    public function refreshCache(): void
    {
        Cache::forget(self::CACHE_KEY);
    }
}
