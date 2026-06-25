<?php

namespace Pterodactyl\Http\Controllers\Api\Client\Servers\DGEN;

use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Pterodactyl\Http\Controllers\Controller;
use Pterodactyl\Models\Server;

class FastDLController extends Controller
{
    public function index(Request $request, Server $server): JsonResponse
    {
        try {
            $fastdlConfig = $this->getFastDLConfig($server);

            return response()->json([
                'enabled' => $fastdlConfig['enabled'] ?? false,
                'url' => $fastdlConfig['url'] ?? null,
                'sync_status' => $fastdlConfig['sync_status'] ?? 'idle',
                'last_sync' => $fastdlConfig['last_sync'] ?? null,
                'content_count' => $fastdlConfig['content_count'] ?? 0,
            ]);
        } catch (\Exception $e) {
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }

    public function sync(Request $request, Server $server): JsonResponse
    {
        try {
            $fastdlDir = $server->server_data_directory . '/.fastdl';
            if (!is_dir($fastdlDir)) {
                mkdir($fastdlDir, 0755, true);
            }

            $resourcePacks = glob($server->server_data_directory . '/resourcepacks/*.{zip,zip.tmp}', GLOB_BRACE);
            $count = 0;

            foreach ($resourcePacks as $pack) {
                $dest = $fastdlDir . '/' . basename($pack);
                if (!file_exists($dest)) {
                    copy($pack, $dest);
                    $count++;
                }
            }

            $this->updateFastDLConfig($server, [
                'sync_status' => 'completed',
                'last_sync' => now()->toDateTimeString(),
                'content_count' => count($resourcePacks),
            ]);

            return response()->json([
                'success' => true,
                'message' => "Synced $count new files",
                'total_files' => count($resourcePacks),
            ]);
        } catch (\Exception $e) {
            return response()->json(['error' => 'Sync failed: ' . $e->getMessage()], 500);
        }
    }

    private function getFastDLConfig(Server $server): array
    {
        $configFile = $server->server_data_directory . '/.fastdl.json';
        if (file_exists($configFile)) {
            return json_decode(file_get_contents($configFile), true) ?? [];
        }
        return ['enabled' => false, 'sync_status' => 'idle'];
    }

    private function updateFastDLConfig(Server $server, array $data): void
    {
        $configFile = $server->server_data_directory . '/.fastdl.json';
        $existing = $this->getFastDLConfig($server);
        $merged = array_merge($existing, $data);
        file_put_contents($configFile, json_encode($merged, JSON_PRETTY_PRINT));
    }
}
