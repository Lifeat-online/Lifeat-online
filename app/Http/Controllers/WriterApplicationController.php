<?php

namespace App\Http\Controllers;

use App\Models\WriterApplication;
use App\Support\Validation\UploadRules;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Query\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Validation\Rule;

class WriterApplicationController extends Controller
{
    public function create(Request $request): View
    {
        return view('writer-applications.create', [
            'user' => $request->user(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $this->validated($request);

        $application = WriterApplication::create([
            'user_id' => $request->user()?->id,
            'first_name' => $data['first_name'],
            'last_name' => $data['last_name'],
            'email' => $data['email'],
            'phone' => $data['phone'],
            'username' => $data['username'],
            'profile_bio' => $data['profile_bio'],
            'profile_photo_path' => $this->storeUpload($data['profile_photo_upload'], 'writer-applications/profile-photos'),
            'available_on_whatsapp' => $request->boolean('available_on_whatsapp'),
            'sample_article_title' => $data['sample_article_title'],
            'sample_article_body' => $data['sample_article_body'],
            'sample_advert_title' => $data['sample_advert_title'],
            'sample_advert_body' => $data['sample_advert_body'],
            'id_document_path' => $this->storePrivateUpload($data['id_document_upload'], 'writer-applications/id-documents'),
            'banking_document_path' => $this->storePrivateUpload($data['banking_document_upload'], 'writer-applications/banking-documents'),
            'proof_of_residence_path' => $this->storePrivateUpload($data['proof_of_residence_upload'], 'writer-applications/proof-of-residence'),
            'bank_name' => $data['bank_name'],
            'account_holder_name' => $data['account_holder_name'],
            'account_number' => $data['account_number'],
            'branch_code' => $data['branch_code'],
            'status' => 'pending',
            'submitted_at' => now(),
        ]);

        return redirect()
            ->route('staff-signup.submitted')
            ->with('submitted_email', $application->email);
    }

    public function submitted(Request $request): View
    {
        return view('writer-applications.submitted', [
            'submittedEmail' => $request->session()->get('submitted_email'),
        ]);
    }

    private function validated(Request $request): array
    {
        $user = $request->user();

        return $request->validate([
            'first_name' => ['required', 'string', 'max:120'],
            'last_name' => ['required', 'string', 'max:120'],
            'email' => [
                'required',
                'string',
                'email',
                'max:255',
                Rule::unique('users', 'email')->ignore($user?->id),
                Rule::unique('writer_applications', 'email')->where(fn (Builder $query) => $query->whereIn('status', $this->activeStatuses())),
            ],
            'phone' => ['required', 'string', 'max:50'],
            'username' => [
                'required',
                'string',
                'max:50',
                'alpha_dash',
                Rule::unique('users', 'username')->ignore($user?->id),
                Rule::unique('writer_applications', 'username')->where(fn (Builder $query) => $query->whereIn('status', $this->activeStatuses())),
            ],
            'profile_bio' => ['required', 'string', 'min:80', 'max:4000'],
            'available_on_whatsapp' => ['nullable', 'boolean'],
            'sample_article_title' => ['required', 'string', 'max:255'],
            'sample_article_body' => ['required', 'string', 'min:300', 'max:12000'],
            'sample_advert_title' => ['required', 'string', 'max:255'],
            'sample_advert_body' => ['required', 'string', 'min:120', 'max:4000'],
            'bank_name' => ['required', 'string', 'max:255'],
            'account_holder_name' => ['required', 'string', 'max:255'],
            'account_number' => ['required', 'string', 'max:60'],
            'branch_code' => ['required', 'string', 'max:30'],
            'profile_photo_upload' => UploadRules::requiredPublicImage(4096),
            'id_document_upload' => UploadRules::requiredPrivateDocument(),
            'banking_document_upload' => UploadRules::requiredPrivateDocument(),
            'proof_of_residence_upload' => UploadRules::requiredPrivateDocument(),
        ]);
    }

    private function storeUpload(UploadedFile $file, string $directory): string
    {
        return $file->store($directory, 'public');
    }

    private function storePrivateUpload(UploadedFile $file, string $directory): string
    {
        return $file->store($directory, 'local');
    }

    private function activeStatuses(): array
    {
        return [
            WriterApplication::STATUS_PENDING,
            WriterApplication::STATUS_UNDER_REVIEW,
            WriterApplication::STATUS_APPROVED,
        ];
    }
}
