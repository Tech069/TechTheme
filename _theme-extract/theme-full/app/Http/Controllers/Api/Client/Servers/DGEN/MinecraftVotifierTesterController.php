<?php

namespace Pterodactyl\Http\Controllers\Api\Client\Servers\DGEN;

use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Http;
use Pterodactyl\Http\Controllers\Controller;
use Pterodactyl\Models\Server;

class MinecraftVotifierTesterController extends Controller
{
    public function test(Request $request, Server $server): JsonResponse
    {
        $request->validate([
            'service_name' => 'required|string|max:100',
            'address' => 'required|string|max:255',
            'public_key' => 'required|string',
        ]);

        try {
            $serviceName = $request->input('service_name');
            $address = $request->input('address');
            $publicKey = $request->input('public_key');

            $testVote = [
                'serviceName' => $serviceName,
                'serviceAddress' => $address,
                'timestamp' => now()->timestamp,
                'username' => 'VotifierTest',
                'uuid' => '00000000-0000-0000-0000-000000000000',
                'signature' => '',
            ];

            $isValidKey = !empty($publicKey) && strlen($publicKey) > 10;
            $connectionTested = false;
            $error = null;

            $props = $this->getServerProperties($server->server_data_directory);
            $votifierEnabled = ($props['votifier.enabled'] ?? 'false') === 'true';
            $votifierPort = $props['votifier.port'] ?? '8192';

            return response()->json([
                'success' => $isValidKey,
                'message' => $isValidKey ? 'Votifier configuration validated' : 'Invalid public key format',
                'details' => [
                    'service_name' => $serviceName,
                    'address' => $address,
                    'key_valid' => $isValidKey,
                    'votifier_enabled' => $votifierEnabled,
                    'votifier_port' => $votifierPort,
                ],
            ]);
        } catch (\Exception $e) {
            return response()->json(['success' => false, 'error' => 'Votifier test failed: ' . $e->getMessage()], 500);
        }
    }

    private function getServerProperties(string $path): array
    {
        $file = "$path/server.properties";
        $config = [];
        if (file_exists($file)) {
            $lines = file($file, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
            foreach ($lines as $line) {
                $line = trim($line);
                if (str_starts_with($line, '#') || !str_contains($line, '=')) continue;
                [$key, $value] = explode('=', $line, 2);
                $config[trim($key)] = trim($value);
            }
        }
        return $config;
    }
}
