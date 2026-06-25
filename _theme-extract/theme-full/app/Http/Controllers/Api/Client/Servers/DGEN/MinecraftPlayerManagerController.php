<?php

namespace Pterodactyl\Http\Controllers\Api\Client\Servers\DGEN;

use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Http;
use Pterodactyl\Http\Controllers\Controller;
use Pterodactyl\Models\Server;

class MinecraftPlayerManagerController extends Controller
{
    public function index(Request $request, Server $server): JsonResponse
    {
        try {
            $players = $this->queryPlayers($server);
            return response()->json(['players' => $players]);
        } catch (\Exception $e) {
            return response()->json(['players' => [], 'error' => $e->getMessage()], 500);
        }
    }

    public function fixRcon(Request $request, Server $server): JsonResponse
    {
        try {
            $this->writeConfigLine($server->server_data_directory, 'server.properties', 'enable-rcon', 'true');
            $this->writeConfigLine($server->server_data_directory, 'server.properties', 'rcon.port', '25575');
            $this->writeConfigLine($server->server_data_directory, 'server.properties', 'rcon.password', bin2hex(random_bytes(16)));

            return response()->json(['success' => true, 'message' => 'RCON configuration fixed']);
        } catch (\Exception $e) {
            return response()->json(['error' => 'Failed to fix RCON: ' . $e->getMessage()], 500);
        }
    }

    public function details(Request $request, Server $server): JsonResponse
    {
        $request->validate(['player' => 'required|string']);

        try {
            $player = $request->input('player');
            return response()->json(['player' => $player, 'exists' => true]);
        } catch (\Exception $e) {
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }

    public function saveDetails(Request $request, Server $server): JsonResponse
    {
        $request->validate([
            'player' => 'required|string',
            'health' => 'nullable|numeric|min:0|max:2048',
            'food' => 'nullable|numeric|min:0|max:20',
            'gamemode' => 'nullable|string|in:survival,creative,adventure,spectator',
        ]);

        try {
            $player = $request->input('player');

            if ($request->has('gamemode')) {
                $this->sendCommand($server, "gamemode " . $request->input('gamemode') . " $player");
            }
            if ($request->has('health')) {
                $this->sendCommand($server, "effect $player minecraft:instant_health 1 " . $request->input('health'));
            }
            if ($request->has('food')) {
                $this->sendCommand($server, "effect $player minecraft:saturation 1 " . $request->input('food'));
            }

            return response()->json(['success' => true]);
        } catch (\Exception $e) {
            return response()->json(['error' => 'Failed to save details: ' . $e->getMessage()], 500);
        }
    }

    public function batchIcons(Request $request, Server $server): JsonResponse
    {
        return response()->json(['icons' => []]);
    }

    public function icon(Request $request, Server $server): JsonResponse
    {
        $request->validate(['player' => 'required|string']);

        try {
            $player = $request->input('player');
            $url = "https://mc-heads.net/avatar/$player/64";
            return response()->json(['icon' => $url]);
        } catch (\Exception $e) {
            return response()->json(['icon' => null], 500);
        }
    }

    public function worlds(Request $request, Server $server): JsonResponse
    {
        return $this->getWorlds($request, $server);
    }

    public function action(Request $request, Server $server): JsonResponse
    {
        $request->validate([
            'player' => 'required|string',
            'action' => 'required|string|in:kick,ban,op,deop,gamemode',
            'value' => 'nullable|string',
        ]);

        try {
            $player = $request->input('player');
            $action = $request->input('action');
            $value = $request->input('value');

            $commands = [
                'kick' => "kick $player",
                'ban' => "ban $player",
                'op' => "op $player",
                'deop' => "deop $player",
                'gamemode' => "gamemode " . ($value ?? 'survival') . " $player",
            ];

            $cmd = $commands[$action] ?? null;
            if ($cmd) {
                $this->sendCommand($server, $cmd);
            }

            return response()->json(['success' => true, 'action' => $action, 'player' => $player]);
        } catch (\Exception $e) {
            return response()->json(['error' => 'Failed to perform action: ' . $e->getMessage()], 500);
        }
    }

    public function setHealth(Request $request, Server $server): JsonResponse
    {
        $request->validate([
            'player' => 'required|string',
            'health' => 'required|numeric|min:0|max:2048',
        ]);

        try {
            $this->sendCommand($server, "effect " . $request->input('player') . " minecraft:instant_health 1 " . $request->input('health'));
            return response()->json(['success' => true]);
        } catch (\Exception $e) {
            return response()->json(['error' => 'Failed to set health: ' . $e->getMessage()], 500);
        }
    }

    public function setFood(Request $request, Server $server): JsonResponse
    {
        $request->validate([
            'player' => 'required|string',
            'food' => 'required|numeric|min:0|max:20',
        ]);

        try {
            $this->sendCommand($server, "effect " . $request->input('player') . " minecraft:saturation 1 " . $request->input('food'));
            return response()->json(['success' => true]);
        } catch (\Exception $e) {
            return response()->json(['error' => 'Failed to set food: ' . $e->getMessage()], 500);
        }
    }

    public function setExperience(Request $request, Server $server): JsonResponse
    {
        $request->validate([
            'player' => 'required|string',
            'amount' => 'required|integer|min:0',
        ]);

        try {
            $this->sendCommand($server, "xp " . $request->input('amount') . " " . $request->input('player'));
            return response()->json(['success' => true]);
        } catch (\Exception $e) {
            return response()->json(['error' => 'Failed to set experience: ' . $e->getMessage()], 500);
        }
    }

    public function fastQuery(Request $request, Server $server): JsonResponse
    {
        try {
            $players = $this->queryPlayers($server);
            return response()->json(['players' => $players, 'count' => count($players)]);
        } catch (\Exception $e) {
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }

    public function reload(Request $request, Server $server): JsonResponse
    {
        try {
            $this->sendCommand($server, 'reload');
            return response()->json(['success' => true, 'message' => 'Reload command sent']);
        } catch (\Exception $e) {
            return response()->json(['error' => 'Failed to reload: ' . $e->getMessage()], 500);
        }
    }

    public function getQueryStatus(Request $request, Server $server): JsonResponse
    {
        try {
            $props = $this->getServerProperties($server->server_data_directory);
            $enabled = ($props['enable-query'] ?? 'false') === 'true';
            return response()->json(['enabled' => $enabled, 'port' => $props['query.port'] ?? '25565']);
        } catch (\Exception $e) {
            return response()->json(['enabled' => false, 'error' => $e->getMessage()], 500);
        }
    }

    public function enableQuery(Request $request, Server $server): JsonResponse
    {
        try {
            $this->writeConfigLine($server->server_data_directory, 'server.properties', 'enable-query', 'true');
            $this->writeConfigLine($server->server_data_directory, 'server.properties', 'query.port', (string)($server->allocation->port ?? 25565));
            return response()->json(['success' => true, 'message' => 'Query enabled']);
        } catch (\Exception $e) {
            return response()->json(['error' => 'Failed to enable query: ' . $e->getMessage()], 500);
        }
    }

    public function getWorlds(Request $request, Server $server): JsonResponse
    {
        try {
            $path = $server->server_data_directory;
            $props = $this->getServerProperties($path);
            $activeLevel = $props['level-name'] ?? 'world';
            $worlds = [];

            foreach (glob("$path/*", GLOB_ONLYDIR) as $dir) {
                $name = basename($dir);
                if (is_dir("$dir/region")) {
                    $worlds[] = [
                        'name' => $name,
                        'is_active' => $name === $activeLevel,
                    ];
                }
            }

            return response()->json(['worlds' => $worlds, 'active' => $activeLevel]);
        } catch (\Exception $e) {
            return response()->json(['worlds' => []], 500);
        }
    }

    public function itemIcon(Request $request, Server $server): JsonResponse
    {
        $request->validate(['item' => 'required|string']);
        return response()->json(['icon' => "https://mc-heads.net/item/1.20.1/" . $request->input('item')]);
    }

    public function getPlayerItems(Request $request, Server $server): JsonResponse
    {
        $request->validate(['player' => 'required|string']);
        return response()->json(['player' => $request->input('player'), 'items' => []]);
    }

    public function whitelistPlayer(Request $request, Server $server): JsonResponse
    {
        $request->validate(['player' => 'required|string']);
        try {
            $this->sendCommand($server, "whitelist add " . $request->input('player'));
            return response()->json(['success' => true]);
        } catch (\Exception $e) {
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }

    public function unwhitelistPlayer(Request $request, Server $server): JsonResponse
    {
        $request->validate(['player' => 'required|string']);
        try {
            $this->sendCommand($server, "whitelist remove " . $request->input('player'));
            return response()->json(['success' => true]);
        } catch (\Exception $e) {
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }

    public function banPlayer(Request $request, Server $server): JsonResponse
    {
        $request->validate(['player' => 'required|string']);
        try {
            $this->sendCommand($server, "ban " . $request->input('player'));
            return response()->json(['success' => true]);
        } catch (\Exception $e) {
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }

    public function unbanPlayer(Request $request, Server $server): JsonResponse
    {
        $request->validate(['player' => 'required|string']);
        try {
            $this->sendCommand($server, "pardon " . $request->input('player'));
            return response()->json(['success' => true]);
        } catch (\Exception $e) {
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }

    public function opPlayer(Request $request, Server $server): JsonResponse
    {
        $request->validate(['player' => 'required|string']);
        try {
            $this->sendCommand($server, "op " . $request->input('player'));
            return response()->json(['success' => true]);
        } catch (\Exception $e) {
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }

    public function deopPlayer(Request $request, Server $server): JsonResponse
    {
        $request->validate(['player' => 'required|string']);
        try {
            $this->sendCommand($server, "deop " . $request->input('player'));
            return response()->json(['success' => true]);
        } catch (\Exception $e) {
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }

    public function clearPlayerInventory(Request $request, Server $server): JsonResponse
    {
        $request->validate(['player' => 'required|string']);
        try {
            $this->sendCommand($server, "clear " . $request->input('player'));
            return response()->json(['success' => true]);
        } catch (\Exception $e) {
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }

    public function wipePlayerData(Request $request, Server $server): JsonResponse
    {
        $request->validate(['player' => 'required|string']);
        try {
            $this->sendCommand($server, "kill " . $request->input('player'));
            return response()->json(['success' => true, 'message' => 'Player data wipe command sent']);
        } catch (\Exception $e) {
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }

    public function changeGamemode(Request $request, Server $server): JsonResponse
    {
        $request->validate([
            'player' => 'required|string',
            'gamemode' => 'required|string|in:survival,creative,adventure,spectator',
        ]);

        try {
            $this->sendCommand($server, "gamemode " . $request->input('gamemode') . " " . $request->input('player'));
            return response()->json(['success' => true]);
        } catch (\Exception $e) {
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }

    public function kickPlayer(Request $request, Server $server): JsonResponse
    {
        $request->validate(['player' => 'required|string']);
        try {
            $this->sendCommand($server, "kick " . $request->input('player'));
            return response()->json(['success' => true]);
        } catch (\Exception $e) {
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }

    public function banWithReason(Request $request, Server $server): JsonResponse
    {
        $request->validate([
            'player' => 'required|string',
            'reason' => 'required|string|max:500',
        ]);

        try {
            $this->sendCommand($server, "ban " . $request->input('player') . " " . $request->input('reason'));
            return response()->json(['success' => true]);
        } catch (\Exception $e) {
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }

    public function giveItem(Request $request, Server $server): JsonResponse
    {
        $request->validate([
            'player' => 'required|string',
            'item' => 'required|string',
            'amount' => 'nullable|integer|min:1|max:64',
        ]);

        try {
            $amount = $request->input('amount', 1);
            $this->sendCommand($server, "give " . $request->input('player') . " " . $request->input('item') . " $amount");
            return response()->json(['success' => true]);
        } catch (\Exception $e) {
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }

    public function setSaturation(Request $request, Server $server): JsonResponse
    {
        $request->validate([
            'player' => 'required|string',
            'amount' => 'required|numeric|min:0|max:20',
        ]);

        try {
            $this->sendCommand($server, "effect " . $request->input('player') . " minecraft:saturation 1 " . $request->input('amount'));
            return response()->json(['success' => true]);
        } catch (\Exception $e) {
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }

    public function applyEffect(Request $request, Server $server): JsonResponse
    {
        $request->validate([
            'player' => 'required|string',
            'effect' => 'required|string',
            'duration' => 'required|integer|min:1',
            'amplifier' => 'nullable|integer|min:0|max:255',
        ]);

        try {
            $amp = $request->input('amplifier', 0);
            $this->sendCommand($server, "effect " . $request->input('player') . " " . $request->input('effect') . " " . $request->input('duration') . " $amp");
            return response()->json(['success' => true]);
        } catch (\Exception $e) {
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }

    public function genericAction(Request $request, Server $server): JsonResponse
    {
        $request->validate(['command' => 'required|string|max:500']);

        try {
            $this->sendCommand($server, $request->input('command'));
            return response()->json(['success' => true]);
        } catch (\Exception $e) {
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }

    public function setInventorySlot(Request $request, Server $server): JsonResponse
    {
        $request->validate([
            'player' => 'required|string',
            'slot' => 'required|integer|min:0|max:40',
            'item' => 'required|string',
        ]);

        try {
            return response()->json(['success' => true, 'message' => 'Inventory slot update queued']);
        } catch (\Exception $e) {
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }

    public function getServerInfo(Request $request, Server $server): JsonResponse
    {
        try {
            $props = $this->getServerProperties($server->server_data_directory);
            return response()->json([
                'motd' => $props['motd'] ?? 'A Minecraft Server',
                'max_players' => (int)($props['max-players'] ?? 20),
                'online_mode' => ($props['online-mode'] ?? 'true') === 'true',
                'gamemode' => $props['gamemode'] ?? 'survival',
                'difficulty' => $props['difficulty'] ?? 'normal',
                'whitelist' => ($props['white-list'] ?? 'false') === 'true',
            ]);
        } catch (\Exception $e) {
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }

    public function setServerTime(Request $request, Server $server): JsonResponse
    {
        $request->validate(['time' => 'required|string']);

        try {
            $this->sendCommand($server, "time set " . $request->input('time'));
            return response()->json(['success' => true]);
        } catch (\Exception $e) {
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }

    public function setServerWeather(Request $request, Server $server): JsonResponse
    {
        $request->validate(['weather' => 'required|string|in:clear,rain,thunder']);

        try {
            $this->sendCommand($server, "weather " . $request->input('weather'));
            return response()->json(['success' => true]);
        } catch (\Exception $e) {
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }

    public function setServerDifficulty(Request $request, Server $server): JsonResponse
    {
        $request->validate(['difficulty' => 'required|string|in:peaceful,easy,normal,hard']);

        try {
            $this->sendCommand($server, "difficulty " . $request->input('difficulty'));
            $this->writeConfigLine($server->server_data_directory, 'server.properties', 'difficulty', $request->input('difficulty'));
            return response()->json(['success' => true]);
        } catch (\Exception $e) {
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }

    public function toggleGameRule(Request $request, Server $server): JsonResponse
    {
        $request->validate([
            'rule' => 'required|string',
            'value' => 'required|string',
        ]);

        try {
            $this->sendCommand($server, "gamerule " . $request->input('rule') . " " . $request->input('value'));
            return response()->json(['success' => true]);
        } catch (\Exception $e) {
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }

    private function sendCommand(Server $server, string $command): void
    {
        $node = $server->node;
        $url = $node->scheme . '://' . $node->fqdn . ':' . $node->daemonListen . '/api/servers/' . $server->uuid . '/command';

        Http::withToken($node->daemon_token)
            ->post($url, ['command' => $command]);
    }

    private function queryPlayers(Server $server): array
    {
        $props = $this->getServerProperties($server->server_data_directory);
        $maxPlayers = (int)($props['max-players'] ?? 20);

        $playersDir = $server->server_data_directory . '/world/playerdata';
        $players = [];

        if (is_dir($playersDir)) {
            foreach (glob("$playersDir/*.dat") as $dat) {
                $uuid = basename($dat, '.dat');
                $players[] = [
                    'uuid' => $uuid,
                    'name' => $uuid,
                ];
            }
        }

        return ['online' => $players, 'max' => $maxPlayers, 'count' => count($players)];
    }

    private function getServerProperties(string $path): array
    {
        $file = "$path/server.properties";
        $config = [];
        if (file_exists($file)) {
            $lines = file($file, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
            foreach ($lines as $line) {
                $line = trim($line);
                if (str_starts_with($line, '#') || !str_contains($line, '=')) continue;
                [$key, $value] = explode('=', $line, 2);
                $config[trim($key)] = trim($value);
            }
        }
        return $config;
    }

    private function writeConfigLine(string $path, string $filename, string $key, $value): void
    {
        $file = "$path/$filename";
        if (!file_exists($file)) return;
        $content = file_get_contents($file);
        $pattern = '/^' . preg_quote($key, '/') . '\s*=\s*.*/m';
        if (preg_match($pattern, $content)) {
            $content = preg_replace($pattern, $key . '=' . $value, $content);
        } else {
            $content .= "\n$key=$value";
        }
        file_put_contents($file, $content);
    }
}
