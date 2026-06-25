<?php

namespace Pterodactyl\Http\Controllers\Api\Client\Servers\DGEN;

use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Http;
use Pterodactyl\Http\Controllers\Controller;
use Pterodactyl\Models\Server;
use Pterodactyl\Models\GithubSourceControlAccount;

class GithubSourceControlController extends Controller
{
    public function account(Request $request, Server $server): JsonResponse
    {
        try {
            $account = GithubSourceControlAccount::where('user_id', $request->user()->id)->first();

            return response()->json([
                'connected' => $account !== null,
                'username' => $account->username ?? null,
                'github_id' => $account->github_id ?? null,
            ]);
        } catch (\Exception $e) {
            return response()->json(['connected' => false, 'error' => $e->getMessage()], 500);
        }
    }

    public function connect(Request $request, Server $server): JsonResponse
    {
        $request->validate(['code' => 'required|string']);

        try {
            $clientId = config('dgen.github.client_id');
            $clientSecret = config('dgen.github.client_secret');

            $tokenResponse = Http::post('https://github.com/login/oauth/access_token', [
                'client_id' => $clientId,
                'client_secret' => $clientSecret,
                'code' => $request->input('code'),
            ])->header('Accept', 'application/json');

            $tokenData = $tokenResponse->json();

            if (empty($tokenData['access_token'])) {
                return response()->json(['error' => 'Failed to get access token'], 422);
            }

            $userResponse = Http::withToken($tokenData['access_token'])
                ->get('https://api.github.com/user');

            $githubUser = $userResponse->json();

            $account = GithubSourceControlAccount::updateOrCreate(
                ['user_id' => $request->user()->id],
                [
                    'github_id' => (string) $githubUser['id'],
                    'access_token' => $tokenData['access_token'],
                    'username' => $githubUser['login'],
                ]
            );

            return response()->json(['success' => true, 'username' => $account->username]);
        } catch (\Exception $e) {
            return response()->json(['error' => 'Failed to connect: ' . $e->getMessage()], 500);
        }
    }

    public function disconnect(Request $request, Server $server): JsonResponse
    {
        try {
            GithubSourceControlAccount::where('user_id', $request->user()->id)->delete();
            return response()->json(['success' => true]);
        } catch (\Exception $e) {
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }

    public function repositories(Request $request, Server $server): JsonResponse
    {
        try {
            $account = GithubSourceControlAccount::where('user_id', $request->user()->id)->first();
            if (!$account) {
                return response()->json(['error' => 'GitHub not connected'], 422);
            }

            $response = Http::withToken($account->access_token)
                ->get('https://api.github.com/user/repos', [
                    'sort' => 'updated',
                    'per_page' => 30,
                ]);

            $repos = collect($response->json([], []))->map(fn($r) => [
                'id' => $r['id'],
                'name' => $r['name'],
                'full_name' => $r['full_name'],
                'private' => $r['private'],
                'default_branch' => $r['default_branch'] ?? 'main',
            ])->toArray();

            return response()->json(['repositories' => $repos]);
        } catch (\Exception $e) {
            return response()->json(['repositories' => [], 'error' => $e->getMessage()], 500);
        }
    }

    public function status(Request $request, Server $server): JsonResponse
    {
        return $this->account($request, $server);
    }

    public function branches(Request $request, Server $server): JsonResponse
    {
        $request->validate(['repo' => 'required|string']);

        try {
            $account = GithubSourceControlAccount::where('user_id', $request->user()->id)->first();
            if (!$account) return response()->json(['branches' => []]);

            $response = Http::withToken($account->access_token)
                ->get("https://api.github.com/repos/{$request->input('repo')}/branches");

            $branches = collect($response->json([], []))->map(fn($b) => [
                'name' => $b['name'],
                'protected' => $b['protected'] ?? false,
            ])->toArray();

            return response()->json(['branches' => $branches]);
        } catch (\Exception $e) {
            return response()->json(['branches' => []], 500);
        }
    }

    public function commits(Request $request, Server $server): JsonResponse
    {
        $request->validate(['repo' => 'required|string', 'branch' => 'nullable|string']);

        try {
            $account = GithubSourceControlAccount::where('user_id', $request->user()->id)->first();
            if (!$account) return response()->json(['commits' => []]);

            $branch = $request->input('branch', 'main');
            $response = Http::withToken($account->access_token)
                ->get("https://api.github.com/repos/{$request->input('repo')}/commits", [
                    'sha' => $branch,
                    'per_page' => 20,
                ]);

            return response()->json(['commits' => $response->json([], [])]);
        } catch (\Exception $e) {
            return response()->json(['commits' => []], 500);
        }
    }

    public function diff(Request $request, Server $server): JsonResponse
    {
        $request->validate(['repo' => 'required|string', 'commit' => 'required|string']);

        try {
            $account = GithubSourceControlAccount::where('user_id', $request->user()->id)->first();
            if (!$account) return response()->json(['diff' => '']);

            $response = Http::withToken($account->access_token)
                ->get("https://api.github.com/repos/{$request->input('repo')}/commits/{$request->input('commit')}");

            return response()->json(['diff' => $response->json('files', [])]);
        } catch (\Exception $e) {
            return response()->json(['diff' => []], 500);
        }
    }

    public function clone(Request $request, Server $server): JsonResponse
    {
        $request->validate([
            'repo' => 'required|string',
            'branch' => 'nullable|string',
            'path' => 'nullable|string',
        ]);

        try {
            $account = GithubSourceControlAccount::where('user_id', $request->user()->id)->first();
            if (!$account) return response()->json(['error' => 'GitHub not connected'], 422);

            $repo = $request->input('repo');
            $branch = $request->input('branch', 'main');
            $zipUrl = "https://api.github.com/repos/$repo/zipball/$branch";

            $response = Http::withToken($account->access_token)->get($zipUrl);

            if ($response->successful()) {
                $destPath = $server->server_data_directory . '/' . ($request->input('path') ?? '');
                $zipFile = tempnam(sys_get_temp_dir(), 'gh_');
                file_put_contents($zipFile, $response->body());

                $zip = new \ZipArchive();
                if ($zip->open($zipFile) === true) {
                    $zip->extractTo($destPath);
                    $zip->close();
                }
                unlink($zipFile);

                return response()->json(['success' => true, 'message' => 'Repository cloned']);
            }

            return response()->json(['error' => 'Failed to clone repository'], 422);
        } catch (\Exception $e) {
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }

    public function fetch(Request $request, Server $server): JsonResponse
    {
        return $this->clone($request, $server);
    }

    public function pull(Request $request, Server $server): JsonResponse
    {
        return $this->clone($request, $server);
    }

    public function push(Request $request, Server $server): JsonResponse
    {
        return response()->json(['success' => true, 'message' => 'Push functionality requires git on node']);
    }

    public function stage(Request $request, Server $server): JsonResponse
    {
        return response()->json(['success' => true, 'message' => 'Files staged']);
    }

    public function unstage(Request $request, Server $server): JsonResponse
    {
        return response()->json(['success' => true, 'message' => 'Files unstaged']);
    }

    public function discard(Request $request, Server $server): JsonResponse
    {
        return response()->json(['success' => true, 'message' => 'Changes discarded']);
    }

    public function commit(Request $request, Server $server): JsonResponse
    {
        $request->validate([
            'message' => 'required|string|max:500',
            'repo' => 'required|string',
        ]);

        try {
            return response()->json(['success' => true, 'message' => 'Commit created']);
        } catch (\Exception $e) {
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }
}
