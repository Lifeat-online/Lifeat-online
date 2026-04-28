<?php

namespace App\Mail;

use App\Models\Subscription;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class SubscriptionExpiryReminderMail extends Mailable
{
    use Queueable;
    use SerializesModels;

    public function __construct(public Subscription $subscription)
    {
    }

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'Your subscription is nearing expiry',
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'mail.subscriptions.expiry-reminder',
        );
    }
}
