<?php

namespace App\Http\Requests\Account;

use App\Models\Listing;
use App\Support\Validation\UploadRules;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class AdCampaignRequest extends FormRequest
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
            'body' => ['nullable', 'string', 'max:5000'],
            'destination_url' => ['nullable', 'url', 'max:2048'],
            'event_id' => ['nullable', 'integer', $this->eventRule()],
            'placement' => ['required', Rule::in([
                'banner',
                'sitewide_banner',
                'in_article_intro',
                'in_article_mid',
                'in_article_end',
                'popup',
            ])],
            'start_at' => ['nullable', 'date'],
            'end_at' => ['nullable', 'date', 'after_or_equal:start_at'],
            'creative_image_upload' => UploadRules::optionalPublicImage(),
            'remove_creative_image' => ['nullable', 'boolean'],
            'status' => ['required', Rule::in(['draft', 'ready', 'active'])],
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
