<?php

namespace Pterodactyl\Jobs\DGEN;

use Illuminate\Support\Facades\Log;
use Pterodactyl\Jobs\Job;
use Pterodactyl\Models\Egg;
use Pterodactyl\Models\Server;

class StartCustomModManagerInstallJob extends Job
{
    /**
     * Create a new job instance.
     */
    public function __construct(
        public int $serverId,
        public string $modManagerType,
        public array $modIds = [],
        public array $options = [],
    ) {
        $this->queue = 'high';
        $this->tries = 1;
    }

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        $server = Server::with(['egg', 'node', 'variables'])->find($this->serverId);
        if (!$server) {
            Log::error("Server #{$this->serverId} not found for custom mod manager install.");
            return;
        }

        if (!$server->isInstalled()) {
            Log::warning("Server #{$this->serverId} is not installed, cannot start mod manager install.");
            return;
        }

        try {
            $installScript = $this->buildInstallScript($server);

            Log::info(
                "Starting custom mod manager install for server #{$this->serverId}.",
                [
                    'mod_manager' => $this->modManagerType,
                    'mod_count' => count($this->modIds),
                ]
            );

            $node = $server->node;
            $connection = app(\Pterodactyl\Services\Nodes\NodeConfigurationService::class);
            $connection->setNode($node)->connect()->getDaemon()->servers()->command(
                $server->uuid,
                $installScript,
            );

            $this->updateServerVariables($server);

            Log::info("Custom mod manager install initiated for server #{$this->serverId}.");
        } catch (\Throwable $e) {
            Log::error(
                "Custom mod manager install failed for server #{$this->serverId}: {$e->getMessage()}"
            );
            throw $e;
        }
    }

    /**
     * Build the install script based on mod manager type.
     */
    protected function buildInstallScript(Server $server): string
    {
        $modList = implode(' ', $this->modIds);

        return match ($this->modManagerType) {
            'steamcmd' => $this->buildSteamCmdScript($server, $modList),
            'oxide' => $this->buildOxideScript($server, $modList),
            'carbon' => $this->buildCarbonScript($server, $modList),
            default => $this->buildGenericScript($server, $modList),
        };
    }

    /**
     * Build a SteamCMD-based install script.
     */
    protected function buildSteamCmdScript(Server $server, string $modList): string
    {
        $workshopIds = implode(' ', $this->modIds);
        return <<<SCRIPT
            #!/bin/bash
            echo "[DGEN] Starting SteamCMD mod installation..."
            for mod_id in $workshopIds; do
                echo "[DGEN] Downloading mod \$mod_id..."
                steamcmd +login anonymous +workshop_download_item {$server->egg->author} \$mod_id +quit
            done
            echo "[DGEN] SteamCMD mod installation complete."
            SCRIPT;
    }

    /**
     * Build an Oxide/Umod install script.
     */
    protected function buildOxideScript(Server $server, string $modList): string
    {
        return <<<SCRIPT
            #!/bin/bash
            echo "[DGEN] Starting Oxide mod installation..."
            cd /home/container
            curl -sSL https://github.com/OxideMod/Oxide/releases/latest/download/Oxide.Rust-linux.zip -o oxide.zip
            unzip -o oxide.zip
            echo "[DGEN] Oxide installation complete."
            SCRIPT;
    }

    /**
     * Build a Carbon install script.
     */
    protected function buildCarbonScript(Server $server, string $modList): string
    {
        return <<<SCRIPT
            #!/bin/bash
            echo "[DGEN] Starting Carbon mod installation..."
            cd /home/container
            curl -sSL https://github.com/CarbonCommunity/Carbon/releases/latest/download/Carbon.Linux.zip -o carbon.zip
            unzip -o carbon.zip
            echo "[DGEN] Carbon installation complete."
            SCRIPT;
    }

    /**
     * Build a generic install script.
     */
    protected function buildGenericScript(Server $server, string $modList): string
    {
        return <<<SCRIPT
            #!/bin/bash
            echo "[DGEN] Starting generic mod installation..."
            echo "[DGEN] Mods to install: $modList
            echo "[DGEN] Generic mod installation complete."
            SCRIPT;
    }

    /**
     * Update server variables with mod manager info.
     */
    protected function updateServerVariables(Server $server): void
    {
        $modManagerVar = $server->variables->firstWhere('env_variable', 'MOD_MANAGER');
        if ($modManagerVar) {
            $serverVariable = \Pterodactyl\Models\ServerVariable::where('server_id', $server->id)
                ->where('variable_id', $modManagerVar->id)
                ->first();

            if ($serverVariable) {
                $serverVariable->update(['variable_value' => $this->modManagerType]);
            }
        }

        $modListVar = $server->variables->firstWhere('env_variable', 'MOD_LIST');
        if ($modListVar) {
            $serverVariable = \Pterodactyl\Models\ServerVariable::where('server_id', $server->id)
                ->where('variable_id', $modListVar->id)
                ->first();

            if ($serverVariable) {
                $serverVariable->update(['variable_value' => implode(',', $this->modIds)]);
            }
        }
    }
}
