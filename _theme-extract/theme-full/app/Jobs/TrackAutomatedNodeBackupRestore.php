<?php

namespace Pterodactyl\Jobs;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Pterodactyl\Models\NodeBackup;
use Pterodactyl\Models\Server;
use Pterodactyl\Models\Backup;

class TrackAutomatedNodeBackupRestore extends Job
{
    /**
     * Create a new job instance.
     */
    public function __construct(
        public int $serverId,
        public int $nodeBackupId,
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
            Log::warning("Server #{$this->serverId} not found for automated backup restore tracking.");
            return;
        }

        $nodeBackup = NodeBackup::find($this->nodeBackupId);
        if (!$nodeBackup) {
            Log::warning("Node backup #{$this->nodeBackupId} not found.");
            return;
        }

        try {
            DB::transaction(function () use ($server, $nodeBackup) {
                $backup = Backup::create([
                    'server_id' => $server->id,
                    'uuid' => \Illuminate\Support\Str::uuid()->toString(),
                    'is_successful' => false,
                    'is_locked' => false,
                    'name' => 'Automated Restore - ' . now()->format('Y-m-d H:i:s'),
                    'ignored_files' => [],
                    'disk' => config('backups.disk', 'local'),
                    'bytes' => 0,
                ]);

                Log::info(
                    "Tracking automated backup restore for server #{$server->id}.",
                    [
                        'backup_id' => $backup->id,
                        'node_backup_id' => $nodeBackup->id,
                    ]
                );
            });
        } catch (\Throwable $e) {
            Log::error("Failed to track automated backup restore for server #{$this->serverId}: {$e->getMessage()}");
            throw $e;
        }
    }
}
