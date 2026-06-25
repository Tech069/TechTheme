<?php

namespace Pterodactyl\Console\Commands\Maintenance;

use Carbon\CarbonImmutable;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Log;

class CleanTempDatabaseExportsCommand extends Command
{
    protected $signature = 'p:maintenance:clean-temp-exports
        {--age=60 : Age in minutes after which temp exports are stale}
        {--dry-run : Show what would be deleted without actually deleting}
        {--path= : Custom path to scan for temp exports}';

    protected $description = 'Clean up temporary database export files that are older than the configured threshold.';

    private const TEMP_EXPORT_PATTERNS = ['*.sql.gz', '*.sql', '*.dump', '*.export.*'];

    public function handle(): int
    {
        $ageMinutes = (int) $this->option('age');
        $customPath = $this->option('path');

        $this->output->title('Temp Database Export Cleanup');

        $exportPath = $customPath ?? storage_path('app/exports');

        if (!File::isDirectory($exportPath)) {
            $this->info("Export directory not found: $exportPath");
            $this->info('Nothing to clean.');

            return 0;
        }

        $cutoff = CarbonImmutable::now()->subMinutes($ageMinutes);
        $deletedCount = 0;
        $failedCount = 0;
        $totalSize = 0;

        foreach (self::TEMP_EXPORT_PATTERNS as $pattern) {
            $files = File::glob($exportPath . '/' . $pattern);

            foreach ($files as $file) {
                $lastModified = CarbonImmutable::createFromTimestamp(File::lastModified($file));

                if ($lastModified->greaterThan($cutoff)) {
                    continue;
                }

                $size = File::size($file);
                $totalSize += $size;
                $relativePath = str_replace($exportPath . '/', '', $file);

                $this->line("  {$relativePath} — " . $this->formatBytes($size) . " (age: {$lastModified->diffForHumans(null, true)})");

                if ($this->option('dry-run')) {
                    $deletedCount++;
                    continue;
                }

                try {
                    File::delete($file);
                    $deletedCount++;
                } catch (\Exception $e) {
                    $failedCount++;
                    $this->error("    Failed to delete: " . $e->getMessage());
                    Log::error('Temp export cleanup failed', [
                        'file' => $file,
                        'error' => $e->getMessage(),
                    ]);
                }
            }
        }

        $this->newLine();

        if ($this->option('dry-run')) {
            $this->warn("[DRY-RUN] Would delete $deletedCount file(s) freeing " . $this->formatBytes($totalSize) . ".");
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
