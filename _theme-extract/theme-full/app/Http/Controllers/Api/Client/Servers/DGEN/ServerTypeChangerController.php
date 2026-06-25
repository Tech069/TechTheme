<?php

namespace Pterodactyl\Http\Controllers\Api\Client\Servers\DGEN;

use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Pterodactyl\Http\Controllers\Controller;
use Pterodactyl\Models\Server;
use Pterodactyl\Models\Egg;
use Pterodactyl\Models\Nest;

class ServerTypeChangerController extends Controller
{
    public function getAllNestsAndEggs(Request $request, Server $server): JsonResponse
    {
        try {
            $nests = Nest::with(['eggs' => function ($query) {
                $query->select(['id', 'nest_id', 'name', 'description', 'docker_images']);
            }])->get();

            return response()->json(['nests' => $nests]);
        } catch (\Exception $e) {
            return response()->json(['nests' => [], 'error' => $e->getMessage()], 500);
        }
    }

    public function getNests(Request $request, Server $server): JsonResponse
    {
        try {
            $nests = Nest::all(['id', 'name', 'description']);
            return response()->json(['nests' => $nests]);
        } catch (\Exception $e) {
            return response()->json(['nests' => []], 500);
        }
    }

    public function getCurrentServerType(Request $request, Server $server): JsonResponse
    {
        try {
            $server->load(['egg', 'nest']);

            return response()->json([
                'server' => [
                    'id' => $server->id,
                    'name' => $server->name,
                ],
                'current' => [
                    'egg_id' => $server->egg_id,
                    'egg_name' => $server->egg->name ?? 'Unknown',
                    'nest_id' => $server->nest_id,
                    'nest_name' => $server->nest->name ?? 'Unknown',
                    'startup' => $server->startup,
                    'docker_image' => $server->image,
                ],
            ]);
        } catch (\Exception $e) {
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }

    public function changeServerType(Request $request, Server $server): JsonResponse
    {
        $request->validate([
            'egg_id' => 'required|integer|exists:eggs,id',
            'startup' => 'nullable|string|max:500',
            'docker_image' => 'nullable|string|max:191',
        ]);

        try {
            $egg = Egg::with('variables')->findOrFail($request->input('egg_id'));

            $updateData = ['egg_id' => $egg->id, 'nest_id' => $egg->nest_id];

            if ($request->has('startup')) {
                $updateData['startup'] = $request->input('startup');
            } elseif ($egg->startup) {
                $updateData['startup'] = $egg->startup;
            }

            if ($request->has('docker_image')) {
                $updateData['image'] = $request->input('docker_image');
            } elseif (!empty($egg->docker_images)) {
                $images = array_values($egg->docker_images);
                $updateData['image'] = $images[0] ?? $server->image;
            }

            $server->update($updateData);

            foreach ($egg->variables as $variable) {
                $existing = $server->variables()->where('variable_id', $variable->id)->first();
                if (!$existing) {
                    $server->variables()->attach($variable->id, [
                        'variable_value' => $variable->default_value,
                    ]);
                }
            }

            return response()->json([
                'success' => true,
                'message' => 'Server type changed to ' . $egg->name,
                'egg' => ['id' => $egg->id, 'name' => $egg->name],
            ]);
        } catch (\Exception $e) {
            return response()->json(['error' => 'Failed to change server type: ' . $e->getMessage()], 500);
        }
    }

    public function getProgress(Request $request, Server $server): JsonResponse
    {
        return response()->json(['progress' => 100, 'status' => 'completed']);
    }
}
