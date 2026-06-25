<?php

namespace Pterodactyl\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;
use Pterodactyl\Models\User;

class HyperV2SecurityAlertMail extends Mailable
{
    use Queueable;
    use SerializesModels;

    /**
     * Create a new message instance.
     */
    public function __construct(
        public User $user,
        public string $alertType,
        public array $metadata = [],
    ) {
    }

    /**
     * Get the message envelope.
     */
    public function envelope(): Envelope
    {
        return new Envelope(
            subject: "[Security Alert] " . ucfirst(str_replace('_', ' ', $this->alertType)),
        );
    }

    /**
     * Get the message content definition.
     */
    public function content(): Content
    {
        return new Content(
            html: $this->buildHtmlContent(),
            text: $this->buildTextContent(),
        );
    }

    /**
     * Build the HTML content.
     */
    protected function buildHtmlContent(): string
    {
        $userName = $this->user->name ?? $this->user->username;
        $alertDescription = $this->getAlertDescription();
        $metadataHtml = $this->buildMetadataHtml();

        return <<<HTML
        <!DOCTYPE html>
        <html>
        <head>
            <meta charset="utf-8">
            <style>
                body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; }
                .container { max-width: 600px; margin: 0 auto; padding: 20px; }
                .alert-box { background: #fee; border: 1px solid #fcc; padding: 15px; border-radius: 5px; margin: 20px 0; }
                .alert-title { color: #c00; font-weight: bold; font-size: 18px; }
                .metadata { background: #f5f5f5; padding: 10px; border-radius: 3px; margin: 10px 0; }
                .footer { margin-top: 30px; font-size: 12px; color: #666; }
            </style>
        </head>
        <body>
            <div class="container">
                <div class="alert-box">
                    <div class="alert-title">Security Alert</div>
                </div>
                <p>Hello {$userName},</p>
                <p>{$alertDescription}</p>
                {$metadataHtml}
                <p>If you did not perform this action, please secure your account immediately by changing your password and enabling two-factor authentication.</p>
                <div class="footer">
                    <p>This is an automated security notification from your hosting panel.</p>
                </div>
            </div>
        </body>
        </html>
        HTML;
    }

    /**
     * Build the plain text content.
     */
    protected function buildTextContent(): string
    {
        $userName = $this->user->name ?? $this->user->username;
        $alertDescription = $this->getAlertDescription();

        $text = "Security Alert\n\n";
        $text .= "Hello {$userName},\n\n";
        $text .= "{$alertDescription}\n\n";

        if (!empty($this->metadata)) {
            $text .= "Details:\n";
            foreach ($this->metadata as $key => $value) {
                if (is_scalar($value)) {
                    $text .= "- {$key}: {$value}\n";
                }
            }
            $text .= "\n";
        }

        $text .= "If you did not perform this action, please secure your account immediately.\n\n";
        $text .= "This is an automated security notification from your hosting panel.\n";

        return $text;
    }

    /**
     * Get a human-readable description for the alert type.
     */
    protected function getAlertDescription(): string
    {
        return match ($this->alertType) {
            'login_new_location' => 'A new login was detected on your account from a new location.',
            'password_changed' => 'Your password has been changed successfully.',
            'email_changed' => 'Your email address has been changed.',
            '2fa_enabled' => 'Two-factor authentication has been enabled on your account.',
            '2fa_disabled' => 'Two-factor authentication has been disabled on your account.',
            'api_key_created' => 'A new API key has been created on your account.',
            'suspicious_activity' => 'Suspicious activity was detected on your account.',
            default => 'A security event occurred on your account.',
        };
    }

    /**
     * Build HTML for metadata display.
     */
    protected function buildMetadataHtml(): string
    {
        if (empty($this->metadata)) {
            return '';
        }

        $html = '<div class="metadata"><strong>Details:</strong><ul>';
        foreach ($this->metadata as $key => $value) {
            if (is_scalar($value)) {
                $key = ucfirst(str_replace('_', ' ', $key));
                $html .= "<li><strong>{$key}:</strong> {$value}</li>";
            }
        }
        $html .= '</ul></div>';

        return $html;
    }
}
