<?php

namespace Pterodactyl\Http\Controllers\Admin;

use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Pterodactyl\Http\Controllers\Controller;
use Pterodactyl\Models\Server;

class PanelLogsController extends Controller
{
    public function index(\Illuminate\Http\Request $request, \Pterodactyl\Models\Server $server): \Illuminate\Http\JsonResponse
    {
        try {
        return response()->json(['success' => true]);
        } catch (\Exception $e) {
            return response()->json(['error' => 'Failed'], 500);
        }
    }

    public function list(\Illuminate\Http\Request $request, \Pterodactyl\Models\Server $server): \Illuminate\Http\JsonResponse
    {
        try {
        return response()->json(['success' => true]);
        } catch (\Exception $e) {
            return response()->json(['error' => 'Failed'], 500);
        }
    }

    public function history(\Illuminate\Http\Request $request, \Pterodactyl\Models\Server $server): \Illuminate\Http\JsonResponse
    {
        try {
        return response()->json(['success' => true]);
        } catch (\Exception $e) {
            return response()->json(['error' => 'Failed'], 500);
        }
    }

    public function stream(\Illuminate\Http\Request $request, \Pterodactyl\Models\Server $server): \Illuminate\Http\JsonResponse
    {
        try {
        return response()->json(['success' => true]);
        } catch (\Exception $e) {
            return response()->json(['error' => 'Failed'], 500);
        }
    }
}
