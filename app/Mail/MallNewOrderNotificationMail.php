<?php

namespace App\Mail;

use App\Models\MallOrder;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class MallNewOrderNotificationMail extends Mailable
{
    use Queueable;
    use SerializesModels;

    public function __construct(public MallOrder $order) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'New Life@ Mall order '.$this->order->order_number,
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'mail.mall.new-order-notification',
        );
    }
}
