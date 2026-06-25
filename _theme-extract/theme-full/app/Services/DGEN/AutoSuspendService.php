<?php

namespace Pterodactyl\Services\DGEN;

use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Cache;
use Pterodactyl\Models\Server;
use Pterodactyl\Models\User;
use Pterodactyl\Services\DGEN\WingsAgentService;

class AutoSuspendService
{
    private const CACHE_KEY_PREFIX = 'auto_suspend:';

    private const CACHE_TTL = 300;

    private const RESOURCE_THRESHOLDS = [
        'cpu' => 95,
        'memory' => 95,
        'disk' => 98,
    ];

    private const INACTIVITY_DAYS = 30;

    public function __construct(
        private WingsAgentService $wingsAgent,
    ) {
    }

    /**
     * Check all servers for auto-suspend conditions.
     */
    public function checkAllServers(): array
    {
        $results = [
            'checked' => 0,
            'suspended' => 0,
            'skipped' => 0,
            'failed' => 0,
        ];

        $servers = Server::query()
            ->whereNull('status')
            ->orWhere('status', '!=', Server::STATUS_SUSPENDED)
            ->with('node')
            ->get();

        foreach ($servers as $server) {
            try {
                $result = $this->checkServer($server);
                $results['checked']++;

                if ($result['suspended']) {
                    $results['suspended']++;
                }
            } catch (\Exception $exception) {
                $results['failed']++;
                Log::error('Auto-suspend check failed', [
                    'server_id' => $server->id,
                    'error' => $exception->getMessage(),
                ]);
            }
        }

        return $results;
    }

    /**
     * Check a single server for auto-suspend conditions.
     */
    public function checkServer(Server $server): array
    {
        $result = [
            'server_id' => $server->id,
            'suspended' => false,
            'reason' => null,
        ];

        if ($server->isSuspended()) {
            $result['reason'] = 'already_suspended';

            return $result;
        }

        // Check billing status
        if ($this->isBillingOverdue($server)) {
            $this->suspendServer($server, 'billing_overdue');
            $result['suspended'] = true;
            $result['reason'] = 'billing_overdue';

            return $result;
        }

        // Check resource abuse
        $resourceCheck = $this->checkResourceAbuse($server);
        if ($resourceCheck['abusing']) {
            $this->suspendServer($server, 'resource_abuse_' . $resourceCheck['resource']);
            $result['suspended'] = true;
            $result['reason'] = 'resource_abuse_' . $resourceCheck['resource'];

            return $result;
        }

        // Check inactivity
        if ($this->isInactive($server)) {
            $this->suspendServer($server, 'inactivity');
            $result['suspended'] = true;
            $result['reason'] = 'inactivity';

            return $result;
        }

        return $result;
    }

    /**
     * Check if a server's billing is overdue.
     */
    private function isBillingOverdue(Server $server): bool
    {
        try {
            $user = User::find($server->owner_id);
            if (!$user) {
                return false;
            }

            // Check if the user has any overdue invoices
            // This is a simplified check - in production you'd query your billing system
            $overdueKey = "billing:overdue:{$user->id}";
            return Cache::has($overdueKey);
        } catch (\Exception $exception) {
            Log::error('Billing check failed', [
                'server_id' => $server->id,
                'error' => $exception->getMessage(),
            ]);

            return false;
        }
    }

    /**
     * Check if a server is abusing resources.
     */
    private function checkResourceAbuse(Server $server): array
    {
        $result = ['abusing' => false, 'resource' => null];

        try {
            $stats = $this->wingsAgent->getServerStats($server);

            if (!$stats) {
                return $result;
            }

            // Check CPU
            if (isset($stats['cpu_absolute']) && $stats['cpu_absolute'] > self::RESOURCE_THRESHOLDS['cpu']) {
                $result['abusing'] = true;
                $result['resource'] = 'cpu';

                return $result;
            }

            // Check memory
            if (isset($stats['memory_bytes']) && $server->memory > 0) {
                $memUsage = ($stats['memory_bytes'] / ($server->memory * 1024 * 1024)) * 100;
                if ($memUsage > self::RESOURCE_THRESHOLDS['memory']) {
                    $result['abusing'] = true;
                    $result['resource'] = 'memory';

                    return $result;
                }
            }

            // Check disk
            if (isset($stats['disk_bytes']) && $server->disk > 0) {
                $diskUsage = ($stats['disk_bytes'] / ($server->disk * 1024 * 1024)) * 100;
                if ($diskUsage > self::RESOURCE_THRESHOLDS['disk']) {
                    $result['abusing'] = true;
                    $result['resource'] = 'disk';

                    return $result;
                }
            }
        } catch (\Exception $exception) {
            Log::warning('Resource abuse check failed', [
                'server_id' => $server->id,
                'error' => $exception->getMessage(),
            ]);
        }

        return $result;
    }

    /**
     * Check if a server has been inactive for too long.
     */
    private function isInactive(Server $server): bool
    {
        $lastActivity = Cache::get("server:last_activity:{$server->id}");

        if (!$lastActivity) {
            return false;
        }

        $inactiveDays = now()->diffInDays(now()->subTimestamp($lastActivity));

        return $inactiveDays >= self::INACTIVITY_DAYS;
    }

    /**
     * Suspend a server with a given reason.
     */
    private function suspendServer(Server $server, string $reason): void
    {
        try {
            $server->update([
                'status' => Server::STATUS_SUSPENDED,
            ]);

            // Notify the Wings agent
            try {
                $this->wingsAgent->syncServer($server);
            } catch (\Exception $exception) {
                Log::warning('Failed to sync suspension to Wings', [
                    'server_id' => $server->id,
                    'error' => $exception->getMessage(),
                ]);
            }

            // Log the suspension
            Log::info('Server auto-suspended', [
                'server_id' => $server->id,
                'reason' => $reason,
            ]);

            Cache::forget(self::CACHE_KEY_PREFIX . $server->id);
        } catch (\Exception $exception) {
            Log::error('Failed to auto-suspend server', [
                'server_id' => $server->id,
                'reason' => $reason,
                'error' => $exception->getMessage(),
            ]);
        }
    }

    /**
     * Unsuspend a server.
     */
    public function unsuspend(Server $server): bool
    {
        if (!$server->isSuspended()) {
            return false;
        }

        try {
            $server->update(['status' => null]);
            $this->wingsAgent->syncServer($server);

            Log::info('Server unsuspended', ['server_id' => $server->id]);

            return true;
        } catch (\Exception $exception) {
            Log::error('Failed to unsuspend server', [
                'server_id' => $server->id,
                'error' => $exception->getMessage(),
            ]);

            return false;
        }
    }

    /**
     * Get auto-suspend configuration.
     */
    public function getConfig(): array
    {
        return [
            'enabled' => config('hyper.auto_suspend.enabled', true),
            'resource_thresholds' => self::RESOURCE_THRESHOLDS,
            'inactivity_days' => self::INACTIVITY_DAYS,
            'billing_check' => config('hyper.auto_suspend.billing_check', true),
            'grace_period_hours' => config('hyper.auto_suspend.grace_period_hours', 24),
        ];
    }
}
