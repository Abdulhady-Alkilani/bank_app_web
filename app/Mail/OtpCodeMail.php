<?php

namespace App\Mail;

use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;

class OtpCodeMail extends Mailable
{
    public function __construct(
        public readonly string $code,
        public readonly int $ttlMinutes,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(subject: __('scoring.otp.subject', [], 'ar').' / '.__('scoring.otp.subject', [], 'en'));
    }

    public function content(): Content
    {
        return new Content(view: 'mail.otp');
    }
}
