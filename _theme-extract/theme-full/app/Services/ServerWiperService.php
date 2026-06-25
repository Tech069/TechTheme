<?php

namespace Pterodactyl\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Artisan;
use Pterodactyl\Models\Server;
use Pterodactyl\Models\Database;
use Pterodactyl\Models\Backup;
use Pterodactyl\Models\Allocation;
use Pterodactyl\Models\WipeSchedule;
use Pterodactyl\Models\WipeExecution;

class ServerWiperService
{
    private const SAFE_PATHS = [
        '.htaccess',
        'server.properties',
    ];

    private const DEFAULT_EXCLUSIONS = [
        '.htaccess',
        'server.properties',
    ];

    public function __construct()
    {
    }

    /**
     * Wipe all data for a server.
     */
    public function wipe(Server $server, array $options = []): array
    {
        $wipeFiles = $options['files'] ?? true;
        $wipeDatabases = $options['databases'] ?? false;
        $wipeBackups = $options['backups'] ?? false;
        $wipeAllocations = $options['allocations'] ?? false;
        $excludePatterns = $options['exclude'] ?? self::DEFAULT_EXCLUSIONS;

        $result = [
            'server_id' => $server->id,
            'files_wiped' => false,
            'databases_wiped' => 0,
            'backups_wiped' => 0,
            'allocations_wiped' => 0,
            'errors' => [],
        ];

        try {
            // Stop the server first
            $this->stopServer($server);

            if ($wipeFiles) {
                $result['files_wiped'] = $this->wipeFiles($server, $excludePatterns);
            }

            if ($wipeDatabases) {
                $result['databases_wiped'] = $this->wipeDatabases($server);
            }

            if ($wipeBackups) {
                $result['backups_wiped'] = $this->wipeBackups($server);
            }

            if ($wipeAllocations) {
                $result['allocations_wiped'] = $this->wipeAllocations($server);
            }

            // Log the wipe execution
            WipeExecution::create([
                'server_id' => $server->id,
                'wipe_files' => $wipeFiles,
                'wipe_databases' => $wipeDatabases,
                'wipe_backups' => $wipeBackups,
                'wipe_allocations' => $wipeAllocations,
                'result' => $result,
                'executed_at' => now(),
            ]);

            Log::info('Server wipe completed', $result);
        } catch (\Exception $exception) {
            $result['errors'][] = $exception->getMessage();

            Log::error('Server wipe failed', [
                'server_id' => $server->id,
                'error' => $exception->getMessage(),
            ]);
        }

        return $result;
    }

    /**
     * Wipe server files on the node.
     */
    private function wipeFiles(Server $server, array $excludePatterns): bool
    {
        try {
            $node = $server->node;
            $serverPath = $node->daemonBase . '/volumes/' . $server->uuid;

            // Use the daemon to wipe files via HTTP
            $url = $node->getConnectionAddress() . '/api/servers/' . $server->uuid . '/files';

            $response = \Illuminate\Support\Facades\Http::withHeaders([
                'Authorization' => 'Bearer ' . $node->getDecryptedKey(),
                'Content-Type' => 'application/json',
            ])->delete($url, [
                'root' => '/',
                'exclude' => $excludePatterns,
            ]);

            return $response->successful();
        } catch (\Exception $exception) {
            Log::error('Failed to wipe server files', [
                'server_id' => $server->id,
                'error' => $exception->getMessage(),
            ]);

            return false;
        }
    }

    /**
     * Wipe all databases associated with the server.
     */
    private function wipeDatabases(Server $server): int
    {
        $databases = Database::where('server_id', $server->id)->get();
        $wipedCount = 0;

        foreach ($databases as $database) {
            try {
                $host = $database->host;

                $pdo = new \PDO(
                    "mysql:host={$host->host};port={$host->port}",
                    $host->username,
                    $host->password
                );

                $pdo->exec("DROP DATABASE IF EXISTS `{$database->database}`");
                $pdo->exec("DROP USER IF EXISTS '{$database->username}'@'%'");
                $pdo->exec("FLUSH PRIVILEGES");

                $database->delete();
                $wipedCount++;
            } catch (\Exception $exception) {
                Log::error('Failed to wipe database', [
                    'database_id' => $database->id,
                    'error' => $exception->getMessage(),
                ]);
            }
        }

        return $wipedCount;
    }

    /**
     * Wipe all backups associated with the server.
     */
    private function wipeBackups(Server $server): int
    {
        $backups = Backup::where('server_id', $server->id)->get();
        $wipedCount = 0;

        foreach ($backups as $backup) {
            try {
                $node = $server->node;
                $url = $node->getConnectionAddress() . '/api/backups/' . $backup->uuid;

                \Illuminate\Support\Facades\Http::withHeaders([
                    'Authorization' => 'Bearer ' . $node->getDecryptedKey(),
                ])->delete($url);

                $backup->delete();
                $wipedCount++;
            } catch (\Exception $exception) {
                Log::error('Failed to wipe backup', [
                    'backup_id' => $backup->id,
                    'error' => $exception->getMessage(),
                ]);
            }
        }

        return $wipedCount;
    }

    /**
     * Wipe all non-primary allocations for the server.
     */
    private function wipeAllocations(Server $server): int
    {
        return Allocation::where('server_id', $server->id)
            ->where('id', '!=', $server->allocation_id)
            ->update(['server_id' => null]);
    }

    /**
     * Stop a server before wiping.
     */
    private function stopServer(Server $server): void
    {
        if ($server->isSuspended()) {
            return;
        }

        try {
            $node = $server->node;
            $url = $node->getConnectionAddress() . '/api/servers/' . $server->uuid . '/power';

            \Illuminate\Support\Facades\Http::withHeaders([
                'Authorization' => 'Bearer ' . $node->getDecryptedKey(),
            ])->post($url, ['action' => 'stop']);

            // Wait briefly for server to stop
            sleep(2);
        } catch (\Exception $exception) {
            Log::warning('Failed to stop server before wipe', [
                'server_id' => $server->id,
                'error' => $exception->getMessage(),
            ]);
        }
    }

    /**
     * Schedule a wipe for later execution.
     */
    public function scheduleWipe(Server $server, string $executeAt, array $options = []): WipeSchedule
    {
        return WipeSchedule::create([
            'server_id' => $server->id,
            'execute_at' => $executeAt,
            'options' => $options,
            'status' => 'pending',
        ]);
    }

    /**
     * Execute scheduled wipes that are due.
     */
    public function executeScheduledWipes(): int
    {
        $dueSchedules = WipeSchedule::where('status', 'pending')
            ->where('execute_at', '<=', now())
            ->get();

        $executedCount = 0;

        foreach ($dueSchedules as $schedule) {
            try {
                $server = Server::find($schedule->server_id);
                if ($server) {
                    $this->wipe($server, $schedule->options ?? []);
                    $schedule->update(['status' => 'completed']);
                    $executedCount++;
                } else {
                    $schedule->update(['status' => 'failed']);
                }
            } catch (\Exception $exception) {
                $schedule->update(['status' => 'failed']);
                Log::error('Scheduled wipe execution failed', [
                    'schedule_id' => $schedule->id,
                    'error' => $exception->getMessage(),
                ]);
            }
        }

        return $executedCount;
    }
}
