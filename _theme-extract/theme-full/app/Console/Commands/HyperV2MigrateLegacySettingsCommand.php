<?php

namespace Pterodactyl\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Pterodactyl\Models\Setting;

class HyperV2MigrateLegacySettingsCommand extends Command
{
    protected $signature = 'hyper:v2:migrate-settings
        {--dry-run : Preview changes without writing to the database}
        {--force : Skip confirmation prompt}';

    protected $description = 'Migrate legacy HyperV2 panel settings to the new structured format.';

    private const LEGACY_MAPPINGS = [
        'settings:branding:name' => 'app:branding:name',
        'settings:branding:logo' => 'app:branding:logo',
        'settings:branding:url' => 'app:branding:url',
        'settings:discord:bot_token' => 'integrations:discord:bot_token',
        'settings:discord:guild_id' => 'integrations:discord:guild_id',
        'settings:discord:webhook_url' => 'integrations:discord:webhook_url',
        'settings:billing:enabled' => 'billing:enabled',
        'settings:billing:currency' => 'billing:currency',
        'settings:billing:gateway' => 'billing:gateway',
        'settings:ddos:protection_enabled' => 'ddos:protection:enabled',
        'settings:ddos:alert_webhook' => 'ddos:protection:alert_webhook',
        'settings:subdomains:enabled' => 'subdomains:enabled',
        'settings:subdomains:cloudflare_token' => 'subdomains:cloudflare:token',
        'settings:auto_suspend:enabled' => 'server:auto_suspend:enabled',
        'settings:auto_suspend:threshold' => 'server:auto_suspend:threshold',
    ];

    private const SETTINGS_KEY_PREFIX = 'hyper:';

    public function handle(): int
    {
        $this->output->title('HyperV2 Legacy Settings Migration');

        $legacySettings = $this->getLegacySettings();

        if ($legacySettings->isEmpty()) {
            $this->info('No legacy settings found. Nothing to migrate.');

            return 0;
        }

        $this->info("Found {$legacySettings->count()} legacy setting(s) to migrate:");
        $this->newLine();

        $rows = [];
        foreach ($legacySettings as $setting) {
            $newKey = self::LEGACY_MAPPINGS[$setting->key] ?? null;
            $rows[] = [
                $setting->key,
                $newKey ?? '<comment>No mapping defined</comment>',
                substr($setting->value, 0, 50) . (strlen($setting->value) > 50 ? '...' : ''),
            ];
        }

        $this->table(['Old Key', 'New Key', 'Value (preview)'], $rows);

        if ($this->option('dry-run')) {
            $this->newLine();
            $this->warn('Dry-run mode: no changes will be written to the database.');

            return 0;
        }

        if (!$this->option('force') && !$this->confirm('Proceed with migration?', true)) {
            $this->info('Migration cancelled.');

            return 0;
        }

        $migratedCount = 0;
        $skippedCount = 0;
        $errorCount = 0;

        foreach ($legacySettings as $setting) {
            $newKey = self::LEGACY_MAPPINGS[$setting->key] ?? null;

            if (!$newKey) {
                $this->line("  <comment>Skip</comment>: {$setting->key} (no mapping defined)");
                $skippedCount++;
                continue;
            }

            try {
                DB::beginTransaction();

                Setting::updateOrCreate(
                    ['key' => $newKey],
                    ['value' => $setting->value]
                );

                $setting->delete();

                DB::commit();
                $migratedCount++;

                $this->line("  <info>Migrated</info>: {$setting->key} -> $newKey");
            } catch (\Exception $e) {
                DB::rollBack();
                $errorCount++;

                $this->error("  Failed to migrate {$setting->key}: " . $e->getMessage());
                Log::error('HyperV2 settings migration failed', [
                    'old_key' => $setting->key,
                    'new_key' => $newKey,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        $this->newLine();
        $this->info("Migration complete: $migratedCount migrated, $skippedCount skipped, $errorCount failed.");

        return $errorCount > 0 ? 1 : 0;
    }

    private function getLegacySettings(): \Illuminate\Support\Collection
    {
        $legacyKeys = array_keys(self::LEGACY_MAPPINGS);

        return Setting::whereIn('key', $legacyKeys)->get();
    }
}
