<?php

namespace Pterodactyl\Http\Controllers\Api\Client\Servers\DGEN;

use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Pterodactyl\Http\Controllers\Controller;
use Pterodactyl\Models\Server;

class FirewallManagerController extends Controller
{
    public function allocations(Request $request, Server $server): JsonResponse
    {
        try {
            $allocations = $server->allocations()->get()->map(function ($alloc) {
                return [
                    'id' => $alloc->id,
                    'ip' => $alloc->ip,
                    'port' => $alloc->port,
                    'firewall_enabled' => true,
                ];
            });

            return response()->json(['allocations' => $allocations]);
        } catch (\Exception $e) {
            return response()->json(['allocations' => []], 500);
        }
    }

    public function rules(Request $request, Server $server): JsonResponse
    {
        try {
            $rulesFile = $server->server_data_directory . '/.firewall_rules.json';
            $rules = [];

            if (file_exists($rulesFile)) {
                $rules = json_decode(file_get_contents($rulesFile), true) ?? [];
            }

            return response()->json(['rules' => $rules]);
        } catch (\Exception $e) {
            return response()->json(['rules' => [], 'error' => $e->getMessage()], 500);
        }
    }

    public function addRule(Request $request, Server $server): JsonResponse
    {
        $request->validate([
            'port' => 'required|integer|min:1|max:65535',
            'protocol' => 'required|string|in:tcp,udp,both',
            'action' => 'required|string|in:allow,deny',
            'ip_range' => 'nullable|string|max:191',
            'description' => 'nullable|string|max:255',
        ]);

        try {
            $rulesFile = $server->server_data_directory . '/.firewall_rules.json';
            $rules = file_exists($rulesFile) ? json_decode(file_get_contents($rulesFile), true) ?? [] : [];

            $rule = [
                'id' => count($rules) + 1,
                'port' => $request->input('port'),
                'protocol' => $request->input('protocol'),
                'action' => $request->input('action'),
                'ip_range' => $request->input('ip_range', '*'),
                'description' => $request->input('description', ''),
                'created_at' => now()->toDateTimeString(),
            ];

            $rules[] = $rule;
            file_put_contents($rulesFile, json_encode($rules, JSON_PRETTY_PRINT));

            return response()->json(['success' => true, 'rule' => $rule]);
        } catch (\Exception $e) {
            return response()->json(['error' => 'Failed to add rule: ' . $e->getMessage()], 500);
        }
    }

    public function deleteRule(Request $request, Server $server): JsonResponse
    {
        $request->validate(['rule_id' => 'required|integer']);

        try {
            $rulesFile = $server->server_data_directory . '/.firewall_rules.json';

            if (!file_exists($rulesFile)) {
                return response()->json(['error' => 'No rules found'], 422);
            }

            $rules = json_decode(file_get_contents($rulesFile), true) ?? [];
            $ruleId = $request->input('rule_id');
            $rules = array_filter($rules, fn($r) => $r['id'] !== $ruleId);
            $rules = array_values($rules);

            file_put_contents($rulesFile, json_encode($rules, JSON_PRETTY_PRINT));

            return response()->json(['success' => true]);
        } catch (\Exception $e) {
            return response()->json(['error' => 'Failed to delete rule: ' . $e->getMessage()], 500);
        }
    }

    public function resetPort(Request $request, Server $server): JsonResponse
    {
        $request->validate(['port' => 'required|integer']);

        try {
            $rulesFile = $server->server_data_directory . '/.firewall_rules.json';

            if (file_exists($rulesFile)) {
                $rules = json_decode(file_get_contents($rulesFile), true) ?? [];
                $port = $request->input('port');
                $rules = array_filter($rules, fn($r) => $r['port'] !== $port);
                $rules = array_values($rules);
                file_put_contents($rulesFile, json_encode($rules, JSON_PRETTY_PRINT));
            }

            return response()->json(['success' => true, 'message' => "Rules for port {$request->input('port')} reset"]);
        } catch (\Exception $e) {
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }
}
