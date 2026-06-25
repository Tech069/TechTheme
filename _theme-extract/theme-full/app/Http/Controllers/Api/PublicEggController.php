<?php

namespace Pterodactyl\Http\Controllers\Api;

use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Pterodactyl\Http\Controllers\Controller;
use Pterodactyl\Models\Server;

class PublicEggController extends Controller
{
    public function index(\Illuminate\Http\Request $request, \Pterodactyl\Models\Server $server): \Illuminate\Http\JsonResponse
    {
        try {
        return response()->json(['eggs' => []]);
        } catch (\Exception $e) {
            return response()->json(['error' => 'Failed'], 500);
        }
    }
}
