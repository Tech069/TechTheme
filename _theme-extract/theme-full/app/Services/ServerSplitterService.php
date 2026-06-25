<?php

namespace Pterodactyl\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Log;
use Pterodactyl\Models\Server;
use Pterodactyl\Models\Allocation;
use Pterodactyl\Models\ServerSplit;
use Ramsey\Uuid\Uuid;

class ServerSplitterService
{
    public function __construct()
    {
    }

    /**
     * Split a server into two or more child servers.
     *
     * @param array $splits Configuration for each split (name, memory, disk, cpu)
     */
    public function split(Server $server, array $splits): array
    {
        if (count($splits) < 2) {
            throw new \InvalidArgumentException('At least two split configurations are required.');
        }

        $originalMemory = $server->memory;
        $originalDisk = $server->disk;
        $originalCpu = $server->cpu;

        $totalRequestedMemory = array_sum(array_column($splits, 'memory'));
        $totalRequestedDisk = array_sum(array_column($splits, 'disk'));
        $totalRequestedCpu = array_sum(array_column($splits, 'cpu'));

        if ($totalRequestedMemory > $originalMemory) {
            throw new \InvalidArgumentException('Total requested memory exceeds original server allocation.');
        }
        if ($totalRequestedDisk > $originalDisk) {
            throw new \InvalidArgumentException('Total requested disk exceeds original server allocation.');
        }
        if ($totalRequestedCpu > $originalCpu) {
            throw new \InvalidArgumentException('Total requested CPU exceeds original server allocation.');
        }

        return DB::transaction(function () use ($server, $splits) {
            $createdServers = [];

            foreach ($splits as $index => $splitConfig) {
                $childServer = $this->createChildServer($server, $splitConfig, $index);
                $createdServers[] = $childServer;

                // Record the split relationship
                ServerSplit::create([
                    'parent_server_id' => $server->id,
                    'child_server_id' => $childServer->id,
                    'split_index' => $index,
                    'original_resource' => $this->getResourceShareDescription($splitConfig),
                ]);
            }

            // Optionally reduce the parent server's resources
            $this->reduceParentResources($server, $totalRequestedMemory, $totalRequestedDisk, $totalRequestedCpu);

            Log::info('Server split completed', [
                'parent_id' => $server->id,
                'child_count' => count($createdServers),
            ]);

            return $createdServers;
        });
    }

    /**
     * Create a child server based on the parent server and split configuration.
     */
    private function createChildServer(Server $parent, array $config, int $index): Server
    {
        // Find an available allocation on the same node
        $allocation = $this->findAvailableAllocation($parent);

        $childServer = new Server();
        $childServer->external_id = null;
        $childServer->uuid = Uuid::uuid4()->toString();
        $childServer->uuidShort = substr(Uuid::uuid4()->toString(), 0, 8);
        $childServer->node_id = $parent->node_id;
        $childServer->name = $config['name'] ?? $parent->name . ' - Split ' . ($index + 1);
        $childServer->description = $config['description'] ?? "Split from server #{$parent->id}";
        $childServer->owner_id = $parent->owner_id;
        $childServer->memory = $config['memory'] ?? $parent->memory;
        $childServer->swap = $config['swap'] ?? $parent->swap;
        $childServer->disk = $config['disk'] ?? $parent->disk;
        $childServer->io = $parent->io;
        $childServer->cpu = $config['cpu'] ?? $parent->cpu;
        $childServer->oom_disabled = $parent->oom_disabled;
        $childServer->allocation_id = $allocation->id;
        $childServer->nest_id = $parent->nest_id;
        $childServer->egg_id = $parent->egg_id;
        $childServer->startup = $parent->startup;
        $childServer->image = $parent->image;
        $childServer->database_limit = $parent->database_limit;
        $childServer->allocation_limit = $parent->allocation_limit;
        $childServer->backup_limit = $parent->backup_limit;
        $childServer->skip_scripts = $parent->skip_scripts;
        $childServer->save();

        // Copy server variables from parent
        $this->copyServerVariables($parent, $childServer);

        return $childServer;
    }

    /**
     * Find an available allocation on the same node as the server.
     */
    private function findAvailableAllocation(Server $server): Allocation
    {
        $allocation = Allocation::where('node_id', $server->node_id)
            ->whereNull('server_id')
            ->first();

        if (!$allocation) {
            throw new \RuntimeException('No available allocations on node ' . $server->node_id . ' for server split.');
        }

        $allocation->server_id = $server->id;
        $allocation->save();

        // Create a new allocation for the split child
        $newAllocation = Allocation::create([
            'node_id' => $server->node_id,
            'ip' => $allocation->ip,
            'port' => $allocation->port + 1000 + rand(1, 9000),
            'server_id' => null,
        ]);

        return $newAllocation;
    }

    /**
     * Copy server variables from parent to child.
     */
    private function copyServerVariables(Server $parent, Server $child): void
    {
        $variables = DB::table('server_variables')
            ->where('server_id', $parent->id)
            ->get();

        foreach ($variables as $variable) {
            DB::table('server_variables')->insert([
                'server_id' => $child->id,
                'variable_id' => $variable->variable_id,
                'variable_value' => $variable->variable_value,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }

    /**
     * Reduce the parent server's resources after a split.
     */
    private function reduceParentResources(Server $parent, int $memoryUsed, int $diskUsed, int $cpuUsed): void
    {
        $parent->update([
            'memory' => $parent->memory - $memoryUsed,
            'disk' => $parent->disk - $diskUsed,
            'cpu' => $parent->cpu - $cpuUsed,
        ]);
    }

    /**
     * Get a description of the resource share.
     */
    private function getResourceShareDescription(array $config): string
    {
        $parts = [];
        if (isset($config['memory'])) {
            $parts[] = "Memory: {$config['memory']}MB";
        }
        if (isset($config['disk'])) {
            $parts[] = "Disk: {$config['disk']}MB";
        }
        if (isset($config['cpu'])) {
            $parts[] = "CPU: {$config['cpu']}%";
        }

        return implode(', ', $parts);
    }

    /**
     * Get all split children for a parent server.
     */
    public function getChildren(Server $parent): array
    {
        return ServerSplit::where('parent_server_id', $parent->id)
            ->with('childServer')
            ->get()
            ->toArray();
    }

    /**
     * Revert a server split by deleting child servers and restoring resources.
     */
    public function revertSplit(Server $parent): void
    {
        $splits = ServerSplit::where('parent_server_id', $parent->id)->get();

        DB::transaction(function () use ($parent, $splits) {
            foreach ($splits as $split) {
                $child = Server::find($split->child_server_id);
                if ($child) {
                    // Free up allocations
                    Allocation::where('server_id', $child->id)->update(['server_id' => null]);
                    // Delete server variables
                    DB::table('server_variables')->where('server_id', $child->id)->delete();
                    // Delete the server
                    $child->delete();
                }
                $split->delete();
            }

            // Restore parent resources from splits
            $totalMemory = $splits->sum(fn ($s) => json_decode($s->original_resource, true)['memory'] ?? 0);
            $totalDisk = $splits->sum(fn ($s) => json_decode($s->original_resource, true)['disk'] ?? 0);
            $totalCpu = $splits->sum(fn ($s) => json_decode($s->original_resource, true)['cpu'] ?? 0);

            $parent->update([
                'memory' => $parent->memory + $totalMemory,
                'disk' => $parent->disk + $totalDisk,
                'cpu' => $parent->cpu + $totalCpu,
            ]);
        });
    }
}
