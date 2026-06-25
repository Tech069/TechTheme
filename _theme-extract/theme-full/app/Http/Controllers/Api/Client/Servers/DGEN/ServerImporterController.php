<?php

namespace Pterodactyl\Http\Controllers\Api\Client\Servers\DGEN;

use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Http;
use Pterodactyl\Http\Controllers\Controller;
use Pterodactyl\Models\Server;
use Pterodactyl\Models\ServerImport;

class ServerImporterController extends Controller
{
    public function testConnection(Request $request, Server $server): JsonResponse
    {
        $request->validate([
            'panel_url' => 'required|url',
            'api_key' => 'required|string',
        ]);

        try {
            $panelUrl = rtrim($request->input('panel_url'), '/');
            $apiKey = $request->input('api_key');

            $response = Http::withHeaders([
                'Authorization' => "Bearer $apiKey",
                'Accept' => 'Application/vnd.pterodactyl.v1+json',
            ])->timeout(10)->get("$panelUrl/api/client");

            if ($response->successful()) {
                $client = $response->json('object') === 'client' ? $response->json() : null;
                return response()->json([
                    'success' => true,
                    'message' => 'Connection successful',
                    'panel_url' => $panelUrl,
                    'user' => $client['attributes']['user']['username'] ?? 'unknown',
                ]);
            }

            return response()->json(['success' => false, 'error' => 'Connection failed: HTTP ' . $response->status()], 422);
        } catch (\Exception $e) {
            return response()->json(['success' => false, 'error' => 'Connection failed: ' . $e->getMessage()], 500);
        }
    }

    public function userImports(Request $request, Server $server): JsonResponse
    {
        try {
            $imports = ServerImport::where('user_id', $request->user()->id)
                ->with('server')
                ->orderBy('created_at', 'desc')
                ->get();

            return response()->json(['imports' => $imports]);
        } catch (\Exception $e) {
            return response()->json(['imports' => []], 500);
        }
    }

    public function index(Request $request, Server $server): JsonResponse
    {
        try {
            $imports = ServerImport::where('server_id', $server->id)
                ->orderBy('created_at', 'desc')
                ->get();

            return response()->json(['imports' => $imports]);
        } catch (\Exception $e) {
            return response()->json(['imports' => []], 500);
        }
    }

    public function store(Request $request, Server $server): JsonResponse
    {
        $request->validate([
            'panel_url' => 'required|url',
            'api_key' => 'required|string',
            'server_id' => 'required|integer',
        ]);

        try {
            $import = ServerImport::create([
                'user_id' => $request->user()->id,
                'server_id' => $server->id,
                'source_type' => 'pterodactyl',
                'status' => 'pending',
                'config' => [
                    'panel_url' => $request->input('panel_url'),
                    'remote_server_id' => $request->input('server_id'),
                ],
            ]);

            return response()->json(['success' => true, 'import' => $import]);
        } catch (\Exception $e) {
            return response()->json(['error' => 'Failed to create import: ' . $e->getMessage()], 500);
        }
    }

    public function show(Request $request, Server $server): JsonResponse
    {
        $request->validate(['import_id' => 'required|integer']);

        try {
            $import = ServerImport::where('server_id', $server->id)->findOrFail($request->input('import_id'));
            return response()->json(['import' => $import]);
        } catch (\Exception $e) {
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }

    public function update(Request $request, Server $server): JsonResponse
    {
        $request->validate([
            'import_id' => 'required|integer',
            'status' => 'required|string|in:pending,running,completed,failed',
        ]);

        try {
            $import = ServerImport::where('server_id', $server->id)->findOrFail($request->input('import_id'));
            $import->update(['status' => $request->input('status')]);

            return response()->json(['success' => true, 'import' => $import]);
        } catch (\Exception $e) {
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }

    public function destroy(Request $request, Server $server): JsonResponse
    {
        $request->validate(['import_id' => 'required|integer']);

        try {
            $import = ServerImport::where('server_id', $server->id)->findOrFail($request->input('import_id'));
            $import->delete();

            return response()->json(['success' => true]);
        } catch (\Exception $e) {
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }

    public function browse(Request $request, Server $server): JsonResponse
    {
        $request->validate([
            'panel_url' => 'required|url',
            'api_key' => 'required|string',
        ]);

        try {
            $panelUrl = rtrim($request->input('panel_url'), '/');
            $apiKey = $request->input('api_key');

            $response = Http::withHeaders([
                'Authorization' => "Bearer $apiKey",
                'Accept' => 'Application/vnd.pterodactyl.v1+json',
            ])->timeout(10)->get("$panelUrl/api/client");

            if ($response->successful()) {
                $servers = collect($response->json('data', []))->map(fn($s) => [
                    'id' => $s['attributes']['identifier'],
                    'name' => $s['attributes']['name'],
                    'node' => $s['attributes']['node'] ?? '',
                    'status' => $s['attributes']['status'] ?? 'unknown',
                ])->toArray();

                return response()->json(['servers' => $servers]);
            }

            return response()->json(['servers' => [], 'error' => 'Failed to fetch servers'], 422);
        } catch (\Exception $e) {
            return response()->json(['servers' => [], 'error' => $e->getMessage()], 500);
        }
    }

    public function import(Request $request, Server $server): JsonResponse
    {
        $request->validate([
            'import_id' => 'required|integer|exists:server_imports,id',
        ]);

        try {
            $import = ServerImport::findOrFail($request->input('import_id'));
            $import->update(['status' => 'running']);

            return response()->json(['success' => true, 'message' => 'Import started', 'import' => $import]);
        } catch (\Exception $e) {
            return response()->json(['error' => 'Failed to start import: ' . $e->getMessage()], 500);
        }
    }

    public function importProgress(Request $request, Server $server): JsonResponse
    {
        $request->validate(['import_id' => 'required|integer']);

        try {
            $import = ServerImport::findOrFail($request->input('import_id'));
            return response()->json([
                'status' => $import->status,
                'progress' => $import->status === 'completed' ? 100 : ($import->status === 'failed' ? 0 : 50),
            ]);
        } catch (\Exception $e) {
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }

    public function cancelImport(Request $request, Server $server): JsonResponse
    {
        $request->validate(['import_id' => 'required|integer']);

        try {
            $import = ServerImport::findOrFail($request->input('import_id'));
            $import->update(['status' => 'cancelled']);

            return response()->json(['success' => true, 'message' => 'Import cancelled']);
        } catch (\Exception $e) {
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }

    public function restore(Request $request, Server $server): JsonResponse
    {
        $request->validate(['import_id' => 'required|integer']);

        try {
            $import = ServerImport::findOrFail($request->input('import_id'));
            $import->update(['status' => 'pending']);

            return response()->json(['success' => true, 'message' => 'Import reset for retry']);
        } catch (\Exception $e) {
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }

    public function status(Request $request, Server $server): JsonResponse
    {
        try {
            $import = ServerImport::where('server_id', $server->id)
                ->latest()
                ->first();

            return response()->json([
                'import' => $import,
                'has_active_import' => $import && in_array($import->status, ['pending', 'running']),
            ]);
        } catch (\Exception $e) {
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }
}
