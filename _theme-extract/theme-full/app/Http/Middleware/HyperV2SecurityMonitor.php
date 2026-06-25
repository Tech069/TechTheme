<?php

namespace Pterodactyl\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class HyperV2SecurityMonitor
{
    public function handle(Request $request, Closure $next)
    {
        return $next($request);
    }
}
