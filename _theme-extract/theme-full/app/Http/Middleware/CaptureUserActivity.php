<?php

namespace Pterodactyl\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Pterodactyl\Models\ActivityLog;

class CaptureUserActivity
{
    /**
     * Handle an incoming request.
     */
    public function handle(Request $request, Closure $next): mixed
    {
        $response = $next($request);

        try {
            if ($request->user() && $this->shouldTrack($request)) {
                $this->logActivity($request);
            }
        } catch (\Throwable $e) {
            Log::error("Failed to capture user activity: {$e->getMessage()}");
        }

        return $response;
    }

    /**
     * Determine if the request should be tracked.
     */
    protected function shouldTrack(Request $request): bool
    {
        if ($request->isJson() || $request->is('api/*')) {
            return in_array($request->method(), ['POST', 'PUT', 'PATCH', 'DELETE']);
        }

        return false;
    }

    /**
     * Log the user activity.
     */
    protected function logActivity(Request $request): void
    {
        $user = $request->user();

        ActivityLog::create([
            'event' => $this->mapMethodToEvent($request->method()),
            'ip' => $request->ip() ?? '127.0.0.1',
            'description' => "{$request->method()} {$request->path()}",
            'actor_type' => get_class($user),
            'actor_id' => $user->id,
            'properties' => [
                'method' => $request->method(),
                'path' => $request->path(),
                'user_agent' => $request->userAgent(),
            ],
            'timestamp' => now(),
        ]);
    }

    /**
     * Map HTTP method to activity event name.
     */
    protected function mapMethodToEvent(string $method): string
    {
        return match ($method) {
            'POST' => 'model:create',
            'PUT', 'PATCH' => 'model:update',
            'DELETE' => 'model:delete',
            default => 'request',
        };
    }
}
