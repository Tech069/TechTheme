<?php

namespace Pterodactyl\Services\ReverseProxy;

use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;
use Pterodactyl\Models\Server;
use Pterodactyl\Models\ReverseProxy;

class ReverseProxyService
{
    public function __construct(
        private NginxConfigService $nginxService,
        private SslService $sslService,
    ) {
    }

    /**
     * Assign a reverse proxy to a server.
     */
    public function assign(Server $server, string $hostname, int $port, array $options = []): array
    {
        try {
            // Generate SSL certificate if not provided
            $sslCertPath = $options['ssl_cert_path'] ?? null;
            $sslKeyPath = $options['ssl_key_path'] ?? null;

            if (!$sslCertPath || !$sslKeyPath) {
                $sslResult = $this->sslService->requestCertificate($hostname);
                $sslCertPath = $sslResult['cert_path'];
                $sslKeyPath = $sslResult['key_path'];
            }

            // Generate nginx config
            $targetUrl = sprintf('http://127.0.0.1:%d', $port);
            $config = $this->nginxService->generateConfig($server, $targetUrl, $port, $hostname, $sslCertPath, $sslKeyPath);
            $filename = $this->nginxService->getConfigFilename($server);

            $writeResult = $this->nginxService->writeConfig($filename, $config);

            if (!$writeResult) {
                return ['success' => false, 'error' => 'Failed to write nginx configuration'];
            }

            // Store in database
            ReverseProxy::create([
                'server_id' => $server->id,
                'hostname' => $hostname,
                'target_port' => $port,
                'ssl_cert_path' => $sslCertPath,
                'ssl_key_path' => $sslKeyPath,
                'config_file' => $filename,
                'status' => 'active',
            ]);

            Log::info('Reverse proxy assigned', [
                'server_id' => $server->id,
                'hostname' => $hostname,
                'port' => $port,
            ]);

            return [
                'success' => true,
                'hostname' => $hostname,
                'port' => $port,
                'url' => "https://$hostname",
            ];
        } catch (\Exception $exception) {
            Log::error('Failed to assign reverse proxy', [
                'server_id' => $server->id,
                'error' => $exception->getMessage(),
            ]);

            return ['success' => false, 'error' => $exception->getMessage()];
        }
    }

    /**
     * Remove a reverse proxy assignment.
     */
    public function remove(Server $server): bool
    {
        try {
            $proxy = ReverseProxy::where('server_id', $server->id)->first();

            if (!$proxy) {
                return false;
            }

            $this->nginxService->removeConfig($proxy->config_file);
            $proxy->delete();

            Log::info('Reverse proxy removed', [
                'server_id' => $server->id,
                'hostname' => $proxy->hostname,
            ]);

            return true;
        } catch (\Exception $exception) {
            Log::error('Failed to remove reverse proxy', [
                'server_id' => $server->id,
                'error' => $exception->getMessage(),
            ]);

            return false;
        }
    }

    /**
     * Get reverse proxy assignment for a server.
     */
    public function getAssignment(Server $server): ?array
    {
        $proxy = ReverseProxy::where('server_id', $server->id)->first();

        if (!$proxy) {
            return null;
        }

        return [
            'hostname' => $proxy->hostname,
            'target_port' => $proxy->target_port,
            'ssl_cert_path' => $proxy->ssl_cert_path,
            'ssl_key_path' => $proxy->ssl_key_path,
            'config_file' => $proxy->config_file,
            'status' => $proxy->status,
        ];
    }

    /**
     * List all reverse proxy assignments.
     */
    public function listAssignments(): array
    {
        return ReverseProxy::with('server:id,name,uuid')
            ->get()
            ->toArray();
    }

    /**
     * Test the nginx configuration.
     */
    public function testConfig(): array
    {
        $output = [];
        $returnCode = 0;
        exec('nginx -t 2>&1', $output, $returnCode);

        return [
            'success' => $returnCode === 0,
            'output' => implode("\n", $output),
        ];
    }

    /**
     * Reload nginx configuration.
     */
    public function reloadNginx(): bool
    {
        exec('systemctl reload nginx 2>&1', $output, $returnCode);

        return $returnCode === 0;
    }
}
