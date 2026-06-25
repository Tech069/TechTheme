<?php

namespace Pterodactyl\Http\Controllers\Api\Client\DGEN;

use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Pterodactyl\Http\Controllers\Controller;
use Pterodactyl\Models\Server;

class WingsAddonController extends Controller
{
    public function checkStatus(Request $request, Server $server): JsonResponse
    {
        try {
            $node = $server->node;
            $isOnline = false;

            try {
                $response = \Illuminate\Support\Facades\Http::withToken($node->daemon_token)
                    ->timeout(5)
                    ->get($node->scheme . '://' . $node->fqdn . ':' . $node->daemonListen . '/api/servers');
                $isOnline = $response->successful();
            } catch (\Exception $e) {
                $isOnline = false;
            }

            return response()->json([
                'wings_online' => $isOnline,
                'node' => [
                    'id' => $node->id,
                    'name' => $node->name,
                ],
            ]);
        } catch (\Exception $e) {
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }

    public function searchFiles(Request $request, Server $server): JsonResponse
    {
        $request->validate(['query' => 'required|string|min:1']);

        try {
            $query = $request->input('query');
            $path = $server->server_data_directory;
            $results = [];

            $iterator = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($path));
            foreach ($iterator as $file) {
                if ($file->isFile() && str_contains(strtolower($file->getFilename()), strtolower($query))) {
                    $results[] = [
                        'path' => str_replace($path . '/', '', $file->getPathname()),
                        'name' => $file->getFilename(),
                        'size' => $file->getSize(),
                    ];
                    if (count($results) >= 50) break;
                }
            }

            return response()->json(['files' => $results]);
        } catch (\Exception $e) {
            return response()->json(['files' => [], 'error' => $e->getMessage()], 500);
        }
    }

    public function replaceFiles(Request $request, Server $server): JsonResponse
    {
        $request->validate([
            'source' => 'required|string',
            'destination' => 'required|string',
        ]);

        try {
            $sourcePath = $server->server_data_directory . '/' . $request->input('source');
            $destPath = $server->server_data_directory . '/' . $request->input('destination');

            if (file_exists($sourcePath)) {
                copy($sourcePath, $destPath);
                return response()->json(['success' => true, 'message' => 'File replaced']);
            }

            return response()->json(['error' => 'Source file not found'], 422);
        } catch (\Exception $e) {
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }

    public function folderSize(Request $request, Server $server): JsonResponse
    {
        $request->validate(['path' => 'nullable|string']);

        try {
            $path = $server->server_data_directory . '/' . ($request->input('path', ''));
            $size = $this->calculateDirectorySize($path);

            return response()->json([
                'path' => $request->input('path', '/'),
                'size' => $size,
                'size_human' => $this->formatBytes($size),
            ]);
        } catch (\Exception $e) {
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }

    public function folderSizeBatch(Request $request, Server $server): JsonResponse
    {
        $request->validate(['paths' => 'required|array']);

        try {
            $results = [];
            foreach ($request->input('paths') as $path) {
                $fullPath = $server->server_data_directory . '/' . $path;
                $size = $this->calculateDirectorySize($fullPath);
                $results[$path] = [
                    'size' => $size,
                    'size_human' => $this->formatBytes($size),
                ];
            }

            return response()->json(['results' => $results]);
        } catch (\Exception $e) {
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }

    private function calculateDirectorySize(string $path): int
    {
        if (!is_dir($path)) return 0;
        $size = 0;
        foreach (new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($path)) as $file) {
            if ($file->isFile()) {
                $size += $file->getSize();
            }
        }
        return $size;
    }

    private function formatBytes(int $bytes): string
    {
        $units = ['B', 'KB', 'MB', 'GB', 'TB'];
        $i = 0;
        while ($bytes >= 1024 && $i < count($units) - 1) {
            $bytes /= 1024;
            $i++;
        }
        return round($bytes, 2) . ' ' . $units[$i];
    }
}
