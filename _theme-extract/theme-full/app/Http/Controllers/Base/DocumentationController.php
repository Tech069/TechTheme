<?php

namespace Pterodactyl\Http\Controllers\Base;

use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Pterodactyl\Http\Controllers\Controller;
use Pterodactyl\Models\Server;

class DocumentationController extends Controller
{
    public function show(\Illuminate\Http\Request $request, \Pterodactyl\Models\Server $server): \Illuminate\Http\JsonResponse
    {
        try {
        return response()->json(['docs' => []]);
        } catch (\Exception $e) {
            return response()->json(['error' => 'Failed'], 500);
        }
    }
}
