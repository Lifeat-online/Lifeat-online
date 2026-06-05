<?php

namespace App\Support\Onboarding;

use App\Models\Listing;
use App\Models\Order;

class ListingOnboardingChecklist
{
    public function forListing(Listing $listing, ?Order $latestOrder = null): array
    {
        $listing->loadMissing([
            'categories',
            'photos',
            'activeSubscription.package',
            'subscriptions.package',
        ]);

        $latestOrder ??= $this->latestOrderFor($listing);
        $latestPayment = $latestOrder?->latestPayment();
        $hasActiveEntitlement = $listing->hasActiveBusinessEntitlement();
        $profileComplete = $this->profileComplete($listing);
        $mediaReady = $this->mediaReady($listing);
        $paid = $hasActiveEntitlement || $latestOrder?->status === 'paid' || $latestPayment?->status === 'paid';
        $publiclyVisible = $listing->status === 'published' && $hasActiveEntitlement;
        $growthStarted = $this->growthStarted($listing);
        $profileStatus = $profileComplete && $mediaReady
            ? 'done'
            : ($latestOrder && ! $paid ? 'pending' : 'next');

        $steps = [
            [
                'label' => 'Starter created',
                'status' => 'done',
                'detail' => 'Your listing workspace exists and can be edited at any time.',
                'action_label' => 'Open workspace',
                'action_url' => route('account.listings.show', $listing),
            ],
            [
                'label' => 'Profile basics',
                'status' => $profileStatus,
                'detail' => $profileComplete && $mediaReady
                    ? 'Business details, category, contact details, and listing media are in place.'
                    : 'Add a description, city, category, contact option, and at least one logo, cover image, or gallery photo.',
                'action_label' => 'Edit profile',
                'action_url' => route('account.listings.edit', $listing),
            ],
            [
                'label' => 'Checkout and payment',
                'status' => $paid ? 'done' : 'next',
                'detail' => $paid
                    ? 'Payment is complete and the directory entitlement has been created.'
                    : ($latestOrder
                        ? 'An order exists. Complete the PayFast handoff or retry payment if the attempt failed.'
                        : 'Choose a business directory package and create the order for this listing.'),
                'action_label' => $latestOrder ? 'Open latest order' : 'Choose package',
                'action_url' => $latestOrder ? route('checkout.show', $latestOrder) : route('checkout.index', ['listing' => $listing->slug]),
            ],
            [
                'label' => 'Public activation',
                'status' => $publiclyVisible ? 'done' : ($hasActiveEntitlement ? 'next' : 'pending'),
                'detail' => $publiclyVisible
                    ? 'The listing is published with an active business entitlement.'
                    : ($hasActiveEntitlement
                        ? 'The package is active. Review the public profile and make any final profile edits.'
                        : 'Activation happens automatically once the directory payment is confirmed.'),
                'action_label' => $publiclyVisible ? 'View public listing' : ($hasActiveEntitlement ? 'Edit profile' : null),
                'action_url' => $publiclyVisible ? route('directory.show', $listing) : ($hasActiveEntitlement ? route('account.listings.edit', $listing) : null),
            ],
            [
                'label' => 'Growth tools',
                'status' => $growthStarted ? 'done' : ($publiclyVisible ? 'next' : 'pending'),
                'detail' => $growthStarted
                    ? 'At least one event, advert, push campaign, or voucher has been started from this listing.'
                    : ($publiclyVisible
                        ? 'Use the workspace to add an event, advert campaign, push campaign, or voucher when you are ready.'
                        : 'Promotion tools unlock cleanly after the business directory package is active.'),
                'action_label' => $publiclyVisible ? 'Open workspace' : null,
                'action_url' => $publiclyVisible ? route('account.listings.show', $listing) : null,
            ],
        ];

        return [
            'completed' => collect($steps)->where('status', 'done')->count(),
            'total' => count($steps),
            'next' => collect($steps)->firstWhere('status', 'next') ?: collect($steps)->firstWhere('status', 'pending'),
            'steps' => $steps,
        ];
    }

    private function latestOrderFor(Listing $listing): ?Order
    {
        $orderItem = $listing->orderItems()
            ->with(['order.payments', 'order.invoices'])
            ->latest('id')
            ->first();

        return $orderItem?->order;
    }

    private function profileComplete(Listing $listing): bool
    {
        return filled($listing->description)
            && filled($listing->city)
            && ($listing->categories->isNotEmpty())
            && (filled($listing->email) || filled($listing->phone) || filled($listing->website_url));
    }

    private function mediaReady(Listing $listing): bool
    {
        return filled($listing->featured_image)
            || filled($listing->logo_path)
            || $listing->photos->isNotEmpty();
    }

    private function growthStarted(Listing $listing): bool
    {
        return $this->relationHasRecords($listing, 'events')
            || $this->relationHasRecords($listing, 'adCampaigns')
            || $this->relationHasRecords($listing, 'pushCampaigns')
            || $this->relationHasRecords($listing, 'vouchers');
    }

    private function relationHasRecords(Listing $listing, string $relation): bool
    {
        if ($listing->relationLoaded($relation)) {
            return $listing->{$relation}->isNotEmpty();
        }

        return $listing->{$relation}()->exists();
    }
}
