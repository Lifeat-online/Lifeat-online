<?php

namespace App\Http\Requests\Account;

use App\Models\Listing;
use Illuminate\Foundation\Http\FormRequest;

class RespondToReviewRequest extends FormRequest
{
    public function authorize(): bool
    {
        $listing = $this->route('listing');

        return $listing instanceof Listing && $this->user()?->can('manage', $listing);
    }

    public function rules(): array
    {
        return [
            'owner_response' => ['required', 'string', 'max:3000'],
        ];
    }
}
