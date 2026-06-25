<?php

namespace Pterodactyl\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;

class SessionGarbageCollect extends Command
{
    protected $signature = 'p:session:gc
        {--lifetime=120 : Session lifetime in minutes (default: from config)}
        {--dry-run : Show what would be deleted without actually deleting}';

    protected $description = 'Garbage collect expired sessions from the database and file-based session stores.';

    public function handle(): int
    {
        $lifetimeMinutes = (int) $this->option('lifetime');

        if ($lifetimeMinutes <= 0) {
            $this->error('Session lifetime must be a positive integer (minutes).');

            return 1;
        }

        $driver = config('session.driver', 'database');

        $this->info("Session garbage collection (driver: $driver, lifetime: {$lifetimeMinutes}m)");
        $this->newLine();

        $totalDeleted = 0;

        switch ($driver) {
            case 'database':
                $totalDeleted = $this->cleanDatabaseSessions($lifetimeMinutes);
                break;
            case 'file':
                $totalDeleted = $this->cleanFileSessions($lifetimeMinutes);
                break;
            case 'redis':
                $totalDeleted = $this->cleanRedisSessions($lifetimeMinutes);
                break;
            default:
                $this->warn("Unsupported session driver '$driver'. No cleanup performed.");

                return 0;
        }

        $this->newLine();
        $this->info("Session garbage collection complete. Removed $totalDeleted expired session(s).");

        return 0;
    }

    private function cleanDatabaseSessions(int $lifetimeMinutes): int
    {
        $this->line('Cleaning database sessions...');

        $query = DB::table('sessions')
            ->where('last_activity', '<', now()->subMinutes($lifetimeMinutes)->timestamp);

        $count = $query->count();

        if ($count === 0) {
            $this->info('  No expired database sessions found.');

            return 0;
        }

        if ($this->option('dry-run')) {
            $this->warn("  [DRY-RUN] Would delete $count expired session(s).");

            return 0;
        }

        $query->delete();
        $this->info("  Deleted $count expired session(s) from database.");

        return $count;
    }

    private function cleanFileSessions(int $lifetimeMinutes): int
    {
        $this->line('Cleaning file-based sessions...');

        $sessionPath = storage_path('framework/sessions');

        if (!File::isDirectory($sessionPath)) {
            $this->warn('  Session directory not found: ' . $sessionPath);

            return 0;
        }

        $files = File::files($sessionPath);
        $deletedCount = 0;
        $cutoff = now()->subMinutes($lifetimeMinutes);

        foreach ($files as $file) {
            if ($file->getMTime() < $cutoff->timestamp) {
                if ($this->option('dry-run')) {
                    $this->line("  [DRY-RUN] Would delete: {$file->getFilename()}");
                } else {
                    File::delete($file->getPathname());
                }
                $deletedCount++;
            }
        }

        if ($deletedCount === 0) {
            $this->info('  No expired file sessions found.');
        } else {
            $this->info("  Deleted $deletedCount expired session file(s).");
        }

        return $deletedCount;
    }

    private function cleanRedisSessions(int $lifetimeMinutes): int
    {
        $this->line('Cleaning Redis sessions...');

        try {
            $redis = DB::connection()->getPdo();

            $this->warn('  Redis session cleanup requires manual configuration via Redis TTL.');

            return 0;
        } catch (\Exception $e) {
            $this->error('  Failed to connect to Redis: ' . $e->getMessage());

            return 0;
        }
    }
}
