<?php

namespace Pterodactyl\Services;

use Illuminate\Support\Facades\Log;
use Pterodactyl\Models\Setting;

class HyperV2LegacySettingsMigrator
{
    private const LEGACY_KEY_PREFIX = 'hyper:';

    private const KEY_MAPPINGS = [
        'hyper:panel:license_key' => 'license:key',
        'hyper:panel:license_expiry' => 'license:expiry',
        'hyper:panel:version' => 'system:version',
        'hyper:panel:maintenance' => 'system:maintenance',
        'hyper:server:default_memory' => 'deploy:default_memory',
        'hyper:server:default_disk' => 'deploy:default_disk',
        'hyper:server:default_cpu' => 'deploy:default_cpu',
        'hyper:server:max_servers' => 'deploy:max_servers_per_user',
        'hyper:discord:bot_token' => 'integrations:discord:bot_token',
        'hyper:discord:webhook_url' => 'integrations:discord:webhook_url',
        'hyper:discord:enabled' => 'integrations:discord:enabled',
        'hyper:email:from_address' => 'mail:from_address',
        'hyper:email:from_name' => 'mail:from_name',
        'hyper:backup:enabled' => 'backups:enabled',
        'hyper:backup:interval' => 'backups:interval_hours',
        'hyper:security:2fa_enforced' => 'security:2fa_enforced',
        'hyper:security:recaptcha_key' => 'security:recaptcha:key',
        'hyper:security:recaptcha_secret' => 'security:recaptcha:secret',
    ];

    public function __construct()
    {
    }

    /**
     * Run the migration process for all legacy settings.
     */
    public function migrate(): array
    {
        $results = [
            'migrated' => 0,
            'skipped' => 0,
            'failed' => 0,
            'details' => [],
        ];

        foreach (self::KEY_MAPPINGS as $oldKey => $newKey) {
            $setting = Setting::where('key', $oldKey)->first();

            if (!$setting) {
                $results['skipped']++;
                $results['details'][] = [
                    'old_key' => $oldKey,
                    'new_key' => $newKey,
                    'status' => 'skipped',
                    'reason' => 'not_found',
                ];
                continue;
            }

            try {
                $existingNew = Setting::where('key', $newKey)->first();

                if ($existingNew) {
                    $results['skipped']++;
                    $results['details'][] = [
                        'old_key' => $oldKey,
                        'new_key' => $newKey,
                        'status' => 'skipped',
                        'reason' => 'new_key_already_exists',
                    ];
                    continue;
                }

                Setting::create([
                    'key' => $newKey,
                    'value' => $setting->value,
                ]);

                $setting->delete();

                $results['migrated']++;
                $results['details'][] = [
                    'old_key' => $oldKey,
                    'new_key' => $newKey,
                    'status' => 'migrated',
                ];
            } catch (\Exception $exception) {
                $results['failed']++;
                $results['details'][] = [
                    'old_key' => $oldKey,
                    'new_key' => $newKey,
                    'status' => 'failed',
                    'error' => $exception->getMessage(),
                ];

                Log::error('Settings migration failed', [
                    'old_key' => $oldKey,
                    'new_key' => $newKey,
                    'error' => $exception->getMessage(),
                ]);
            }
        }

        return $results;
    }

    /**
     * Check if there are any legacy settings remaining.
     */
    public function hasLegacySettings(): bool
    {
        $legacyKeys = array_keys(self::KEY_MAPPINGS);

        return Setting::whereIn('key', $legacyKeys)->exists();
    }

    /**
     * Get a list of all legacy settings still present.
     */
    public function getLegacySettings(): array
    {
        $legacyKeys = array_keys(self::KEY_MAPPINGS);

        return Setting::whereIn('key', $legacyKeys)
            ->get()
            ->map(fn ($setting) => [
                'old_key' => $setting->key,
                'new_key' => self::KEY_MAPPINGS[$setting->key] ?? null,
                'value' => $setting->value,
            ])
            ->toArray();
    }

    /**
     * Rollback the migration by restoring legacy settings from new keys.
     */
    public function rollback(): array
    {
        $results = [
            'restored' => 0,
            'skipped' => 0,
            'failed' => 0,
            'details' => [],
        ];

        $reverseMappings = array_flip(self::KEY_MAPPINGS);

        foreach ($reverseMappings as $newKey => $oldKey) {
            $setting = Setting::where('key', $newKey)->first();

            if (!$setting) {
                $results['skipped']++;
                $results['details'][] = [
                    'old_key' => $oldKey,
                    'new_key' => $newKey,
                    'status' => 'skipped',
                    'reason' => 'not_found',
                ];
                continue;
            }

            try {
                $existingLegacy = Setting::where('key', $oldKey)->first();

                if ($existingLegacy) {
                    $results['skipped']++;
                    $results->details[] = [
                        'old_key' => $oldKey,
                        'new_key' => $newKey,
                        'status' => 'skipped',
                        'reason' => 'legacy_key_already_exists',
                    ];
                    continue;
                }

                Setting::create([
                    'key' => $oldKey,
                    'value' => $setting->value,
                ]);

                $setting->delete();

                $results['restored']++;
                $results['details'][] = [
                    'old_key' => $oldKey,
                    'new_key' => $newKey,
                    'status' => 'restored',
                ];
            } catch (\Exception $exception) {
                $results['failed']++;
                $results['details'][] = [
                    'old_key' => $oldKey,
                    'new_key' => $newKey,
                    'status' => 'failed',
                    'error' => $exception->getMessage(),
                ];
            }
        }

        return $results;
    }
}
