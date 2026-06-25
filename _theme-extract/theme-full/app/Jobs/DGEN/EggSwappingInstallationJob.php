<?php

namespace Pterodactyl\Jobs\DGEN;

use Illuminate\Support\Facades\Log;
use Pterodactyl\Jobs\Job;
use Pterodactyl\Models\Egg;
use Pterodactyl\Models\EggVariable;
use Pterodactyl\Models\Server;
use Pterodactyl\Models\ServerVariable;

class EggSwappingInstallationJob extends Job
{
    /**
     * Create a new job instance.
     */
    public function __construct(
        public int $serverId,
        public int $newEggId,
        public array $variableMapping = [],
    ) {
        $this->queue = 'high';
        $this->tries = 1;
    }

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        $server = Server::with(['egg', 'variables'])->find($this->serverId);
        if (!$server) {
            Log::error("Server #{$this->serverId} not found for egg swap.");
            return;
        }

        $newEgg = Egg::with(['variables'])->find($this->newEggId);
        if (!$newEgg) {
            Log::error("Target egg #{$this->newEggId} not found for egg swap.");
            return;
        }

        try {
            $oldEggId = $server->egg_id;

            $server->update([
                'egg_id' => $newEgg->id,
                'startup' => $newEgg->startup ?? $server->startup,
                'image' => $newEgg->docker_images[0] ?? $server->image,
            ]);

            $this->migrateVariables($server, $newEgg, $oldEggId);

            Log::info(
                "Egg swap completed for server #{$this->serverId}: egg #{$oldEggId} -> #{$newEgg->id}."
            );
        } catch (\Throwable $e) {
            Log::error("Egg swap failed for server #{$this->serverId}: {$e->getMessage()}");
            throw $e;
        }
    }

    /**
     * Migrate server variables from old egg to new egg.
     */
    protected function migrateVariables(Server $server, Egg $newEgg, int $oldEggId): void
    {
        $oldVariables = $server->variables()->get();

        foreach ($oldVariables as $oldVar) {
            $mappedVarName = $this->variableMapping[$oldVar->env_variable] ?? $oldVar->env_variable;

            $newEggVar = $newEgg->variables->firstWhere('env_variable', $mappedVarName);

            if ($newEggVar) {
                $existingServerVar = ServerVariable::where('server_id', $server->id)
                    ->where('variable_id', $newEggVar->id)
                    ->first();

                if ($existingServerVar) {
                    $existingServerVar->update(['variable_value' => $oldVar->variable_value]);
                } else {
                    ServerVariable::create([
                        'server_id' => $server->id,
                        'variable_id' => $newEggVar->id,
                        'variable_value' => $oldVar->variable_value,
                    ]);
                }
            } else {
                Log::info(
                    "No matching variable found for '{$oldVar->env_variable}' in new egg #{$newEgg->id}."
                );
            }
        }

        foreach ($newEgg->variables as $newEggVar) {
            $existingServerVar = ServerVariable::where('server_id', $server->id)
                ->where('variable_id', $newEggVar->id)
                ->first();

            if (!$existingServerVar && $newEggVar->default_value !== null) {
                ServerVariable::create([
                    'server_id' => $server->id,
                    'variable_id' => $newEggVar->id,
                    'variable_value' => $newEggVar->default_value,
                ]);
            }
        }
    }
}
