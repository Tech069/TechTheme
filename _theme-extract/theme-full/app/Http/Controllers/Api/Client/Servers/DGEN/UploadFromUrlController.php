<?php

namespace Pterodactyl\Http\Controllers\Api\Client\Servers\DGEN;

use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Http;
use Pterodactyl\Http\Controllers\Controller;
use Pterodactyl\Models\Server;

class UploadFromUrlController extends Controller
{
    public function query(Request $request, Server $server): JsonResponse
    {
        $request->validate(['url' => 'required|url']);

        try {
            $url = $request->input('url');
            $response = Http::head($url, [], ['timeout' => 10]);

            if ($response->successful()) {
                return response()->json([
                    'success' => true,
                    'content_type' => $response->header('Content-Type', 'unknown'),
                    'content_length' => (int) $response->header('Content-Length', 0),
                    'filename' => $this->guessFilename($url, $response),
                ]);
            }

            return response()->json(['error' => 'Could not fetch URL info'], 422);
        } catch (\Exception $e) {
            return response()->json(['error' => 'Failed to query URL: ' . $e->getMessage()], 500);
        }
    }

    public function upload(Request $request, Server $server): JsonResponse
    {
        $request->validate([
            'url' => 'required|url',
            'path' => 'nullable|string|max:500',
        ]);

        try {
            $url = $request->input('url');
            $filename = $this->guessFilename($url);
            $destPath = $server->server_data_directory . '/' . ($request->input('path') ?? '');

            if (!is_dir($destPath)) {
                mkdir($destPath, 0755, true);
            }

            $response = Http::timeout(60)->get($url);

            if ($response->successful()) {
                $filePath = $destPath . '/' . $filename;
                file_put_contents($filePath, $response->body());

                return response()->json([
                    'success' => true,
                    'message' => 'File downloaded successfully',
                    'filename' => $filename,
                    'size' => strlen($response->body()),
                    'path' => $request->input('path', '') . '/' . $filename,
                ]);
            }

            return response()->json(['error' => 'Failed to download file: HTTP ' . $response->status()], 422);
        } catch (\Exception $e) {
            return response()->json(['error' => 'Upload failed: ' . $e->getMessage()], 500);
        }
    }

    private function guessFilename(string $url, $response = null): string
    {
        $path = parse_url($url, PHP_URL_PATH);
        $filename = basename($path);

        if (empty($filename) || !str_contains($filename, '.')) {
            $filename = 'download_' . date('Y-m-d_His');

            if ($response) {
                $contentType = $response->header('Content-Type', '');
                $extensions = [
                    'application/zip' => '.zip',
                    'application/x-tar' => '.tar',
                    'application/gzip' => '.gz',
                    'application/x-rar' => '.rar',
                    'application/octet-stream' => '.bin',
                    'text/plain' => '.txt',
                    'text/html' => '.html',
                ];
                foreach ($extensions as $mime => $ext) {
                    if (str_contains($contentType, $mime)) {
                        $filename .= $ext;
                        break;
                    }
                }
            }
        }

        return $filename;
    }
}
