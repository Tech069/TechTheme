<?php

namespace Pterodactyl\Services\DGEN;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Cache;
use Pterodactyl\Models\Server;
use Pterodactyl\Models\User;
use Pterodactyl\Models\HyperCommandHistory;

class CommandHistoryService
{
    private const CACHE_PREFIX = 'cmd_history:';

    private const CACHE_TTL = 60;

    private const MAX_HISTORY_PER_SERVER = 500;

    private const RETENTION_DAYS = 90;

    public function __construct()
    {
    }

    /**
     * Log a command execution to the database.
     */
    public function logCommand(Server $server, User $user, string $command, ?string $output = null, int $exitCode = 0): HyperCommandHistory
    {
        $record = HyperCommandHistory::create([
            'server_id' => $server->id,
            'user_id' => $user->id,
            'command' => $command,
            'output' => $output,
            'exit_code' => $exitCode,
        ]);

        // Invalidate the cache for this server's command history
        Cache::forget(self::CACHE_PREFIX . $server->id);

        // Prune old records if we exceed the limit
        $this->pruneServerHistory($server->id);

        return $record;
    }

    /**
     * Get command history for a server.
     */
    public function getServerHistory(Server $server, int $limit = 50, int $offset = 0): array
    {
        $cacheKey = self::CACHE_PREFIX . $server->id . ":$limit:$offset";

        return Cache::remember($cacheKey, self::CACHE_TTL, function () use ($server, $limit, $offset) {
            return HyperCommandHistory::where('server_id', $server->id)
                ->with('user:id,username')
                ->orderByDesc('created_at')
                ->offset($offset)
                ->limit($limit)
                ->get()
                ->toArray();
        });
    }

    /**
     * Get command history for a specific user across all servers.
     */
    public function getUserHistory(User $user, int $limit = 50, int $offset = 0): array
    {
        return HyperCommandHistory::where('user_id', $user->id)
            ->with('server:id,name')
            ->orderByDesc('created_at')
            ->offset($offset)
            ->limit($limit)
            ->get()
            ->toArray();
    }

    /**
     * Get recent commands for a server.
     */
    public function getRecentCommands(Server $server, int $count = 10): array
    {
        return $this->getServerHistory($server, $count, 0);
    }

    /**
     * Search command history.
     */
    public function searchHistory(string $query, ?int $serverId = null, ?int $userId = null, int $limit = 50): array
    {
        $queryBuilder = HyperCommandHistory::where('command', 'LIKE', "%$query%");

        if ($serverId !== null) {
            $queryBuilder->where('server_id', $serverId);
        }

        if ($userId !== null) {
            $queryBuilder->where('user_id', $userId);
        }

        return $queryBuilder
            ->with(['server:id,name', 'user:id,username'])
            ->orderByDesc('created_at')
            ->limit($limit)
            ->get()
            ->toArray();
    }

    /**
     * Get command statistics for a server.
     */
    public function getStats(Server $server, int $days = 30): array
    {
        $since = now()->subDays($days);

        $stats = HyperCommandHistory::where('server_id', $server->id)
            ->where('created_at', '>=', $since)
            ->selectRaw('COUNT(*) as total_commands')
            ->selectRaw('COUNT(DISTINCT user_id) as unique_users')
            ->selectRaw('AVG(LENGTH(command)) as avg_command_length')
            ->first();

        $topUsers = HyperCommandHistory::where('server_id', $server->id)
            ->where('created_at', '>=', $since)
            ->selectRaw('user_id, COUNT(*) as command_count')
            ->groupBy('user_id')
            ->orderByDesc('command_count')
            ->limit(5)
            ->get();

        return [
            'total_commands' => $stats->total_commands ?? 0,
            'unique_users' => $stats->unique_users ?? 0,
            'avg_command_length' => round($stats->avg_command_length ?? 0, 1),
            'top_users' => $topUsers->toArray(),
            'period_days' => $days,
        ];
    }

    /**
     * Prune old command history for a server.
     */
    private function pruneServerHistory(int $serverId): void
    {
        $count = HyperCommandHistory::where('server_id', $serverId)->count();

        if ($count > self::MAX_HISTORY_PER_SERVER) {
            $toDelete = $count - self::MAX_HISTORY_PER_SERVER;
            $oldestIds = HyperCommandHistory::where('server_id', $serverId)
                ->orderBy('created_at')
                ->limit($toDelete)
                ->pluck('id');

            HyperCommandHistory::whereIn('id', $oldestIds)->delete();
        }
    }

    /**
     * Prune all old command history beyond retention period.
     */
    public function pruneOldHistory(): int
    {
        $cutoff = now()->subDays(self::RETENTION_DAYS);

        return HyperCommandHistory::where('created_at', '<', $cutoff)->delete();
    }

    /**
     * Clear command history for a server.
     */
    public function clearServerHistory(Server $server): int
    {
        $deleted = HyperCommandHistory::where('server_id', $server->id)->delete();
        Cache::forget(self::CACHE_PREFIX . $server->id);

        return $deleted;
    }
}
