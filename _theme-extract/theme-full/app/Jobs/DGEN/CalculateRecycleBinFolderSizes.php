<?php

namespace Pterodactyl\Jobs\DGEN;

use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Pterodactyl\Jobs\Job;
use Pterodactyl\Models\Server;
use Pterodactyl\Models\ServerRecycleBin;

class CalculateRecycleBinFolderSizes extends Job
{
    /**
     * Create a new job instance.
     */
    public function __construct(
        public ?int $serverId = null,
    ) {
        $this->queue = 'default';
    }

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        $query = ServerRecycleBin::query();

        if ($this->serverId) {
            $query->where('server_id', $this->serverId);
        }

        $recycleBinEntries = $query->get();

        foreach ($recycleBinEntries as $entry) {
            try {
                $this->calculateAndStoreSize($entry);
            } catch (\Throwable $e) {
                Log::error(
                    "Failed to calculate recycle bin size for entry #{$entry->id}: {$e->getMessage()}"
                );
            }
        }

        Log::info("Recycle bin size calculation completed for " . $recycleBinEntries->count() . " entries.");
    }

    /**
     * Calculate and store the size for a single recycle bin entry.
     */
    protected function calculateAndStoreSize(ServerRecycleBin $entry): void
    {
        $server = $entry->server;
        if (!$server) {
            return;
        }

        $data = $entry->data ?? [];
        $folderPath = $data['folder_path'] ?? null;

        if (!$folderPath) {
            return;
        }

        $node = $server->node;
        $diskPath = $node->daemonBase . '/' . $server->uuid . '/recyclebin/' . $folderPath;

        $size = $this->calculateDirectorySize($diskPath);

        $entry->update([
            'data' => array_merge($data, [
                'folder_size_bytes' => $size,
                'folder_size_human' => $this->formatBytes($size),
                'calculated_at' => now()->toIso8601String(),
            ]),
        ]);
    }

    /**
     * Calculate the size of a directory recursively.
     */
    protected function calculateDirectorySize(string $path): int
    {
        $totalSize = 0;

        if (!is_dir($path)) {
            return 0;
        }

        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($path, \RecursiveDirectoryIterator::SKIP_DOTS)
        );

        foreach ($iterator as $file) {
            if ($file->isFile()) {
                $totalSize += $file->getSize();
            }
        }

        return $totalSize;
    }

    /**
     * Format bytes to human readable string.
     */
    protected function formatBytes(int $bytes, int $precision = 2): string
    {
        $units = ['B', 'KB', 'MB', 'GB', 'TB'];

        $bytes = max($bytes, 0);
        $pow = floor(($bytes ? log($bytes) : 0) / log(1024));
        $pow = min($pow, count($units) - 1);

        $bytes /= pow(1024, $pow);

        return round($bytes, $precision) . ' ' . $units[$pow];
    }
}
