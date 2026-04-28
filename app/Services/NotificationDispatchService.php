<?php

namespace App\Services;

use App\Mail\InvoiceIssuedMail;
use App\Mail\RenewalPaymentReminderMail;
use App\Mail\SubscriptionExpiryReminderMail;
use App\Models\Invoice;
use App\Models\NotificationLog;
use App\Models\Order;
use App\Models\Subscription;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Mail;
use RuntimeException;
use Throwable;

class NotificationDispatchService
{
    public function __construct(private readonly NotificationLogService $notificationLogService)
    {
    }

    public function resend(NotificationLog $notification): NotificationLog
    {
        $notification->loadMissing('notifiable');
        $this->ensureResendAllowed($notification);

        return match ($notification->notification_type) {
            'invoice_issued' => $this->sendInvoiceIssued($notification->notifiable, true),
            'subscription_expiry_reminder' => $this->sendSubscriptionExpiryReminder($notification->notifiable, true),
            'renewal_payment_reminder' => $this->sendRenewalPaymentReminder($notification->notifiable, true),
            default => throw new RuntimeException('Notification type cannot be resent.'),
        };
    }

    public function canResend(NotificationLog $notification): bool
    {
        return in_array($notification->notification_type, [
            'invoice_issued',
            'subscription_expiry_reminder',
            'renewal_payment_reminder',
        ], true);
    }

    public function resendAvailableAt(NotificationLog $notification, int $cooldownMinutes = 5): ?Carbon
    {
        if (! $this->canResend($notification)) {
            return null;
        }

        $latest = NotificationLog::query()
            ->where('notification_type', $notification->notification_type)
            ->where('notifiable_type', $notification->notifiable_type)
            ->where('notifiable_id', $notification->notifiable_id)
            ->latest('sent_at')
            ->first();

        if (! $latest?->sent_at) {
            return null;
        }

        $availableAt = $latest->sent_at->copy()->addMinutes($cooldownMinutes);

        return $availableAt->isFuture() ? $availableAt : null;
    }

    private function ensureResendAllowed(NotificationLog $notification, int $cooldownMinutes = 5): void
    {
        if (! $this->canResend($notification)) {
            throw new RuntimeException('Notification type cannot be resent.');
        }

        $availableAt = $this->resendAvailableAt($notification, $cooldownMinutes);

        if ($availableAt) {
            throw new RuntimeException('Notification was sent recently. Try again after '.$availableAt->format('H:i').'.');
        }
    }

    public function sendInvoiceIssued(?Invoice $invoice, bool $resent = false): NotificationLog
    {
        if (! $invoice?->order?->user?->email) {
            throw new RuntimeException('Invoice recipient email is missing.');
        }

        return $this->dispatch(
            'invoice_issued',
            $invoice,
            $invoice->order->user->email,
            fn () => Mail::to($invoice->order->user->email)->send(new InvoiceIssuedMail($invoice)),
            ['invoice_number' => $invoice->invoice_number, 'resent' => $resent]
        );
    }

    public function sendSubscriptionExpiryReminder(?Subscription $subscription, bool $resent = false): NotificationLog
    {
        if (! $subscription?->user?->email) {
            throw new RuntimeException('Subscription recipient email is missing.');
        }

        return $this->dispatch(
            'subscription_expiry_reminder',
            $subscription,
            $subscription->user->email,
            fn () => Mail::to($subscription->user->email)->send(new SubscriptionExpiryReminderMail($subscription)),
            ['package' => $subscription->package?->name, 'resent' => $resent]
        );
    }

    public function sendRenewalPaymentReminder(?Order $order, bool $resent = false): NotificationLog
    {
        if (! $order?->user?->email) {
            throw new RuntimeException('Order recipient email is missing.');
        }

        return $this->dispatch(
            'renewal_payment_reminder',
            $order,
            $order->user->email,
            fn () => Mail::to($order->user->email)->send(new RenewalPaymentReminderMail($order)),
            ['order_number' => $order->order_number, 'resent' => $resent]
        );
    }

    private function dispatch(
        string $notificationType,
        ?Model $notifiable,
        string $recipient,
        callable $sender,
        array $meta = []
    ): NotificationLog {
        try {
            $sender();

            return $this->notificationLogService->log(
                $notificationType,
                $notifiable,
                $recipient,
                'email',
                'sent',
                $meta
            );
        } catch (Throwable $exception) {
            $this->notificationLogService->log(
                $notificationType,
                $notifiable,
                $recipient,
                'email',
                'failed',
                array_merge($meta, ['error_message' => $exception->getMessage()])
            );

            throw new RuntimeException('Notification delivery failed: '.$exception->getMessage(), previous: $exception);
        }
    }
}
