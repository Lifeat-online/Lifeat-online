<?php

namespace App\Mail;

use App\Models\Order;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class RenewalPaymentReminderMail extends Mailable
{
    use Queueable;
    use SerializesModels;

    public function __construct(public Order $order)
    {
    }

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'Payment reminder for your renewal order',
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'mail.subscriptions.renewal-payment-reminder',
        );
    }
}
