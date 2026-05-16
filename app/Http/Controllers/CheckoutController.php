<?php

namespace App\Http\Controllers;

use App\Models\AdCampaign;
use App\Models\Event;
use App\Models\Invoice;
use App\Models\Listing;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Payment;
use App\Models\Package;
use App\Models\PushCampaign;
use App\Models\Setting;
use App\Models\Subscription;
use App\Services\NotificationDispatchService;
use App\Services\PayFastCheckoutService;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Response;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class CheckoutController extends Controller
{
    public function basket(Request $request): View
    {
        $package = null;
        $listing = null;
        $event = null;
        $campaign = null;
        $pushCampaign = null;

        if ($request->filled('package')) {
            $package = Package::with('type')->where('slug', $request->string('package'))->first();
        }

        if ($request->filled('listing')) {
            $listing = Listing::where('slug', $request->string('listing'))->first();
        }

        if ($request->filled('event')) {
            $event = Event::with('listing')->where('slug', $request->string('event'))->first();
        }

        if ($request->filled('campaign')) {
            $campaign = AdCampaign::with(['listing', 'event'])->where('slug', $request->string('campaign'))->first();
            $listing = $campaign?->listing;
            $event = $campaign?->event;
        }

        if ($request->filled('push_campaign')) {
            $pushCampaign = PushCampaign::with(['listing', 'event'])->where('slug', $request->string('push_campaign'))->first();
            $listing = $pushCampaign?->listing;
            $event = $pushCampaign?->event;
        }

        return view('checkout.basket', compact('package', 'listing', 'event', 'campaign', 'pushCampaign'));
    }

    public function index(Request $request): View
    {
        $selectedPackage = null;
        $selectedListing = null;
        $selectedEvent = null;
        $selectedCampaign = null;
        $selectedPushCampaign = null;

        if ($request->filled('listing')) {
            $selectedListing = Listing::where('slug', $request->string('listing'))->first();
        }

        if ($request->filled('event')) {
            $selectedEvent = Event::with('listing')->where('slug', $request->string('event'))->first();
            $selectedListing = $selectedEvent?->listing;
        }

        if ($request->filled('campaign')) {
            $selectedCampaign = AdCampaign::with(['listing', 'event'])->where('slug', $request->string('campaign'))->first();
            $selectedListing = $selectedCampaign?->listing;
            $selectedEvent = $selectedCampaign?->event;
        }

        if ($request->filled('push_campaign')) {
            $selectedPushCampaign = PushCampaign::with(['listing', 'event'])->where('slug', $request->string('push_campaign'))->first();
            $selectedListing = $selectedPushCampaign?->listing;
            $selectedEvent = $selectedPushCampaign?->event;
        }

        $packageType = $selectedPushCampaign
            ? 'push_campaign'
            : ($selectedCampaign ? 'advert_package' : ($selectedEvent ? 'event_package' : 'business_directory'));

        $packages = Package::with('type', 'prices')
            ->active()
            ->whereHas('type', fn ($query) => $query->where('slug', $packageType))
            ->get();

        if ($request->filled('package')) {
            $selectedPackage = $packages->firstWhere('slug', $request->string('package')->toString());
        }

        return view('checkout.index', compact('packages', 'selectedPackage', 'selectedListing', 'selectedEvent', 'selectedCampaign', 'selectedPushCampaign', 'packageType'));
    }

    public function start(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'package_slug' => ['required', 'string', 'exists:packages,slug'],
            'listing_slug' => ['nullable', 'string', 'exists:listings,slug'],
            'event_slug' => ['nullable', 'string', 'exists:events,slug'],
            'campaign_slug' => ['nullable', 'string', 'exists:ad_campaigns,slug'],
            'push_campaign_slug' => ['nullable', 'string', 'exists:push_campaigns,slug'],
        ]);

        $package = Package::with('type', 'prices')->active()->where('slug', $validated['package_slug'])->firstOrFail();
        $listing = ! empty($validated['listing_slug']) ? Listing::where('slug', $validated['listing_slug'])->firstOrFail() : null;
        $event = ! empty($validated['event_slug']) ? Event::with('listing')->where('slug', $validated['event_slug'])->firstOrFail() : null;
        $campaign = ! empty($validated['campaign_slug']) ? AdCampaign::with(['listing', 'event'])->where('slug', $validated['campaign_slug'])->firstOrFail() : null;
        $pushCampaign = ! empty($validated['push_campaign_slug']) ? PushCampaign::with(['listing', 'event'])->where('slug', $validated['push_campaign_slug'])->firstOrFail() : null;

        if (! $listing && ! $event && ! $campaign && ! $pushCampaign) {
            throw ValidationException::withMessages([
                'listing_slug' => 'A listing, event, advert campaign, or push campaign is required to start checkout.',
            ]);
        }

        if ($event) {
            $listing = $event->listing;
        }

        if ($campaign) {
            $listing = $campaign->listing;
            $event = $campaign->event;
        }

        if ($pushCampaign) {
            $listing = $pushCampaign->listing;
            $event = $pushCampaign->event;
        }

        if ($listing?->user_id) {
            Gate::authorize('startCheckout', $listing);
        }

        if ($package->type?->slug === 'event_package') {
            if (! $event || ! $listing || ! $listing->hasActiveBusinessEntitlement()) {
                throw ValidationException::withMessages([
                    'event_slug' => 'Event packages require an event linked to a listing with an active business entitlement.',
                ]);
            }
        }

        if ($package->type?->slug === 'advert_package') {
            if (! $campaign || ! $listing || ! $listing->hasActiveBusinessEntitlement()) {
                throw ValidationException::withMessages([
                    'campaign_slug' => 'Advert packages require a campaign linked to a listing with an active business entitlement.',
                ]);
            }
        }

        if ($package->type?->slug === 'push_campaign') {
            if (! $pushCampaign || ! $listing || ! $listing->hasActiveBusinessEntitlement()) {
                throw ValidationException::withMessages([
                    'push_campaign_slug' => 'Push packages require a push campaign linked to a listing with an active business entitlement.',
                ]);
            }
        }

        $price = $package->currentPrice();

        if (! $price) {
            throw ValidationException::withMessages([
                'package_slug' => 'Selected package does not currently have an active price.',
            ]);
        }

        $amount = (float) $price->amount;
        $vatPercentage = (float) Setting::getValue('billing.vat_percentage', 15);
        $vatAmount = $price->vat_inclusive ? round($amount - ($amount / (1 + ($vatPercentage / 100))), 2) : round($amount * ($vatPercentage / 100), 2);
        $subtotal = $price->vat_inclusive ? round($amount - $vatAmount, 2) : $amount;
        $total = $price->vat_inclusive ? $amount : round($amount + $vatAmount, 2);

        $order = Order::create([
            'user_id' => $request->user()->id,
            'order_number' => $this->nextOrderNumber(),
            'status' => 'pending_payment',
            'currency' => $price->currency,
            'subtotal' => $subtotal,
            'vat_amount' => $vatAmount,
            'total' => $total,
        ]);

        OrderItem::create([
            'order_id' => $order->id,
            'package_id' => $package->id,
            'purchasable_type' => $pushCampaign ? PushCampaign::class : ($campaign ? AdCampaign::class : ($event ? Event::class : Listing::class)),
            'purchasable_id' => $pushCampaign?->id ?? $campaign?->id ?? $event?->id ?? $listing?->id,
            'name_snapshot' => $package->name,
            'unit_price' => $price->amount,
            'quantity' => 1,
            'billing_model' => $package->billing_model,
            'starts_at' => now(),
            'ends_at' => now()->copy()->addDays($package->duration_days),
        ]);

        Invoice::create([
            'order_id' => $order->id,
            'invoice_number' => $this->nextInvoiceNumber(),
            'invoice_prefix_snapshot' => (string) Setting::getValue('billing.invoice_prefix', 'LIFE'),
            'status' => 'draft',
            'currency' => $price->currency,
            'subtotal' => $subtotal,
            'vat_amount' => $vatAmount,
            'total' => $total,
        ]);

        Payment::create([
            'order_id' => $order->id,
            'user_id' => $request->user()->id,
            'provider' => 'payfast',
            'status' => 'pending',
            'amount' => $total,
            'currency' => $price->currency,
        ]);

        return redirect()->route('checkout.show', $order);
    }

    public function show(Request $request, Order $order): View
    {
        Gate::authorize('manage', $order);

        $order->load(['items.package', 'items.purchasable', 'payments', 'invoices']);

        return view('checkout.show', [
            'order' => $order,
            'payment' => $order->latestPayment(),
            'invoice' => $order->latestInvoice(),
            'latestAttempt' => $order->latestPayment()?->attempts()->latest()->first(),
        ]);
    }

    public function payfastInitiate(Request $request, Order $order, PayFastCheckoutService $payFastCheckoutService): RedirectResponse
    {
        Gate::authorize('manage', $order);

        $order->loadMissing(['user', 'payments']);
        $payment = $order->latestPayment() ?? $order->payments()->create([
            'user_id' => $order->user_id,
            'provider' => 'payfast',
            'status' => 'pending',
            'amount' => $order->total,
            'currency' => $order->currency,
        ]);

        $attempt = $payFastCheckoutService->initiate(
            $order,
            $payment,
            route('checkout.payfast.callback'),
            route('checkout.show', $order),
            route('checkout.show', $order)
        );

        return redirect()->route('checkout.show', $order)->with('status', 'PayFast initiation payload generated for this order.')
            ->with('payfast_attempt_id', $attempt->id);
    }

    public function retryPayment(Request $request, Order $order, PayFastCheckoutService $payFastCheckoutService): RedirectResponse
    {
        Gate::authorize('manage', $order);

        if ($order->status === 'paid') {
            return redirect()->route('checkout.show', $order)->with('status', 'This order is already paid.');
        }

        $payment = $order->payments()->create([
            'user_id' => $order->user_id,
            'provider' => 'payfast',
            'status' => 'pending',
            'amount' => $order->total,
            'currency' => $order->currency,
        ]);

        $order->update([
            'status' => 'pending_payment',
        ]);

        $payFastCheckoutService->initiate(
            $order->loadMissing('user'),
            $payment,
            route('checkout.payfast.callback'),
            route('checkout.show', $order),
            route('checkout.show', $order)
        );

        return redirect()->route('checkout.show', $order)->with('status', 'A new payment attempt has been created for this order.');
    }

    public function sendInvoice(Request $request, Order $order): RedirectResponse
    {
        Gate::authorize('manage', $order);

        $invoice = $order->latestInvoice() ?? $order->invoices()->firstOrFail();
        $invoice->loadMissing('order.user');
        if ($invoice->order?->user?->email) {
            try {
                app(NotificationDispatchService::class)->sendInvoiceIssued($invoice);
            } catch (\RuntimeException $exception) {
                return redirect()->route('checkout.show', $order)->withErrors([
                    'invoice' => $exception->getMessage(),
                ]);
            }
        }
        $invoice->markEmailed();

        return redirect()->route('checkout.show', $order)->with('status', 'Invoice marked as sent.');
    }

    public function renewSubscription(Request $request, Subscription $subscription): RedirectResponse
    {
        $subscription->loadMissing(['package', 'subscribable']);

        Gate::authorize('manage', $subscription);

        $entity = $subscription->subscribable;

        if ($entity instanceof Event) {
            return redirect()->route('checkout.index', [
                'package' => $subscription->package?->slug,
                'event' => $entity->slug,
            ]);
        }

        if ($entity instanceof AdCampaign) {
            return redirect()->route('checkout.index', [
                'package' => $subscription->package?->slug,
                'campaign' => $entity->slug,
            ]);
        }

        if ($entity instanceof PushCampaign) {
            return redirect()->route('checkout.index', [
                'package' => $subscription->package?->slug,
                'push_campaign' => $entity->slug,
            ]);
        }

        if ($entity instanceof Listing) {
            return redirect()->route('checkout.index', [
                'package' => $subscription->package?->slug,
                'listing' => $entity->slug,
            ]);
        }

        abort(404);
    }

    public function payfastCallback(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'order_number' => ['required', 'string', 'exists:orders,order_number'],
            'status' => ['required', 'string'],
            'provider_transaction_id' => ['nullable', 'string', 'max:255'],
            'failure_reason' => ['nullable', 'string'],
            'amount_gross' => ['nullable', 'numeric', 'min:0'],
            'amount' => ['nullable', 'numeric', 'min:0'],
            'currency' => ['nullable', 'string', 'size:3'],
            'signature' => ['required', 'string'],
        ]);

        $order = Order::with('payments.attempts')->where('order_number', $validated['order_number'])->firstOrFail();
        $payment = $order->latestPayment() ?? $order->payments()->latest('id')->firstOrFail();
        $normalizedStatus = strtolower($validated['status']);
        $latestAttempt = $payment->attempts()->latest()->first();

        if (! app(PayFastCheckoutService::class)->verifyCallback($validated)) {
            $latestAttempt?->update([
                'status' => 'invalid_signature',
                'response_payload_json' => $validated,
            ]);

            return response()->json([
                'ok' => false,
                'error' => 'Invalid signature.',
            ], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        if (in_array($normalizedStatus, ['complete', 'completed', 'paid', 'success'], true)) {
            $providerTransactionId = (string) ($validated['provider_transaction_id'] ?? '');

            if ($providerTransactionId === '') {
                throw ValidationException::withMessages([
                    'provider_transaction_id' => 'A paid PayFast callback requires a transaction reference.',
                ]);
            }

            $duplicatePayment = Payment::where('provider_transaction_id', $providerTransactionId)
                ->whereKeyNot($payment->id)
                ->first();

            if ($duplicatePayment) {
                $latestAttempt?->update([
                    'status' => 'duplicate_transaction',
                    'response_payload_json' => $validated,
                ]);

                return response()->json([
                    'ok' => false,
                    'error' => 'Duplicate PayFast transaction reference.',
                ], Response::HTTP_CONFLICT);
            }

            $callbackAmount = $validated['amount_gross'] ?? $validated['amount'] ?? null;
            if ($callbackAmount !== null && abs((float) $callbackAmount - (float) $payment->amount) > 0.01) {
                $latestAttempt?->update([
                    'status' => 'amount_mismatch',
                    'response_payload_json' => $validated,
                ]);

                return response()->json([
                    'ok' => false,
                    'error' => 'Callback amount does not match the pending payment.',
                ], Response::HTTP_UNPROCESSABLE_ENTITY);
            }

            if (! empty($validated['currency']) && strtoupper($validated['currency']) !== strtoupper($payment->currency)) {
                $latestAttempt?->update([
                    'status' => 'currency_mismatch',
                    'response_payload_json' => $validated,
                ]);

                return response()->json([
                    'ok' => false,
                    'error' => 'Callback currency does not match the pending payment.',
                ], Response::HTTP_UNPROCESSABLE_ENTITY);
            }

            $payment->update([
                'status' => 'paid',
                'paid_at' => now(),
                'provider_transaction_id' => $providerTransactionId,
                'failure_reason' => null,
            ]);
            $latestAttempt?->update([
                'status' => 'completed',
                'response_payload_json' => $validated,
            ]);
        } elseif (in_array($normalizedStatus, ['failed', 'cancelled', 'canceled'], true)) {
            $payment->update([
                'status' => 'failed',
                'failure_reason' => $validated['failure_reason'] ?: 'Payment failed or was cancelled.',
            ]);
            $latestAttempt?->update([
                'status' => 'failed',
                'response_payload_json' => $validated,
            ]);

            $order->update([
                'status' => 'cancelled',
            ]);
        }

        return response()->json([
            'ok' => true,
            'order_number' => $order->order_number,
            'payment_status' => $payment->fresh()->status,
        ]);
    }

    private function nextOrderNumber(): string
    {
        return 'ORD-'.now()->format('YmdHis').'-'.Str::upper(Str::random(4));
    }

    private function nextInvoiceNumber(): string
    {
        $prefix = (string) Setting::getValue('billing.invoice_prefix', 'LIFE');

        return $prefix.'-'.now()->format('YmdHis').'-'.Str::upper(Str::random(4));
    }
}
