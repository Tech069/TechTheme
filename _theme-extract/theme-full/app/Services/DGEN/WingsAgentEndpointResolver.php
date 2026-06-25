<?php

namespace Pterodactyl\Services\DGEN;

use Illuminate\Support\Facades\Log;
use Pterodactyl\Models\Node;

class WingsAgentEndpointResolver
{
    private const ENDPOINTS = [
        'server' => '/api/servers/{server_uuid}',
        'server_command' => '/api/servers/{server_uuid}/command',
        'server_power' => '/api/servers/{server_uuid}/power',
        'server_files' => '/api/servers/{server_uuid}/files',
        'server_stats' => '/api/servers/{server_uuid}/stats',
        'backup' => '/api/backups/{backup_uuid}',
        'system' => '/api/system',
        'configuration' => '/api/configuration',
    ];

    public function __construct()
    {
    }

    /**
     * Resolve an endpoint for a given node.
     */
    public function resolve(Node $node, string $endpoint, array $params = []): string
    {
        if (!isset(self::ENDPOINTS[$endpoint])) {
            throw new \InvalidArgumentException("Unknown endpoint: $endpoint");
        }

        $path = self::ENDPOINTS[$endpoint];

        foreach ($params as $key => $value) {
            $path = str_replace('{' . $key . '}', $value, $path);
        }

        return $node->getConnectionAddress() . $path;
    }

    /**
     * Get all available endpoints.
     */
    public function getAvailableEndpoints(): array
    {
        return array_keys(self::ENDPOINTS);
    }

    /**
     * Get the full URL for a specific endpoint.
     */
    public function getUrl(Node $node, string $endpoint, array $params = []): string
    {
        return $this->resolve($node, $endpoint, $params);
    }

    /**
     * Get the server endpoint URL.
     */
    public function getServerEndpoint(Node $node, string $serverUuid): string
    {
        return $this->resolve($node, 'server', ['server_uuid' => $serverUuid]);
    }

    /**
     * Get the system endpoint URL.
     */
    public function getSystemEndpoint(Node $node): string
    {
        return $this->resolve($node, 'system');
    }

    /**
     * Register a new endpoint.
     */
    public function registerEndpoint(string $name, string $path): void
    {
        self::ENDPOINTS[$name] = $path;
    }
}
