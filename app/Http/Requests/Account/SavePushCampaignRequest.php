<?php

namespace App\Http\Requests\Account;

use App\Models\Listing;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class SavePushCampaignRequest extends FormRequest
{
    public function authorize(): bool
    {
        $listing = $this->listing();

        return $listing !== null && $this->user()?->can('manage', $listing);
    }

    public function rules(): array
    {
        return [
            'title' => ['required', 'string', 'max:255'],
            'headline' => ['nullable', 'string', 'max:255'],
            'message' => ['required', 'string', 'max:2000'],
            'event_id' => ['nullable', 'integer', $this->eventRule()],
            'schedule_at' => ['nullable', 'date'],
            'audience_scope' => ['required', Rule::in(['listing_city', 'listing_region', 'custom_radius'])],
            'target_city' => ['nullable', 'string', 'max:255'],
            'target_region' => ['nullable', 'string', 'max:255'],
            'radius_km' => ['nullable', 'integer', 'min:1', 'max:200'],
            'status' => ['required', Rule::in(['draft', 'ready', 'scheduled', 'active'])],
        ];
    }

    private function listing(): ?Listing
    {
        $listing = $this->route('listing');

        return $listing instanceof Listing ? $listing : null;
    }

    private function eventRule(): mixed
    {
        $listing = $this->listing();

        return $listing
            ? Rule::exists('events', 'id')->where('listing_id', $listing->id)
            : Rule::exists('events', 'id');
    }
}
