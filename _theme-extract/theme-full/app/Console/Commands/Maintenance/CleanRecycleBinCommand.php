<?php

namespace Pterodactyl\Console\Commands\Maintenance;

use Carbon\CarbonImmutable;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Log;

class CleanRecycleBinCommand extends Command
{
    protected $signature = 'p:maintenance:clean-recycle-bin
        {--dry-run : Show what would be deleted without actually deleting}
        {--server-id= : Specific server ID to clean}';

    protected $description = 'Clean expired entries from the server recycle bin and remove their physical files.';

    public function handle(): int
    {
        $this->output->title('Recycle Bin Cleanup');

        $query = DB::table('server_recycle_bin')
            ->where('expires_at', '<', CarbonImmutable::now());

        if ($this->option('server-id')) {
            $query->where('server_id', (int) $this->option('server-id'));
        }

        $expiredEntries = $query->get();

        if ($expiredEntries->isEmpty()) {
            $this->info('No expired recycle bin entries found.');

            return 0;
        }

        $this->info("Found {$expiredEntries->count()} expired recycle bin entry(ies)...");
        $this->newLine();

        $totalSize = 0;
        $deletedCount = 0;
        $failedCount = 0;

        foreach ($expiredEntries as $entry) {
            $size = $entry->size ?? 0;
            $totalSize += $size;

            $this->line("  {$entry->name} (Server #{$entry->server_id}) — " . $this->formatBytes($size));

            if ($this->option('dry-run')) {
                $deletedCount++;
                continue;
            }

            try {
                if ($entry->recycle_bin_path && File::exists($entry->recycle_bin_path)) {
                    if ($entry->is_file) {
                        File::delete($entry->recycle_bin_path);
                    } else {
                        File::deleteDirectory($entry->recycle_bin_path);
                    }
                }

                DB::table('server_recycle_bin')
                    ->where('id', $entry->id)
                    ->delete();

                $deletedCount++;
            } catch (\Exception $e) {
                $failedCount++;
                $this->error("    Failed to delete: " . $e->getMessage());
                Log::error('Recycle bin cleanup failed', [
                    'entry_id' => $entry->id,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        $this->newLine();

        if ($this->option('dry-run')) {
            $this->warn("[DRY-RUN] Would free " . $this->formatBytes($totalSize) . " across $deletedCount entry(ies).");
        } else {
            $this->info("Cleanup complete: $deletedCount deleted, $failedCount failed, " . $this->formatBytes($totalSize) . " freed.");
        }

        return $failedCount > 0 ? 1 : 0;
    }

    private function formatBytes(int $bytes): string
    {
        if ($bytes === 0) {
            return '0 B';
        }

        $units = ['B', 'KB', 'MB', 'GB', 'TB'];
        $i = (int) floor(log($bytes, 1024));

        return round($bytes / pow(1024, $i), 2) . ' ' . ($units[$i] ?? 'B');
    }
}
