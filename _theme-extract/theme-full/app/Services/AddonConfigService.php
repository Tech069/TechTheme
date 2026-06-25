<?php

namespace Pterodactyl\Services;

use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Pterodactyl\Models\Server;

class AddonConfigService
{
    private const CONFIG_CACHE_TTL = 3600;

    private const ADDON_CONFIG_PATH = 'app/addons';

    public function __construct()
    {
    }

    /**
     * Get the configuration file path for a given addon and server.
     */
    private function getConfigPath(Server $server, string $addon): string
    {
        return storage_path(self::ADDON_CONFIG_PATH . '/' . $server->uuid . '/' . $addon . '.json');
    }

    /**
     * Read addon configuration for a server.
     */
    public function read(Server $server, string $addon): array
    {
        $cacheKey = "addon_config:{$server->id}:$addon";

        return Cache::remember($cacheKey, self::CONFIG_CACHE_TTL, function () use ($server, $addon) {
            $path = $this->getConfigPath($server, $addon);

            if (!File::exists($path)) {
                return [];
            }

            $contents = File::get($path);
            $decoded = json_decode($contents, true);

            if (json_last_error() !== JSON_ERROR_NONE) {
                Log::error('Failed to parse addon config JSON', [
                    'server_id' => $server->id,
                    'addon' => $addon,
                    'error' => json_last_error_msg(),
                ]);

                return [];
            }

            return $decoded;
        });
    }

    /**
     * Write addon configuration for a server.
     */
    public function write(Server $server, string $addon, array $config): bool
    {
        $path = $this->getConfigPath($server, $addon);
        $directory = dirname($path);

        if (!File::isDirectory($directory)) {
            File::makeDirectory($directory, 0755, true, true);
        }

        $json = json_encode($config, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);

        if (json_last_error() !== JSON_ERROR_NONE) {
            Log::error('Failed to encode addon config to JSON', [
                'server_id' => $server->id,
                'addon' => $addon,
                'error' => json_last_error_msg(),
            ]);

            return false;
        }

        File::put($path, $json);

        Cache::forget("addon_config:{$server->id}:$addon");

        return true;
    }

    /**
     * Delete addon configuration for a server.
     */
    public function delete(Server $server, string $addon): bool
    {
        $path = $this->getConfigPath($server, $addon);

        if (!File::exists($path)) {
            return true;
        }

        $deleted = File::delete($path);
        Cache::forget("addon_config:{$server->id}:$addon");

        return $deleted;
    }

    /**
     * Merge values into an existing addon configuration.
     */
    public function merge(Server $server, string $addon, array $updates): bool
    {
        $current = $this->read($server, $addon);
        $merged = array_merge_recursive($current, $updates);

        return $this->write($server, $addon, $merged);
    }

    /**
     * List all addons configured for a server.
     */
    public function listAddons(Server $server): array
    {
        $directory = storage_path(self::ADDON_CONFIG_PATH . '/' . $server->uuid);

        if (!File::isDirectory($directory)) {
            return [];
        }

        $files = File::files($directory);

        return array_map(function ($file) {
            return pathinfo($file->getFilename(), PATHINFO_FILENAME);
        }, $files);
    }

    /**
     * Check if an addon config exists for a server.
     */
    public function exists(Server $server, string $addon): bool
    {
        return File::exists($this->getConfigPath($server, $addon));
    }
}
