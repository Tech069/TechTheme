<?php

namespace Pterodactyl\Services;

use Illuminate\Support\Facades\Log;
use Pterodactyl\Models\Server;
use Pterodactyl\Models\Egg;

class HyperV2AddonDefaultsService
{
    private const DEFAULT_ADDON_CONFIGS = [
        'discord_integration' => [
            'enabled' => false,
            'webhook_url' => '',
            'server_status_channel_id' => '',
            'player_count_channel_id' => '',
            'notify_on_start' => true,
            'notify_on_stop' => true,
            'notify_on_crash' => true,
            'notify_on_backup' => false,
            'embed_color' => 5814783,
            'show_player_list' => true,
        ],
        'auto_backup' => [
            'enabled' => false,
            'interval_hours' => 24,
            'max_backups' => 5,
            'include_databases' => false,
            'notify_on_complete' => true,
            'exclude_patterns' => ['*.log', '*.tmp'],
        ],
        'resource_monitoring' => [
            'enabled' => true,
            'cpu_alert_threshold' => 90,
            'memory_alert_threshold' => 85,
            'disk_alert_threshold' => 90,
            'check_interval_seconds' => 60,
            'alert_cooldown_minutes' => 30,
        ],
        'auto_restart' => [
            'enabled' => false,
            'schedule' => '0 4 * * *',
            'warning_minutes' => 10,
            'notify_users' => true,
        ],
        'rcon' => [
            'enabled' => false,
            'host' => '127.0.0.1',
            'port' => 25575,
            'password' => '',
            'type' => 'minecraft',
        ],
        'performance_tweaks' => [
            'enabled' => false,
            'gc_interval_minutes' => 30,
            'optimize_io' => true,
            'cache_size_mb' => 256,
        ],
    ];

    private const EGG_SPECIFIC_DEFAULTS = [
        'minecraft' => [
            'rcon' => ['enabled' => true, 'type' => 'minecraft', 'port' => 25575],
            'auto_backup' => ['include_databases' => true],
        ],
        'source_engine' => [
            'rcon' => ['enabled' => true, 'type' => 'source', 'port' => 27015],
            'auto_restart' => ['enabled' => true],
        ],
        'rust' => [
            'auto_backup' => ['exclude_patterns' => ['*.log', '*.tmp', 'oxide/config/*.json']],
            'resource_monitoring' => ['cpu_alert_threshold' => 95],
        ],
        'fivem' => [
            'rcon' => ['enabled' => true, 'type' => 'fivem', 'port' => 30120],
        ],
    ];

    public function __construct()
    {
    }

    /**
     * Get default addon configurations for a server based on its egg.
     */
    public function getDefaults(Server $server): array
    {
        $defaults = self::DEFAULT_ADDON_CONFIGS;

        $eggCategory = $this->detectEggCategory($server);

        if ($eggCategory && isset(self::EGG_SPECIFIC_DEFAULTS[$eggCategory])) {
            $defaults = $this->mergeDefaults($defaults, self::EGG_SPECIFIC_DEFAULTS[$eggCategory]);
        }

        return $defaults;
    }

    /**
     * Get defaults for a specific addon.
     */
    public function getAddonDefault(string $addon): array
    {
        return self::DEFAULT_ADDON_CONFIGS[$addon] ?? [];
    }

    /**
     * Get defaults for a specific addon applied to a server.
     */
    public function getAddonDefaultForServer(Server $server, string $addon): array
    {
        $defaults = $this->getAddonDefault($addon);
        $eggCategory = $this->detectEggCategory($server);

        if ($eggCategory && isset(self::EGG_SPECIFIC_DEFAULTS[$eggCategory][$addon])) {
            $defaults = array_merge($defaults, self::EGG_SPECIFIC_DEFAULTS[$eggCategory][$addon]);
        }

        return $defaults;
    }

    /**
     * Detect the egg category from the server's egg name.
     */
    private function detectEggCategory(Server $server): ?string
    {
        try {
            $egg = Egg::find($server->egg_id);
            if (!$egg) {
                return null;
            }

            $name = strtolower($egg->name);

            if (str_contains($name, 'minecraft') || str_contains($name, 'paper') || str_contains($name, 'spigot') || str_contains($name, 'forge')) {
                return 'minecraft';
            }
            if (str_contains($name, 'source') || str_contains($name, 'gmod') || str_contains($name, 'cs2') || str_contains($name, 'valve')) {
                return 'source_engine';
            }
            if (str_contains($name, 'rust')) {
                return 'rust';
            }
            if (str_contains($name, 'fivem') || str_contains($name, 'redm')) {
                return 'fivem';
            }

            return null;
        } catch (\Exception $exception) {
            Log::warning('Failed to detect egg category', [
                'server_id' => $server->id,
                'error' => $exception->getMessage(),
            ]);

            return null;
        }
    }

    /**
     * Merge egg-specific defaults into the base defaults.
     */
    private function mergeDefaults(array $base, array $overrides): array
    {
        foreach ($overrides as $addon => $overrideValues) {
            if (isset($base[$addon]) && is_array($base[$addon])) {
                $base[$addon] = array_merge($base[$addon], $overrideValues);
            }
        }

        return $base;
    }

    /**
     * Get all available addon keys.
     */
    public function getAvailableAddons(): array
    {
        return array_keys(self::DEFAULT_ADDON_CONFIGS);
    }
}
