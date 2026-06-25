<?php

namespace Pterodactyl\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Pterodactyl\Models\Server;

class HyperV2RequiredUpdateService
{
    private const UPDATE_CHECK_CACHE_KEY = 'hyper:update_check';

    private const UPDATE_CHECK_CACHE_TTL = 3600;

    private const VERSION_API_URL = 'https://api.hyperpanel.dev/v1/version';

    public function __construct()
    {
    }

    /**
     * Get the current installed panel version.
     */
    public function getCurrentVersion(): string
    {
        return config('app.version', '0.0.0');
    }

    /**
     * Check if a required update is available by querying the version API.
     */
    public function checkForUpdate(): array
    {
        return Cache::remember(self::UPDATE_CHECK_CACHE_KEY, self::UPDATE_CHECK_CACHE_TTL, function () {
            try {
                $currentVersion = $this->getCurrentVersion();

                $response = Http::timeout(10)->get(self::VERSION_API_URL, [
                    'current' => $currentVersion,
                ]);

                if (!$response->successful()) {
                    Log::warning('Update check API returned non-success status', [
                        'status' => $response->status(),
                    ]);

                    return [
                        'update_available' => false,
                        'current_version' => $currentVersion,
                        'latest_version' => $currentVersion,
                        'error' => 'API returned status ' . $response->status(),
                    ];
                }

                $data = $response->json();

                return [
                    'update_available' => version_compare($data['latest_version'] ?? $currentVersion, $currentVersion, '>'),
                    'current_version' => $currentVersion,
                    'latest_version' => $data['latest_version'] ?? $currentVersion,
                    'download_url' => $data['download_url'] ?? null,
                    'changelog' => $data['changelog'] ?? null,
                    'required' => $data['required'] ?? false,
                    'security_patch' => $data['security_patch'] ?? false,
                ];
            } catch (\Exception $exception) {
                Log::error('Failed to check for updates', [
                    'error' => $exception->getMessage(),
                ]);

                return [
                    'update_available' => false,
                    'current_version' => $this->getCurrentVersion(),
                    'latest_version' => null,
                    'error' => $exception->getMessage(),
                ];
            }
        });
    }

    /**
     * Check if the current version is outdated.
     */
    public function isOutdated(): bool
    {
        $check = $this->checkForUpdate();

        return $check['update_available'] ?? false;
    }

    /**
     * Check if a security patch is available.
     */
    public function hasSecurityPatch(): bool
    {
        $check = $this->checkForUpdate();

        return $check['security_patch'] ?? false;
    }

    /**
     * Force refresh the update check.
     */
    public function forceCheck(): array
    {
        Cache::forget(self::UPDATE_CHECK_CACHE_KEY);

        return $this->checkForUpdate();
    }

    /**
     * Compare two version strings.
     */
    public function compareVersions(string $version1, string $version2): int
    {
        return version_compare($version1, $version2);
    }

    /**
     * Check if the installed version meets minimum requirements.
     */
    public function meetsMinimumVersion(string $minimumVersion): bool
    {
        return version_compare($this->getCurrentVersion(), $minimumVersion, '>=');
    }
}
