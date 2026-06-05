<?php

namespace App\Http\Requests\Checkout;

use Illuminate\Foundation\Http\FormRequest;

class StartCheckoutRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    public function rules(): array
    {
        return [
            'package_slug' => ['required', 'string', 'exists:packages,slug'],
            'listing_slug' => ['nullable', 'string', 'exists:listings,slug'],
            'event_slug' => ['nullable', 'string', 'exists:events,slug'],
            'campaign_slug' => ['nullable', 'string', 'exists:ad_campaigns,slug'],
            'push_campaign_slug' => ['nullable', 'string', 'exists:push_campaigns,slug'],
            'renewal_subscription_id' => ['nullable', 'integer', 'exists:subscriptions,id'],
        ];
    }
}
