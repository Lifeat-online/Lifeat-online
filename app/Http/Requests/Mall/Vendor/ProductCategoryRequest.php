<?php

namespace App\Http\Requests\Mall\Vendor;

use Illuminate\Foundation\Http\FormRequest;

class ProductCategoryRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:100'],
            'sort_order' => ['required', 'integer', 'min:-999999', 'max:999999'],
        ];
    }
}
