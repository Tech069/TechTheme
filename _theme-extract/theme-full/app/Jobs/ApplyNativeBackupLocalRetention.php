<?php

namespace Pterodactyl\Jobs;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Pterodactyl\Models\Backup;
use Pterodactyl\Models\Server;

class ApplyNativeBackupLocalRetention extends Job
{
    /**
     * Create a new job instance.
     */
    public function __construct(
        public int $serverId,
        public int $retainCount = 5,
    ) {
        $this->queue = 'high';
    }

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        $server = Server::withTrashed()->find($this->serverId);
        if (!$server) {
            Log::warning("Server #{$this->serverId} not found, skipping backup retention.");
            return;
        }

        $backups = Backup::where('server_id', $this->serverId)
            ->where('is_successful', true)
            ->where('is_locked', false)
            ->orderByDesc('created_at')
            ->get();

        if ($backups->count() <= $this->retainCount) {
            return;
        }

        $backupsToDelete = $backups->slice($this->retainCount);

        foreach ($backupsToDelete as $backup) {
            try {
                $this->deleteBackupFiles($backup);
                $backup->delete();

                Log::info("Deleted old backup {$backup->uuid} for server #{$this->serverId}.");
            } catch (\Exception $e) {
                Log::error("Failed to delete backup {$backup->uuid}: {$e->getMessage()}");
            }
        }
    }

    /**
     * Delete backup files from the storage disk.
     */
    protected function deleteBackupFiles(Backup $backup): void
    {
        $disk = config('backups.disk') ?: $backup->disk;

        if (!\Illuminate\Support\Facades\Storage::disk($disk)->exists($backup->server_id . '/' . $backup->uuid)) {
            return;
        }

        \Illuminate\Support\Facades\Storage::disk($disk)->delete($backup->server_id . '/' . $backup->uuid);

        if ($backup->checksum) {
            $checksumPath = $backup->server_id . '/' . $backup->uuid . '.sha256';
            if (\Illuminate\Support\Facades\Storage::disk($disk)->exists($checksumPath)) {
                \Illuminate\Support\Facades\Storage::disk($disk)->delete($checksumPath);
            }
        }
    }
}
