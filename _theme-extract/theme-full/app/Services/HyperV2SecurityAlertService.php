<?php

namespace Pterodactyl\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Pterodactyl\Models\User;
use Pterodactyl\Models\Server;
use Pterodactyl\Models\Node;

class HyperV2SecurityAlertService
{
    private const SEVERITY_LOW = 'low';
    private const SEVERITY_MEDIUM = 'medium';
    private const SEVERITY_HIGH = 'high';
    private const SEVERITY_CRITICAL = 'critical';

    public function __construct()
    {
    }

    /**
     * Send a security alert through all configured channels.
     */
    public function sendAlert(string $title, string $message, string $severity = self::SEVERITY_MEDIUM, array $context = []): void
    {
        $alertData = [
            'title' => $title,
            'message' => $message,
            'severity' => $severity,
            'context' => $context,
            'timestamp' => now()->toISOString(),
            'panel_url' => config('app.url'),
        ];

        Log::warning('Security Alert', $alertData);

        $this->sendDiscordAlert($alertData);
        $this->sendEmailAlerts($alertData);
        $this->logToDatabase($alertData);
    }

    /**
     * Send alert about failed login attempts.
     */
    public function alertFailedLogin(string $email, string $ipAddress, int $attempts): void
    {
        $this->sendAlert(
            'Failed Login Attempts',
            sprintf('Multiple failed login attempts detected for %s from IP %s (%d attempts)', $email, $ipAddress, $attempts),
            $attempts >= 10 ? self::SEVERITY_HIGH : self::SEVERITY_MEDIUM,
            ['email' => $email, 'ip' => $ipAddress, 'attempts' => $attempts]
        );
    }

    /**
     * Send alert about unauthorized access attempts.
     */
    public function alertUnauthorizedAccess(Server $server, User $user, string $action): void
    {
        $this->sendAlert(
            'Unauthorized Access Attempt',
            sprintf('User %s (ID: %d) attempted unauthorized action "%s" on server %s (ID: %d)', $user->username, $user->id, $action, $server->name, $server->id),
            self::SEVERITY_HIGH,
            ['server_id' => $server->id, 'user_id' => $user->id, 'action' => $action]
        );
    }

    /**
     * Send alert about node connectivity issues.
     */
    public function alertNodeOffline(Node $node): void
    {
        $this->sendAlert(
            'Node Offline',
            sprintf('Node %s (ID: %d) at %s is no longer reachable', $node->name, $node->id, $node->fqdn),
            self::SEVERITY_CRITICAL,
            ['node_id' => $node->id, 'fqdn' => $node->fqdn]
        );
    }

    /**
     * Send alert about suspicious file changes.
     */
    public function alertFileIntegrityFailure(array $modifiedFiles): void
    {
        $this->sendAlert(
            'File Integrity Check Failed',
            sprintf('Detected %d modified files during integrity check', count($modifiedFiles)),
            self::SEVERITY_CRITICAL,
            ['modified_files' => $modifiedFiles]
        );
    }

    /**
     * Send alert about resource abuse.
     */
    public function alertResourceAbuse(Server $server, string $resource, float $usage): void
    {
        $this->sendAlert(
            'Resource Abuse Detected',
            sprintf('Server %s (ID: %d) using %.1f%% %s', $server->name, $server->id, $usage, $resource),
            self::SEVERITY_MEDIUM,
            ['server_id' => $server->id, 'resource' => $resource, 'usage' => $usage]
        );
    }

    /**
     * Send a Discord webhook notification.
     */
    private function sendDiscordAlert(array $alertData): void
    {
        $webhookUrl = config('services.discord.security_webhook_url');

        if (empty($webhookUrl)) {
            return;
        }

        $colorMap = [
            self::SEVERITY_LOW => 0x3498DB,
            self::SEVERITY_MEDIUM => 0xF39C12,
            self::SEVERITY_HIGH => 0xE74C3C,
            self::SEVERITY_CRITICAL => 0x8E44AD,
        ];

        $embed = [
            'title' => ':shield: ' . $alertData['title'],
            'description' => $alertData['message'],
            'color' => $colorMap[$alertData['severity']] ?? 0x95A5A6,
            'fields' => [
                ['name' => 'Severity', 'value' => strtoupper($alertData['severity']), 'inline' => true],
                ['name' => 'Time', 'value' => $alertData['timestamp'], 'inline' => true],
            ],
            'footer' => ['text' => 'HyperPanel Security Alert'],
        ];

        if (!empty($alertData['context'])) {
            $embed['fields'][] = [
                'name' => 'Context',
                'value' => '```' . json_encode($alertData['context'], JSON_PRETTY_PRINT) . '```',
                'inline' => false,
            ];
        }

        try {
            Http::post($webhookUrl, [
                'embeds' => [$embed],
            ]);
        } catch (\Exception $exception) {
            Log::error('Failed to send Discord security alert', [
                'error' => $exception->getMessage(),
            ]);
        }
    }

    /**
     * Send email alerts to administrators.
     */
    private function sendEmailAlerts(array $alertData): void
    {
        if (!config('mail.enabled')) {
            return;
        }

        $adminEmails = User::where('root_admin', true)->pluck('email')->toArray();

        if (empty($adminEmails)) {
            return;
        }

        try {
            foreach ($adminEmails as $email) {
                Mail::raw($alertData['message'] . "\n\nContext: " . json_encode($alertData['context']), function ($message) use ($email, $alertData) {
                    $message->to($email)
                        ->subject('[Security Alert] ' . $alertData['title'])
                        ->from(config('mail.from.address'), config('mail.from.name'));
                });
            }
        } catch (\Exception $exception) {
            Log::error('Failed to send email security alerts', [
                'error' => $exception->getMessage(),
            ]);
        }
    }

    /**
     * Log alert to the database for audit trail.
     */
    private function logToDatabase(array $alertData): void
    {
        try {
            \Pterodactyl\Models\AuditLog::instance('security:alert', [
                'title' => $alertData['title'],
                'message' => $alertData['message'],
                'severity' => $alertData['severity'],
                'context' => $alertData['context'],
            ], true)->save();
        } catch (\Exception $exception) {
            Log::error('Failed to log security alert to database', [
                'error' => $exception->getMessage(),
            ]);
        }
    }
}
