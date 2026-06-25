<?php

namespace Pterodactyl\Services\ReverseProxy;

use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\File;
use Pterodactyl\Models\Server;

class NginxConfigService
{
    private const CONFIG_DIR = '/etc/nginx/sites-available';

    private const ENABLED_DIR = '/etc/nginx/sites-enabled';

    private const REVERSE_PROXY_TEMPLATE = <<<'NGINX'
server {
    listen %d ssl http2;
    server_name %s;

    ssl_certificate %s;
    ssl_certificate_key %s;
    ssl_protocols TLSv1.2 TLSv1.3;
    ssl_ciphers HIGH:!aNULL:!MD5;
    ssl_prefer_server_ciphers on;
    ssl_session_cache shared:SSL:10m;
    ssl_session_timeout 10m;

    location / {
        proxy_pass %s;
        proxy_http_version 1.1;
        proxy_set_header Upgrade $http_upgrade;
        proxy_set_header Connection "upgrade";
        proxy_set_header Host $host;
        proxy_set_header X-Real-IP $remote_addr;
        proxy_set_header X-Forwarded-For $proxy_add_x_forwarded_for;
        proxy_set_header X-Forwarded-Proto $scheme;
        proxy_read_timeout 300s;
        proxy_connect_timeout 60s;
    }

    access_log /var/log/nginx/proxy_%s_access.log;
    error_log /var/log/nginx/proxy_%s_error.log;
}
NGINX;

    public function __construct()
    {
    }

    /**
     * Generate an nginx reverse proxy configuration.
     */
    public function generateConfig(Server $server, string $targetUrl, int $port, string $hostname, string $sslCertPath, string $sslKeyPath): string
    {
        $targetParsed = parse_url($targetUrl);
        $upstream = sprintf('%s://%s:%s', $targetParsed['scheme'] ?? 'http', $targetParsed['host'] ?? '127.0.0.1', $targetParsed['port'] ?? $port);

        return sprintf(
            self::REVERSE_PROXY_TEMPLATE,
            $port,
            $hostname,
            $sslCertPath,
            $sslKeyPath,
            $upstream,
            $server->uuid,
            $server->uuid
        );
    }

    /**
     * Write an nginx configuration to disk.
     */
    public function writeConfig(string $filename, string $content): bool
    {
        try {
            $configFile = self::CONFIG_DIR . '/' . $filename;

            File::put($configFile, $content);

            // Enable the site
            $enabledFile = self::ENABLED_DIR . '/' . $filename;
            if (!File::exists($enabledFile)) {
                symlink($configFile, $enabledFile);
            }

            // Test nginx configuration
            $output = [];
            $returnCode = 0;
            exec('nginx -t 2>&1', $output, $returnCode);

            if ($returnCode === 0) {
                exec('systemctl reload nginx 2>&1');

                return true;
            } else {
                Log::error('Nginx config test failed', [
                    'filename' => $filename,
                    'output' => implode("\n", $output),
                ]);

                File::delete($configFile);
                File::delete($enabledFile);

                return false;
            }
        } catch (\Exception $exception) {
            Log::error('Failed to write nginx config', [
                'filename' => $filename,
                'error' => $exception->getMessage(),
            ]);

            return false;
        }
    }

    /**
     * Remove an nginx configuration.
     */
    public function removeConfig(string $filename): bool
    {
        try {
            File::delete(self::CONFIG_DIR . '/' . $filename);
            File::delete(self::ENABLED_DIR . '/' . $filename);

            exec('systemctl reload nginx 2>&1');

            return true;
        } catch (\Exception $exception) {
            Log::error('Failed to remove nginx config', [
                'filename' => $filename,
                'error' => $exception->getMessage(),
            ]);

            return false;
        }
    }

    /**
     * List all active reverse proxy configurations.
     */
    public function listConfigs(): array
    {
        if (!File::isDirectory(self::CONFIG_DIR)) {
            return [];
        }

        $files = File::files(self::CONFIG_DIR);
        $configs = [];

        foreach ($files as $file) {
            if ($file->getExtension() === 'conf') {
                $configs[] = [
                    'filename' => $file->getFilename(),
                    'path' => $file->getPathname(),
                    'size' => $file->getSize(),
                    'modified' => $file->getMTime(),
                ];
            }
        }

        return $configs;
    }

    /**
     * Get the config filename for a server.
     */
    public function getConfigFilename(Server $server): string
    {
        return 'proxy_' . $server->uuid . '.conf';
    }
}
