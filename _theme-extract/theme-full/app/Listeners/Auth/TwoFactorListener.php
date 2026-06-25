<?php

namespace Pterodactyl\Listeners\Auth;

use Illuminate\Contracts\Events\Dispatcher;
use Illuminate\Support\Facades\Log;
use Pterodactyl\Facades\Activity;
use Pterodactyl\Events\Auth\ProvidedAuthenticationToken;

class TwoFactorListener
{
    /**
     * Handle two-factor authentication events.
     */
    public function handle(ProvidedAuthenticationToken $event): void
    {
        $user = $event->user;
        $isRecovery = $event->recovery ?? false;

        try {
            $eventName = $isRecovery ? 'auth:2fa:recovery' : 'auth:2fa:success';

            Activity::event($eventName)
                ->withRequestMetadata()
                ->subject($user)
                ->properties([
                    'method' => $isRecovery ? 'recovery_token' : 'totp',
                ])
                ->log();

            Log::info(
                "Two-factor authentication " . ($isRecovery ? 'recovery token' : 'TOTP') .
                " used by user #{$user->id} ({$user->username})."
            );
        } catch (\Throwable $e) {
            Log::error("Failed to log 2FA event for user #{$user->id}: {$e->getMessage()}");
        }
    }

    /**
     * Subscribe to events.
     */
    public function subscribe(Dispatcher $events): void
    {
        $events->listen(ProvidedAuthenticationToken::class, [self::class, 'handle']);
    }
}
