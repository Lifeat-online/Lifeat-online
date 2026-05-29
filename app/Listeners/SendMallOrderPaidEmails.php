<?php

namespace App\Listeners;

use App\Events\MallOrderPaid;
use App\Mail\MallNewOrderNotificationMail;
use App\Mail\MallOrderConfirmationMail;
use Illuminate\Support\Facades\Mail;

class SendMallOrderPaidEmails
{
    public function handle(MallOrderPaid $event): void
    {
        $order = $event->order->loadMissing('user', 'store.vendorProfile', 'items');

        if ($order->user?->email) {
            Mail::to($order->user->email)->queue(new MallOrderConfirmationMail($order));
        }

        if ($order->store?->vendorProfile?->contact_email) {
            Mail::to($order->store->vendorProfile->contact_email)->queue(new MallNewOrderNotificationMail($order));
        }
    }
}
