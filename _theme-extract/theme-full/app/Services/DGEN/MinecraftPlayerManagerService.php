<?php

namespace Pterodactyl\Services\DGEN;

use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Cache;
use Pterodactyl\Models\Server;

class MinecraftPlayerManagerService
{
    private const WHITELIST_CACHE_PREFIX = 'mc_whitelist:';

    private const BAN_LIST_CACHE_PREFIX = 'mc_banlist:';

    private const CACHE_TTL = 300;

    public function __construct()
    {
    }

    /**
     * Get the whitelist for a server.
     */
    public function getWhitelist(Server $server): array
    {
        $cacheKey = self::WHITELIST_CACHE_PREFIX . $server->id;

        return Cache::remember($cacheKey, self::CACHE_TTL, function () use ($server) {
            $configPath = $this->getWhitelistPath($server);

            if (!file_exists($configPath)) {
                return [];
            }

            $contents = file_get_contents($configPath);
            $decoded = json_decode($contents, true);

            if (!is_array($decoded)) {
                return [];
            }

            return $decoded;
        });
    }

    /**
     * Add a player to the whitelist.
     */
    public function addToWhitelist(Server $server, string $playerName, ?string $uuid = null): bool
    {
        $whitelist = $this->getWhitelist($server);

        // Check if already whitelisted
        foreach ($whitelist as $entry) {
            if (($entry['name'] ?? '') === $playerName) {
                return false;
            }
        }

        $whitelist[] = [
            'name' => $playerName,
            'uuid' => $uuid,
            'added_at' => now()->toISOString(),
        ];

        return $this->saveWhitelist($server, $whitelist);
    }

    /**
     * Remove a player from the whitelist.
     */
    public function removeFromWhitelist(Server $server, string $playerName): bool
    {
        $whitelist = $this->getWhitelist($server);
        $filtered = array_filter($whitelist, fn ($entry) => ($entry['name'] ?? '') !== $playerName);
        $filtered = array_values($filtered);

        return $this->saveWhitelist($server, $filtered);
    }

    /**
     * Check if a player is whitelisted.
     */
    public function isWhitelisted(Server $server, string $playerName): bool
    {
        $whitelist = $this->getWhitelist($server);

        foreach ($whitelist as $entry) {
            if (($entry['name'] ?? '') === $playerName) {
                return true;
            }
        }

        return false;
    }

    /**
     * Get the ban list for a server.
     */
    public function getBanList(Server $server): array
    {
        $cacheKey = self::BAN_LIST_CACHE_PREFIX . $server->id;

        return Cache::remember($cacheKey, self::CACHE_TTL, function () use ($server) {
            $configPath = $this->getBanListPath($server);

            if (!file_exists($configPath)) {
                return [];
            }

            $contents = file_get_contents($configPath);
            $decoded = json_decode($contents, true);

            if (!is_array($decoded)) {
                return [];
            }

            return $decoded;
        });
    }

    /**
     * Ban a player from the server.
     */
    public function banPlayer(Server $server, string $playerName, ?string $reason = null, ?string $bannedBy = null): bool
    {
        $banList = $this->getBanList($server);

        // Check if already banned
        foreach ($banList as $entry) {
            if (($entry['name'] ?? '') === $playerName) {
                return false;
            }
        }

        $banList[] = [
            'name' => $playerName,
            'reason' => $reason ?? 'No reason specified',
            'banned_by' => $bannedBy ?? 'System',
            'banned_at' => now()->toISOString(),
        ];

        $result = $this->saveBanList($server, $banList);

        if ($result) {
            // Execute the ban command via the server console
            $this->executeCommand($server, "ban $playerName " . ($reason ?? 'Banned by administrator'));
        }

        return $result;
    }

    /**
     * Unban a player from the server.
     */
    public function unbanPlayer(Server $server, string $playerName): bool
    {
        $banList = $this->getBanList($server);
        $filtered = array_filter($banList, fn ($entry) => ($entry['name'] ?? '') !== $playerName);
        $filtered = array_values($filtered);

        $result = $this->saveBanList($server, $filtered);

        if ($result) {
            $this->executeCommand($server, "pardon $playerName");
        }

        return $result;
    }

    /**
     * Check if a player is banned.
     */
    public function isBanned(Server $server, string $playerName): bool
    {
        $banList = $this->getBanList($server);

        foreach ($banList as $entry) {
            if (($entry['name'] ?? '') === $playerName) {
                return true;
            }
        }

        return false;
    }

    /**
     * Kick a player from the server.
     */
    public function kickPlayer(Server $server, string $playerName, ?string $reason = null): bool
    {
        return $this->executeCommand($server, "kick $playerName " . ($reason ?? 'Kicked by administrator'));
    }

    /**
     * Execute a command on the server via the Wings agent.
     */
    private function executeCommand(Server $server, string $command): bool
    {
        try {
            $node = $server->node;
            $url = $node->getConnectionAddress() . '/api/servers/' . $server->uuid . '/command';

            $response = \Illuminate\Support\Facades\Http::withHeaders([
                'Authorization' => 'Bearer ' . $node->getDecryptedKey(),
                'Content-Type' => 'application/json',
            ])->post($url, ['command' => $command]);

            return $response->successful();
        } catch (\Exception $exception) {
            Log::error('Failed to execute server command', [
                'server_id' => $server->id,
                'command' => $command,
                'error' => $exception->getMessage(),
            ]);

            return false;
        }
    }

    /**
     * Get the whitelist file path.
     */
    private function getWhitelistPath(Server $server): string
    {
        return storage_path('minecraft/whitelist/' . $server->uuid . '.json');
    }

    /**
     * Get the ban list file path.
     */
    private function getBanListPath(Server $server): string
    {
        return storage_path('minecraft/banlist/' . $server->uuid . '.json');
    }

    /**
     * Save the whitelist to file.
     */
    private function saveWhitelist(Server $server, array $whitelist): bool
    {
        $path = $this->getWhitelistPath($server);
        $directory = dirname($path);

        if (!is_dir($directory)) {
            mkdir($directory, 0755, true);
        }

        $result = file_put_contents($path, json_encode($whitelist, JSON_PRETTY_PRINT));

        if ($result !== false) {
            Cache::forget(self::WHITELIST_CACHE_PREFIX . $server->id);

            return true;
        }

        return false;
    }

    /**
     * Save the ban list to file.
     */
    private function saveBanList(Server $server, array $banList): bool
    {
        $path = $this->getBanListPath($server);
        $directory = dirname($path);

        if (!is_dir($directory)) {
            mkdir($directory, 0755, true);
        }

        $result = file_put_contents($path, json_encode($banList, JSON_PRETTY_PRINT));

        if ($result !== false) {
            Cache::forget(self::BAN_LIST_CACHE_PREFIX . $server->id);

            return true;
        }

        return false;
    }
}
