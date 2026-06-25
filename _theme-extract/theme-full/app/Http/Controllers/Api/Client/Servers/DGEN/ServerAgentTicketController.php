<?php

namespace Pterodactyl\Http\Controllers\Api\Client\Servers\DGEN;

use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Http;
use Pterodactyl\Http\Controllers\Controller;
use Pterodactyl\Models\Server;

class ServerAgentTicketController extends Controller
{
    public function ticket(Request $request, Server $server): JsonResponse
    {
        $request->validate([
            'subject' => 'required|string|max:191',
            'message' => 'required|string|max:5000',
            'priority' => 'sometimes|string|in:low,medium,high',
        ]);

        try {
            $node = $server->node;

            $ticketData = [
                'server_id' => $server->id,
                'server_name' => $server->name,
                'node_name' => $node->name,
                'user_id' => $request->user()->id,
                'user_email' => $request->user()->email,
                'subject' => $request->input('subject'),
                'message' => $request->input('message'),
                'priority' => $request->input('priority', 'medium'),
                'server_status' => $server->status,
                'created_at' => now()->toDateTimeString(),
            ];

            $webhookUrl = config('dgen.agent_ticket_webhook');
            if ($webhookUrl) {
                Http::post($webhookUrl, $ticketData);
            }

            return response()->json([
                'success' => true,
                'message' => 'Support ticket submitted',
                'ticket' => [
                    'id' => 'TICKET-' . strtoupper(uniqid()),
                    'subject' => $request->input('subject'),
                    'status' => 'open',
                    'priority' => $request->input('priority', 'medium'),
                ],
            ]);
        } catch (\Exception $e) {
            return response()->json(['error' => 'Failed to submit ticket: ' . $e->getMessage()], 500);
        }
    }
}
