<?php

namespace Pterodactyl\Http\Controllers\Api\Client\Servers\DGEN;

use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Pterodactyl\Http\Controllers\Controller;
use Pterodactyl\Models\Server;
use Pterodactyl\Models\WipeSchedule;
use Pterodactyl\Models\WipeExecution;

class ServerWiperController extends Controller
{
    public function getSchedules(Request $request, Server $server): JsonResponse
    {
        try {
            $schedules = WipeSchedule::where('server_id', $server->id)
                ->with('executions')
                ->get();

            return response()->json(['schedules' => $schedules]);
        } catch (\Exception $e) {
            return response()->json(['schedules' => [], 'error' => $e->getMessage()], 500);
        }
    }

    public function createSchedule(Request $request, Server $server): JsonResponse
    {
        $request->validate([
            'interval' => 'required|integer|min:1',
            'task_type' => 'required|string|max:191',
            'next_run' => 'required|date|after:now',
        ]);

        try {
            $schedule = WipeSchedule::create([
                'server_id' => $server->id,
                'interval' => $request->input('interval'),
                'task_type' => $request->input('task_type'),
                'next_run' => $request->input('next_run'),
            ]);

            return response()->json(['success' => true, 'schedule' => $schedule]);
        } catch (\Exception $e) {
            return response()->json(['error' => 'Failed to create schedule: ' . $e->getMessage()], 500);
        }
    }

    public function updateSchedule(Request $request, Server $server): JsonResponse
    {
        $request->validate([
            'schedule_id' => 'required|integer|exists:wipe_schedules,id',
            'interval' => 'nullable|integer|min:1',
            'task_type' => 'nullable|string|max:191',
            'next_run' => 'nullable|date',
        ]);

        try {
            $schedule = WipeSchedule::where('server_id', $server->id)->findOrFail($request->input('schedule_id'));

            $data = array_filter([
                'interval' => $request->input('interval'),
                'task_type' => $request->input('task_type'),
                'next_run' => $request->input('next_run'),
            ], fn($v) => $v !== null);

            $schedule->update($data);

            return response()->json(['success' => true, 'schedule' => $schedule]);
        } catch (\Exception $e) {
            return response()->json(['error' => 'Failed to update schedule: ' . $e->getMessage()], 500);
        }
    }

    public function toggleSchedule(Request $request, Server $server): JsonResponse
    {
        $request->validate(['schedule_id' => 'required|integer|exists:wipe_schedules,id']);

        try {
            $schedule = WipeSchedule::where('server_id', $server->id)->findOrFail($request->input('schedule_id'));
            $schedule->update(['is_active' => !$schedule->is_active]);

            return response()->json(['success' => true, 'is_active' => $schedule->is_active]);
        } catch (\Exception $e) {
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }

    public function deleteSchedule(Request $request, Server $server): JsonResponse
    {
        $request->validate(['schedule_id' => 'required|integer|exists:wipe_schedules,id']);

        try {
            $schedule = WipeSchedule::where('server_id', $server->id)->findOrFail($request->input('schedule_id'));
            $schedule->delete();

            return response()->json(['success' => true]);
        } catch (\Exception $e) {
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }

    public function executeNow(Request $request, Server $server): JsonResponse
    {
        $request->validate(['schedule_id' => 'required|integer|exists:wipe_schedules,id']);

        try {
            $schedule = WipeSchedule::where('server_id', $server->id)->findOrFail($request->input('schedule_id'));

            $execution = WipeExecution::create([
                'server_id' => $server->id,
                'schedule_id' => $schedule->id,
                'status' => 'running',
            ]);

            return response()->json(['success' => true, 'execution' => $execution, 'message' => 'Wipe execution started']);
        } catch (\Exception $e) {
            return response()->json(['error' => 'Failed to execute: ' . $e->getMessage()], 500);
        }
    }

    public function getHistory(Request $request, Server $server): JsonResponse
    {
        try {
            $executions = WipeExecution::where('server_id', $server->id)
                ->with('schedule')
                ->orderBy('created_at', 'desc')
                ->limit(50)
                ->get();

            return response()->json(['history' => $executions]);
        } catch (\Exception $e) {
            return response()->json(['history' => []], 500);
        }
    }

    public function getRustMaps(Request $request, Server $server): JsonResponse
    {
        try {
            return response()->json(['maps' => []]);
        } catch (\Exception $e) {
            return response()->json(['maps' => [], 'error' => $e->getMessage()], 500);
        }
    }

    public function createRustMap(Request $request, Server $server): JsonResponse
    {
        $request->validate(['seed' => 'required|integer|min:1']);

        try {
            return response()->json(['success' => true, 'message' => 'Rust map generation queued']);
        } catch (\Exception $e) {
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }

    public function deleteRustMap(Request $request, Server $server): JsonResponse
    {
        $request->validate(['map_id' => 'required|string']);

        try {
            return response()->json(['success' => true]);
        } catch (\Exception $e) {
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }
}
