<?php

namespace Pterodactyl\Console\Commands\DGEN;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class ArmaReforgerWebhookLogCommand extends Command
{
    protected $signature = 'dgen:arma:webhook-log
        {--webhook-url= : Override webhook URL to send to}
        {--test : Send a test payload instead of reading logs}
        {--follow : Follow log output in real-time}';

    protected $description = 'Monitor and log Arma Reforger webhook events for debugging and auditing.';

    private const WEBHOOK_ENDPOINT = '/api/v1/arma-reforger/webhook';

    public function handle(): int
    {
        $this->output->title('Arma Reforger Webhook Log Monitor');

        if ($this->option('test')) {
            return $this->sendTestPayload();
        }

        return $this->monitorLogs();
    }

    private function sendTestPayload(): int
    {
        $testPayload = [
            'event' => 'server_status',
            'timestamp' => now()->toIso8601String(),
            'server' => [
                'name' => 'Test Arma Reforger Server',
                'port' => 2001,
                'player_count' => 16,
                'max_players' => 64,
                'map' => 'GM_PG7',
                'game_version' => '1.2.3.45',
                'mod_list' => ['GM_Tanoa', 'rhs_afrf3'],
            ],
            'players' => [
                ['name' => 'Player1', 'steam_id' => '76561198000000001'],
                ['name' => 'Player2', 'steam_id' => '76561198000000002'],
            ],
        ];

        $webhookUrl = $this->option('webhook-url') ?? config('dgen arma.webhook_url');

        if (!$webhookUrl) {
            $webhookUrl = config('app.url') . self::WEBHOOK_ENDPOINT;
        }

        $this->info("Sending test payload to: $webhookUrl");
        $this->newLine();

        try {
            $response = Http::timeout(10)
                ->withHeaders(['Content-Type' => 'application/json'])
                ->withBody(json_encode($testPayload), 'application/json')
                ->post($webhookUrl);

            $this->table(
                ['Field', 'Value'],
                [
                    ['Status', $response->status()],
                    ['URL', $webhookUrl],
                    ['Response', substr($response->body(), 0, 500)],
                ]
            );

            if ($response->successful()) {
                $this->info('Test payload delivered successfully.');
                return 0;
            }

            $this->warn('Webhook returned non-success status.');
            return 1;
        } catch (\Exception $e) {
            $this->error('Failed to send test payload: ' . $e->getMessage());
            return 1;
        }
    }

    private function monitorLogs(): int
    {
        $logFile = storage_path('logs/arma-reforger-webhooks.log');

        if (!file_exists($logFile)) {
            $this->warn('No Arma Reforger webhook log file found at: ' . $logFile);
            $this->info('Webhook logs will appear here when events are received.');

            return 0;
        }

        if ($this->option('follow')) {
            return $this->followLog($logFile);
        }

        $lines = file($logFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        $tailCount = 50;
        $recentLines = array_slice($lines, -$tailCount);

        $this->info("Showing last " . count($recentLines) . " webhook log entries:");
        $this->newLine();

        foreach ($recentLines as $line) {
            $this->line("  $line");
        }

        return 0;
    }

    private function followLog(string $logFile): int
    {
        $this->info('Following Arma Reforger webhook logs (Ctrl+C to stop)...');
        $this->newLine();

        $handle = fopen($logFile, 'r');

        if (!$handle) {
            $this->error('Failed to open log file for reading.');

            return 1;
        }

        fseek($handle, 0, SEEK_END);

        try {
            while (true) {
                $line = fgets($handle);

                if ($line !== false) {
                    $this->line("  " . trim($line));
                }

                usleep(250000);
            }
        } catch (\Exception $e) {
            $this->error('Log monitoring interrupted: ' . $e->getMessage());
        } finally {
            fclose($handle);
        }

        return 0;
    }
}
