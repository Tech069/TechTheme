<?php

namespace Pterodactyl\Http\Controllers\Base;

use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Pterodactyl\Http\Controllers\Controller;
use Pterodactyl\Models\Server;

class PublicStatusPageController extends Controller
{
    public function index(\Illuminate\Http\Request $request, \Pterodactyl\Models\Server $server): \Illuminate\Http\JsonResponse
    {
        try {
        return response()->json(['nodes' => []]);
        } catch (\Exception $e) {
            return response()->json(['error' => 'Failed'], 500);
        }
    }
}
