<?php

namespace Pterodactyl\Jobs;

use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Process;
use Pterodactyl\Models\Setting;

class HyperV2UpdateJob extends Job
{
    /**
     * Create a new job instance.
     */
    public function __construct(
        public string $targetVersion,
    ) {
        $this->queue = 'high';
        $this->tries = 1;
    }

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        Log::info("Starting HyperV2 panel update to version {$this->targetVersion}.");

        try {
            $currentVersion = Setting::where('key', 'app:version')->value('value') ?? 'unknown';
            Log::info("Current panel version: {$currentVersion}");

            $result = Process::run('git fetch --all 2>&1');

            if ($result->failed()) {
                throw new \RuntimeException("Git fetch failed: {$result->errorOutput()}");
            }

            $result = Process::run("git checkout {$this->targetVersion} 2>&1");

            if ($result->failed()) {
                throw new \RuntimeException("Git checkout failed: {$result->errorOutput()}");
            }

            $result = Process::run('composer install --no-dev --optimize-autoloader 2>&1');

            if ($result->failed()) {
                throw new \RuntimeException("Composer install failed: {$result->errorOutput()}");
            }

            $result = Process::run('php artisan migrate --force 2>&1');

            if ($result->failed()) {
                throw new \RuntimeException("Migration failed: {$result->errorOutput()}");
            }

            $result = Process::run('php artisan config:cache 2>&1');
            $result = Process::run('php artisan route:cache 2>&1');
            $result = Process::run('php artisan view:cache 2>&1');

            Setting::where('key', 'app:version')->update(['value' => $this->targetVersion]);

            Log::info("HyperV2 panel updated successfully to version {$this->targetVersion}.");
        } catch (\Throwable $e) {
            Log::error("HyperV2 update failed: {$e->getMessage()}");
            throw $e;
        }
    }
}
