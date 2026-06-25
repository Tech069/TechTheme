<?php

namespace Pterodactyl\Console\Commands\DGEN;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class TestDdosWebhookCommand extends Command
{
    protected $signature = 'dgen:ddos:test-webhook
        {--webhook-url= : Override webhook URL to test}
        {--type=attack : Test event type (attack, resolved, mitigation)}
        {--host=mc.example.com : Target host for the test event}';

    protected $description = 'Test DDoS webhook delivery by sending a simulated alert payload.';

    private const TEST_PAYLOADS = [
        'attack' => [
            'event' => 'ddos_detected',
            'description' => 'DDoS attack detected',
        ],
        'resolved' => [
            'event' => 'ddos_resolved',
            'description' => 'DDoS attack resolved',
        ],
        'mitigation' => [
            'event' => 'ddos_mitigation_started',
            'description' => 'DDoS mitigation in progress',
        ],
    ];

    public function handle(): int
    {
        $webhookUrl = $this->option('webhook-url') ?? config('dgen.ddos.webhook_url');
        $type = $this->option('type');
        $host = $this->option('host');

        if (!$webhookUrl) {
            $webhookUrl = config('dgen.discord.webhook_url');
        }

        if (!$webhookUrl) {
            $this->error('No webhook URL configured. Set --webhook-url or configure dgen.ddos.webhook_url.');
            return 1;
        }

        if (!array_key_exists($type, self::TEST_PAYLOADS)) {
            $this->error("Invalid type: $type. Available: " . implode(', ', array_keys(self::TEST_PAYLOADS)));
            return 1;
        }

        $payload = $this->buildTestPayload($type, $host);

        $this->info("Testing DDoS webhook ($type)...");
        $this->info("  URL: $webhookUrl");
        $this->newLine();

        $this->line('Payload:');
        $this->line(json_encode($payload, JSON_PRETTY_PRINT));
        $this->newLine();

        if (!$this->confirm('Send this test payload?', true)) {
            $this->info('Cancelled.');
            return 0;
        }

        try {
            $response = Http::timeout(15)
                ->withHeaders(['Content-Type' => 'application/json'])
                ->withBody(json_encode($payload), 'application/json')
                ->post($webhookUrl);

            $this->table(
                ['Field', 'Value'],
                [
                    ['Status Code', $response->status()],
                    ['Successful', $response->successful() ? 'Yes' : 'No'],
                    ['Response Time', $response->transferStats?->getTime()?->convertToMs() . 'ms' ?? 'N/A'],
                    ['Response Body', substr($response->body(), 0, 300)],
                ]
            );

            if ($response->successful()) {
                $this->info('Webhook delivered successfully.');
                return 0;
            }

            $this->warn('Webhook returned a non-success status code.');

            return 1;
        } catch (\Exception $e) {
            $this->error('Failed to send webhook: ' . $e->getMessage());
            Log::error('DDoS webhook test failed', ['error' => $e->getMessage()]);

            return 1;
        }
    }

    private function buildTestPayload(string $type, string $host): array
    {
        $base = self::TEST_PAYLOADS[$type];

        return [
            'event' => $base['event'],
            'attack_hash' => 'test_' . strtoupper(bin2hex(random_bytes(8))),
            'host' => $host,
            'status' => $type === 'resolved' ? 'resolved' : ($type === 'mitigation' ? 'mitigating' : 'detected'),
            'reason' => $base['description'],
            'peak_bps' => 2500000000,
            'peak_pps' => 3500000,
            'started_at' => now()->subMinutes(15)->toIso8601String(),
            'ended_at' => $type === 'resolved' ? now()->toIso8601String() : null,
            'first_seen_at' => now()->subMinutes(15)->toIso8601String(),
            'last_seen_at' => now()->toIso8601String(),
            'raw_payload' => [
                'source' => 'test_webhook_command',
                'attack_vector' => [
                    'type' => 'layer4',
                    'protocol' => 'udp',
                ],
                'geo_distribution' => [
                    'US' => 35,
                    'CN' => 30,
                    'RU' => 20,
                    'BR' => 10,
                    'Other' => 5,
                ],
            ],
            'timestamp' => now()->toIso8601String(),
        ];
    }
}
