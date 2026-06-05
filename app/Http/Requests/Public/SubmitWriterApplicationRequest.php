<?php

namespace App\Http\Requests\Public;

use App\Models\WriterApplication;
use App\Models\User;
use App\Support\Validation\UploadRules;
use Illuminate\Database\Query\Builder;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class SubmitWriterApplicationRequest extends FormRequest
{
    private function activeStatuses(): array
    {
        return [
            WriterApplication::STATUS_PENDING,
            WriterApplication::STATUS_UNDER_REVIEW,
        ];
    }

    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $user = $this->user();

        return [
            'first_name' => ['required', 'string', 'max:120'],
            'last_name' => ['required', 'string', 'max:120'],
            'email' => [
                'required',
                'string',
                'email',
                'max:255',
                Rule::unique(User::class, 'email')->ignore($user?->id),
                Rule::unique('writer_applications', 'email')->where(fn (Builder $query) => $query->whereIn('status', $this->activeStatuses())),
            ],
            'phone' => ['required', 'string', 'max:50', 'regex:/^[\d\s\-\+\(\)]+$/'],
            'username' => [
                'required',
                'string',
                'max:50',
                'alpha_dash',
                Rule::unique(User::class, 'username')->ignore($user?->id),
                Rule::unique('writer_applications', 'username')->where(fn (Builder $query) => $query->whereIn('status', $this->activeStatuses())),
            ],
            'profile_bio' => ['required', 'string', 'min:80', 'max:4000'],
            'available_on_whatsapp' => ['nullable', 'boolean'],
            'sample_article_title' => ['required', 'string', 'max:255'],
            'sample_article_body' => ['required', 'string', 'min:300', 'max:12000'],
            'sample_advert_title' => ['required', 'string', 'max:255'],
            'sample_advert_body' => ['required', 'string', 'min:120', 'max:4000'],
            'bank_name' => ['nullable', 'string', 'max:255'],
            'account_holder_name' => ['nullable', 'string', 'max:255'],
            'account_number' => ['nullable', 'string', 'max:60'],
            'branch_code' => ['nullable', 'string', 'max:30'],
            'profile_photo_upload' => UploadRules::requiredPublicImage(4096),
            'id_document_upload' => UploadRules::requiredPrivateDocument(),
            'banking_document_upload' => UploadRules::optionalPrivateDocument(),
            'proof_of_residence_upload' => UploadRules::requiredPrivateDocument(),
        ];
    }

    public function messages(): array
    {
        return [
            'phone.regex' => 'Phone may only contain digits, spaces, dashes, plus signs, and parentheses.',
            'profile_bio.min' => 'Profile bio must be at least :min characters.',
            'sample_article_body.min' => 'Sample article must be at least :min characters.',
            'sample_advert_body.min' => 'Sample advert must be at least :min characters.',
        ];
    }
}
