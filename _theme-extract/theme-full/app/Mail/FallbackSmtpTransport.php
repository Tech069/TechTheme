<?php

namespace Pterodactyl\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class FallbackSmtpTransport extends Mailable
{
    use Queueable;
    use SerializesModels;

    /**
     * Create a new message instance.
     */
    public function __construct(
        public string $toAddress,
        public string $subject,
        public string $htmlBody,
        public ?string $textBody = null,
    ) {
    }

    /**
     * Get the message envelope.
     */
    public function envelope(): Envelope
    {
        return new Envelope(
            subject: $this->subject,
        );
    }

    /**
     * Get the message content definition.
     */
    public function content(): Content
    {
        return new Content(
            html: $this->htmlBody,
            text: $this->textBody ?? strip_tags($this->htmlBody),
        );
    }

    /**
     * Get the attachments for the message.
     *
     * @return array<int, \Illuminate\Mail\Mailables\Attachment>
     */
    public function attachments(): array
    {
        return [];
    }

    /**
     * Build the message using the fallback SMTP transport.
     */
    public static function build(
        string $toAddress,
        string $subject,
        string $htmlBody,
        ?string $textBody = null,
    ): self {
        return (new self(
            toAddress: $toAddress,
            subject: $subject,
            htmlBody: $htmlBody,
            textBody: $textBody,
        ))->to($toAddress);
    }
}
