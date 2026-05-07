<?php

namespace App\Mail;

use App\Models\Voucher;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class VoucherUsageThresholdReachedMail extends Mailable
{
    use Queueable;
    use SerializesModels;

    public function __construct(public Voucher $voucher, public int $thresholdPercent)
    {
        $this->voucher->loadMissing('listing.owner');
    }

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'Voucher usage alert: '.$this->voucher->title,
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'mail.vouchers.threshold',
        );
    }
}

