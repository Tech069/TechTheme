<?php

namespace Pterodactyl\Services;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

class MinecraftPlayerCountService
{
    private const QUERY_TIMEOUT = 5;

    private const CACHE_TTL = 30;

    private const PROTOCOL_VERSION_1_7 = 0x07;
    private const PROTOCOL_VERSION_1_8 = 0x47;
    private const PROTOCOL_VERSION_1_9 = 0x49;
    private const PROTOCOL_VERSION_1_10 = 0x51;
    private const PROTOCOL_VERSION_1_12 = 0x340;
    private const PROTOCOL_VERSION_1_13 = 0x341;
    private const PROTOCOL_VERSION_1_14 = 0x342;
    private const PROTOCOL_VERSION_1_15 = 0x343;
    private const PROTOCOL_VERSION_1_16 = 0x400;
    private const PROTOCOL_VERSION_1_17 = 0x404;
    private const PROTOCOL_VERSION_1_18 = 0x408;
    private const PROTOCOL_VERSION_1_19 = 0x43C;

    public function __construct()
    {
    }

    /**
     * Get cached player count for a server.
     */
    public function getPlayerCount(string $host, int $port = 25565): ?array
    {
        $cacheKey = "mc_player_count:{$host}:{$port}";

        return Cache::remember($cacheKey, self::CACHE_TTL, function () use ($host, $port) {
            return $this->queryServer($host, $port);
        });
    }

    /**
     * Query a Minecraft server for player count using the query protocol.
     */
    public function queryServer(string $host, int $port = 25565): ?array
    {
        try {
            $socket = @fsocknew('tcp', $host, $port);
            if ($socket === false) {
                Log::warning('Failed to connect to Minecraft server', ['host' => $host, 'port' => $port]);

                return null;
            }

            stream_set_timeout($socket, self::QUERY_TIMEOUT);
            stream_set_option($socket, STREAM_OPTION_READ_TIMEOUT, self::QUERY_TIMEOUT * 1000000);

            // Send handshake
            $this->writeVarInt($socket, 0x00);
            $this->writeVarInt($socket, self::PROTOCOL_VERSION_1_19);
            $this->writeString($socket, $host);
            $this->writeShort($socket, $port);
            $this->writeVarInt($socket, 1);
            $this->writePacket($socket);

            // Send status request
            $this->writeVarInt($socket, 0x00);
            $this->writePacket($socket);

            // Read response length
            $length = $this->readVarInt($socket);
            if ($length === false || $length <= 0) {
                fclose($socket);

                return null;
            }

            // Read packet ID
            $this->readVarInt($socket);

            // Read JSON response length
            $jsonLength = $this->readVarInt($socket);
            if ($jsonLength === false || $jsonLength <= 0) {
                fclose($socket);

                return null;
            }

            // Read JSON response
            $json = $this->readString($socket, $jsonLength);
            fclose($socket);

            if (empty($json)) {
                return null;
            }

            $data = json_decode($json, true);

            if (json_last_error() !== JSON_ERROR_NONE) {
                return null;
            }

            $players = $data['players'] ?? [];

            return [
                'online' => true,
                'motd' => $data['description']['text'] ?? ($data['description'] ?? ''),
                'version' => $data['version']['name'] ?? 'Unknown',
                'protocol' => $data['version']['protocol'] ?? 0,
                'player_count' => $players['online'] ?? 0,
                'max_players' => $players['max'] ?? 0,
                'sample' => $players['sample'] ?? [],
                'favicon' => $data['favicon'] ?? null,
            ];
        } catch (\Exception $exception) {
            Log::error('Minecraft server query failed', [
                'host' => $host,
                'port' => $port,
                'error' => $exception->getMessage(),
            ]);

            return null;
        }
    }

    /**
     * Get player count for multiple servers.
     */
    public function getMultipleServerCounts(array $servers): array
    {
        $results = [];

        foreach ($servers as $server) {
            $host = $server['host'] ?? $server;
            $port = $server['port'] ?? 25565;
            $results["{$host}:{$port}"] = $this->getPlayerCount($host, $port);
        }

        return $results;
    }

    /**
     * Force refresh player count for a server.
     */
    public function refreshPlayerCount(string $host, int $port = 25565): ?array
    {
        $cacheKey = "mc_player_count:{$host}:{$port}";
        Cache::forget($cacheKey);

        return $this->queryServer($host, $port);
    }

    /**
     * Write a VarInt to the socket.
     */
    private function writeVarInt($socket, int $value): void
    {
        do {
            $byte = $value & 0x7F;
            $value >>= 7;

            if ($value !== 0) {
                $byte |= 0x80;
            }

            fwrite($socket, pack('C', $byte));
        } while ($value !== 0);
    }

    /**
     * Read a VarInt from the socket.
     */
    private function readVarInt($socket): int|false
    {
        $result = 0;
        $shift = 0;

        do {
            $byte = fread($socket, 1);
            if ($byte === false || $byte === '') {
                return false;
            }

            $byte = ord($byte);
            $result |= ($byte & 0x7F) << $shift;

            if (($byte & 0x80) === 0) {
                break;
            }

            $shift += 7;

            if ($shift >= 35) {
                return false;
            }
        } while (true);

        return $result;
    }

    /**
     * Write a string to the socket.
     */
    private function writeString($socket, string $string): void
    {
        $this->writeVarInt($socket, strlen($string));
        fwrite($socket, $string);
    }

    /**
     * Read a string from the socket.
     */
    private function readString($socket, int $length): string
    {
        $data = '';
        $remaining = $length;

        while ($remaining > 0) {
            $chunk = fread($socket, min($remaining, 8192));
            if ($chunk === false || $chunk === '') {
                break;
            }

            $data .= $chunk;
            $remaining -= strlen($chunk);
        }

        return $data;
    }

    /**
     * Write a short to the socket.
     */
    private function writeShort($socket, int $value): void
    {
        fwrite($socket, pack('n', $value));
    }

    /**
     * Write the packet length header and flush.
     */
    private function writePacket($socket): void
    {
        // The packet data has already been written directly to the socket
        // This is a simplified approach for status ping
        fflush($socket);
    }
}
