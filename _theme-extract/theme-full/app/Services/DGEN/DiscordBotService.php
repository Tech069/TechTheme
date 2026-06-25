<?php

namespace Pterodactyl\Services\DGEN;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Cache;
use Pterodactyl\Models\User;
use Pterodactyl\Models\Server;

class DiscordBotService
{
    private const API_BASE = 'https://discord.com/api/v10';

    private const RATE_LIMIT_CACHE = 'discord_rate_limit';

    private string $botToken;

    private string $guildId;

    public function __construct()
    {
        $this->botToken = config('services.discord.bot_token', '');
        $this->guildId = config('services.discord.guild_id', '');
    }

    /**
     * Make an authenticated request to the Discord API.
     */
    private function request(string $method, string $endpoint, array $data = [], array $headers = []): ?array
    {
        if (empty($this->botToken)) {
            Log::warning('Discord bot token is not configured.');

            return null;
        }

        // Check rate limit
        $rateLimitKey = self::RATE_LIMIT_CACHE . ':' . md5($endpoint);
        if (Cache::has($rateLimitKey)) {
            Log::debug('Discord API rate limit active', ['endpoint' => $endpoint]);

            return null;
        }

        try {
            $url = self::API_BASE . $endpoint;

            $response = Http::withHeaders(array_merge([
                'Authorization' => 'Bot ' . $this->botToken,
                'Content-Type' => 'application/json',
            ], $headers))->timeout(15);

            $response = match ($method) {
                'GET' => $response->get($url),
                'POST' => $response->post($url, $data),
                'PATCH' => $response->patch($url, $data),
                'DELETE' => $response->delete($url),
                default => $response->get($url),
            };

            if ($response->status() === 429) {
                $retryAfter = $response->json('retry_after', 1);
                Cache::put($rateLimitKey, true, (int) ceil($retryAfter * 1000));

                return null;
            }

            if ($response->successful()) {
                return $response->json();
            }

            Log::warning('Discord API request failed', [
                'endpoint' => $endpoint,
                'status' => $response->status(),
                'body' => $response->body(),
            ]);

            return null;
        } catch (\Exception $exception) {
            Log::error('Discord API error', [
                'endpoint' => $endpoint,
                'error' => $exception->getMessage(),
            ]);

            return null;
        }
    }

    /**
     * Send a message to a Discord channel.
     */
    public function sendMessage(string $channelId, string $content, ?array $embed = null): ?array
    {
        $payload = ['content' => $content];

        if ($embed !== null) {
            $payload['embeds'] = [$embed];
        }

        return $this->request('POST', "/channels/$channelId/messages", $payload);
    }

    /**
     * Send an embedded message to a Discord channel.
     */
    public function sendEmbed(string $channelId, string $title, string $description, int $color = 0x3498DB, array $fields = []): ?array
    {
        $embed = [
            'title' => $title,
            'description' => $description,
            'color' => $color,
            'timestamp' => now()->toISOString(),
        ];

        if (!empty($fields)) {
            $embed['fields'] = array_map(function ($field) {
                return [
                    'name' => $field['name'] ?? '',
                    'value' => $field['value'] ?? '',
                    'inline' => $field['inline'] ?? false,
                ];
            }, $fields);
        }

        return $this->sendMessage($channelId, '', $embed);
    }

    /**
     * Sync a user's Discord roles with their panel permissions.
     */
    public function syncUserRoles(User $user): array
    {
        if (empty($this->guildId) || empty($this->botToken)) {
            return ['synced' => false, 'error' => 'Discord not configured'];
        }

        try {
            // Find the Discord member by their email or external ID
            $member = $this->findMemberByEmail($user->email);

            if (!$member) {
                return ['synced' => false, 'error' => 'Discord member not found'];
            }

            $memberId = $member['user']['id'];
            $currentRoles = $member['roles'] ?? [];

            $roleMap = config('services.discord.role_map', []);
            $desiredRoles = [];

            foreach ($roleMap as $panelPermission => $discordRoleId) {
                if ($user->root_admin || $this->userHasPermission($user, $panelPermission)) {
                    $desiredRoles[] = $discordRoleId;
                }
            }

            // Add default role
            $defaultRole = config('services.discord.default_role_id');
            if ($defaultRole) {
                $desiredRoles[] = $defaultRole;
            }

            $rolesToAdd = array_diff($desiredRoles, $currentRoles);
            $rolesToRemove = array_intersect($currentRoles, array_values($roleMap));

            foreach ($rolesToAdd as $roleId) {
                $this->request('PUT', "/guilds/$this->guildId/members/$memberId/roles/$roleId");
            }

            foreach ($rolesToRemove as $roleId) {
                $this->request('DELETE', "/guilds/$this->guildId/members/$memberId/roles/$roleId");
            }

            return [
                'synced' => true,
                'roles_added' => count($rolesToAdd),
                'roles_removed' => count($rolesToRemove),
            ];
        } catch (\Exception $exception) {
            Log::error('Discord role sync failed', [
                'user_id' => $user->id,
                'error' => $exception->getMessage(),
            ]);

            return ['synced' => false, 'error' => $exception->getMessage()];
        }
    }

    /**
     * Find a Discord member by email.
     */
    private function findMemberByEmail(string $email): ?array
    {
        // Search through guild members
        $members = $this->request('GET', "/guilds/$this->guildId/members?limit=1000");

        if (!$members) {
            return null;
        }

        foreach ($members as $member) {
            if (isset($member['user']['email']) && $member['user']['email'] === $email) {
                return $member;
            }
        }

        return null;
    }

    /**
     * Check if a user has a specific permission.
     */
    private function userHasPermission(User $user, string $permission): bool
    {
        // Simplified check - in production you'd check against the user's subuser permissions
        return $user->root_admin;
    }

    /**
     * Get Discord bot information.
     */
    public function getBotInfo(): ?array
    {
        return $this->request('GET', '/users/@me');
    }

    /**
     * Send a notification to a channel about a server event.
     */
    public function notifyServerEvent(Server $server, string $event, array $data = []): void
    {
        $channelId = config('services.discord.notification_channel_id');

        if (empty($channelId)) {
            return;
        }

        $colorMap = [
            'start' => 0x2ECC71,
            'stop' => 0xE74C3C,
            'crash' => 0xE74C3C,
            'backup' => 0x3498DB,
            'suspend' => 0xF39C12,
        ];

        $this->sendEmbed(
            $channelId,
            'Server ' . ucfirst($event),
            sprintf('**%s** (ID: %d) has been %s.', $server->name, $server->id, $event),
            $colorMap[$event] ?? 0x95A5A6,
            array_merge([
                ['name' => 'Server', 'value' => $server->name, 'inline' => true],
                ['name' => 'Node', 'value' => $server->node->name ?? 'Unknown', 'inline' => true],
            ], array_map(fn ($key, $value) => ['name' => $key, 'value' => (string) $value], array_keys($data), $data))
        );
    }
}
