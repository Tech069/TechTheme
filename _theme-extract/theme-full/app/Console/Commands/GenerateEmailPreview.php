<?php

namespace Pterodactyl\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\View;

class GenerateEmailPreview extends Command
{
    protected $signature = 'p:email:preview
        {type=server : The email type to preview (server, account, billing, password-reset)}
        {--email= : Email address to send the preview to}
        {--user-id= : User ID to use for dynamic data}';

    protected $description = 'Generate and optionally send a preview of an email template for testing purposes.';

    private const EMAIL_TYPES = [
        'server' => ['subject' => 'Server Suspension Notice', 'view' => 'emails.server.suspended'],
        'account' => ['subject' => 'Welcome to Pterodactyl', 'view' => 'emails.account.welcome'],
        'billing' => ['subject' => 'Payment Confirmation', 'view' => 'emails.billing.payment-complete'],
        'password-reset' => ['subject' => 'Password Reset Request', 'view' => 'emails.auth.reset'],
    ];

    public function handle(): int
    {
        $type = $this->argument('type');

        if (!array_key_exists($type, self::EMAIL_TYPES)) {
            $this->error("Invalid email type: $type");
            $this->info('Available types: ' . implode(', ', array_keys(self::EMAIL_TYPES)));

            return 1;
        }

        $emailConfig = self::EMAIL_TYPES[$type];

        if (!$this->option('email') && !$this->option('user-id')) {
            $this->warn('Running in local preview mode. No email will be sent.');
            $this->newLine();
        }

        $data = $this->resolveTemplateData($type);

        if ($this->option('email')) {
            return $this->sendPreviewEmail($this->option('email'), $emailConfig, $data);
        }

        $this->renderPreview($type, $emailConfig, $data);

        return 0;
    }

    private function resolveTemplateData(string $type): array
    {
        $userId = $this->option('user-id');

        $user = $userId
            ? \Pterodactyl\Models\User::find($userId)
            : null;

        return match ($type) {
            'server' => [
                'user' => $user,
                'server_name' => $user?->servers->first()?->name ?? 'Test Server',
                'server_id' => $user?->servers->first()?->id ?? 1,
                'reason' => 'Resource limit exceeded',
            ],
            'account' => [
                'user' => $user,
                'username' => $user?->username ?? 'testuser',
                'email' => $user?->email ?? 'test@example.com',
                'panel_url' => config('app.url', 'http://localhost'),
            ],
            'billing' => [
                'user' => $user,
                'amount' => '9.99',
                'currency' => 'USD',
                'invoice_id' => 'INV-' . strtoupper(uniqid()),
                'date' => now()->toFormattedDateString(),
            ],
            'password-reset' => [
                'user' => $user,
                'email' => $user?->email ?? 'test@example.com',
                'reset_url' => config('app.url', 'http://localhost') . '/auth/password/reset?token=preview-token',
            ],
            default => [],
        };
    }

    private function sendPreviewEmail(string $email, array $emailConfig, array $data): int
    {
        $this->info("Sending preview email to: $email");

        try {
            Mail::raw([], function ($message) use ($email, $emailConfig, $data) {
                $message->to($email)
                    ->subject('[Preview] ' . $emailConfig['subject'])
                    ->from(config('mail.from.address'), config('mail.from.name'));
            });

            $this->info('Preview email sent successfully.');

            return 0;
        } catch (\Exception $e) {
            $this->error('Failed to send preview email: ' . $e->getMessage());

            return 1;
        }
    }

    private function renderPreview(string $type, array $emailConfig, array $data): void
    {
        $this->output->title('Email Preview: ' . ucfirst($type));
        $this->info('Subject: ' . $emailConfig['subject']);
        $this->info('Template: ' . $emailConfig['view']);
        $this->newLine();

        $view = $emailConfig['view'];

        if (View::exists($view)) {
            $rendered = View::make($view, $data)->render();
            $this->line($rendered);
        } else {
            $this->warn("View template '$view' not found. Displaying raw data:");
            $this->newLine();
            foreach ($data as $key => $value) {
                if (is_object($value)) {
                    $value = class_basename($value) . ' (#' . ($value->id ?? 'unknown') . ')';
                }
                $this->line("  $key: " . ($value ?? 'null'));
            }
        }
    }
}
