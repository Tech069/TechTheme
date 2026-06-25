<?php

namespace Pterodactyl\Listeners\Auth;

use Illuminate\Auth\Events\PasswordReset;
use Illuminate\Contracts\Events\Dispatcher;
use Illuminate\Support\Facades\Log;
use Pterodactyl\Facades\Activity;

class PasswordResetListener
{
    /**
     * Handle password reset events.
     */
    public function handle(PasswordReset $event): void
    {
        $user = $event->user;

        try {
            Activity::event('auth:password-reset')
                ->withRequestMetadata()
                ->subject($user)
                ->log();

            Log::info("Password reset completed for user #{$user->id} ({$user->username}).");

            $this->revokeOldSessions($user);
        } catch (\Throwable $e) {
            Log::error("Failed to log password reset for user #{$user->id}: {$e->getMessage()}");
        }
    }

    /**
     * Revoke old sessions after password reset for security.
     */
    protected function revokeOldSessions($user): void
    {
        try {
            $user->tokens()->delete();

            Log::info("Revoked all API tokens for user #{$user->id} after password reset.");
        } catch (\Throwable $e) {
            Log::error("Failed to revoke tokens for user #{$user->id}: {$e->getMessage()}");
        }
    }

    /**
     * Subscribe to events.
     */
    public function subscribe(Dispatcher $events): void
    {
        $events->listen(PasswordReset::class, [self::class, 'handle']);
    }
}
