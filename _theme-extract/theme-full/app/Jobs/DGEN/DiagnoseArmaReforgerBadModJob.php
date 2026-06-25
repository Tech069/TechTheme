<?php

namespace Pterodactyl\Jobs\DGEN;

use Illuminate\Support\Facades\Log;
use Pterodactyl\Jobs\Job;
use Pterodactyl\Models\Egg;
use Pterodactyl\Models\Server;

class DiagnoseArmaReforgerBadModJob extends Job
{
    /**
     * Known problematic mod patterns for Arma Reforger.
     */
    protected array $knownBadModPatterns = [
        'incompatible_version',
        'missing_dependency',
        'corrupted_mod',
        'version_mismatch',
    ];

    /**
     * Create a new job instance.
     */
    public function __construct(
        public int $serverId,
        public ?string $modId = null,
    ) {
        $this->queue = 'default';
    }

    /**
     * Execute the job.
     */
    public function handle(): array
    {
        $server = Server::with(['egg', 'variables'])->find($this->serverId);
        if (!$server) {
            Log::error("Server #{$this->serverId} not found for Arma Reforger diagnosis.");
            return ['success' => false, 'error' => 'Server not found'];
        }

        $diagnostics = [
            'server_id' => $server->id,
            'server_name' => $server->name,
            'egg_name' => $server->egg?->name,
            'issues' => [],
            'recommendations' => [],
            'diagnosed_at' => now()->toIso8601String(),
        ];

        try {
            $this->checkEggCompatibility($server, $diagnostics);
            $this->checkModVariables($server, $diagnostics);
            $this->checkDockerImage($server, $diagnostics);
            $this->checkServerResources($server, $diagnostics);

            if (empty($diagnostics['issues'])) {
                $diagnostics['status'] = 'healthy';
                $diagnostics['recommendations'][] = 'No issues detected. Server configuration appears valid.';
            } else {
                $diagnostics['status'] = 'issues_found';
                $diagnostics['issues_count'] = count($diagnostics['issues']);
            }

            Log::info(
                "Arma Reforger diagnosis completed for server #{$this->serverId}.",
                ['issues_count' => count($diagnostics['issues'])]
            );

            return $diagnostics;
        } catch (\Throwable $e) {
            Log::error("Diagnosis failed for server #{$this->serverId}: {$e->getMessage()}");
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }

    /**
     * Check if the egg is configured correctly for Arma Reforger.
     */
    protected function checkEggCompatibility(Server $server, array &$diagnostics): void
    {
        if (!$server->egg) {
            $diagnostics['issues'][] = [
                'type' => 'egg_not_found',
                'severity' => 'critical',
                'message' => 'Server has no egg assigned.',
            ];
            return;
        }

        $armaEggIds = config('dgen.arma_reforger.egg_ids', []);
        if (!empty($armaEggIds) && !in_array($server->egg_id, $armaEggIds)) {
            $diagnostics['issues'][] = [
                'type' => 'wrong_egg',
                'severity' => 'warning',
                'message' => "Egg '{$server->egg->name}' may not be the correct egg for Arma Reforger.",
            ];
            $diagnostics['recommendations'][] = 'Ensure the server is using the official Arma Reforger egg.';
        }
    }

    /**
     * Check mod-related variables for common misconfigurations.
     */
    protected function checkModVariables(Server $server, array &$diagnostics): void
    {
        $modVariable = $server->variables->firstWhere('env_variable', 'MOD_LIST');

        if ($this->modId && $modVariable) {
            $modList = $modVariable->server_value ?? '';
            $mods = array_map('trim', explode(',', $modList));

            if (!in_array($this->modId, $mods)) {
                $diagnostics['issues'][] = [
                    'type' => 'mod_not_in_list',
                    'severity' => 'warning',
                    'message' => "Mod {$this->modId} is not in the server's mod list.",
                ];
            }
        }

        $workshopVariable = $server->variables->firstWhere('env_variable', 'WORKSHOP_ID');
        if ($workshopVariable && empty($workshopVariable->server_value)) {
            $diagnostics['issues'][] = [
                'type' => 'missing_workshop_id',
                'severity' => 'info',
                'message' => 'Workshop ID variable is empty. Mods may not load correctly.',
            ];
        }
    }

    /**
     * Check Docker image configuration.
     */
    protected function checkDockerImage(Server $server, array &$diagnostics): void
    {
        $supportedImages = [
            'ghcr.io/games-labs arma-reforger',
            'itzg/arma-reforger',
        ];

        $currentImage = $server->image;
        $isSupported = false;

        foreach ($supportedImages as $supported) {
            if (str_contains(strtolower($currentImage), strtolower($supported))) {
                $isSupported = true;
                break;
            }
        }

        if (!$isSupported) {
            $diagnostics['issues'][] = [
                'type' => 'unsupported_docker_image',
                'severity' => 'warning',
                'message' => "Docker image '{$currentImage}' may not be fully supported.",
            ];
            $diagnostics['recommendations'][] = 'Consider using an officially supported Docker image.';
        }
    }

    /**
     * Check server resource allocation.
     */
    protected function checkServerResources(Server $server, array &$diagnostics): void
    {
        if ($server->memory < 8192) {
            $diagnostics['issues'][] = [
                'type' => 'low_memory',
                'severity' => 'warning',
                'message' => "Server memory ({$server->memory}MB) may be insufficient for Arma Reforger with mods.",
            ];
            $diagnostics['recommendations'][] = 'Arma Reforger recommends at least 8GB of RAM for modded servers.';
        }

        if ($server->disk < 20480) {
            $diagnostics['issues'][] = [
                'type' => 'low_disk',
                'severity' => 'info',
                'message' => "Server disk ({$server->disk}MB) may be insufficient for large mod collections.",
            ];
        }
    }
}
