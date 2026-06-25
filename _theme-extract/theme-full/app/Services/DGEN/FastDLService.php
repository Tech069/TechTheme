<?php

namespace Pterodactyl\Services\DGEN;

use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Cache;
use Pterodactyl\Models\Server;

class FastDLService
{
    private const BASE_URL_KEY = 'fastdl:base_url';

    private const CACHE_TTL = 300;

    private const SYNC_BATCH_SIZE = 100;

    public function __construct(
        private FastDLNginxService $nginxService,
    ) {
    }

    /**
     * Enable FastDL for a server.
     */
    public function enable(Server $server, int $port, string $hostname): bool
    {
        $fastDLPath = $this->nginxService->getFastDLPath($server);

        if (!File::isDirectory($fastDLPath)) {
            File::makeDirectory($fastDLPath, 0755, true, true);
        }

        $result = $this->nginxService->writeConfig($server, $port, $hostname);

        if ($result) {
            Cache::put("fastdl:enabled:{$server->id}", true);
            Log::info('FastDL enabled for server', [
                'server_id' => $server->id,
                'port' => $port,
                'hostname' => $hostname,
            ]);
        }

        return $result;
    }

    /**
     * Disable FastDL for a server.
     */
    public function disable(Server $server): bool
    {
        $result = $this->nginxService->removeConfig($server);

        if ($result) {
            Cache::forget("fastdl:enabled:{$server->id}");
            Log::info('FastDL disabled for server', ['server_id' => $server->id]);
        }

        return $result;
    }

    /**
     * Sync server resource files to the FastDL directory.
     */
    public function syncFiles(Server $server): array
    {
        $result = [
            'synced' => 0,
            'skipped' => 0,
            'failed' => 0,
        ];

        try {
            $node = $server->node;
            $serverPath = $node->daemonBase . '/volumes/' . $server->uuid;
            $fastDLPath = $this->nginxService->getFastDLPath($server);

            // Get the list of downloadable files from the server
            $downloadableFiles = $this->getDownloadableFiles($server);

            foreach ($downloadableFiles as $relativePath) {
                $sourcePath = $serverPath . '/' . $relativePath;
                $destPath = $fastDLPath . '/' . $relativePath;

                if (!File::exists($sourcePath)) {
                    $result['skipped']++;
                    continue;
                }

                $destDir = dirname($destPath);
                if (!File::isDirectory($destDir)) {
                    File::makeDirectory($destDir, 0755, true, true);
                }

                try {
                    copy($sourcePath, $destPath);
                    $result['synced']++;
                } catch (\Exception $exception) {
                    $result['failed']++;
                    Log::warning('Failed to sync file to FastDL', [
                        'server_id' => $server->id,
                        'file' => $relativePath,
                        'error' => $exception->getMessage(),
                    ]);
                }
            }

            Cache::put("fastdl:last_sync:{$server->id}", now()->timestamp, self::CACHE_TTL);
        } catch (\Exception $exception) {
            Log::error('FastDL sync failed', [
                'server_id' => $server->id,
                'error' => $exception->getMessage(),
            ]);
        }

        return $result;
    }

    /**
     * Get files that should be served through FastDL.
     */
    private function getDownloadableFiles(Server $server): array
    {
        $extensions = ['bz2', 'gz', 'tar', 'zip', 'rar', '7z', 'litemod', 'jar', 'lang', 'properties'];
        $files = [];

        try {
            $node = $server->node;
            $serverPath = $node->daemonBase . '/volumes/' . $server->uuid;

            if (!File::isDirectory($serverPath)) {
                return [];
            }

            $allFiles = File::allFiles($serverPath);

            foreach ($allFiles as $file) {
                $extension = $file->getExtension();
                if (in_array(strtolower($extension), $extensions, true)) {
                    $files[] = $file->getRelativePathname();
                }
            }
        } catch (\Exception $exception) {
            Log::error('Failed to list downloadable files', [
                'server_id' => $server->id,
                'error' => $exception->getMessage(),
            ]);
        }

        return $files;
    }

    /**
     * Generate a download URL for a server file.
     */
    public function getFileUrl(Server $server, string $filePath): ?string
    {
        $baseUrl = Cache::get(self::BASE_URL_KEY, config('app.url'));
        $fastDLPath = $this->nginxService->getFastDLPath($server);
        $fullPath = $fastDLPath . '/' . $filePath;

        if (!File::exists($fullPath)) {
            return null;
        }

        return rtrim($baseUrl, '/') . '/' . $server->uuid . '/' . ltrim($filePath, '/');
    }

    /**
     * Check if FastDL is enabled for a server.
     */
    public function isEnabled(Server $server): bool
    {
        return $this->nginxService->configExists($server);
    }

    /**
     * Get FastDL status for a server.
     */
    public function getStatus(Server $server): array
    {
        $enabled = $this->isEnabled($server);
        $lastSync = Cache::get("fastdl:last_sync:{$server->id}");
        $fastDLPath = $this->nginxService->getFastDLPath($server);

        $fileCount = 0;
        $totalSize = 0;

        if ($enabled && File::isDirectory($fastDLPath)) {
            $files = File::allFiles($fastDLPath);
            $fileCount = count($files);
            $totalSize = array_sum(array_map(fn ($file) => $file->getSize(), $files));
        }

        return [
            'enabled' => $enabled,
            'last_sync' => $lastSync ? date('Y-m-d H:i:s', $lastSync) : null,
            'file_count' => $fileCount,
            'total_size_bytes' => $totalSize,
        ];
    }
}
