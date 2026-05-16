<?php

namespace App\Services;

use App\Models\AdCampaign;
use App\Models\Event;
use App\Models\Invoice;
use App\Models\Listing;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Package;
use App\Models\Payment;
use App\Models\PushCampaign;
use App\Models\Setting;
use App\Models\User;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class AdvertisingBundleCheckoutService
{
    public function create(User $user, array $data): Order
    {
        return DB::transaction(function () use ($user, $data) {
            $listingPackage = $this->package((string) $data['listing_package_slug'], 'business_directory');

            $listing = Listing::create([
                'user_id' => $user->id,
                'registered_by_user_id' => $user->hasRole('staff') ? $user->id : null,
                'source_channel' => $listingPackage->is_self_service ? 'self_service' : 'staff_assisted',
                'title' => $data['business_name'],
                'slug' => $this->uniqueSlug(Listing::class, $data['business_name'], 'listing'),
                'city' => $data['city'] ?? null,
                'status' => 'draft',
            ]);

            $items = [
                [
                    'package' => $listingPackage,
                    'purchasable_type' => Listing::class,
                    'purchasable_id' => $listing->id,
                    'name' => $listingPackage->name,
                ],
            ];

            if (! empty($data['event_package_slug'])) {
                $eventPackage = $this->package((string) $data['event_package_slug'], 'event_package');
                $event = Event::create([
                    'listing_id' => $listing->id,
                    'user_id' => $user->id,
                    'title' => $data['event_title'] ?: $listing->title.' event promotion',
                    'slug' => $this->uniqueSlug(Event::class, $data['event_title'] ?: $listing->title.' event', 'event'),
                    'city' => $listing->city,
                    'start_at' => now()->addWeeks(2),
                    'status' => 'draft',
                ]);

                $items[] = [
                    'package' => $eventPackage,
                    'purchasable_type' => Event::class,
                    'purchasable_id' => $event->id,
                    'name' => $eventPackage->name,
                ];
            }

            foreach (Arr::wrap($data['advert_package_slugs'] ?? []) as $slug) {
                $advertPackage = $this->package((string) $slug, 'advert_package');
                $placement = (string) ($advertPackage->settings_json['placement'] ?? 'banner');
                $campaign = AdCampaign::create([
                    'listing_id' => $listing->id,
                    'user_id' => $user->id,
                    'title' => $this->placementLabel($placement).' for '.$listing->title,
                    'slug' => $this->uniqueSlug(AdCampaign::class, $listing->title.' '.$placement.' advert', 'advert-campaign'),
                    'headline' => $listing->title,
                    'placement' => $placement,
                    'destination_url' => null,
                    'status' => 'ready',
                ]);

                $items[] = [
                    'package' => $advertPackage,
                    'purchasable_type' => AdCampaign::class,
                    'purchasable_id' => $campaign->id,
                    'name' => $advertPackage->name,
                ];
            }

            if (! empty($data['push_package_slug'])) {
                $pushPackage = $this->package((string) $data['push_package_slug'], 'push_campaign');
                $pushCampaign = PushCampaign::create([
                    'listing_id' => $listing->id,
                    'user_id' => $user->id,
                    'title' => $listing->title.' push notification',
                    'slug' => $this->uniqueSlug(PushCampaign::class, $listing->title.' push notification', 'push-campaign'),
                    'headline' => $listing->title,
                    'message' => 'Promotional push campaign for '.$listing->title.'.',
                    'audience_scope' => 'listing_city',
                    'target_city' => $listing->city,
                    'status' => 'ready',
                ]);

                $items[] = [
                    'package' => $pushPackage,
                    'purchasable_type' => PushCampaign::class,
                    'purchasable_id' => $pushCampaign->id,
                    'name' => $pushPackage->name,
                ];
            }

            [$subtotal, $vatAmount, $total, $currency] = $this->totals($items);

            $order = Order::create([
                'user_id' => $user->id,
                'referred_by_user_id' => $user->hasRole('staff') ? $user->id : null,
                'order_number' => $this->nextOrderNumber(),
                'status' => 'pending_payment',
                'currency' => $currency,
                'subtotal' => $subtotal,
                'vat_amount' => $vatAmount,
                'total' => $total,
            ]);

            foreach ($items as $item) {
                $package = $item['package'];
                $price = $package->currentPrice();
                $startsAt = now();

                OrderItem::create([
                    'order_id' => $order->id,
                    'package_id' => $package->id,
                    'purchasable_type' => $item['purchasable_type'],
                    'purchasable_id' => $item['purchasable_id'],
                    'name_snapshot' => $item['name'],
                    'unit_price' => $price->amount,
                    'quantity' => 1,
                    'billing_model' => $package->billing_model,
                    'starts_at' => $startsAt,
                    'ends_at' => $startsAt->copy()->addDays($package->duration_days),
                ]);
            }

            Invoice::create([
                'order_id' => $order->id,
                'invoice_number' => $this->nextInvoiceNumber(),
                'invoice_prefix_snapshot' => (string) Setting::getValue('billing.invoice_prefix', 'LIFE'),
                'status' => 'draft',
                'currency' => $currency,
                'subtotal' => $subtotal,
                'vat_amount' => $vatAmount,
                'total' => $total,
            ]);

            Payment::create([
                'order_id' => $order->id,
                'user_id' => $user->id,
                'provider' => 'payfast',
                'status' => 'pending',
                'amount' => $total,
                'currency' => $currency,
            ]);

            return $order->fresh(['items.package', 'payments', 'invoices']);
        });
    }

    private function package(string $slug, string $type): Package
    {
        $package = Package::with('type', 'prices')
            ->active()
            ->where('slug', $slug)
            ->whereHas('type', fn ($query) => $query->where('slug', $type))
            ->first();

        if (! $package || ! $package->currentPrice()) {
            throw ValidationException::withMessages([
                'package' => 'Selected package is not currently available.',
            ]);
        }

        return $package;
    }

    private function totals(array $items): array
    {
        $vatPercentage = (float) Setting::getValue('billing.vat_percentage', 15);
        $subtotal = 0.0;
        $vatAmount = 0.0;
        $total = 0.0;
        $currency = 'ZAR';

        foreach ($items as $item) {
            $price = $item['package']->currentPrice();
            $amount = (float) $price->amount;
            $currency = $price->currency;
            $itemVat = $price->vat_inclusive
                ? round($amount - ($amount / (1 + ($vatPercentage / 100))), 2)
                : round($amount * ($vatPercentage / 100), 2);

            $subtotal += $price->vat_inclusive ? round($amount - $itemVat, 2) : $amount;
            $vatAmount += $itemVat;
            $total += $price->vat_inclusive ? $amount : round($amount + $itemVat, 2);
        }

        return [round($subtotal, 2), round($vatAmount, 2), round($total, 2), $currency];
    }

    private function placementLabel(string $placement): string
    {
        return Str::headline(str_replace('in_article', 'in article', $placement));
    }

    private function uniqueSlug(string $modelClass, string $title, string $fallback): string
    {
        $base = Str::slug($title);
        $slug = $base !== '' ? $base : $fallback;
        $counter = 2;

        while ($modelClass::query()->where('slug', $slug)->exists()) {
            $slug = ($base !== '' ? $base : $fallback).'-'.$counter;
            $counter++;
        }

        return $slug;
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
