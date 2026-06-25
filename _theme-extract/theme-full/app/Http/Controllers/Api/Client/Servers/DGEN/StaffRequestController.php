<?php

namespace Pterodactyl\Http\Controllers\Api\Client\Servers\DGEN;

use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Pterodactyl\Http\Controllers\Controller;
use Pterodactyl\Models\Server;
use Pterodactyl\Models\StaffRequest;

class StaffRequestController extends Controller
{
    public function index(Request $request, Server $server): JsonResponse
    {
        try {
            $requests = StaffRequest::where('server_id', $server->id)
                ->orderBy('created_at', 'desc')
                ->get();

            return response()->json(['requests' => $requests]);
        } catch (\Exception $e) {
            return response()->json(['requests' => [], 'error' => $e->getMessage()], 500);
        }
    }

    public function count(Request $request, Server $server): JsonResponse
    {
        try {
            $count = StaffRequest::where('server_id', $server->id)->count();
            return response()->json(['count' => $count]);
        } catch (\Exception $e) {
            return response()->json(['count' => 0], 500);
        }
    }

    public function ownerRequests(Request $request, Server $server): JsonResponse
    {
        try {
            $requests = StaffRequest::where('user_id', $request->user()->id)
                ->orderBy('created_at', 'desc')
                ->get();

            return response()->json(['requests' => $requests]);
        } catch (\Exception $e) {
            return response()->json(['requests' => []], 500);
        }
    }

    public function store(Request $request, Server $server): JsonResponse
    {
        $request->validate([
            'subject' => 'required|string|max:191',
            'message' => 'required|string|max:5000',
            'priority' => 'required|string|in:low,medium,high,urgent',
        ]);

        try {
            $staffRequest = StaffRequest::create([
                'server_id' => $server->id,
                'user_id' => $request->user()->id,
                'subject' => $request->input('subject'),
                'message' => $request->input('message'),
                'status' => 'pending',
                'priority' => $request->input('priority'),
            ]);

            return response()->json(['success' => true, 'request' => $staffRequest]);
        } catch (\Exception $e) {
            return response()->json(['error' => 'Failed to create request: ' . $e->getMessage()], 500);
        }
    }

    public function accept(Request $request, Server $server): JsonResponse
    {
        $request->validate(['request_id' => 'required|integer|exists:staff_requests,id']);

        try {
            $staffRequest = StaffRequest::where('server_id', $server->id)->findOrFail($request->input('request_id'));
            $staffRequest->update(['status' => 'accepted']);

            return response()->json(['success' => true]);
        } catch (\Exception $e) {
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }

    public function reject(Request $request, Server $server): JsonResponse
    {
        $request->validate(['request_id' => 'required|integer|exists:staff_requests,id']);

        try {
            $staffRequest = StaffRequest::where('server_id', $server->id)->findOrFail($request->input('request_id'));
            $staffRequest->update(['status' => 'rejected']);

            return response()->json(['success' => true]);
        } catch (\Exception $e) {
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }

    public function destroy(Request $request, Server $server): JsonResponse
    {
        $request->validate(['request_id' => 'required|integer|exists:staff_requests,id']);

        try {
            $staffRequest = StaffRequest::where('server_id', $server->id)->findOrFail($request->input('request_id'));
            $staffRequest->delete();

            return response()->json(['success' => true]);
        } catch (\Exception $e) {
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }

    public function autoReject(Request $request, Server $server): JsonResponse
    {
        try {
            StaffRequest::where('server_id', $server->id)
                ->where('status', 'pending')
                ->where('created_at', '<', now()->subDays(30))
                ->update(['status' => 'auto_rejected']);

            return response()->json(['success' => true, 'message' => 'Old pending requests auto-rejected']);
        } catch (\Exception $e) {
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }

    public function searchServers(Request $request, Server $server): JsonResponse
    {
        $request->validate(['query' => 'required|string|min:1']);

        try {
            $query = $request->input('query');
            $servers = Server::where('name', 'LIKE', "%$query%")
                ->limit(10)
                ->get(['id', 'name', 'node_id', 'egg_id']);

            return response()->json(['servers' => $servers]);
        } catch (\Exception $e) {
            return response()->json(['servers' => []], 500);
        }
    }

    public function myServers(Request $request, Server $server): JsonResponse
    {
        try {
            $servers = Server::where('owner_id', $request->user()->id)
                ->get(['id', 'name']);

            return response()->json(['servers' => $servers]);
        } catch (\Exception $e) {
            return response()->json(['servers' => []], 500);
        }
    }

    public function serverRequests(Request $request, Server $server): JsonResponse
    {
        try {
            $requests = StaffRequest::where('server_id', $server->id)
                ->where('status', 'pending')
                ->orderBy('created_at', 'desc')
                ->get();

            return response()->json(['requests' => $requests]);
        } catch (\Exception $e) {
            return response()->json(['requests' => []], 500);
        }
    }

    public function serverPendingCount(Request $request, Server $server): JsonResponse
    {
        try {
            $count = StaffRequest::where('server_id', $server->id)
                ->where('status', 'pending')
                ->count();

            return response()->json(['count' => $count]);
        } catch (\Exception $e) {
            return response()->json(['count' => 0], 500);
        }
    }
}
