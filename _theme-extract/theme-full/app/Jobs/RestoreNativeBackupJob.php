<?php

namespace Pterodactyl\Jobs;

use Illuminate\Container\Container;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Pterodactyl\Models\Backup;
use Pterodactyl\Models\Server;
use Pterodactyl\Services\Nodes\NodeConfigurationService;

class RestoreNativeBackupJob extends Job
{
    /**
     * Create a new job instance.
     */
    public function __construct(
        public int $backupId,
    ) {
        $this->queue = 'high';
        $this->tries = 1;
    }

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        $backup = Backup::with(['server', 'server.node', 'server.allocations'])
            ->where('is_successful', true)
            ->find($this->backupId);

        if (!$backup) {
            Log::error("Backup #{$this->backupId} not found or not successful.");
            return;
        }

        $server = $backup->server;
        if (!$server) {
            Log::error("Server for backup #{$this->backupId} not found.");
            return;
        }

        try {
            $server->update(['status' => Server::STATUS_RESTORING_BACKUP]);

            $disk = config('backups.disk') ?: $backup->disk;
            $backupPath = $server->id . '/' . $backup->uuid;

            if (!Storage::disk($disk)->exists($backupPath)) {
                throw new \RuntimeException("Backup file not found on disk: $backupPath");
            }

            $node = $server->node;
            $connection = Container::getInstance()->make(NodeConfigurationService::class);
            $response = $connection->setNode($node)->connect()->getDaemon()->servers()->set(
                $server->uuid,
                ['restore' => $backup->uuid]
            );

            $backup->update(['upload_id' => $response->json('attributes.restore_id') ?? null]);

            Log::info("Backup restore initiated for server #{$server->id}, backup {$backup->uuid}.");
        } catch (\Throwable $e) {
            Log::error("Backup restore failed for server #{$server->id}: {$e->getMessage()}");

            $server->update(['status' => null]);

            $backup->update(['is_successful' => false]);

            throw $e;
        }
    }
}
