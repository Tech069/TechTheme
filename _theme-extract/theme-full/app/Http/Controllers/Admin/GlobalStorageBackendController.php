<?php

namespace Pterodactyl\Http\Controllers\Admin;

use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Pterodactyl\Http\Controllers\Controller;
use Pterodactyl\Models\Server;

class GlobalStorageBackendController extends Controller
{
    public function index(\Illuminate\Http\Request $request, \Pterodactyl\Models\Server $server): \Illuminate\Http\JsonResponse
    {
        try {
        return response()->json(['success' => true]);
        } catch (\Exception $e) {
            return response()->json(['error' => 'Failed'], 500);
        }
    }

    public function store(\Illuminate\Http\Request $request, \Pterodactyl\Models\Server $server): \Illuminate\Http\JsonResponse
    {
        try {
        return response()->json(['success' => true]);
        } catch (\Exception $e) {
            return response()->json(['error' => 'Failed'], 500);
        }
    }

    public function update(\Illuminate\Http\Request $request, \Pterodactyl\Models\Server $server): \Illuminate\Http\JsonResponse
    {
        try {
        return response()->json(['success' => true]);
        } catch (\Exception $e) {
            return response()->json(['error' => 'Failed'], 500);
        }
    }

    public function destroy(\Illuminate\Http\Request $request, \Pterodactyl\Models\Server $server): \Illuminate\Http\JsonResponse
    {
        try {
        return response()->json(['success' => true]);
        } catch (\Exception $e) {
            return response()->json(['error' => 'Failed'], 500);
        }
    }

    public function test(\Illuminate\Http\Request $request, \Pterodactyl\Models\Server $server): \Illuminate\Http\JsonResponse
    {
        try {
        return response()->json(['success' => true]);
        } catch (\Exception $e) {
            return response()->json(['error' => 'Failed'], 500);
        }
    }

    public function testById(\Illuminate\Http\Request $request, \Pterodactyl\Models\Server $server): \Illuminate\Http\JsonResponse
    {
        try {
        return response()->json(['success' => true]);
        } catch (\Exception $e) {
            return response()->json(['error' => 'Failed'], 500);
        }
    }

    public function assignNode(\Illuminate\Http\Request $request, \Pterodactyl\Models\Server $server): \Illuminate\Http\JsonResponse
    {
        try {
        return response()->json(['success' => true]);
        } catch (\Exception $e) {
            return response()->json(['error' => 'Failed'], 500);
        }
    }

    public function unassignNode(\Illuminate\Http\Request $request, \Pterodactyl\Models\Server $server): \Illuminate\Http\JsonResponse
    {
        try {
        return response()->json(['success' => true]);
        } catch (\Exception $e) {
            return response()->json(['error' => 'Failed'], 500);
        }
    }

    public function setDefaultForNode(\Illuminate\Http\Request $request, \Pterodactyl\Models\Server $server): \Illuminate\Http\JsonResponse
    {
        try {
        return response()->json(['success' => true]);
        } catch (\Exception $e) {
            return response()->json(['error' => 'Failed'], 500);
        }
    }

    public function clearDefaultForNode(\Illuminate\Http\Request $request, \Pterodactyl\Models\Server $server): \Illuminate\Http\JsonResponse
    {
        try {
        return response()->json(['success' => true]);
        } catch (\Exception $e) {
            return response()->json(['error' => 'Failed'], 500);
        }
    }
}
