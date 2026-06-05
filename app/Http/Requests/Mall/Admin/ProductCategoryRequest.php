<?php

namespace App\Http\Requests\Mall\Admin;

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
            'mall_store_id' => ['required', 'integer', 'exists:mall_stores,id'],
            'name' => ['required', 'string', 'max:100'],
            'sort_order' => ['required', 'integer', 'min:-999999', 'max:999999'],
        ];
    }
}
