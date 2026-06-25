<?php

namespace Pterodactyl\Http\Middleware\Api\Client\Server;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Pterodactyl\Models\Server;

class EnsureDiscordMembership
{
    /**
     * Discord API base URL.
     */
    protected string $discordApiBase = 'https://discord.com/api/v10';

    /**
     * Handle an incoming request.
     */
    public function handle(Request $request, Closure $next): mixed
    {
        $server = $request->route()->parameter('server');

        if (!$server instanceof Server) {
            return $next($request);
        }

        $discordGuildId = config('dgen.discord.guild_id');
        $botToken = config('dgen.discord.bot_token');

        if (!$discordGuildId || !$botToken) {
            return $next($request);
        }

        $user = $request->user();
        if (!$user) {
            return $next($request);
        }

        $discordId = $user->discord_id ?? null;
        if (!$discordId) {
            $discordId = $this->getDiscordIdFromExternalId($user);
        }

        if (!$discordId) {
            return $this->denyAccess($request, 'Discord account not linked.');
        }

        try {
            $isMember = $this->checkGuildMembership($discordGuildId, $discordId, $botToken);

            if (!$isMember) {
                return $this->denyAccess($request, 'You must be a member of our Discord server.');
            }
        } catch (\Throwable $e) {
            Log::error("Discord membership check failed: {$e->getMessage()}");
        }

        return $next($request);
    }

    /**
     * Get Discord ID from external_id field.
     */
    protected function getDiscordIdFromExternalId($user): ?string
    {
        if (!$user->external_id) {
            return null;
        }

        if (is_numeric($user->external_id)) {
            return $user->external_id;
        }

        return null;
    }

    /**
     * Check if a user is a member of the Discord guild.
     */
    protected function checkGuildMembership(string $guildId, string $userId, string $botToken): bool
    {
        $response = Http::withHeaders([
            'Authorization' => "Bot {$botToken}",
            'Content-Type' => 'application/json',
        ])->get("{$this->discordApiBase}/guilds/{$guildId}/members/{$userId}");

        return $response->successful();
    }

    /**
     * Deny access with a response.
     */
    protected function denyAccess(Request $request, string $message): mixed
    {
        if ($request->isJson() || $request->is('api/*')) {
            return response()->json([
                'error' => 'Discord membership required',
                'message' => $message,
            ], 403);
        }

        return redirect()->back()->withErrors(['discord' => $message]);
    }
}
