<?php

namespace Pterodactyl\Http\Controllers\Api\Client\Servers\DGEN;

use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Pterodactyl\Http\Controllers\Controller;
use Pterodactyl\Models\Server;
use Pterodactyl\Models\Schedule;
use Pterodactyl\Models\Task;

class SchedulePresetsController extends Controller
{
    public function applyPreset(Request $request, Server $server): JsonResponse
    {
        $request->validate(['preset' => 'required|string']);

        try {
            $presets = $this->getSchedulePresets();
            $presetName = $request->input('preset');
            $preset = collect($presets)->firstWhere('name', $presetName);

            if (!$preset) {
                return response()->json(['error' => 'Preset not found'], 422);
            }

            $schedule = Schedule::create([
                'server_id' => $server->id,
                'name' => $preset['name'] . ' Schedule',
                'cron_day_of_week' => '*',
                'cron_month' => '*',
                'cron_day_of_month' => '*',
                'cron_hour' => $preset['cron_hour'] ?? '*',
                'cron_minute' => $preset['cron_minute'] ?? '0',
                'is_active' => true,
                'only_when_online' => $preset['only_when_online'] ?? false,
            ]);

            foreach ($preset['tasks'] as $taskData) {
                Task::create([
                    'schedule_id' => $schedule->id,
                    'sequence_id' => $taskData['sequence'] ?? 1,
                    'action' => $taskData['action'],
                    'payload' => $taskData['payload'] ?? '',
                    'time_offset' => $taskData['time_offset'] ?? 0,
                    'is_queued' => false,
                    'run_server_id' => $server->id,
                ]);
            }

            return response()->json(['success' => true, 'schedule' => $schedule->load('tasks')]);
        } catch (\Exception $e) {
            return response()->json(['error' => 'Failed to apply preset: ' . $e->getMessage()], 500);
        }
    }

    public function importSchedule(Request $request, Server $server): JsonResponse
    {
        $request->validate([
            'name' => 'required|string|max:191',
            'cron' => 'required|string',
            'tasks' => 'required|array',
        ]);

        try {
            $cronParts = explode(' ', $request->input('cron'));
            $schedule = Schedule::create([
                'server_id' => $server->id,
                'name' => $request->input('name'),
                'cron_minute' => $cronParts[0] ?? '*',
                'cron_hour' => $cronParts[1] ?? '*',
                'cron_day_of_month' => $cronParts[2] ?? '*',
                'cron_month' => $cronParts[3] ?? '*',
                'cron_day_of_week' => $cronParts[4] ?? '*',
                'is_active' => true,
                'only_when_online' => false,
            ]);

            $sequence = 1;
            foreach ($request->input('tasks') as $taskData) {
                Task::create([
                    'schedule_id' => $schedule->id,
                    'sequence_id' => $sequence++,
                    'action' => $taskData['action'] ?? 'command',
                    'payload' => $taskData['payload'] ?? '',
                    'time_offset' => $taskData['time_offset'] ?? 0,
                    'is_queued' => false,
                    'run_server_id' => $server->id,
                ]);
            }

            return response()->json(['success' => true, 'schedule' => $schedule->load('tasks')]);
        } catch (\Exception $e) {
            return response()->json(['error' => 'Failed to import schedule: ' . $e->getMessage()], 500);
        }
    }

    private function getSchedulePresets(): array
    {
        return [
            [
                'name' => 'Daily Restart',
                'cron_hour' => '4',
                'cron_minute' => '0',
                'only_when_online' => false,
                'tasks' => [
                    ['action' => 'command', 'payload' => 'save-all', 'sequence' => 1],
                    ['action' => 'command', 'payload' => 'say Server restarting in 5 minutes!', 'sequence' => 2, 'time_offset' => -300],
                    ['action' => 'power', 'payload' => 'restart', 'sequence' => 3, 'time_offset' => 0],
                ],
            ],
            [
                'name' => 'Auto Backup',
                'cron_hour' => '3',
                'cron_minute' => '0',
                'only_when_online' => true,
                'tasks' => [
                    ['action' => 'backup', 'payload' => '', 'sequence' => 1, 'time_offset' => 0],
                ],
            ],
            [
                'name' => 'Weekly Clean',
                'cron_hour' => '5',
                'cron_minute' => '0',
                'only_when_online' => false,
                'tasks' => [
                    ['action' => 'command', 'payload' => 'save-all', 'sequence' => 1],
                    ['action' => 'command', 'payload' => 'gc', 'sequence' => 2],
                    ['action' => 'command', 'payload' => 'say World cleanup complete!', 'sequence' => 3],
                ],
            ],
        ];
    }
}
