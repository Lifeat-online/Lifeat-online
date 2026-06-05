<?php

namespace App\Http\Controllers;

use App\Http\Requests\Public\SubmitWriterApplicationRequest;
use App\Models\WriterApplication;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;

class WriterApplicationController extends Controller
{
    public function create(Request $request): View
    {
        return view('writer-applications.create', [
            'user' => $request->user(),
        ]);
    }

    public function store(SubmitWriterApplicationRequest $request): RedirectResponse
    {
        $data = $request->validated();

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
            'banking_document_path' => isset($data['banking_document_upload'])
                ? $this->storePrivateUpload($data['banking_document_upload'], 'writer-applications/banking-documents')
                : null,
            'proof_of_residence_path' => $this->storePrivateUpload($data['proof_of_residence_upload'], 'writer-applications/proof-of-residence'),
            'bank_name' => $data['bank_name'] ?? null,
            'account_holder_name' => $data['account_holder_name'] ?? null,
            'account_number' => $data['account_number'] ?? null,
            'branch_code' => $data['branch_code'] ?? null,
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

    private function storeUpload(UploadedFile $file, string $directory): string
    {
        return $file->store($directory, 'public');
    }

    private function storePrivateUpload(UploadedFile $file, string $directory): string
    {
        return $file->store($directory, 'local');
    }
}
