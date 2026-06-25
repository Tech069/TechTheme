<?php

namespace Pterodactyl\Http\Controllers\Api\Client\Servers\DGEN;

use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Pterodactyl\Http\Controllers\Controller;
use Pterodactyl\Models\Server;

class ConfigEditorController extends Controller
{
    private const ALLOWED_FILES = [
        'server.properties',
        'bukkit.yml',
        'spigot.yml',
        'paper.yml',
        'paper-global.yml',
        'pufferfish.yml',
        'purpur.yml',
        'config/paper-global.yml',
        'config/paper.yml',
        'eula.txt',
    ];

    public function getAvailableFiles(Request $request, Server $server): JsonResponse
    {
        try {
            $path = $server->server_data_directory;
            $files = [];

            foreach (self::ALLOWED_FILES as $file) {
                $fullPath = "$path/$file";
                if (file_exists($fullPath)) {
                    $files[] = [
                        'name' => $file,
                        'size' => filesize($fullPath),
                        'modified' => date('Y-m-d H:i:s', filemtime($fullPath)),
                        'readable' => is_readable($fullPath),
                    ];
                }
            }

            $iniFiles = glob("$path/*.ini");
            foreach ($iniFiles as $iniFile) {
                $name = basename($iniFile);
                $files[] = [
                    'name' => $name,
                    'size' => filesize($iniFile),
                    'modified' => date('Y-m-d H:i:s', filemtime($iniFile)),
                    'readable' => is_readable($iniFile),
                ];
            }

            return response()->json(['files' => $files]);
        } catch (\Exception $e) {
            return response()->json(['files' => [], 'error' => $e->getMessage()], 500);
        }
    }

    public function getFileContent(Request $request, Server $server): JsonResponse
    {
        $request->validate(['file' => 'required|string']);

        try {
            $file = $request->input('file');

            if (!$this->isAllowedFile($file)) {
                return response()->json(['error' => 'File not allowed'], 422);
            }

            $fullPath = $server->server_data_directory . '/' . $file;

            if (!file_exists($fullPath)) {
                return response()->json(['error' => 'File not found'], 422);
            }

            $content = file_get_contents($fullPath);
            $lines = explode("\n", $content);

            return response()->json([
                'file' => $file,
                'content' => $content,
                'lines' => count($lines),
                'size' => filesize($fullPath),
            ]);
        } catch (\Exception $e) {
            return response()->json(['error' => 'Failed to read file: ' . $e->getMessage()], 500);
        }
    }

    public function updateFileContent(Request $request, Server $server): JsonResponse
    {
        $request->validate([
            'file' => 'required|string',
            'content' => 'required|string',
        ]);

        try {
            $file = $request->input('file');

            if (!$this->isAllowedFile($file)) {
                return response()->json(['error' => 'File not allowed'], 422);
            }

            $fullPath = $server->server_data_directory . '/' . $file;

            $backupPath = $fullPath . '.backup.' . date('Y-m-d-His');
            if (file_exists($fullPath)) {
                copy($fullPath, $backupPath);
            }

            $dir = dirname($fullPath);
            if (!is_dir($dir)) {
                mkdir($dir, 0755, true);
            }

            file_put_contents($fullPath, $request->input('content'));

            return response()->json([
                'success' => true,
                'message' => 'File updated successfully',
                'backup' => basename($backupPath),
            ]);
        } catch (\Exception $e) {
            return response()->json(['error' => 'Failed to update file: ' . $e->getMessage()], 500);
        }
    }

    private function isAllowedFile(string $file): bool
    {
        $normalized = str_replace('\\', '/', $file);
        return in_array($normalized, self::ALLOWED_FILES) ||
               preg_match('/\.ini$/', $normalized);
    }
}
