<?php

namespace App\Mail;

use App\Models\VoucherRedemption;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class VoucherRedeemedMail extends Mailable
{
    use Queueable;
    use SerializesModels;

    public function __construct(public VoucherRedemption $redemption)
    {
        $this->redemption->loadMissing('voucher.listing');
    }

    public function envelope(): Envelope
    {
        $title = $this->redemption->voucher?->title ?: 'Voucher';

        return new Envelope(
            subject: 'Your voucher code: '.$title,
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'mail.vouchers.redeemed',
        );
    }
}

