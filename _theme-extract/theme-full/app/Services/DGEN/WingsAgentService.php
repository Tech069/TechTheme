<?php

namespace Pterodactyl\Services\DGEN;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Contracts\Encryption\Encrypter;
use Pterodactyl\Models\Node;
use Pterodactyl\Models\Server;

class WingsAgentService
{
    private const REQUEST_TIMEOUT = 15;

    public function __construct(
        private WingsAgentEndpointResolver $endpointResolver,
        private Encrypter $encrypter,
    ) {
    }

    /**
     * Make an HTTP request to the Wings agent.
     */
    private function request(Node $node, string $method, string $endpoint, array $data = []): ?array
    {
        try {
            $url = $this->endpointResolver->resolve($node, $endpoint);
            $token = $node->getDecryptedKey();

            $response = Http::withHeaders([
                'Authorization' => 'Bearer ' . $token,
                'Content-Type' => 'application/json',
                'Accept' => 'application/json',
            ])->timeout(self::REQUEST_TIMEOUT);

            $response = match ($method) {
                'GET' => $response->get($url),
                'POST' => $response->post($url, $data),
                'PATCH' => $response->patch($url, $data),
                'DELETE' => $response->delete($url),
                default => $response->get($url),
            };

            if ($response->successful()) {
                return $response->json();
            }

            Log::warning('Wings agent request failed', [
                'node_id' => $node->id,
                'endpoint' => $endpoint,
                'status' => $response->status(),
                'body' => $response->body(),
            ]);

            return null;
        } catch (\Exception $exception) {
            Log::error('Wings agent request error', [
                'node_id' => $node->id,
                'endpoint' => $endpoint,
                'error' => $exception->getMessage(),
            ]);

            return null;
        }
    }

    /**
     * Get the system status from a Wings node.
     */
    public function getSystemStatus(Node $node): ?array
    {
        return $this->request($node, 'GET', 'system');
    }

    /**
     * Get the configuration from a Wings node.
     */
    public function getConfiguration(Node $node): ?array
    {
        return $this->request($node, 'GET', 'configuration');
    }

    /**
     * Sync a server's state with the Wings agent.
     */
    public function syncServer(Server $server): ?array
    {
        return $this->request($server->node, 'GET', 'server', ['server_uuid' => $server->uuid]);
    }

    /**
     * Send a command to a server.
     */
    public function sendCommand(Server $server, string $command): bool
    {
        $result = $this->request($server->node, 'POST', 'server_command', [
            'server_uuid' => $server->uuid,
            'command' => $command,
        ]);

        return $result !== null;
    }

    /**
     * Send a power action to a server.
     */
    public function sendPowerAction(Server $server, string $action): bool
    {
        $result = $this->request($server->node, 'POST', 'server_power', [
            'server_uuid' => $server->uuid,
            'action' => $action,
        ]);

        return $result !== null;
    }

    /**
     * Get server statistics from the Wings agent.
     */
    public function getServerStats(Server $server): ?array
    {
        return $this->request($server->node, 'GET', 'server_stats', ['server_uuid' => $server->uuid]);
    }

    /**
     * Get file listing for a server.
     */
    public function getServerFiles(Server $server, string $directory = '/'): ?array
    {
        return $this->request($server->node, 'GET', 'server_files', [
            'server_uuid' => $server->uuid,
            'directory' => $directory,
        ]);
    }

    /**
     * Delete a backup via Wings.
     */
    public function deleteBackup(Node $node, string $backupUuid): bool
    {
        $result = $this->request($node, 'DELETE', 'backup', ['backup_uuid' => $backupUuid]);

        return $result !== null;
    }

    /**
     * Create a backup via Wings.
     */
    public function createBackup(Server $server, string $backupUuid, bool $truncate = true): bool
    {
        $result = $this->request($server->node, 'POST', 'backup', [
            'server_uuid' => $server->uuid,
            'backup_uuid' => $backupUuid,
            'truncate' => $truncate,
        ]);

        return $result !== null;
    }

    /**
     * Check if a Wings node is reachable.
     */
    public function isReachable(Node $node): bool
    {
        try {
            $url = $node->getConnectionAddress() . '/api/system';
            $response = Http::withHeaders([
                'Authorization' => 'Bearer ' . $node->getDecryptedKey(),
            ])->timeout(5)->get($url);

            return $response->successful();
        } catch (\Exception $exception) {
            return false;
        }
    }
}
