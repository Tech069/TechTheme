<?php
/**
 * VexyThemes License Middleware
 * 
 * Checks license on every page load.
 * If no valid license → shows license prompt page.
 * If valid → normal theme loads.
 * 
 * Install: Add to app/Http/Kernel.php $middleware array, or register in routes.
 * API: https://vt-panel-api.vercel.app
 */

namespace Pterodactyl\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class VexyThemesLicenseGate
{
    const LICENSE_API = 'https://vt-panel-api.vercel.app';

    public function handle(Request $request, Closure $next)
    {
        // Skip license check for certain routes
        $skipPaths = [
            '/api/v2/vexythemes/license',
            '/api/v2/vexythemes/discord',
            '/vexythemes-license',
            '/logout',
            '/auth',
        ];

        $path = $request->path();
        foreach ($skipPaths as $skip) {
            if (str_starts_with($path, $skip)) {
                return $next($request);
            }
        }

        // Check if license is valid (cached in settings table)
        $valid = $this->isLicenseValid();
        
        if (!$valid) {
            // Show license prompt page instead of normal content
            return response()->view('vexythemes.license-gate', [
                'apiUrl' => self::LICENSE_API,
                'panelUrl' => config('app.url'),
            ]);
        }

        return $next($request);
    }

    private function isLicenseValid(): bool
    {
        try {
            $key = DB::table('settings')->where('key', 'vexythemes_license_key')->first();
            if (!$key || empty($key->value)) return false;

            // Check cache (valid for 24 hours)
            $cache = DB::table('settings')->where('key', 'vexythemes_license_cache')->first();
            if ($cache && $cache->value) {
                $cached = json_decode($cache->value, true);
                if ($cached && isset($cached['valid']) && isset($cached['time'])) {
                    if (time() - $cached['time'] < 86400) {
                        return $cached['valid'];
                    }
                }
            }

            // Validate against API
            $ip = $this->getServerIp();
            $response = @file_get_contents(self::LICENSE_API . '/api/index', false, stream_context_create([
                'http' => [
                    'method' => 'POST',
                    'header' => 'Content-Type: application/json',
                    'content' => json_encode(['_endpoint' => 'license', 'action' => 'validate', 'key' => $key->value, 'ip' => $ip]),
                    'timeout' => 5,
                ],
                'ssl' => ['verify_peer' => false],
            ]));

            if ($response) {
                $data = json_decode($response, true);
                $valid = $data['valid'] ?? false;
                DB::table('settings')->updateOrCreate(
                    ['key' => 'vexythemes_license_cache'],
                    ['value' => json_encode(['valid' => $valid, 'time' => time(), 'ip' => $ip])]
                );
                return $valid;
            }

            return false;
        } catch (\Throwable $e) {
            return false;
        }
    }

    private function getServerIp(): string
    {
        try {
            $ip = @file_get_contents('https://api.ipify.org', false, stream_context_create(['http' => ['timeout' => 3]]));
            if ($ip) return trim($ip);
        } catch (\Throwable $e) {}
        
        return $_SERVER['SERVER_ADDR'] ?? '127.0.0.1';
    }
}
