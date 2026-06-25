<?php

namespace Pterodactyl\Http\Controllers\Api\Client\Servers\DGEN;

use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Pterodactyl\Http\Controllers\Controller;
use Pterodactyl\Models\Server;
use Pterodactyl\Models\CustomMonitor;

class CustomMonitorController extends Controller
{
    public function index(Request $request, Server $server): JsonResponse
    {
        try {
            $monitors = CustomMonitor::where('server_id', $server->id)->get();
            return response()->json(['monitors' => $monitors]);
        } catch (\Exception $e) {
            return response()->json(['monitors' => []], 500);
        }
    }

    public function store(Request $request, Server $server): JsonResponse
    {
        $request->validate([
            'type' => 'required|string|max:191',
            'regex' => 'nullable|string|max:191',
            'threshold' => 'nullable|numeric',
            'description' => 'nullable|string|max:255',
        ]);

        try {
            $monitor = CustomMonitor::create([
                'server_id' => $server->id,
                'type' => $request->input('type'),
                'regex' => $request->input('regex'),
                'threshold' => $request->input('threshold'),
                'description' => $request->input('description', ''),
            ]);

            return response()->json(['success' => true, 'monitor' => $monitor]);
        } catch (\Exception $e) {
            return response()->json(['error' => 'Failed to create monitor: ' . $e->getMessage()], 500);
        }
    }

    public function update(Request $request, Server $server): JsonResponse
    {
        $request->validate([
            'monitor_id' => 'required|integer|exists:custom_monitors,id',
            'type' => 'nullable|string|max:191',
            'regex' => 'nullable|string|max:191',
            'threshold' => 'nullable|numeric',
            'description' => 'nullable|string|max:255',
        ]);

        try {
            $monitor = CustomMonitor::where('server_id', $server->id)->findOrFail($request->input('monitor_id'));

            $data = array_filter([
                'type' => $request->input('type'),
                'regex' => $request->input('regex'),
                'threshold' => $request->input('threshold'),
                'description' => $request->input('description'),
            ], fn($v) => $v !== null);

            $monitor->update($data);

            return response()->json(['success' => true, 'monitor' => $monitor]);
        } catch (\Exception $e) {
            return response()->json(['error' => 'Failed to update monitor: ' . $e->getMessage()], 500);
        }
    }

    public function destroy(Request $request, Server $server): JsonResponse
    {
        $request->validate(['monitor_id' => 'required|integer|exists:custom_monitors,id']);

        try {
            $monitor = CustomMonitor::where('server_id', $server->id)->findOrFail($request->input('monitor_id'));
            $monitor->delete();

            return response()->json(['success' => true]);
        } catch (\Exception $e) {
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }
}
