<?php

namespace App\Http\Requests\Account;

use App\Models\Listing;
use App\Support\Validation\UploadRules;
use Illuminate\Foundation\Http\FormRequest;

class StoreListingPhotoRequest extends FormRequest
{
    public function authorize(): bool
    {
        $listing = $this->route('listing');

        return $listing instanceof Listing && $this->user()?->can('manage', $listing);
    }

    public function rules(): array
    {
        return [
            'photo_upload' => UploadRules::requiredPublicImage(),
            'caption' => ['nullable', 'string', 'max:255'],
        ];
    }
}
