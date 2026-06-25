<?php

namespace Pterodactyl\Http\Controllers\Api\Client\Servers\DGEN;

use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Pterodactyl\Http\Controllers\Controller;
use Pterodactyl\Models\Server;
use Pterodactyl\Models\ServerRecycleBin;

class RecycleBinController extends Controller
{
    public function index(Request $request, Server $server): JsonResponse
    {
        try {
            $items = ServerRecycleBin::where('server_id', $server->id)
                ->orderBy('deleted_at', 'desc')
                ->get();

            return response()->json(['items' => $items]);
        } catch (\Exception $e) {
            return response()->json(['items' => [], 'error' => $e->getMessage()], 500);
        }
    }

    public function stats(Request $request, Server $server): JsonResponse
    {
        try {
            $count = ServerRecycleBin::where('server_id', $server->id)->count();
            $totalSize = ServerRecycleBin::where('server_id', $server->id)
                ->selectRaw('COALESCE(SUM(JSON_EXTRACT(data, "$.size")), 0) as total_size')
                ->value('total_size');

            return response()->json([
                'count' => $count,
                'total_size' => (int) $totalSize,
                'has_items' => $count > 0,
            ]);
        } catch (\Exception $e) {
            return response()->json(['count' => 0, 'error' => $e->getMessage()], 500);
        }
    }

    public function store(Request $request, Server $server): JsonResponse
    {
        $request->validate([
            'file_path' => 'required|string|max:500',
            'file_name' => 'required|string|max:255',
        ]);

        try {
            $filePath = $request->input('file_path');
            $fullPath = $server->server_data_directory . '/' . $filePath;

            $recycleData = [
                'file_path' => $filePath,
                'file_name' => $request->input('file_name'),
                'size' => file_exists($fullPath) ? filesize($fullPath) : 0,
                'backup_path' => null,
            ];

            if (file_exists($fullPath)) {
                $backupDir = $server->server_data_directory . '/.recycle_bin';
                if (!is_dir($backupDir)) {
                    mkdir($backupDir, 0755, true);
                }
                $backupPath = $backupDir . '/' . md5($filePath) . '_' . basename($filePath);
                copy($fullPath, $backupPath);
                $recycleData['backup_path'] = $backupPath;
            }

            $item = ServerRecycleBin::create([
                'server_id' => $server->id,
                'user_id' => $request->user()->id,
                'data' => $recycleData,
                'deleted_at' => now(),
                'restore_until' => now()->addDays(7),
            ]);

            return response()->json(['success' => true, 'item' => $item]);
        } catch (\Exception $e) {
            return response()->json(['error' => 'Failed to move to recycle bin: ' . $e->getMessage()], 500);
        }
    }

    public function restore(Request $request, Server $server): JsonResponse
    {
        $request->validate(['item_id' => 'required|integer|exists:server_recycle_bins,id']);

        try {
            $item = ServerRecycleBin::where('server_id', $server->id)->findOrFail($request->input('item_id'));
            $data = $item->data;

            if (!empty($data['backup_path']) && file_exists($data['backup_path'])) {
                $destPath = $server->server_data_directory . '/' . $data['file_path'];
                $dir = dirname($destPath);
                if (!is_dir($dir)) {
                    mkdir($dir, 0755, true);
                }
                copy($data['backup_path'], $destPath);
                unlink($data['backup_path']);
            }

            $item->delete();

            return response()->json(['success' => true, 'message' => 'File restored successfully']);
        } catch (\Exception $e) {
            return response()->json(['error' => 'Failed to restore: ' . $e->getMessage()], 500);
        }
    }

    public function restoreMultiple(Request $request, Server $server): JsonResponse
    {
        $request->validate(['item_ids' => 'required|array', 'item_ids.*' => 'integer']);

        try {
            $restored = 0;
            foreach ($request->input('item_ids') as $id) {
                $item = ServerRecycleBin::where('server_id', $server->id)->find($id);
                if ($item) {
                    $data = $item->data;
                    if (!empty($data['backup_path']) && file_exists($data['backup_path'])) {
                        $destPath = $server->server_data_directory . '/' . $data['file_path'];
                        $dir = dirname($destPath);
                        if (!is_dir($dir)) mkdir($dir, 0755, true);
                        copy($data['backup_path'], $destPath);
                        unlink($data['backup_path']);
                    }
                    $item->delete();
                    $restored++;
                }
            }

            return response()->json(['success' => true, 'restored' => $restored]);
        } catch (\Exception $e) {
            return response()->json(['error' => 'Failed to restore: ' . $e->getMessage()], 500);
        }
    }

    public function permanentDelete(Request $request, Server $server): JsonResponse
    {
        $request->validate(['item_id' => 'required|integer|exists:server_recycle_bins,id']);

        try {
            $item = ServerRecycleBin::where('server_id', $server->id)->findOrFail($request->input('item_id'));
            $data = $item->data;

            if (!empty($data['backup_path']) && file_exists($data['backup_path'])) {
                unlink($data['backup_path']);
            }

            $item->delete();

            return response()->json(['success' => true, 'message' => 'Item permanently deleted']);
        } catch (\Exception $e) {
            return response()->json(['error' => 'Failed to delete: ' . $e->getMessage()], 500);
        }
    }

    public function empty(Request $request, Server $server): JsonResponse
    {
        try {
            $items = ServerRecycleBin::where('server_id', $server->id)->get();
            foreach ($items as $item) {
                $data = $item->data;
                if (!empty($data['backup_path']) && file_exists($data['backup_path'])) {
                    unlink($data['backup_path']);
                }
            }

            ServerRecycleBin::where('server_id', $server->id)->delete();

            return response()->json(['success' => true, 'message' => 'Recycle bin emptied']);
        } catch (\Exception $e) {
            return response()->json(['error' => 'Failed to empty recycle bin: ' . $e->getMessage()], 500);
        }
    }

    public function show(Request $request, Server $server): JsonResponse
    {
        $request->validate(['item_id' => 'required|integer|exists:server_recycle_bins,id']);

        try {
            $item = ServerRecycleBin::where('server_id', $server->id)->findOrFail($request->input('item_id'));
            return response()->json(['item' => $item]);
        } catch (\Exception $e) {
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }

    public function preview(Request $request, Server $server): JsonResponse
    {
        $request->validate(['item_id' => 'required|integer|exists:server_recycle_bins,id']);

        try {
            $item = ServerRecycleBin::where('server_id', $server->id)->findOrFail($request->input('item_id'));
            $data = $item->data;
            $content = null;

            if (!empty($data['backup_path']) && file_exists($data['backup_path'])) {
                $content = file_get_contents($data['backup_path']);
                if (strlen($content) > 10000) {
                    $content = substr($content, 0, 10000) . "\n... [truncated]";
                }
            }

            return response()->json([
                'item' => $item,
                'preview' => $content,
                'has_preview' => $content !== null,
            ]);
        } catch (\Exception $e) {
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }

    public function download(Request $request, Server $server): JsonResponse
    {
        $request->validate(['item_id' => 'required|integer|exists:server_recycle_bins,id']);

        try {
            $item = ServerRecycleBin::where('server_id', $server->id)->findOrFail($request->input('item_id'));
            $data = $item->data;

            if (empty($data['backup_path']) || !file_exists($data['backup_path'])) {
                return response()->json(['error' => 'Backup file not found'], 422);
            }

            return response()->json([
                'download_url' => $data['backup_path'],
                'filename' => $data['file_name'] ?? basename($data['backup_path']),
                'size' => filesize($data['backup_path']),
            ]);
        } catch (\Exception $e) {
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }
}
