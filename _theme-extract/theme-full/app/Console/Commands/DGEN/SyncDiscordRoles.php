<?php

namespace Pterodactyl\Console\Commands\DGEN;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Pterodactyl\Models\User;
use Pterodactyl\Models\DGEN\PermissionRole;

class SyncDiscordRoles extends Command
{
    protected $signature = 'dgen:discord:sync-roles
        {--dry-run : Show changes without applying them}
        {--notify : Send Discord notification with sync summary}';

    protected $description = 'Synchronize Discord server roles with Pterodactyl panel permissions.';

    public function handle(): int
    {
        $this->output->title('Discord Role Sync');

        $botToken = config('dgen.discord.bot_token');
        $guildId = config('dgen.discord.guild_id');

        if (!$botToken || !$guildId) {
            $this->error('Discord bot token or guild ID not configured.');
            return 1;
        }

        $discordRoles = $this->fetchDiscordRoles($botToken, $guildId);

        if ($discordRoles === null) {
            $this->error('Failed to fetch roles from Discord API.');
            return 1;
        }

        $this->info("Found " . count($discordRoles) . " role(s) on Discord guild.");
        $this->newLine();

        $permissionRoles = PermissionRole::all();
        $roleMap = $this->buildRoleMap($permissionRoles);

        $syncedCount = 0;
        $createdCount = 0;
        $skippedCount = 0;

        foreach ($discordRoles as $discordRole) {
            $roleName = $discordRole['name'];
            $discordRoleId = $discordRole['id'];

            if ($this->isIgnorableRole($roleName)) {
                $skippedCount++;
                continue;
            }

            $existing = $permissionRoles->first(fn ($pr) => $pr->discord_role_id === $discordRoleId);

            if ($existing) {
                if ($existing->name !== $roleName) {
                    if (!$this->option('dry-run')) {
                        $existing->update(['name' => $roleName]);
                    }
                    $this->line("  <info>Updated</info>: {$existing->name} -> {$roleName}");
                    $syncedCount++;
                } else {
                    $this->line("  <comment>Unchanged</comment>: {$roleName}");
                    $skippedCount++;
                }
            } else {
                if (!$this->option('dry-run')) {
                    PermissionRole::create([
                        'name' => $roleName,
                        'discord_role_id' => $discordRoleId,
                        'permissions' => json_encode([]),
                    ]);
                }
                $this->line("  <info>Created</info>: {$roleName} (Discord ID: {$discordRoleId})");
                $createdCount++;
            }
        }

        $this->newLine();

        if ($this->option('dry-run')) {
            $this->warn('[DRY-RUN] No changes applied.');
        }

        $this->table(
            ['Metric', 'Count'],
            [
                ['Synced (updated)', $syncedCount],
                ['Created', $createdCount],
                ['Skipped', $skippedCount],
            ]
        );

        return 0;
    }

    private function fetchDiscordRoles(string $botToken, string $guildId): ?array
    {
        try {
            $response = Http::withHeaders([
                'Authorization' => 'Bot ' . $botToken,
                'Content-Type' => 'application/json',
            ])->get("https://discord.com/api/v10/guilds/{$guildId}/roles");

            if ($response->successful()) {
                return $response->json();
            }

            Log::error('Discord API returned error', ['status' => $response->status()]);

            return null;
        } catch (\Exception $e) {
            Log::error('Failed to fetch Discord roles', ['error' => $e->getMessage()]);

            return null;
        }
    }

    private function buildRoleMap(\Illuminate\Support\Collection $permissionRoles): array
    {
        $map = [];

        foreach ($permissionRoles as $role) {
            if ($role->discord_role_id) {
                $map[$role->discord_role_id] = $role;
            }
        }

        return $map;
    }

    private function isIgnorableRole(string $roleName): bool
    {
        $ignorable = ['@everyone', 'Dyno', 'MEE6', 'Carl-bot', 'Ticket Tool'];

        return in_array($roleName, $ignorable) || str_starts_with($roleName, 'bot-');
    }
}
