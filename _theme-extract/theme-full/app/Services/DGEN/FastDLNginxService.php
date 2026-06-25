<?php

namespace Pterodactyl\Services\DGEN;

use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Log;
use Pterodactyl\Models\Server;

class FastDLNginxService
{
    private const NGINX_CONFIG_TEMPLATE = <<<'NGINX'
server {
    listen %d;
    server_name %s;

    root %s;
    index index.html;

    location ~* \.(bz2|gz|tar|zip|rar|7z|litemod|jar|lang|properties)$ {
        add_header Cache-Control "public, max-age=86400";
        add_header Content-Disposition 'attachment';
        add_header Access-Control-Allow-Origin "*";
    }

    location / {
        try_files $uri $uri/ =404;
        autoindex on;
        autoindex_exact_size off;
        autoindex_localtime on;
    }

    location ~ /\. {
        deny all;
        access_log off;
        log_not_found off;
    }

    access_log /var/log/nginx/fastdl_%s_access.log;
    error_log /var/log/nginx/fastdl_%s_error.log;

    gzip on;
    gzip_types text/plain text/css application/json application/javascript text/xml application/xml;
    gzip_min_length 256;
}
NGINX;

    private const CONFIG_DIR = '/etc/nginx/sites-available';

    private const ENABLED_DIR = '/etc/nginx/sites-enabled';

    public function __construct()
    {
    }

    /**
     * Generate an nginx configuration for a FastDL server.
     */
    public function generateConfig(Server $server, int $port, string $hostname): string
    {
        $rootPath = $this->getFastDLPath($server);

        return sprintf(
            self::NGINX_CONFIG_TEMPLATE,
            $port,
            $hostname,
            $rootPath,
            $server->uuid,
            $server->uuid
        );
    }

    /**
     * Write the nginx configuration to disk.
     */
    public function writeConfig(Server $server, int $port, string $hostname): bool
    {
        try {
            $config = $this->generateConfig($server, $port, $hostname);
            $configFile = self::CONFIG_DIR . "/fastdl_{$server->uuid}.conf";

            File::put($configFile, $config);

            // Create symlink to enable the site
            $enabledFile = self::ENABLED_DIR . "/fastdl_{$server->uuid}.conf";

            if (!File::exists($enabledFile)) {
                symlink($configFile, $enabledFile);
            }

            // Test nginx configuration
            $testResult = shell_exec('nginx -t 2>&1');

            if (strpos($testResult, 'successful') !== false || strpos($testResult, 'ok') !== false) {
                // Reload nginx
                shell_exec('systemctl reload nginx 2>&1');

                return true;
            } else {
                Log::error('Nginx config test failed', [
                    'server_id' => $server->id,
                    'output' => $testResult,
                ]);

                // Remove the config on failure
                File::delete($configFile);
                File::delete($enabledFile);

                return false;
            }
        } catch (\Exception $exception) {
            Log::error('Failed to write FastDL nginx config', [
                'server_id' => $server->id,
                'error' => $exception->getMessage(),
            ]);

            return false;
        }
    }

    /**
     * Remove the nginx configuration for a server.
     */
    public function removeConfig(Server $server): bool
    {
        try {
            $configFile = self::CONFIG_DIR . "/fastdl_{$server->uuid}.conf";
            $enabledFile = self::ENABLED_DIR . "/fastdl_{$server->uuid}.conf";

            File::delete($configFile);
            File::delete($enabledFile);

            shell_exec('systemctl reload nginx 2>&1');

            return true;
        } catch (\Exception $exception) {
            Log::error('Failed to remove FastDL nginx config', [
                'server_id' => $server->id,
                'error' => $exception->getMessage(),
            ]);

            return false;
        }
    }

    /**
     * Get the FastDL storage path for a server.
     */
    public function getFastDLPath(Server $server): string
    {
        return storage_path('fastdl/' . $server->uuid);
    }

    /**
     * Check if an nginx config exists for a server.
     */
    public function configExists(Server $server): bool
    {
        $configFile = self::CONFIG_DIR . "/fastdl_{$server->uuid}.conf";

        return File::exists($configFile);
    }
}
