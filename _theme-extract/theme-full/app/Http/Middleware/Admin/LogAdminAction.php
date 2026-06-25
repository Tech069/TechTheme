<?php

namespace Pterodactyl\Http\Middleware\Admin;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Pterodactyl\Facades\Activity;
use Pterodactyl\Models\AdminAuditLog;

class LogAdminAction
{
    /**
     * Handle an incoming request.
     */
    public function handle(Request $request, Closure $next): mixed
    {
        $response = $next($request);

        try {
            if ($this->shouldLog($request)) {
                $this->logAdminAction($request, $response);
            }
        } catch (\Throwable $e) {
            Log::error("Failed to log admin action: {$e->getMessage()}");
        }

        return $response;
    }

    /**
     * Determine if this request should be logged.
     */
    protected function shouldLog(Request $request): bool
    {
        if (!$request->user() || !$request->user()->root_admin) {
            return false;
        }

        if (!in_array($request->method(), ['POST', 'PUT', 'PATCH', 'DELETE'])) {
            return false;
        }

        return str_starts_with($request->path(), 'admin/');
    }

    /**
     * Log the admin action.
     */
    protected function logAdminAction(Request $request, $response): void
    {
        $user = $request->user();

        $event = $this->resolveEvent($request);

        AdminAuditLog::create([
            'user_id' => $user->id,
            'event' => $event,
            'subevent' => $this->resolveSubevent($request),
            'metadata' => [
                'method' => $request->method(),
                'path' => $request->path(),
                'ip_address' => $request->ip(),
                'user_agent' => $request->userAgent(),
                'status_code' => $response->getStatusCode(),
            ],
        ]);

        Activity::event($event)
            ->withRequestMetadata()
            ->subject($user)
            ->properties([
                'method' => $request->method(),
                'path' => $request->path(),
            ])
            ->log();
    }

    /**
     * Resolve the event name from the request.
     */
    protected function resolveEvent(Request $request): string
    {
        $path = $request->path();
        $method = $request->method();

        if (str_contains($path, 'users')) {
            return match ($method) {
                'POST' => 'admin:user:create',
                'PUT', 'PATCH' => 'admin:user:update',
                'DELETE' => 'admin:user:delete',
                default => 'admin:user:access',
            };
        }

        if (str_contains($path, 'servers')) {
            return match ($method) {
                'POST' => 'admin:server:create',
                'PUT', 'PATCH' => 'admin:server:update',
                'DELETE' => 'admin:server:delete',
                default => 'admin:server:access',
            };
        }

        if (str_contains($path, 'nodes')) {
            return match ($method) {
                'POST' => 'admin:node:create',
                'PUT', 'PATCH' => 'admin:node:update',
                'DELETE' => 'admin:node:delete',
                default => 'admin:node:access',
            };
        }

        if (str_contains($path, 'eggs')) {
            return match ($method) {
                'POST' => 'admin:egg:create',
                'PUT', 'PATCH' => 'admin:egg:update',
                'DELETE' => 'admin:egg:delete',
                default => 'admin:egg:access',
            };
        }

        return 'admin:action';
    }

    /**
     * Resolve the subevent from the request segments.
     */
    protected function resolveSubevent(Request $request): string
    {
        $segments = $request->segments();

        return implode('.', array_slice($segments, 1));
    }
}
