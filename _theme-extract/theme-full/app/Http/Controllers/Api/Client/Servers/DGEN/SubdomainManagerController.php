<?php

namespace Pterodactyl\Http\Controllers\Api\Client\Servers\DGEN;

use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Pterodactyl\Http\Controllers\Controller;
use Pterodactyl\Models\Server;
use Pterodactyl\Models\ServerSubdomain;

class SubdomainManagerController extends Controller
{
    public function testConnection(Request $request, Server $server): JsonResponse
    {
        try {
            $settings = $this->getCloudflareSettings();

            if (empty($settings['api_token']) || empty($settings['zone_id'])) {
                return response()->json(['success' => false, 'error' => 'Cloudflare credentials not configured'], 422);
            }

            $response = Http::withToken($settings['api_token'])
                ->get("https://api.cloudflare.com/client/v4/zones/{$settings['zone_id']}");

            if ($response->successful()) {
                return response()->json(['success' => true, 'zone_name' => $response->json('result.name', '')]);
            }

            return response()->json(['success' => false, 'error' => 'Connection failed: ' . $response->json('errors.0.message', 'Unknown error')], 422);
        } catch (\Exception $e) {
            return response()->json(['success' => false, 'error' => 'Connection test failed: ' . $e->getMessage()], 500);
        }
    }

    public function fetchDomains(Request $request, Server $server): JsonResponse
    {
        try {
            $settings = $this->getCloudflareSettings();

            $response = Http::withToken($settings['api_token'])
                ->get("https://api.cloudflare.com/client/v4/zones/{$settings['zone_id']}/dns_records", [
                    'type' => 'A',
                    'per_page' => 100,
                ]);

            if ($response->successful()) {
                $domains = collect($response->json('result', []))->pluck('name')->unique()->values()->toArray();
                return response()->json(['domains' => $domains]);
            }

            return response()->json(['domains' => []]);
        } catch (\Exception $e) {
            return response()->json(['domains' => []], 500);
        }
    }

    public function fetchAllSubdomains(Request $request, Server $server): JsonResponse
    {
        try {
            $subdomains = ServerSubdomain::where('server_id', $server->id)->get();
            return response()->json(['subdomains' => $subdomains]);
        } catch (\Exception $e) {
            return response()->json(['subdomains' => []], 500);
        }
    }

    public function deleteSubdomainAdmin(Request $request, Server $server): JsonResponse
    {
        $request->validate(['subdomain_id' => 'required|integer|exists:server_subdomains,id']);

        try {
            $subdomain = ServerSubdomain::where('server_id', $server->id)->findOrFail($request->input('subdomain_id'));

            $settings = $this->getCloudflareSettings();
            if (!empty($settings['api_token']) && !empty($subdomain->cf_record_id)) {
                Http::withToken($settings['api_token'])
                    ->delete("https://api.cloudflare.com/client/v4/zones/{$settings['zone_id']}/dns_records/{$subdomain->cf_record_id}");
            }

            $subdomain->delete();

            return response()->json(['success' => true]);
        } catch (\Exception $e) {
            return response()->json(['error' => 'Failed to delete subdomain: ' . $e->getMessage()], 500);
        }
    }

    public function index(Request $request, Server $server): JsonResponse
    {
        try {
            $subdomains = ServerSubdomain::where('server_id', $server->id)->get();
            return response()->json(['subdomains' => $subdomains]);
        } catch (\Exception $e) {
            return response()->json(['subdomains' => []], 500);
        }
    }

    public function store(Request $request, Server $server): JsonResponse
    {
        $request->validate([
            'subdomain' => 'required|string|max:63|regex:/^[a-zA-Z0-9][a-zA-Z0-9\-]*$/',
            'domain' => 'required|string|max:191',
        ]);

        try {
            $settings = $this->getCloudflareSettings();
            $allocation = $server->allocation;
            $ipAddress = $allocation?->ip ?? '0.0.0.0';

            $fullDomain = $request->input('subdomain') . '.' . $request->input('domain');

            $cfRecordId = null;
            if (!empty($settings['api_token']) && !empty($settings['zone_id'])) {
                $response = Http::withToken($settings['api_token'])
                    ->post("https://api.cloudflare.com/client/v4/zones/{$settings['zone_id']}/dns_records", [
                        'type' => 'A',
                        'name' => $fullDomain,
                        'content' => $ipAddress,
                        'ttl' => 1,
                        'proxied' => false,
                    ]);

                if ($response->successful()) {
                    $cfRecordId = $response->json('result.id');
                }
            }

            $subdomain = ServerSubdomain::create([
                'server_id' => $server->id,
                'subdomain' => $request->input('subdomain'),
                'domain' => $request->input('domain'),
                'ip_address' => $ipAddress,
                'cf_record_id' => $cfRecordId,
            ]);

            return response()->json(['success' => true, 'subdomain' => $subdomain]);
        } catch (\Exception $e) {
            return response()->json(['error' => 'Failed to create subdomain: ' . $e->getMessage()], 500);
        }
    }

    public function checkAvailability(Request $request, Server $server): JsonResponse
    {
        $request->validate([
            'subdomain' => 'required|string|max:63',
            'domain' => 'required|string|max:191',
        ]);

        try {
            $fullDomain = $request->input('subdomain') . '.' . $request->input('domain');
            $exists = ServerSubdomain::where('subdomain', $request->input('subdomain'))
                ->where('domain', $request->input('domain'))
                ->where('server_id', '!=', $server->id)
                ->exists();

            return response()->json(['available' => !$exists, 'domain' => $fullDomain]);
        } catch (\Exception $e) {
            return response()->json(['available' => false, 'error' => $e->getMessage()], 500);
        }
    }

    public function destroy(Request $request, Server $server): JsonResponse
    {
        $request->validate(['subdomain_id' => 'required|integer']);

        try {
            $subdomain = ServerSubdomain::where('server_id', $server->id)->findOrFail($request->input('subdomain_id'));

            $settings = $this->getCloudflareSettings();
            if (!empty($settings['api_token']) && !empty($subdomain->cf_record_id)) {
                Http::withToken($settings['api_token'])
                    ->delete("https://api.cloudflare.com/client/v4/zones/{$settings['zone_id']}/dns_records/{$subdomain->cf_record_id}");
            }

            $subdomain->delete();

            return response()->json(['success' => true]);
        } catch (\Exception $e) {
            return response()->json(['error' => 'Failed to delete subdomain: ' . $e->getMessage()], 500);
        }
    }

    private function getCloudflareSettings(): array
    {
        return Cache::remember('cloudflare_settings', 3600, function () {
            return [
                'api_token' => config('dgen.cloudflare.api_token', ''),
                'zone_id' => config('dgen.cloudflare.zone_id', ''),
            ];
        });
    }
}
