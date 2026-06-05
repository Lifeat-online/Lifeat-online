<?php

namespace App\Http\Requests\Account;

use App\Models\Listing;
use App\Models\Voucher;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class SaveVoucherRequest extends FormRequest
{
    public function authorize(): bool
    {
        $listing = $this->route('listing');

        return $listing instanceof Listing && $this->user()?->can('manage', $listing);
    }

    public function rules(): array
    {
        return [
            'title' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:2000'],
            'voucher_type' => ['required', Rule::in([
                Voucher::TYPE_DISCOUNT_AMOUNT,
                Voucher::TYPE_DISCOUNT_PERCENT,
                Voucher::TYPE_FIXED_PRICE,
                Voucher::TYPE_PROMO_OFFER,
            ])],
            'discount_amount' => ['nullable', 'numeric', 'min:0'],
            'discount_percent' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'currency' => ['nullable', 'string', 'size:3'],
            'usage_limit' => ['required', 'integer', 'min:1'],
            'start_at' => ['nullable', 'date'],
            'end_at' => ['nullable', 'date', 'after_or_equal:start_at'],
            'terms' => ['nullable', 'string', 'max:6000'],
            'status' => ['required', Rule::in(['draft', 'published'])],
            'category_ids' => ['nullable', 'array'],
            'category_ids.*' => ['integer', Rule::exists('categories', 'id')->where('type', 'listing')],
        ];
    }

    public function validatedWithDefaults(): array
    {
        $data = $this->validated();

        if (in_array($data['voucher_type'], [Voucher::TYPE_DISCOUNT_AMOUNT, Voucher::TYPE_FIXED_PRICE], true) && ($data['discount_amount'] ?? null) === null) {
            $data['discount_amount'] = 0;
        }

        if ($data['voucher_type'] === Voucher::TYPE_DISCOUNT_PERCENT && ($data['discount_percent'] ?? null) === null) {
            $data['discount_percent'] = 0;
        }

        return $data;
    }
}
