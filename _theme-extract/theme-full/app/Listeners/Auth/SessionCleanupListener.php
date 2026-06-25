<?php

namespace Pterodactyl\Listeners\Auth;

use Illuminate\Contracts\Events\Dispatcher;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Pterodactyl\Facades\Activity;

class SessionCleanupListener
{
    /**
     * Handle session cleanup.
     */
    public function handle(): void
    {
        try {
            $inactiveMinutes = config('session.cleanup_inactive_minutes', 60);

            $cutoff = now()->subMinutes($inactiveMinutes);

            $deletedCount = DB::table('sessions')
                ->where('last_activity', '<', $cutoff->timestamp)
                ->delete();

            if ($deletedCount > 0) {
                Log::info("Cleaned up {$deletedCount} inactive sessions.");
            }

            $expiredCount = DB::table('sessions')
                ->where('lifetime', '<', now()->timestamp)
                ->delete();

            if ($expiredCount > 0) {
                Log::info("Removed {$expiredCount} expired sessions.");
            }
        } catch (\Throwable $e) {
            Log::error("Session cleanup failed: {$e->getMessage()}");
        }
    }

    /**
     * Subscribe to events.
     */
    public function subscribe(Dispatcher $events): void
    {
        $events->listen('auth:logout', [self::class, 'handle']);
    }
}
