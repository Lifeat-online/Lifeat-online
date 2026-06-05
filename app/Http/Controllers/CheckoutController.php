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
use App\Models\NumberSequence;
use App\Exceptions\CallbackRejectionException;
use App\Services\NotificationDispatchService;
use App\Services\PayFastCheckoutService;
use App\Services\SubscriptionRenewalService;
use App\Support\Caching\PublicReadCache;
use App\Support\Logging\OperationalLog;
use App\Support\Onboarding\ListingOnboardingChecklist;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Response;
use Illuminate\Http\RedirectResponse;
use App\Http\Requests\Checkout\StartCheckoutRequest;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
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

    public function index(Request $request, ListingOnboardingChecklist $onboarding): View
    {
        $selectedPackage = null;
        $selectedListing = null;
        $selectedEvent = null;
        $selectedCampaign = null;
        $selectedPushCampaign = null;
        $selectedRenewalSubscription = null;

        if ($request->filled('renewal_subscription')) {
            $selectedRenewalSubscription = Subscription::with(['package', 'subscribable'])->findOrFail($request->integer('renewal_subscription'));
            Gate::authorize('manage', $selectedRenewalSubscription);
        }

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

        if ($selectedRenewalSubscription) {
            $entity = $selectedRenewalSubscription->subscribable;

            if ($entity instanceof Event) {
                $selectedEvent ??= $entity->loadMissing('listing');
                $selectedListing ??= $selectedEvent->listing;
            } elseif ($entity instanceof AdCampaign) {
                $selectedCampaign ??= $entity->loadMissing(['listing', 'event']);
                $selectedListing ??= $selectedCampaign->listing;
                $selectedEvent ??= $selectedCampaign->event;
            } elseif ($entity instanceof PushCampaign) {
                $selectedPushCampaign ??= $entity->loadMissing(['listing', 'event']);
                $selectedListing ??= $selectedPushCampaign->listing;
                $selectedEvent ??= $selectedPushCampaign->event;
            } elseif ($entity instanceof Listing) {
                $selectedListing ??= $entity;
            }
        }

        $packageType = $selectedPushCampaign
            ? 'push_campaign'
            : ($selectedCampaign ? 'advert_package' : ($selectedEvent ? 'event_package' : 'business_directory'));

        $packages = PublicReadCache::activePackagesForType($packageType);

        if ($request->filled('package')) {
            $selectedPackage = $packages->firstWhere('slug', $request->string('package')->toString());
        }

        $listingOnboarding = $selectedListing && $request->user()?->can('manage', $selectedListing)
            ? $onboarding->forListing($selectedListing)
            : null;

        return view('checkout.index', compact('packages', 'selectedPackage', 'selectedListing', 'selectedEvent', 'selectedCampaign', 'selectedPushCampaign', 'selectedRenewalSubscription', 'packageType', 'listingOnboarding'));
    }

    public function start(StartCheckoutRequest $request): RedirectResponse
    {
        $validated = $request->validated();

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

        $renewalSubscription = ! empty($validated['renewal_subscription_id'])
            ? Subscription::with(['package.type', 'package.prices', 'subscribable'])->findOrFail($validated['renewal_subscription_id'])
            : null;

        if ($renewalSubscription) {
            Gate::authorize('manage', $renewalSubscription);

            if ((int) $renewalSubscription->package_id !== (int) $package->id) {
                throw ValidationException::withMessages([
                    'package_slug' => 'Renewal checkout must use the existing subscription package.',
                ]);
            }
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

        if ($renewalSubscription) {
            $order = app(SubscriptionRenewalService::class)->createRenewalOrder($renewalSubscription);

            return redirect()->route('checkout.show', $order);
        }

        $amount = (float) $price->amount;
        $vatPercentage = (float) Setting::getValue('billing.vat_percentage', 15);
        $vatAmount = $price->vat_inclusive ? round($amount - ($amount / (1 + ($vatPercentage / 100))), 2) : round($amount * ($vatPercentage / 100), 2);
        $subtotal = $price->vat_inclusive ? round($amount - $vatAmount, 2) : $amount;
        $total = $price->vat_inclusive ? $amount : round($amount + $vatAmount, 2);

        $order = DB::transaction(function () use ($request, $package, $pushCampaign, $campaign, $event, $listing, $price, $subtotal, $vatAmount, $total) {
            $order = Order::create([
                'user_id' => $request->user()->id,
                'order_number' => NumberSequence::next('order', 'ORD'),
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

            $invoicePrefix = (string) Setting::getValue('billing.invoice_prefix', 'INV');

            Invoice::create([
                'order_id' => $order->id,
                'invoice_number' => NumberSequence::next('invoice', $invoicePrefix),
                'invoice_prefix_snapshot' => $invoicePrefix,
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

            return $order;
        });

        OperationalLog::info('checkout.order_created', [
            'order_id' => $order->id,
            'order_number' => $order->order_number,
            'user_id' => $request->user()->id,
            'package_id' => $package->id,
            'package_slug' => $package->slug,
            'package_type' => $package->type?->slug,
            'listing_id' => $listing?->id,
            'event_id' => $event?->id,
            'ad_campaign_id' => $campaign?->id,
            'push_campaign_id' => $pushCampaign?->id,
            'amount' => $total,
            'currency' => $price->currency,
        ]);

        return redirect()->route('checkout.show', $order);
    }

    public function show(Request $request, Order $order, ListingOnboardingChecklist $onboarding): View
    {
        Gate::authorize('manage', $order);

        $order->load(['items.package', 'items.purchasable', 'payments.attempts', 'invoices', 'renewedSubscription.package']);
        $payment = $order->payments->sortByDesc('id')->first();
        $paymentAttempts = $order->payments
            ->flatMap(fn ($payment) => $payment->attempts)
            ->sortByDesc('attempted_at')
            ->values();
        $listing = $order->items
            ->map(fn ($item) => $item->purchasable)
            ->first(fn ($purchasable) => $purchasable instanceof Listing);

        return view('checkout.show', [
            'order' => $order,
            'payment' => $payment,
            'invoice' => $order->latestInvoice(),
            'latestAttempt' => $paymentAttempts->first(),
            'paymentAttempts' => $paymentAttempts,
            'listingOnboarding' => $listing ? $onboarding->forListing($listing, $order) : null,
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

        return redirect()->route('checkout.show', $order)->with('status', 'PayFast payment handoff prepared for this order.')
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

        $attempt = $payFastCheckoutService->initiate(
            $order->loadMissing('user'),
            $payment,
            route('checkout.payfast.callback'),
            route('checkout.show', $order),
            route('checkout.show', $order)
        );

        OperationalLog::info('payment.retry_created', [
            'order_id' => $order->id,
            'order_number' => $order->order_number,
            'payment_id' => $payment->id,
            'payment_attempt_id' => $attempt->id,
            'user_id' => $order->user_id,
            'amount' => (float) $payment->amount,
            'currency' => $payment->currency,
        ]);

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

        return redirect()->route('checkout.show', $order)->with('status', 'Invoice email sent.');
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
                'renewal_subscription' => $subscription->id,
            ]);
        }

        if ($entity instanceof AdCampaign) {
            return redirect()->route('checkout.index', [
                'package' => $subscription->package?->slug,
                'campaign' => $entity->slug,
                'renewal_subscription' => $subscription->id,
            ]);
        }

        if ($entity instanceof PushCampaign) {
            return redirect()->route('checkout.index', [
                'package' => $subscription->package?->slug,
                'push_campaign' => $entity->slug,
                'renewal_subscription' => $subscription->id,
            ]);
        }

        if ($entity instanceof Listing) {
            return redirect()->route('checkout.index', [
                'package' => $subscription->package?->slug,
                'listing' => $entity->slug,
                'renewal_subscription' => $subscription->id,
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
        $callbackContext = [
            'order_id' => $order->id,
            'order_number' => $order->order_number,
            'payment_id' => $payment->id,
            'payment_attempt_id' => $latestAttempt?->id,
            'provider' => $payment->provider,
            'callback_status' => $normalizedStatus,
            'current_payment_status' => $payment->status,
            'current_order_status' => $order->status,
            'provider_transaction_id' => $validated['provider_transaction_id'] ?? null,
            'callback_amount' => $validated['amount_gross'] ?? $validated['amount'] ?? null,
            'callback_currency' => $validated['currency'] ?? null,
        ];

        OperationalLog::info('payment.callback_received', $callbackContext);

        if (! app(PayFastCheckoutService::class)->verifyCallback($validated)) {
            $latestAttempt?->update([
                'status' => 'invalid_signature',
                'response_payload_json' => $validated,
            ]);

            OperationalLog::warning('payment.callback_invalid_signature', $callbackContext);

            return response()->json([
                'ok' => false,
                'error' => 'Invalid signature.',
            ], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        if (in_array($normalizedStatus, ['complete', 'completed', 'paid', 'success'], true)) {
            $providerTransactionId = (string) ($validated['provider_transaction_id'] ?? '');

            if ($providerTransactionId === '') {
                OperationalLog::warning('payment.callback_rejected', array_merge($callbackContext, [
                    'rejection_reason' => 'missing_provider_transaction_id',
                ]));

                throw ValidationException::withMessages([
                    'provider_transaction_id' => 'A paid PayFast callback requires a transaction reference.',
                ]);
            }

            try {
                DB::transaction(function () use ($order, $payment, $latestAttempt, $providerTransactionId, $validated, $callbackContext) {
                    $duplicatePayment = Payment::where('provider_transaction_id', $providerTransactionId)
                        ->whereKeyNot($payment->id)
                        ->lockForUpdate()
                        ->first();

                    if ($duplicatePayment) {
                        $latestAttempt?->update([
                            'status' => 'duplicate_transaction',
                            'response_payload_json' => $validated,
                        ]);

                        OperationalLog::warning('payment.callback_rejected', array_merge($callbackContext, [
                            'rejection_reason' => 'duplicate_transaction',
                            'duplicate_payment_id' => $duplicatePayment->id,
                        ]));

                        throw new CallbackRejectionException('Duplicate PayFast transaction reference.', Response::HTTP_CONFLICT);
                    }

                    $callbackAmount = $validated['amount_gross'] ?? $validated['amount'] ?? null;
                    if ($callbackAmount !== null && abs((float) $callbackAmount - (float) $payment->amount) > 0.01) {
                        $latestAttempt?->update([
                            'status' => 'amount_mismatch',
                            'response_payload_json' => $validated,
                        ]);

                        OperationalLog::warning('payment.callback_rejected', array_merge($callbackContext, [
                            'rejection_reason' => 'amount_mismatch',
                            'expected_amount' => (float) $payment->amount,
                        ]));

                        throw new CallbackRejectionException('Callback amount does not match the pending payment.', Response::HTTP_UNPROCESSABLE_ENTITY);
                    }

                    if (! empty($validated['currency']) && strtoupper($validated['currency']) !== strtoupper($payment->currency)) {
                        $latestAttempt?->update([
                            'status' => 'currency_mismatch',
                            'response_payload_json' => $validated,
                        ]);

                        OperationalLog::warning('payment.callback_rejected', array_merge($callbackContext, [
                            'rejection_reason' => 'currency_mismatch',
                            'expected_currency' => $payment->currency,
                        ]));

                        throw new CallbackRejectionException('Callback currency does not match the pending payment.', Response::HTTP_UNPROCESSABLE_ENTITY);
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
                });

                OperationalLog::info('payment.callback_paid', array_merge($callbackContext, [
                    'payment_status' => 'paid',
                    'order_status' => $order->fresh()->status,
                ]));
            } catch (CallbackRejectionException $exception) {
                return response()->json([
                    'ok' => false,
                    'error' => $exception->getMessage(),
                ], $exception->getCode());
            }
        } elseif (in_array($normalizedStatus, ['failed', 'cancelled', 'canceled'], true)) {
            if ($payment->status === 'paid' || $order->status === 'paid') {
                OperationalLog::info('payment.callback_ignored', array_merge($callbackContext, [
                    'ignored_reason' => 'already_paid',
                    'payment_status' => $payment->fresh()->status,
                    'order_status' => $order->fresh()->status,
                ]));

                return response()->json([
                    'ok' => true,
                    'order_number' => $order->order_number,
                    'payment_status' => $payment->fresh()->status,
                    'ignored' => 'Payment is already paid.',
                ]);
            }

            DB::transaction(function () use ($order, $payment, $latestAttempt, $validated) {
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
            });

            OperationalLog::warning('payment.callback_failed', array_merge($callbackContext, [
                'payment_status' => $payment->fresh()->status,
                'order_status' => $order->fresh()->status,
                'failure_reason' => $validated['failure_reason'] ?? null,
            ]));
        } else {
            OperationalLog::warning('payment.callback_unhandled_status', $callbackContext);
        }

        return response()->json([
            'ok' => true,
            'order_number' => $order->order_number,
            'payment_status' => $payment->fresh()->status,
        ]);
    }
}
