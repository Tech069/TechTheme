<?php

namespace Pterodactyl\Console\Commands\DGEN;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use Pterodactyl\Services\DGEN\DiscordBotService;

class RunDiscordBot extends Command
{
    protected $signature = 'dgen:discord:run
        {--webhook-only : Only process webhook events, don\'t start the full bot}
        {--log-level=info : Log level for the bot (debug, info, warning, error)}';

    protected $description = 'Start the DGEN Discord bot for server management and community interaction.';

    public function __construct(private DiscordBotService $discordBotService)
    {
        parent::__construct();
    }

    public function handle(): int
    {
        $this->output->title('DGEN Discord Bot');

        $botToken = config('dgen.discord.bot_token');
        $webhookUrl = config('dgen.discord.webhook_url');

        if (!$botToken && !$this->option('webhook-only')) {
            $this->error('Discord bot token not configured. Set DGEN_DISCORD_BOT_TOKEN in your .env file.');
            return 1;
        }

        if (!$webhookUrl) {
            $this->error('Discord webhook URL not configured. Set DGEN_DISCORD_WEBHOOK_URL in your .env file.');
            return 1;
        }

        $logLevel = $this->option('log-level');

        $this->info('Starting Discord bot...');
        $this->info("  Log Level: $logLevel");
        $this->info("  Webhook URL: " . substr($webhookUrl, 0, 40) . '...');

        if ($this->option('webhook-only')) {
            $this->warn('Running in webhook-only mode. Bot commands will not be processed.');
        }

        $this->newLine();
        $this->info('Press Ctrl+C to stop the bot.');
        $this->newLine();

        Log::info('Discord bot starting', [
            'log_level' => $logLevel,
            'webhook_only' => $this->option('webhook-only'),
        ]);

        try {
            $this->runBotLoop($botToken, $webhookUrl, $logLevel);
        } catch (\Exception $e) {
            Log::error('Discord bot crashed', ['error' => $e->getMessage()]);
            $this->error('Bot crashed: ' . $e->getMessage());

            return 1;
        }

        return 0;
    }

    private function runBotLoop(?string $botToken, string $webhookUrl, string $logLevel): void
    {
        $running = true;

        pcntl_signal(SIGINT, function () use (&$running) {
            $running = false;
        });

        pcntl_signal(SIGTERM, function () use (&$running) {
            $running = false;
        });

        while ($running) {
            pcntl_signal_dispatch();

            try {
                if (!$this->option('webhook-only')) {
                    $this->discordBotService->processEvents();
                }

                $pendingWebhooks = $this->discordBotService->getPendingWebhooks();

                foreach ($pendingWebhooks as $webhook) {
                    $this->processWebhook($webhookUrl, $webhook);
                }
            } catch (\Exception $e) {
                if ($logLevel === 'debug') {
                    $this->error("Error in bot loop: " . $e->getMessage());
                }
                Log::error('Discord bot loop error', ['error' => $e->getMessage()]);
            }

            sleep(1);
        }

        $this->info('Bot shutdown gracefully.');
        Log::info('Discord bot stopped');
    }

    private function processWebhook(string $webhookUrl, array $webhook): void
    {
        try {
            \Illuminate\Support\Facades\Http::timeout(10)
                ->post($webhookUrl, $webhook);
        } catch (\Exception $e) {
            Log::error('Failed to send Discord webhook', [
                'error' => $e->getMessage(),
                'webhook_type' => $webhook['type'] ?? 'unknown',
            ]);
        }
    }
}
