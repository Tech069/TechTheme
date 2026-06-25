<?php

namespace Pterodactyl\Jobs;

use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Pterodactyl\Models\Backup;
use Pterodactyl\Models\Server;

class UploadNativeBackupExternally extends Job
{
    /**
     * Create a new job instance.
     */
    public function __construct(
        public int $backupId,
        public string $targetDisk = 's3',
    ) {
        $this->queue = 'high';
    }

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        $backup = Backup::with('server')->find($this->backupId);
        if (!$backup) {
            Log::error("Backup #{$this->backupId} not found for external upload.");
            return;
        }

        if (!$backup->is_successful) {
            Log::warning("Backup #{$this->backupId} is not successful, skipping external upload.");
            return;
        }

        $sourceDisk = config('backups.disk') ?: $backup->disk;
        $sourcePath = $backup->server_id . '/' . $backup->uuid;

        try {
            if (!Storage::disk($sourceDisk)->exists($sourcePath)) {
                throw new \RuntimeException("Source backup file not found: $sourcePath on disk $sourceDisk");
            }

            $stream = Storage::disk($sourceDisk)->readStream($sourcePath);

            Storage::disk($this->targetDisk)->put($sourcePath, $stream, 'public');

            if (is_resource($stream)) {
                fclose($stream);
            }

            $checksumPath = $sourcePath . '.sha256';
            if (Storage::disk($sourceDisk)->exists($checksumPath)) {
                $checksumStream = Storage::disk($sourceDisk)->readStream($checksumPath);
                Storage::disk($this->targetDisk)->put($checksumPath, $checksumStream, 'public');

                if (is_resource($checksumStream)) {
                    fclose($checksumStream);
                }
            }

            Log::info(
                "Backup #{$this->backupId} uploaded to external disk {$this->targetDisk}.",
                ['server_id' => $backup->server_id, 'size' => $backup->bytes]
            );
        } catch (\Throwable $e) {
            Log::error(
                "Failed to upload backup #{$this->backupId} to external disk: {$e->getMessage()}",
                ['exception' => $e]
            );
            throw $e;
        }
    }
}
