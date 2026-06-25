<?php

namespace Pterodactyl\Http\Controllers\Api\Client\Servers\DGEN;

use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Pterodactyl\Http\Controllers\Controller;
use Pterodactyl\Models\Server;
use Pterodactyl\Models\ServerQuickAccess;

class QuickFileAccessController extends Controller
{
    public function index(Request $request, Server $server): JsonResponse
    {
        try {
            $shortcuts = ServerQuickAccess::where('server_id', $server->id)
                ->where('user_id', $request->user()->id)
                ->get();

            return response()->json(['shortcuts' => $shortcuts]);
        } catch (\Exception $e) {
            return response()->json(['shortcuts' => [], 'error' => $e->getMessage()], 500);
        }
    }

    public function store(Request $request, Server $server): JsonResponse
    {
        $request->validate([
            'label' => 'required|string|max:191',
            'path' => 'required|string|max:500',
        ]);

        try {
            $shortcut = ServerQuickAccess::create([
                'user_id' => $request->user()->id,
                'server_id' => $server->id,
                'label' => $request->input('label'),
                'path' => $request->input('path'),
            ]);

            return response()->json(['success' => true, 'shortcut' => $shortcut]);
        } catch (\Exception $e) {
            return response()->json(['error' => 'Failed to create shortcut: ' . $e->getMessage()], 500);
        }
    }

    public function toggle(Request $request, Server $server): JsonResponse
    {
        $request->validate(['shortcut_id' => 'required|integer']);

        try {
            $shortcut = ServerQuickAccess::where('server_id', $server->id)
                ->where('user_id', $request->user()->id)
                ->findOrFail($request->input('shortcut_id'));

            $shortcut->update(['enabled' => !$shortcut->enabled]);

            return response()->json(['success' => true, 'enabled' => $shortcut->enabled]);
        } catch (\Exception $e) {
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }

    public function check(Request $request, Server $server): JsonResponse
    {
        $request->validate(['path' => 'required|string']);

        try {
            $fullPath = $server->server_data_directory . '/' . $request->input('path');
            $exists = file_exists($fullPath);

            return response()->json([
                'exists' => $exists,
                'is_directory' => $exists && is_dir($fullPath),
                'is_file' => $exists && is_file($fullPath),
            ]);
        } catch (\Exception $e) {
            return response()->json(['exists' => false, 'error' => $e->getMessage()], 500);
        }
    }

    public function validateItems(Request $request, Server $server): JsonResponse
    {
        $request->validate(['paths' => 'required|array']);

        try {
            $results = [];
            foreach ($request->input('paths') as $path) {
                $fullPath = $server->server_data_directory . '/' . $path;
                $results[$path] = [
                    'exists' => file_exists($fullPath),
                    'is_valid' => str_starts_with(realpath($fullPath) ?? '', $server->server_data_directory),
                ];
            }

            return response()->json(['results' => $results]);
        } catch (\Exception $e) {
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }

    public function destroy(Request $request, Server $server): JsonResponse
    {
        $request->validate(['shortcut_id' => 'required|integer']);

        try {
            $shortcut = ServerQuickAccess::where('server_id', $server->id)
                ->where('user_id', $request->user()->id)
                ->findOrFail($request->input('shortcut_id'));

            $shortcut->delete();

            return response()->json(['success' => true]);
        } catch (\Exception $e) {
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }

    public function destroyByPath(Request $request, Server $server): JsonResponse
    {
        $request->validate(['path' => 'required|string']);

        try {
            ServerQuickAccess::where('server_id', $server->id)
                ->where('user_id', $request->user()->id)
                ->where('path', $request->input('path'))
                ->delete();

            return response()->json(['success' => true]);
        } catch (\Exception $e) {
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }
}
