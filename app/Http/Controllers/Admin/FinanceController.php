<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\Invoice;
use App\Models\NotificationLog;
use App\Models\Order;
use App\Models\Payment;
use App\Models\User;
use App\Models\PaymentRefund;
use App\Models\Subscription;
use App\Models\SubscriptionReminder;
use App\Services\NotificationDispatchService;
use App\Services\StaffCommissionService;
use App\Services\SubscriptionLifecycleService;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Contracts\View\View;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\StreamedResponse;

class FinanceController extends Controller
{
    public function orders(Request $request): View
    {
        Gate::authorize('viewAnyFinance', Order::class);

        return view('admin.finance.orders', [
            'orders' => Order::with(['user', 'payments', 'invoices'])->latest()->paginate(25),
        ]);
    }

    public function showOrder(Request $request, Order $order): View
    {
        Gate::authorize('viewFinance', $order);

        $order->load(['user', 'referredBy', 'items.package', 'items.purchasable', 'payments.attempts', 'payments.refunds.processor', 'invoices', 'renewedSubscription']);
        $notifications = NotificationLog::where(function ($query) use ($order) {
            $query->where('notifiable_type', Invoice::class)->whereIn('notifiable_id', $order->invoices->modelKeys())
                ->orWhere(function ($inner) use ($order) {
                    $inner->where('notifiable_type', Order::class)->where('notifiable_id', $order->id);
                });
        })->latest()->get();

        return view('admin.finance.order-show', [
            'order' => $order,
            'notifications' => $notifications,
            'timeline' => $this->paginateTimeline($this->filterTimeline($this->buildOrderTimeline($order, $notifications), $request), $request),
            'timelineFilters' => $this->timelineFilters($request),
            'staffUsers' => User::where(function ($q) {
                $q->where('role', 'staff')
                    ->orWhereHas('roles', fn ($r) => $r->where('slug', 'sales_staff'));
            })->orderBy('name')->get(['id', 'name', 'email']),
        ]);
    }

    public function setOrderAttribution(Request $request, Order $order): RedirectResponse
    {
        Gate::authorize('setAttribution', $order);

        $validated = $request->validate([
            'referred_by_user_id' => ['nullable', 'integer', 'exists:users,id'],
        ]);

        $before = ['referred_by_user_id' => $order->referred_by_user_id];
        $order->update(['referred_by_user_id' => $validated['referred_by_user_id'] ?: null]);

        AuditLog::create([
            'actor_user_id' => $request->user()?->id,
            'action'        => 'order.attribution_set',
            'subject_type'  => Order::class,
            'subject_id'    => $order->id,
            'before_json'   => $before,
            'after_json'    => ['referred_by_user_id' => $order->referred_by_user_id],
            'ip_address'    => $request->ip(),
            'user_agent'    => $request->userAgent(),
        ]);

        return redirect()->route('admin.finance.orders.show', $order)
            ->with('status', 'Commission attribution updated.');
    }

    public function payments(Request $request): View
    {
        Gate::authorize('viewAnyFinance', Payment::class);

        $status = $request->string('status')->toString();

        return view('admin.finance.payments', [
            'payments' => Payment::with(['order', 'user', 'attempts', 'refunds'])
                ->when($status !== '', fn ($query) => $query->where('status', $status))
                ->latest()
                ->paginate(25)
                ->withQueryString(),
            'filters' => compact('status'),
        ]);
    }

    public function showPayment(Payment $payment): View
    {
        Gate::authorize('viewFinance', $payment);

        $request = request();
        $payment->load(['order.items', 'user', 'attempts', 'refunds.processor']);
        $notifications = NotificationLog::where('notifiable_type', Order::class)
            ->where('notifiable_id', $payment->order_id)
            ->latest()
            ->get();

        return view('admin.finance.payment-show', [
            'payment' => $payment,
            'notifications' => $notifications,
            'timeline' => $this->paginateTimeline($this->filterTimeline($this->buildPaymentTimeline($payment, $notifications), $request), $request),
            'timelineFilters' => $this->timelineFilters($request),
        ]);
    }

    public function subscriptions(Request $request): View
    {
        Gate::authorize('viewAnyFinance', Subscription::class);

        $status = $request->string('status')->toString();
        $endingWithinDays = (int) $request->integer('ending_within_days', 0);

        return view('admin.finance.subscriptions', [
            'subscriptions' => Subscription::with(['user', 'package', 'reminders'])
                ->when($status !== '', fn ($query) => $query->where('status', $status))
                ->when($endingWithinDays > 0, function ($query) use ($endingWithinDays) {
                    $query->whereNotNull('ends_at')
                        ->whereBetween('ends_at', [now(), now()->addDays($endingWithinDays)]);
                })
                ->latest()
                ->paginate(25)
                ->withQueryString(),
            'filters' => [
                'status' => $status,
                'ending_within_days' => $endingWithinDays > 0 ? $endingWithinDays : '',
            ],
        ]);
    }

    public function notifications(Request $request): View
    {
        Gate::authorize('viewAnyFinance', NotificationLog::class);

        $type = $request->string('type')->toString();
        $status = $request->string('status')->toString();
        $channel = $request->string('channel')->toString();

        return view('admin.finance.notifications', [
            'notifications' => NotificationLog::query()
                ->when($type !== '', fn ($query) => $query->where('notification_type', $type))
                ->when($status !== '', function ($query) use ($status) {
                    if ($status === 'attention') {
                        return $query->whereIn('status', ['pending', 'queued', 'failed']);
                    }

                    return $query->where('status', $status);
                })
                ->when($channel !== '', fn ($query) => $query->where('channel', $channel))
                ->latest()
                ->paginate(25)
                ->withQueryString(),
            'filters' => compact('type', 'status', 'channel'),
        ]);
    }

    public function showNotification(NotificationLog $notification): View
    {
        Gate::authorize('viewFinance', $notification);

        $dispatchService = app(NotificationDispatchService::class);
        $availableAt = $dispatchService->resendAvailableAt($notification);

        return view('admin.finance.notification-show', [
            'notification' => $notification->load('notifiable'),
            'canResend' => $dispatchService->canResend($notification),
            'resendAvailableAt' => $availableAt,
        ]);
    }

    public function resendNotification(Request $request, NotificationLog $notification): RedirectResponse
    {
        Gate::authorize('resend', $notification);

        try {
            $resent = app(NotificationDispatchService::class)->resend($notification);
        } catch (\RuntimeException $exception) {
            return redirect()->route('admin.finance.notifications.show', $notification)
                ->withErrors(['notification' => $exception->getMessage()]);
        }

        $this->logAudit(
            $request,
            'notification.resent',
            $notification,
            $notification->only(['notification_type', 'recipient', 'status']),
            ['resent_log_id' => $resent->id, 'status' => 'sent']
        );

        return redirect()->route('admin.finance.notifications.show', $notification)->with('status', 'Notification resent.');
    }

    public function showSubscription(Subscription $subscription): View
    {
        Gate::authorize('viewFinance', $subscription);

        $request = request();
        $subscription->load(['user', 'package', 'subscribable', 'entitlements', 'reminders', 'payment']);
        $notifications = NotificationLog::where('notifiable_type', Subscription::class)
            ->where('notifiable_id', $subscription->id)
            ->latest()
            ->get();

        return view('admin.finance.subscription-show', [
            'subscription' => $subscription,
            'notifications' => $notifications,
            'timeline' => $this->paginateTimeline($this->filterTimeline($this->buildSubscriptionTimeline($subscription, $notifications), $request), $request),
            'timelineFilters' => $this->timelineFilters($request),
        ]);
    }

    public function index(Request $request): View
    {
        Gate::authorize('viewAnyFinance', Order::class);

        $orderStatus = $request->string('order_status')->toString();
        $paymentStatus = $request->string('payment_status')->toString();
        $subscriptionStatus = $request->string('subscription_status')->toString();

        return view('admin.finance.index', [
            'summary' => [
                'pending_orders' => Order::where('status', 'pending_payment')->count(),
                'paid_orders' => Order::where('status', 'paid')->count(),
                'paid_revenue' => (float) Payment::where('status', 'paid')->sum('amount'),
                'active_subscriptions' => Subscription::where('status', 'active')->count(),
            ],
            'orders' => Order::with(['user', 'payments', 'invoices'])
                ->when($orderStatus !== '', fn ($query) => $query->where('status', $orderStatus))
                ->latest()->limit(15)->get(),
            'payments' => Payment::with(['order', 'user', 'attempts', 'refunds'])
                ->when($paymentStatus !== '', fn ($query) => $query->where('status', $paymentStatus))
                ->latest()->limit(15)->get(),
            'invoices' => Invoice::with(['order.user'])->latest()->limit(15)->get(),
            'subscriptions' => Subscription::with(['user', 'package', 'reminders'])
                ->when($subscriptionStatus !== '', fn ($query) => $query->where('status', $subscriptionStatus))
                ->latest()->limit(15)->get(),
            'refunds' => PaymentRefund::with(['payment.order', 'processor'])->latest()->limit(15)->get(),
            'filters' => compact('orderStatus', 'paymentStatus', 'subscriptionStatus'),
            'reminders' => SubscriptionReminder::with('subscription.user')->latest()->limit(15)->get(),
        ]);
    }

    public function export(string $dataset): StreamedResponse
    {
        Gate::authorize('exportFinance', Order::class);

        return match ($dataset) {
            'orders' => $this->streamOrders(),
            'payments' => $this->streamPayments(),
            'invoices' => $this->streamInvoices(),
            'subscriptions' => $this->streamSubscriptions(),
            default => abort(404),
        };
    }

    public function markPaymentPaid(Request $request, Payment $payment): RedirectResponse
    {
        Gate::authorize('reconcile', $payment);

        $before = $payment->only(['status', 'paid_at', 'provider_transaction_id', 'failure_reason']);

        $payment->update([
            'status' => 'paid',
            'paid_at' => $payment->paid_at ?: now(),
            'provider_transaction_id' => $payment->provider_transaction_id ?: 'MANUAL-'.strtoupper(str()->random(8)),
            'failure_reason' => null,
        ]);

        $payment->order?->update([
            'status' => 'paid',
            'placed_at' => $payment->order?->placed_at ?: now(),
        ]);

        $this->logAudit($request, 'payment.marked_paid', $payment, $before, $payment->fresh()->only(['status', 'paid_at', 'provider_transaction_id', 'failure_reason']));

        return $this->redirectAfterAction($request, 'admin.finance.index', [], 'Payment marked paid.');
    }

    public function markPaymentFailed(Request $request, Payment $payment): RedirectResponse
    {
        Gate::authorize('reconcile', $payment);

        $before = $payment->only(['status', 'failure_reason']);

        $payment->update([
            'status' => 'failed',
            'failure_reason' => $request->input('failure_reason', 'Marked failed by finance.'),
        ]);

        $payment->order?->update([
            'status' => 'cancelled',
        ]);

        $this->logAudit($request, 'payment.marked_failed', $payment, $before, $payment->fresh()->only(['status', 'failure_reason']));

        return $this->redirectAfterAction($request, 'admin.finance.index', [], 'Payment marked failed.');
    }

    public function refundPayment(Request $request, Payment $payment, StaffCommissionService $commissionService): RedirectResponse
    {
        Gate::authorize('refund', $payment);

        $before = $payment->only(['status', 'failure_reason']);
        $validated = $request->validate([
            'refund_amount' => ['nullable', 'numeric', 'min:0.01'],
            'refund_reason' => ['nullable', 'string'],
        ]);

        $amount = (float) ($validated['refund_amount'] ?? $payment->amount);

        $payment->refunds()->create([
            'processed_by_user_id' => $request->user()->id,
            'amount' => $amount,
            'status' => 'processed',
            'reason' => $validated['refund_reason'] ?? 'Manual refund recorded by finance.',
            'refunded_at' => now(),
        ]);

        $payment->update([
            'status' => $amount >= (float) $payment->amount ? 'refunded' : $payment->status,
        ]);

        $payment->order?->update([
            'status' => $amount >= (float) $payment->amount ? 'refunded' : $payment->order?->status,
        ]);

        if ($amount >= (float) $payment->amount) {
            $commissionService->reverseForRefund($payment);

            Subscription::where('payment_id', $payment->id)
                ->get()
                ->each(function (Subscription $subscription) use ($validated) {
                    app(SubscriptionLifecycleService::class)->suspend($subscription, $validated['refund_reason'] ?? null);
                });
        }

        $this->logAudit($request, 'payment.refunded', $payment, $before, $payment->fresh()->only(['status', 'failure_reason']));

        return $this->redirectAfterAction($request, 'admin.finance.index', [], 'Refund recorded.');
    }

    public function extendSubscription(Request $request, Subscription $subscription): RedirectResponse
    {
        Gate::authorize('extend', $subscription);

        $before = $subscription->only(['status', 'starts_at', 'ends_at', 'renews_at']);
        $validated = $request->validate([
            'extension_days' => ['required', 'integer', 'min:1'],
        ]);

        app(SubscriptionLifecycleService::class)->extend($subscription, (int) $validated['extension_days']);

        $this->logAudit($request, 'subscription.extended', $subscription, $before, $subscription->fresh()->only(['status', 'starts_at', 'ends_at', 'renews_at']));

        return $this->redirectAfterAction($request, 'admin.finance.index', [], 'Subscription extended.');
    }

    public function suspendSubscription(Request $request, Subscription $subscription): RedirectResponse
    {
        Gate::authorize('suspend', $subscription);

        $before = $subscription->only(['status', 'ends_at', 'renews_at']);
        app(SubscriptionLifecycleService::class)->suspend($subscription, $request->input('suspension_reason'));

        $this->logAudit($request, 'subscription.suspended', $subscription, $before, $subscription->fresh()->only(['status', 'ends_at', 'renews_at']));

        return $this->redirectAfterAction($request, 'admin.finance.index', [], 'Subscription suspended.');
    }

    public function sendSubscriptionReminder(Request $request, Subscription $subscription): RedirectResponse
    {
        Gate::authorize('sendReminder', $subscription);

        $reminder = app(SubscriptionLifecycleService::class)->logReminder(
            $subscription,
            $request->input('reminder_type', 'expiry_notice'),
            $request->input('channel', 'email')
        );

        $this->logAudit($request, 'subscription.reminder_logged', $subscription, [], ['reminder_id' => $reminder->id, 'reminder_type' => $reminder->reminder_type]);

        return $this->redirectAfterAction($request, 'admin.finance.index', [], 'Subscription reminder logged.');
    }

    private function redirectAfterAction(Request $request, string $fallbackRoute, array $fallbackParameters, string $status): RedirectResponse
    {
        $returnTo = (string) $request->input('return_to', '');
        $appUrl = rtrim(url('/'), '/');

        if (
            $returnTo !== '' &&
            (
                Str::startsWith($returnTo, $appUrl.'/admin/')
                || Str::startsWith($returnTo, '/admin/')
            )
        ) {
            return redirect()->to($returnTo)->with('status', $status);
        }

        return redirect()->route($fallbackRoute, $fallbackParameters)->with('status', $status);
    }

    private function logAudit(Request $request, string $action, Model $subject, array $before, array $after): void
    {
        AuditLog::create([
            'actor_user_id' => $request->user()?->id,
            'action' => $action,
            'subject_type' => $subject::class,
            'subject_id' => $subject->getKey(),
            'before_json' => $before,
            'after_json' => $after,
            'ip_address' => $request->ip(),
            'user_agent' => $request->userAgent(),
        ]);
    }

    private function buildOrderTimeline(Order $order, $notifications)
    {
        $paymentIds = $order->payments->modelKeys();
        $invoiceIds = $order->invoices->modelKeys();

        $auditLogs = AuditLog::with('actor')
            ->where(function ($query) use ($order, $paymentIds, $invoiceIds) {
                $query->where(function ($inner) use ($order) {
                    $inner->where('subject_type', Order::class)->where('subject_id', $order->id);
                });

                if (! empty($paymentIds)) {
                    $query->orWhere(function ($inner) use ($paymentIds) {
                        $inner->where('subject_type', Payment::class)->whereIn('subject_id', $paymentIds);
                    });
                }

                if (! empty($invoiceIds)) {
                    $query->orWhere(function ($inner) use ($invoiceIds) {
                        $inner->where('subject_type', Invoice::class)->whereIn('subject_id', $invoiceIds);
                    });
                }
            })
            ->get();

        $entries = collect([
            [$this->timelineEntry('lifecycle', 'order_created', 'Order created', $order->order_number, $order->created_at, 'Lifecycle')],
            $order->payments->map(fn ($payment) => $this->timelineEntry('payment', 'payment', 'Payment '.ucfirst($payment->status), $payment->currency.' '.number_format((float) $payment->amount, 2), $payment->paid_at ?: $payment->updated_at, 'Payment')),
            $order->payments->flatMap(fn ($payment) => $payment->attempts->map(fn ($attempt) => $this->timelineEntry('attempt', 'payment_attempt', 'Payment attempt '.ucfirst($attempt->status), $attempt->provider, $attempt->attempted_at ?: $attempt->created_at, 'Gateway'))),
            $order->payments->flatMap(fn ($payment) => $payment->refunds->map(fn ($refund) => $this->timelineEntry('refund', 'refund', 'Refund '.ucfirst($refund->status), number_format((float) $refund->amount, 2), $refund->refunded_at ?: $refund->created_at, 'Refund'))),
            $order->invoices->map(fn ($invoice) => $this->timelineEntry('invoice', 'invoice', 'Invoice '.ucfirst($invoice->status), $invoice->invoice_number, $invoice->emailed_at ?: $invoice->updated_at, 'Invoice')),
            $notifications->map(fn ($notification) => $this->timelineEntry('notification', 'notification', ucfirst(str_replace('_', ' ', $notification->notification_type)), ($notification->recipient ?: 'No recipient').' · '.ucfirst($notification->status), $notification->sent_at ?: $notification->created_at, 'Email')),
            $auditLogs->map(fn ($audit) => $this->timelineEntry('audit', 'audit', ucfirst(str_replace('.', ' ', $audit->action)), $audit->actor?->name ?: 'System', $audit->created_at, 'Admin')),
        ])->flatten(1)->filter()->sortByDesc(fn ($entry) => optional($entry['occurred_at'])->timestamp ?? 0)->values();

        return $entries;
    }

    private function buildPaymentTimeline(Payment $payment, $notifications)
    {
        $auditLogs = AuditLog::with('actor')
            ->where('subject_type', Payment::class)
            ->where('subject_id', $payment->id)
            ->get();

        return collect([
            [$this->timelineEntry('lifecycle', 'payment_created', 'Payment created', $payment->currency.' '.number_format((float) $payment->amount, 2), $payment->created_at, 'Lifecycle')],
            $payment->attempts->map(fn ($attempt) => $this->timelineEntry('attempt', 'payment_attempt', 'Attempt '.ucfirst($attempt->status), $attempt->provider, $attempt->attempted_at ?: $attempt->created_at, 'Gateway')),
            $payment->refunds->map(fn ($refund) => $this->timelineEntry('refund', 'refund', 'Refund '.ucfirst($refund->status), number_format((float) $refund->amount, 2), $refund->refunded_at ?: $refund->created_at, 'Refund')),
            $notifications->map(fn ($notification) => $this->timelineEntry('notification', 'notification', ucfirst(str_replace('_', ' ', $notification->notification_type)), ($notification->recipient ?: 'No recipient').' · '.ucfirst($notification->status), $notification->sent_at ?: $notification->created_at, 'Email')),
            $auditLogs->map(fn ($audit) => $this->timelineEntry('audit', 'audit', ucfirst(str_replace('.', ' ', $audit->action)), $audit->actor?->name ?: 'System', $audit->created_at, 'Admin')),
        ])->flatten(1)->filter()->sortByDesc(fn ($entry) => optional($entry['occurred_at'])->timestamp ?? 0)->values();
    }

    private function buildSubscriptionTimeline(Subscription $subscription, $notifications)
    {
        $auditLogs = AuditLog::with('actor')
            ->where('subject_type', Subscription::class)
            ->where('subject_id', $subscription->id)
            ->get();

        return collect([
            [$this->timelineEntry('lifecycle', 'subscription_created', 'Subscription created', $subscription->package?->name, $subscription->created_at, 'Lifecycle')],
            $subscription->reminders->map(fn ($reminder) => $this->timelineEntry('reminder', 'reminder', ucfirst(str_replace('_', ' ', $reminder->reminder_type)), ucfirst($reminder->status), $reminder->sent_at ?: $reminder->created_at, 'Reminder')),
            $notifications->map(fn ($notification) => $this->timelineEntry('notification', 'notification', ucfirst(str_replace('_', ' ', $notification->notification_type)), ($notification->recipient ?: 'No recipient').' · '.ucfirst($notification->status), $notification->sent_at ?: $notification->created_at, 'Email')),
            $auditLogs->map(fn ($audit) => $this->timelineEntry('audit', 'audit', ucfirst(str_replace('.', ' ', $audit->action)), $audit->actor?->name ?: 'System', $audit->created_at, 'Admin')),
        ])->flatten(1)->filter()->sortByDesc(fn ($entry) => optional($entry['occurred_at'])->timestamp ?? 0)->values();
    }

    private function timelineEntry(string $source, string $type, string $title, ?string $detail, $occurredAt, string $badge): array
    {
        return [
            'source' => $source,
            'type' => $type,
            'badge' => $badge,
            'title' => $title,
            'detail' => $detail,
            'occurred_at' => $occurredAt,
        ];
    }

    private function filterTimeline($timeline, Request $request)
    {
        $type = $request->string('timeline_type')->toString();
        $source = $request->string('timeline_source')->toString();

        return collect($timeline)
            ->when($type !== '', fn ($entries) => $entries->where('type', $type))
            ->when($source !== '', fn ($entries) => $entries->where('source', $source))
            ->values();
    }

    private function timelineFilters(Request $request): array
    {
        return [
            'timeline_type' => $request->string('timeline_type')->toString(),
            'timeline_source' => $request->string('timeline_source')->toString(),
        ];
    }

    private function paginateTimeline($timeline, Request $request, int $perPage = 12): LengthAwarePaginator
    {
        $page = LengthAwarePaginator::resolveCurrentPage('timeline_page');
        $items = collect($timeline);

        return (new LengthAwarePaginator(
            $items->forPage($page, $perPage)->values(),
            $items->count(),
            $perPage,
            $page,
            [
                'path' => $request->url(),
                'pageName' => 'timeline_page',
            ]
        ))->appends($request->query());
    }

    private function streamOrders(): StreamedResponse
    {
        $orders = Order::with('user')->latest()->get();

        return response()->streamDownload(function () use ($orders) {
            $handle = fopen('php://output', 'w');
            fputcsv($handle, ['Order Number', 'Customer', 'Status', 'Currency', 'Total']);
            foreach ($orders as $order) {
                fputcsv($handle, [$order->order_number, $order->user?->name, $order->status, $order->currency, $order->total]);
            }
            fclose($handle);
        }, 'orders-export.csv');
    }

    private function streamPayments(): StreamedResponse
    {
        $payments = Payment::with(['order', 'user'])->latest()->get();

        return response()->streamDownload(function () use ($payments) {
            $handle = fopen('php://output', 'w');
            fputcsv($handle, ['Order Number', 'Customer', 'Status', 'Provider', 'Amount', 'Transaction Ref']);
            foreach ($payments as $payment) {
                fputcsv($handle, [$payment->order?->order_number, $payment->user?->name, $payment->status, $payment->provider, $payment->amount, $payment->provider_transaction_id]);
            }
            fclose($handle);
        }, 'payments-export.csv');
    }

    private function streamInvoices(): StreamedResponse
    {
        $invoices = Invoice::with('order.user')->latest()->get();

        return response()->streamDownload(function () use ($invoices) {
            $handle = fopen('php://output', 'w');
            fputcsv($handle, ['Invoice Number', 'Order Number', 'Customer', 'Status', 'Total']);
            foreach ($invoices as $invoice) {
                fputcsv($handle, [$invoice->invoice_number, $invoice->order?->order_number, $invoice->order?->user?->name, $invoice->status, $invoice->total]);
            }
            fclose($handle);
        }, 'invoices-export.csv');
    }

    private function streamSubscriptions(): StreamedResponse
    {
        $subscriptions = Subscription::with(['user', 'package'])->latest()->get();

        return response()->streamDownload(function () use ($subscriptions) {
            $handle = fopen('php://output', 'w');
            fputcsv($handle, ['Package', 'User', 'Status', 'Starts At', 'Ends At']);
            foreach ($subscriptions as $subscription) {
                fputcsv($handle, [$subscription->package?->name, $subscription->user?->name, $subscription->status, $subscription->starts_at, $subscription->ends_at]);
            }
            fclose($handle);
        }, 'subscriptions-export.csv');
    }
}
