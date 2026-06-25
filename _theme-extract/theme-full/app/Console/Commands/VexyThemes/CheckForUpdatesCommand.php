<?php

namespace Pterodactyl\Console\Commands\VexyThemes;

use Illuminate\Console\Command;
use Pterodactyl\Services\VexyThemes\UpdateService;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

class CheckForUpdatesCommand extends Command
{
    protected $signature = 'vexythemes:check-updates';
    protected $description = 'Check for VexyThemes updates and notify if available';

    public function handle(UpdateService $updateService): int
    {
        $licenseKey = $this->getLicenseKey();
        if (!$licenseKey) {
            $this->info('No license key configured. Skipping update check.');
            return self::SUCCESS;
        }

        $lastCheck = Cache::get('vexythemes:last_update_check');
        if ($lastCheck && now()->diffInMinutes(now()->parse($lastCheck)) < 60) {
            $this->info('Update checked recently. Skipping.');
            return self::SUCCESS;
        }

        $this->info('Checking for VexyThemes updates...');
        $result = $updateService->checkForUpdate($licenseKey);

        Cache::put('vexythemes:last_update_check', now()->toIso8601String());

        if (isset($result['available']) && $result['available']) {
            Cache::put('vexythemes:update_available', true);
            Cache::put('vexythemes:update_version', $result['version'] ?? 'unknown');
            Cache::put('vexythemes:update_changelog', $result['changelog'] ?? '');
            Cache::put('vexythemes:update_date', $result['released'] ?? '');

            $this->info("Update available: v{$result['version']}");
            Log::info("VexyThemes update available: v{$result['version']}");
        } else {
            Cache::forget('vexythemes:update_available');
            $this->info('Theme is up to date.');
        }

        return self::SUCCESS;
    }

    private function getLicenseKey(): ?string
    {
        $setting = \Pterodactyl\Models\Setting::where('key', 'vexythemes:license_key')->first();
        return $setting?->value;
    }
}
