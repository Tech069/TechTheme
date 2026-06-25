<?php

namespace Pterodactyl\Services\DGEN;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Cache;
use Pterodactyl\Models\Server;

class FiveMUtilsService
{
    private const QUERY_TIMEOUT = 5;

    private const CACHE_TTL = 30;

    private const FIVEM_INFO_ENDPOINT = 'http://%s:%d/info.json';
    private const FIVEM_PLAYERS_ENDPOINT = 'http://%s:%d/players.json';
    private const FIVEM_DYNAMIC_ENDPOINT = 'http://%s:%d/dynamic.json';
    private const FIVEM_PURGE_ENDPOINT = 'http://%s:%d/purge';

    public function __construct()
    {
    }

    /**
     * Query a FiveM server for its status and information.
     */
    public function queryServer(string $host, int $port = 30120): ?array
    {
        $cacheKey = "fivem_query:{$host}:{$port}";

        return Cache::remember($cacheKey, self::CACHE_TTL, function () use ($host, $port) {
            try {
                $infoUrl = sprintf(self::FIVEM_INFO_ENDPOINT, $host, $port);
                $response = Http::timeout(self::QUERY_TIMEOUT)->get($infoUrl);

                if (!$response->successful()) {
                    return null;
                }

                $info = $response->json();

                // Get player list
                $playersUrl = sprintf(self::FIVEM_PLAYERS_ENDPOINT, $host, $port);
                $playersResponse = Http::timeout(self::QUERY_TIMEOUT)->get($playersUrl);
                $players = $playersResponse->successful() ? $playersResponse->json() : [];

                return [
                    'online' => true,
                    'hostname' => $info['hostname'] ?? 'Unknown',
                    'description' => $info['description'] ?? '',
                    'gametype' => $info['gametype'] ?? '',
                    'mapname' => $info['mapName'] ?? '',
                    'version' => $info['version'] ?? '',
                    'resource_count' => $info['resources'] ?? 0,
                    'player_count' => count($players),
                    'max_players' => $info['sv_maxClients'] ?? 32,
                    'players' => array_map(fn ($p) => [
                        'id' => $p['id'] ?? 0,
                        'name' => $p['name'] ?? 'Unknown',
                        'ping' => $p['ping'] ?? 0,
                    ], $players),
                ];
            } catch (\Exception $exception) {
                Log::warning('FiveM server query failed', [
                    'host' => $host,
                    'port' => $port,
                    'error' => $exception->getMessage(),
                ]);

                return null;
            }
        });
    }

    /**
     * Get player list from a FiveM server.
     */
    public function getPlayers(string $host, int $port = 30120): array
    {
        try {
            $url = sprintf(self::FIVEM_PLAYERS_ENDPOINT, $host, $port);
            $response = Http::timeout(self::QUERY_TIMEOUT)->get($url);

            if ($response->successful()) {
                return $response->json() ?? [];
            }

            return [];
        } catch (\Exception $exception) {
            Log::error('FiveM player list fetch failed', [
                'host' => $host,
                'port' => $port,
                'error' => $exception->getMessage(),
            ]);

            return [];
        }
    }

    /**
     * Kick a player from a FiveM server via RCON.
     */
    public function kickPlayer(string $host, int $port, string $token, int $playerId, ?string $reason = null): bool
    {
        try {
            $url = sprintf('http://%s:%d/players/%d', $host, $port, $playerId);
            $response = Http::withHeaders([
                'Authorization' => 'Bearer ' . $token,
            ])->timeout(self::QUERY_TIMEOUT)->delete($url);

            return $response->successful();
        } catch (\Exception $exception) {
            Log::error('FiveM player kick failed', [
                'host' => $host,
                'port' => $port,
                'player_id' => $playerId,
                'error' => $exception->getMessage(),
            ]);

            return false;
        }
    }

    /**
     * Send a command to a FiveM server.
     */
    public function sendCommand(string $host, int $port, string $token, string $command): bool
    {
        try {
            $url = sprintf('http://%s:%d/command', $host, $port);
            $response = Http::withHeaders([
                'Authorization' => 'Bearer ' . $token,
            ])->timeout(self::QUERY_TIMEOUT)->post($url, [
                'command' => $command,
            ]);

            return $response->successful();
        } catch (\Exception $exception) {
            Log::error('FiveM command send failed', [
                'host' => $host,
                'port' => $port,
                'error' => $exception->getMessage(),
            ]);

            return false;
        }
    }

    /**
     * Get resources list from a FiveM server.
     */
    public function getResources(string $host, int $port, string $token): array
    {
        try {
            $url = sprintf('http://%s:%d/resources', $host, $port);
            $response = Http::withHeaders([
                'Authorization' => 'Bearer ' . $token,
            ])->timeout(self::QUERY_TIMEOUT)->get($url);

            if ($response->successful()) {
                return $response->json('resources', []);
            }

            return [];
        } catch (\Exception $exception) {
            Log::error('FiveM resources fetch failed', [
                'host' => $host,
                'port' => $port,
                'error' => $exception->getMessage(),
            ]);

            return [];
        }
    }

    /**
     * Restart a specific resource on a FiveM server.
     */
    public function restartResource(string $host, int $port, string $token, string $resource): bool
    {
        try {
            $url = sprintf('http://%s:%d/resources/%s/restart', $host, $port, $resource);
            $response = Http::withHeaders([
                'Authorization' => 'Bearer ' . $token,
            ])->timeout(self::QUERY_TIMEOUT)->post($url);

            return $response->successful();
        } catch (\Exception $exception) {
            Log::error('FiveM resource restart failed', [
                'host' => $host,
                'port' => $port,
                'resource' => $resource,
                'error' => $exception->getMessage(),
            ]);

            return false;
        }
    }
}
