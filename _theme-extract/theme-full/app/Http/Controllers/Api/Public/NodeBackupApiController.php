<?php

namespace Pterodactyl\Http\Controllers\Api\Public;

use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Http;
use Pterodactyl\Http\Controllers\Controller;
use Pterodactyl\Models\NodeBackup;

class NodeBackupApiController extends Controller
{
    public function __construct()
    {
    }

    public function store(Request $request): JsonResponse
    {
        $request->validate([
            'node_id' => 'required|integer|exists:nodes,id',
            'backup_path' => 'required|string|max:191',
            'schedule' => 'nullable|string|max:191',
            'location_id' => 'required|integer|exists:locations,id',
        ]);

        try {
            $backup = NodeBackup::create([
                'node_id' => $request->input('node_id'),
                'backup_path' => $request->input('backup_path'),
                'schedule' => $request->input('schedule'),
                'location_id' => $request->input('location_id'),
            ]);

            return response()->json(['success' => true, 'backup' => $backup]);
        } catch (\Exception $e) {
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }

    public function index(Request $request): JsonResponse
    {
        try {
            $backups = NodeBackup::with('node', 'location')
                ->orderBy('created_at', 'desc')
                ->get();

            return response()->json(['backups' => $backups]);
        } catch (\Exception $e) {
            return response()->json(['backups' => []], 500);
        }
    }

    public function destroy(Request $request, int $id): JsonResponse
    {
        try {
            $backup = NodeBackup::findOrFail($id);
            $backup->delete();

            return response()->json(['success' => true]);
        } catch (\Exception $e) {
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }
}
