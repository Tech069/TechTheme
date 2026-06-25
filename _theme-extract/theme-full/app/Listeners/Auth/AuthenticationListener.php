<?php

namespace Pterodactyl\Listeners\Auth;

use Illuminate\Auth\Events\Failed;
use Illuminate\Auth\Events\Login;
use Illuminate\Auth\Events\Registered;
use Illuminate\Contracts\Events\Dispatcher;
use Illuminate\Support\Facades\Log;
use Pterodactyl\Facades\Activity;
use Pterodactyl\Models\UserLoginHistory;

class AuthenticationListener
{
    /**
     * Handle user login events.
     */
    public function handleLogin(Login $event): void
    {
        $user = $event->user;
        $request = request();

        try {
            Activity::event('auth:login')
                ->withRequestMetadata()
                ->subject($user)
                ->log();

            UserLoginHistory::create([
                'user_id' => $user->id,
                'ip_address' => $request->ip() ?? '127.0.0.1',
                'user_agent' => $request->userAgent(),
                'success' => true,
            ]);
        } catch (\Throwable $e) {
            Log::error("Failed to log login event for user #{$user->id}: {$e->getMessage()}");
        }
    }

    /**
     * Handle failed login attempts.
     */
    public function handleFailed(Failed $event): void
    {
        $request = request();
        $credentials = $event->credentials ?? [];

        try {
            $email = $credentials['email'] ?? $credentials['username'] ?? null;

            Activity::event('auth:fail')
                ->withRequestMetadata()
                ->properties([
                    'email' => $email,
                ])
                ->log();

            if ($email) {
                $user = \Pterodactyl\Models\User::where('email', $email)
                    ->orWhere('username', $email)
                    ->first();

                if ($user) {
                    UserLoginHistory::create([
                        'user_id' => $user->id,
                        'ip_address' => $request->ip() ?? '127.0.0.1',
                        'user_agent' => $request->userAgent(),
                        'success' => false,
                    ]);
                }
            }
        } catch (\Throwable $e) {
            Log::error("Failed to log failed login attempt: {$e->getMessage()}");
        }
    }

    /**
     * Handle user registration events.
     */
    public function handleRegistered(Registered $event): void
    {
        try {
            Activity::event('auth:register')
                ->withRequestMetadata()
                ->subject($event->user)
                ->log();

            Log::info("New user registered: {$event->user->username} (#{$event->user->id}).");
        } catch (\Throwable $e) {
            Log::error("Failed to log registration event: {$e->getMessage()}");
        }
    }

    /**
     * Subscribe to events.
     */
    public function subscribe(Dispatcher $events): void
    {
        $events->listen(Login::class, [self::class, 'handleLogin']);
        $events->listen(Failed::class, [self::class, 'handleFailed']);
        $events->listen(Registered::class, [self::class, 'handleRegistered']);
    }
}
