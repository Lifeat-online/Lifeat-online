<?php

namespace App\Http\Requests\Account;

use App\Models\StaffWallet;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class RequestPayoutRequest extends FormRequest
{
    public function authorize(): bool
    {
        $wallet = StaffWallet::firstOrCreate(
            ['user_id' => $this->user()->id],
            ['currency' => 'ZAR']
        );

        if ($wallet->pendingPayoutRequest()) {
            return false;
        }

        return $this->user()->can('requestPayout', $wallet);
    }

    public function rules(): array
    {
        $wallet = StaffWallet::firstOrCreate(
            ['user_id' => $this->user()->id],
            ['currency' => 'ZAR']
        );

        return [
            'amount' => ['required', 'numeric', 'min:1', "max:{$wallet->available_balance}"],
            'bank_name' => ['required', 'string', 'max:100'],
            'account_holder' => ['required', 'string', 'max:150'],
            'account_number' => ['required', 'string', 'max:30', 'regex:/^[0-9\-]+$/'],
            'branch_code' => ['required', 'string', 'max:10', 'regex:/^[0-9\-]+$/'],
            'notes' => ['nullable', 'string', 'max:500'],
        ];
    }

    public function messages(): array
    {
        return [
            'amount.max' => 'Amount cannot exceed your available wallet balance.',
            'account_number.regex' => 'Account number may only contain digits and dashes.',
            'branch_code.regex' => 'Branch code may only contain digits and dashes.',
        ];
    }
}
