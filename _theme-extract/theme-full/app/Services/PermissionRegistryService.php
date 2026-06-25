<?php

namespace Pterodactyl\Services;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Pterodactyl\Models\Permission;

class PermissionRegistryService
{
    private const CACHE_KEY = 'permission_registry';

    private const CACHE_TTL = 3600;

    /**
     * Custom DGEN-specific permissions that extend the base Pterodactyl permissions.
     */
    private const DGEN_PERMISSIONS = [
        'dgen' => [
            'description' => 'Permissions for DGEN-specific features and management.',
            'keys' => [
                'server-split' => 'Allows a user to split servers and create child instances.',
                'server-wipe' => 'Allows a user to wipe server data and files.',
                'fastdl-manage' => 'Allows a user to manage FastDL configurations.',
                'subdomain-manage' => 'Allows a user to manage subdomain assignments.',
                'reverse-proxy-manage' => 'Allows a user to manage reverse proxy configurations.',
                'auto-suspend-manage' => 'Allows a user to configure auto-suspension rules.',
                'ddos-alerts' => 'Allows a user to view and manage DDoS alerts.',
                'billing-read' => 'Allows a user to view billing information.',
                'billing-manage' => 'Allows a user to manage billing and orders.',
                'store-manage' => 'Allows a user to manage the store and products.',
                'discord-manage' => 'Allows a user to manage Discord bot integration.',
                'node-status' => 'Allows a user to view node status and metrics.',
                'command-history' => 'Allows a user to view command history.',
                'player-manager' => 'Allows a user to manage Minecraft players.',
                'security-alerts' => 'Allows a user to view and manage security alerts.',
            ],
        ],
    ];

    public function __construct()
    {
    }

    /**
     * Get all registered permissions including base and DGEN permissions.
     */
    public function getAll(): array
    {
        return Cache::remember(self::CACHE_KEY, self::CACHE_TTL, function () {
            $basePermissions = Permission::permissions()->toArray();

            return array_merge($basePermissions, self::DGEN_PERMISSIONS);
        });
    }

    /**
     * Get only DGEN-specific permissions.
     */
    public function getDgenPermissions(): array
    {
        return self::DGEN_PERMISSIONS;
    }

    /**
     * Check if a permission key exists.
     */
    public function exists(string $permission): bool
    {
        $allPermissions = $this->getAll();

        foreach ($allPermissions as $group) {
            if (isset($group['keys'][$permission])) {
                return true;
            }
        }

        return false;
    }

    /**
     * Get all permission keys as a flat array.
     */
    public function getFlatKeys(): array
    {
        $allPermissions = $this->getAll();
        $keys = [];

        foreach ($allPermissions as $group => $data) {
            if (isset($data['keys'])) {
                foreach ($data['keys'] as $key => $description) {
                    $keys[] = "$group.$key";
                }
            }
        }

        return $keys;
    }

    /**
     * Get the description for a specific permission.
     */
    public function getDescription(string $permission): ?string
    {
        $allPermissions = $this->getAll();

        foreach ($allPermissions as $group) {
            if (isset($group['keys'][$permission])) {
                return $group['keys'][$permission];
            }
        }

        return null;
    }

    /**
     * Refresh the permission cache.
     */
    public function refreshCache(): void
    {
        Cache::forget(self::CACHE_KEY);
    }

    /**
     * Register a custom permission group.
     */
    public function registerGroup(string $name, array $definition): void
    {
        self::DGEN_PERMISSIONS[$name] = $definition;
        $this->refreshCache();
    }
}
