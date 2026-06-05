<?php

namespace App\Http\Requests\Public;

use App\Models\CivicFaultReport;
use App\Support\Validation\UploadRules;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Support\Str;

class StoreFaultReportRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null && (bool) $this->input('consent', false);
    }

    protected function prepareForValidation(): void
    {
        $clientUuid = trim((string) $this->input('client_uuid', ''));
        if ($clientUuid === '' || $clientUuid === 'undefined' || $clientUuid === 'null' || ! Str::isUuid($clientUuid)) {
            $this->merge(['client_uuid' => null]);
        }
    }

    public function rules(): array
    {
        return [
            'client_uuid' => ['nullable', 'uuid'],
            'category' => ['required', Rule::in(array_keys(CivicFaultReport::categories()))],
            'severity' => ['required', Rule::in(array_keys(CivicFaultReport::severities()))],
            'description' => ['required', 'string', 'min:10', 'max:500'],
            'latitude' => ['required', 'numeric', 'between:-90,90'],
            'longitude' => ['required', 'numeric', 'between:-180,180'],
            'address_label' => ['nullable', 'string', 'max:255'],
            'photos' => ['nullable', 'array', 'max:5'],
            'photos.*' => UploadRules::requiredPublicImage(),
            'consent' => ['accepted'],
        ];
    }
}
