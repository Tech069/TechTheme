<?php

namespace Pterodactyl\Console\Commands\DGEN;

use Carbon\CarbonImmutable;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class SeedDdosDemoCommand extends Command
{
    protected $signature = 'dgen:ddos:seed-demo
        {--count=10 : Number of demo alert events to create}
        {--clear : Clear existing demo data before seeding}';

    protected $description = 'Seed demo DDoS alert event data for testing and development.';

    private const ATTACK_STATUSES = ['mitigating', 'resolved', 'detected', 'unknown'];
    private const ATTACK_REASONS = [
        'SYN flood attack detected',
        'UDP amplification attack',
        'HTTP/2 rapid reset attack',
        'DNS amplification detected',
        'Carpet bombing attack pattern',
        'Slowloris connection exhaustion',
        'Volumetric L3/L4 attack',
        'Application layer L7 attack',
    ];
    private const TARGET_HOSTS = [
        'mc.example.com',
        'rust.example.com',
        'valheim.example.com',
        'terraria.example.com',
    ];

    public function handle(): int
    {
        $count = (int) $this->option('count');

        if ($count <= 0) {
            $this->error('Count must be a positive integer.');

            return 1;
        }

        if ($this->option('clear')) {
            $deleted = DB::table('ddos_alert_events')
                ->where('attack_hash', 'like', 'demo_%')
                ->delete();

            $this->info("Cleared $deleted existing demo record(s).");
        }

        $this->info("Seeding $count demo DDoS alert event(s)...");
        $this->newLine();

        $createdCount = 0;

        for ($i = 0; $i < $count; $i++) {
            $data = $this->generateDemoEvent($i);

            try {
                DB::table('ddos_alert_events')->insert($data);
                $createdCount++;

                $this->line("  <info>Created</info>: {$data['attack_hash']} — {$data['host']}");
            } catch (\Exception $e) {
                $this->error("  Failed to create event: " . $e->getMessage());
                Log::error('DDoS demo seed failed', ['error' => $e->getMessage()]);
            }
        }

        $this->newLine();
        $this->info("Seeded $createdCount demo DDoS alert event(s).");

        return 0;
    }

    private function generateDemoEvent(int $index): array
    {
        $host = self::TARGET_HOSTS[array_rand(self::TARGET_HOSTS)];
        $status = self::ATTACK_STATUSES[array_rand(self::ATTACK_STATUSES)];
        $reason = self::ATTACK_REASONS[array_rand(self::ATTACK_REASONS)];

        $startedAt = CarbonImmutable::now()->subMinutes(rand(5, 2880));
        $firstSeenAt = $startedAt->addSeconds(rand(0, 30));
        $peakBps = round(rand(1000000, 5000000000) / 1000000, 2) * 1000000;
        $peakPps = rand(100000, 50000000);

        $endedAt = null;
        $lastSeenAt = $firstSeenAt->addSeconds(rand(60, 7200));

        if ($status === 'resolved') {
            $endedAt = $lastSeenAt;
        }

        return [
            'attack_hash' => 'demo_' . strtoupper(bin2hex(random_bytes(16))),
            'host' => $host,
            'status' => $status,
            'reason' => $reason,
            'peak_bps' => $peakBps,
            'peak_pps' => $peakPps,
            'started_at' => $startedAt,
            'ended_at' => $endedAt,
            'first_seen_at' => $firstSeenAt,
            'last_seen_at' => $lastSeenAt,
            'last_notified_at' => null,
            'raw_payload' => json_encode([
                'source' => 'demo_seeder',
                'attack_vector' => ['type' => 'layer4', 'protocol' => 'udp'],
                'geo_distribution' => ['US' => 40, 'CN' => 25, 'RU' => 20, 'Other' => 15],
            ]),
            'created_at' => CarbonImmutable::now(),
            'updated_at' => CarbonImmutable::now(),
        ];
    }
}
