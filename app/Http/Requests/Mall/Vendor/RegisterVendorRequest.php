<?php

namespace App\Http\Requests\Mall\Vendor;

use App\Models\MallStore;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class RegisterVendorRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null
            && ! MallStore::where('owner_user_id', $this->user()->id)->exists();
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:100'],
            'tagline' => ['nullable', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:2000'],
            'primary_color' => ['nullable', 'regex:/^#[0-9a-fA-F]{6}$/'],
            'payfast_merchant_id' => ['nullable', 'string', 'max:20'],
            'payfast_merchant_key' => ['nullable', 'string', 'max:20'],
            'category_ids' => ['array'],
            'category_ids.*' => ['integer', 'exists:mall_store_categories,id'],
            'contact_name' => ['required', 'string', 'max:100'],
            'contact_email' => ['required', 'email', 'max:150'],
            'contact_phone' => ['nullable', 'string', 'max:20'],
            'business_reg' => ['nullable', 'string', 'max:50'],
            'bank_name' => ['nullable', 'string', 'max:50'],
            'bank_account' => ['nullable', 'string', 'max:30'],
            'bank_branch_code' => ['nullable', 'string', 'max:10'],
        ];
    }

    public function messages(): array
    {
        return [
            'primary_color.regex' => 'Primary color must be a 6-digit hex code (e.g. #3B82F6).',
        ];
    }
}
