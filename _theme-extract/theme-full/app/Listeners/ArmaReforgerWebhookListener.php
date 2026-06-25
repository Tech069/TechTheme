<?php

namespace Pterodactyl\Listeners;

use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Http;
use Pterodactyl\Models\Server;

class ArmaReforgerWebhookListener
{
    /**
     * Handle the Arma Reforger webhook event.
     */
    public function handle(array $event): void
    {
        $serverId = $event['server_id'] ?? null;
        $eventType = $event['type'] ?? 'unknown';
        $payload = $event['payload'] ?? [];

        if (!$serverId) {
            Log::warning('Arma Reforger webhook received without server ID.');
            return;
        }

        $server = Server::find($serverId);
        if (!$server) {
            Log::warning("Server #{$serverId} not found for Arma Reforger webhook.");
            return;
        }

        try {
            match ($eventType) {
                'mod_loaded' => $this->handleModLoaded($server, $payload),
                'mod_failed' => $this->handleModFailed($server, $payload),
                'server_crash' => $this->handleServerCrash($server, $payload),
                'player_connected' => $this->handlePlayerEvent($server, $payload, 'connected'),
                'player_disconnected' => $this->handlePlayerEvent($server, $payload, 'disconnected'),
                default => Log::info("Unhandled Arma Reforger webhook type: {$eventType}"),
            };

            $this->notifyDiscord($server, $eventType, $payload);
        } catch (\Throwable $e) {
            Log::error(
                "Failed to process Arma Reforger webhook for server #{$serverId}: {$e->getMessage()}"
            );
        }
    }

    /**
     * Handle mod loaded event.
     */
    protected function handleModLoaded(Server $server, array $payload): void
    {
        $modId = $payload['mod_id'] ?? 'unknown';
        $modName = $payload['mod_name'] ?? $modId;

        Log::info("Mod '{$modName}' ({$modId}) loaded on server #{$server->id}.");
    }

    /**
     * Handle mod failed event.
     */
    protected function handleModFailed(Server $server, array $payload): void
    {
        $modId = $payload['mod_id'] ?? 'unknown';
        $error = $payload['error'] ?? 'Unknown error';

        Log::warning("Mod {$modId} failed to load on server #{$server->id}: {$error}");
    }

    /**
     * Handle server crash event.
     */
    protected function handleServerCrash(Server $server, array $payload): void
    {
        $reason = $payload['reason'] ?? 'Unknown';

        Log::critical("Server #{$server->id} ({$server->name}) crashed: {$reason}");
    }

    /**
     * Handle player connect/disconnect events.
     */
    protected function handlePlayerEvent(Server $server, array $payload, string $action): void
    {
        $playerName = $payload['player_name'] ?? 'Unknown';
        $playerId = $payload['player_id'] ?? null;

        Log::info("Player '{$playerName}' {$action} server #{$server->id}.");
    }

    /**
     * Send Discord notification if webhook is configured.
     */
    protected function notifyDiscord(Server $server, string $eventType, array $payload): void
    {
        $webhookUrl = config('dgen.arma_reforger.discord_webhook');
        if (!$webhookUrl) {
            return;
        }

        $embed = [
            'title' => "Arma Reforger Event: {$eventType}",
            'description' => "Server: {$server->name} (#{$server->id})",
            'color' => match ($eventType) {
                'mod_failed', 'server_crash' => 0xFF0000,
                'mod_loaded' => 0x00FF00,
                default => 0x3498DB,
            },
            'fields' => [],
            'timestamp' => now()->toIso8601String(),
        ];

        foreach ($payload as $key => $value) {
            if (is_scalar($value)) {
                $embed['fields'][] = [
                    'name' => $key,
                    'value' => (string) $value,
                    'inline' => true,
                ];
            }
        }

        try {
            Http::post($webhookUrl, ['embeds' => [$embed]]);
        } catch (\Throwable $e) {
            Log::error("Failed to send Discord webhook for Arma Reforger event: {$e->getMessage()}");
        }
    }
}
