<?php

namespace Pterodactyl\Console\Commands;

use Carbon\CarbonImmutable;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Pterodactyl\Models\Server;
use Pterodactyl\Models\WipeSchedule;
use Pterodactyl\Models\WipeExecution;

class ProcessScheduledWipes extends Command
{
    protected $signature = 'p:wipes:process
        {--limit=10 : Maximum number of wipes to process per run}
        {--dry-run : Show what would be executed without actually running}';

    protected $description = 'Process pending wipe schedules that are due for execution.';

    public function handle(): int
    {
        $limit = (int) $this->option('limit');
        $now = CarbonImmutable::now();

        $schedules = WipeSchedule::query()
            ->where('is_active', true)
            ->where('next_run_at', '<=', $now)
            ->with(['server', 'server.node'])
            ->limit($limit)
            ->get();

        if ($schedules->isEmpty()) {
            $this->info('No pending wipes to process.');

            return 0;
        }

        $this->info("Processing {$schedules->count()} wipe schedule(s)...");
        $this->newLine();

        $processedCount = 0;
        $failedCount = 0;

        foreach ($schedules as $schedule) {
            $server = $schedule->server;

            if (!$server) {
                $this->error("Schedule #{$schedule->id}: Server not found (ID: {$schedule->server_id}), skipping.");
                $failedCount++;
                continue;
            }

            if ($server->isSuspended()) {
                $this->warn("Schedule #{$schedule->id}: Server '{$server->name}' is suspended, skipping.");
                $this->updateNextRunTime($schedule);
                continue;
            }

            if ($this->option('dry-run')) {
                $this->line("  <comment>[DRY-RUN]</comment> Would wipe server: {$server->name} (#{$server->id})");
                $processedCount++;
                continue;
            }

            try {
                $this->executeWipe($schedule, $server);
                $this->updateNextRunTime($schedule);
                $processedCount++;
            } catch (\Exception $e) {
                $failedCount++;
                $this->error("Schedule #{$schedule->id}: Failed to wipe server '{$server->name}': " . $e->getMessage());
                Log::error('Wipe execution failed', [
                    'schedule_id' => $schedule->id,
                    'server_id' => $server->id,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        $this->newLine();
        $this->info("Wipe processing complete: $processedCount processed, $failedCount failed.");

        return $failedCount > 0 ? 1 : 0;
    }

    private function executeWipe(WipeSchedule $schedule, Server $server): void
    {
        $execution = WipeExecution::create([
            'schedule_id' => $schedule->id,
            'server_id' => $server->id,
            'status' => 'running',
            'started_at' => CarbonImmutable::now(),
        ]);

        try {
            if ($schedule->stop_server && !$server->isSuspended()) {
                $this->stopServer($server);
                $execution->update(['server_was_stopped' => true]);
            }

            $filePatterns = json_decode($schedule->file_patterns, true) ?? ['**/*'];
            $deletedFiles = $this->deleteFiles($server, $filePatterns);

            if (!empty($schedule->commands)) {
                $commands = json_decode($schedule->commands, true);
                $this->executeCommands($server, $commands);
                $execution->update(['commands_executed' => $commands]);
            }

            $execution->update([
                'status' => 'completed',
                'deleted_files' => $deletedFiles,
                'files_deleted_count' => count($deletedFiles),
                'completed_at' => CarbonImmutable::now(),
            ]);

            $this->info("  <info>Completed</info>: Server '{$server->name}' wiped successfully (" . count($deletedFiles) . " files removed).");

            if ($schedule->stop_server && $execution->server_was_stopped) {
                $this->startServer($server);
                $execution->update(['server_was_started' => true]);
            }
        } catch (\Exception $e) {
            $execution->update([
                'status' => 'failed',
                'error_message' => $e->getMessage(),
                'completed_at' => CarbonImmutable::now(),
            ]);
            throw $e;
        }
    }

    private function deleteFiles(Server $server, array $filePatterns): array
    {
        $deletedFiles = [];
        $serverPath = $server->node->daemonBase . '/volumes/' . $server->uuid;

        foreach ($filePatterns as $pattern) {
            $fullPath = $serverPath . '/' . $pattern;
            $matchedFiles = glob($fullPath, GLOB_NOSORT);

            if ($matchedFiles === false) {
                continue;
            }

            foreach ($matchedFiles as $file) {
                if (is_file($file)) {
                    if (unlink($file)) {
                        $deletedFiles[] = str_replace($serverPath . '/', '', $file);
                    }
                }
            }
        }

        return $deletedFiles;
    }

    private function executeCommands(Server $server, array $commands): void
    {
        $serverPath = $server->node->daemonBase . '/volumes/' . $server->uuid;

        foreach ($commands as $command) {
            $fullCommand = "cd $serverPath && $command 2>&1";
            exec($fullCommand, $output, $returnCode);

            if ($returnCode !== 0) {
                throw new \RuntimeException("Command failed (exit $returnCode): $command\nOutput: " . implode("\n", $output));
            }
        }
    }

    private function stopServer(Server $server): void
    {
        try {
            $repository = app(\Pterodactyl\Repositories\Wings\DaemonPowerRepository::class);
            $repository->setServer($server)->send('stop');
            sleep(5);
        } catch (\Exception $e) {
            Log::warning("Failed to stop server {$server->id} for wipe: " . $e->getMessage());
        }
    }

    private function startServer(Server $server): void
    {
        try {
            $repository = app(\Pterodactyl\Repositories\Wings\DaemonPowerRepository::class);
            $repository->setServer($server)->send('start');
        } catch (\Exception $e) {
            Log::warning("Failed to start server {$server->id} after wipe: " . $e->getMessage());
        }
    }

    private function updateNextRunTime(WipeSchedule $schedule): void
    {
        if ($schedule->schedule_type === 'recurring') {
            $config = json_decode($schedule->recurrence_config, true) ?? [];
            $intervalMinutes = $config['interval_minutes'] ?? 60;
            $schedule->update([
                'next_run_at' => CarbonImmutable::now()->addMinutes($intervalMinutes),
                'last_run_at' => CarbonImmutable::now(),
            ]);
        } else {
            $schedule->update([
                'is_active' => false,
                'last_run_at' => CarbonImmutable::now(),
            ]);
        }
    }
}
