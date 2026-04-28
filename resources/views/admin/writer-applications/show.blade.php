@php use Illuminate\Support\Facades\Storage; @endphp

<x-app-layout>
    <x-slot name="header">
        <div class="flex items-center justify-between gap-4">
            <div>
                <h2 class="font-semibold text-xl text-gray-800 leading-tight">Review Application</h2>
                <p class="mt-1 text-sm text-gray-500">{{ $application->fullName() }} • {{ '@'.$application->username }}</p>
            </div>
            <a href="{{ route('admin.writer-applications.index') }}" class="rounded-md border border-gray-300 px-4 py-2 text-sm text-gray-700">Back to queue</a>
        </div>
    </x-slot>

    <div class="py-12">
        <div class="max-w-7xl mx-auto space-y-6 sm:px-6 lg:px-8">
            @if (session('status'))
                <div class="rounded-md bg-green-50 p-3 text-sm text-green-700">{{ session('status') }}</div>
            @endif

            @if ($errors->any())
                <div class="rounded-md bg-red-50 p-3 text-sm text-red-700">
                    <ul class="list-disc space-y-1 pl-5">
                        @foreach ($errors->all() as $error)
                            <li>{{ $error }}</li>
                        @endforeach
                    </ul>
                </div>
            @endif

            <div class="grid gap-6 lg:grid-cols-3">
                <div class="space-y-6 lg:col-span-2">
                    <div class="rounded-lg bg-white p-6 shadow-sm">
                        <h3 class="text-lg font-semibold text-gray-900">Applicant Profile</h3>
                        <div class="mt-4 grid gap-4 md:grid-cols-2 text-sm">
                            <div>
                                <p class="text-gray-500">Full name</p>
                                <p class="font-medium text-gray-900">{{ $application->fullName() }}</p>
                            </div>
                            <div>
                                <p class="text-gray-500">Preferred username</p>
                                <p class="font-medium text-gray-900">{{ '@'.$application->username }}</p>
                            </div>
                            <div>
                                <p class="text-gray-500">Email</p>
                                <p class="font-medium text-gray-900">{{ $application->email }}</p>
                            </div>
                            <div>
                                <p class="text-gray-500">Phone</p>
                                <p class="font-medium text-gray-900">{{ $application->phone }}</p>
                            </div>
                            <div>
                                <p class="text-gray-500">WhatsApp available</p>
                                <p class="font-medium text-gray-900">{{ $application->available_on_whatsapp ? 'Yes' : 'No' }}</p>
                            </div>
                            <div>
                                <p class="text-gray-500">Linked platform user</p>
                                <p class="font-medium text-gray-900">{{ $application->user?->name ?: 'Guest applicant' }}</p>
                            </div>
                        </div>

                        <div class="mt-6">
                            <p class="text-sm text-gray-500">Professional bio</p>
                            <div class="mt-2 rounded-md bg-gray-50 p-4 text-sm text-gray-700 whitespace-pre-line">{{ $application->profile_bio }}</div>
                        </div>
                    </div>

                    <div class="rounded-lg bg-white p-6 shadow-sm">
                        <h3 class="text-lg font-semibold text-gray-900">Content Samples</h3>
                        <div class="mt-5 space-y-6">
                            <div>
                                <p class="text-sm text-gray-500">Article sample</p>
                                <h4 class="mt-1 font-semibold text-gray-900">{{ $application->sample_article_title }}</h4>
                                <div class="mt-2 rounded-md bg-gray-50 p-4 text-sm text-gray-700 whitespace-pre-line">{{ $application->sample_article_body }}</div>
                            </div>
                            <div>
                                <p class="text-sm text-gray-500">Advert sample</p>
                                <h4 class="mt-1 font-semibold text-gray-900">{{ $application->sample_advert_title }}</h4>
                                <div class="mt-2 rounded-md bg-gray-50 p-4 text-sm text-gray-700 whitespace-pre-line">{{ $application->sample_advert_body }}</div>
                            </div>
                        </div>
                    </div>

                    <div class="rounded-lg bg-white p-6 shadow-sm">
                        <h3 class="text-lg font-semibold text-gray-900">Banking And Documents</h3>
                        <div class="mt-4 grid gap-4 md:grid-cols-2 text-sm">
                            <div>
                                <p class="text-gray-500">Bank</p>
                                <p class="font-medium text-gray-900">{{ $application->bank_name }}</p>
                            </div>
                            <div>
                                <p class="text-gray-500">Account holder</p>
                                <p class="font-medium text-gray-900">{{ $application->account_holder_name }}</p>
                            </div>
                            <div>
                                <p class="text-gray-500">Account number</p>
                                <p class="font-medium text-gray-900">{{ $application->account_number }}</p>
                            </div>
                            <div>
                                <p class="text-gray-500">Branch code</p>
                                <p class="font-medium text-gray-900">{{ $application->branch_code }}</p>
                            </div>
                        </div>

                        <div class="mt-5 grid gap-4 md:grid-cols-2 xl:grid-cols-4">
                            @if ($application->profile_photo_path)
                                <a href="{{ Storage::disk('public')->url($application->profile_photo_path) }}" target="_blank" class="rounded-lg border border-gray-200 p-4 text-sm text-indigo-600">
                                    Open profile photo
                                </a>
                            @endif
                            <a href="{{ Storage::disk('public')->url($application->id_document_path) }}" target="_blank" class="rounded-lg border border-gray-200 p-4 text-sm text-indigo-600">
                                Open ID document
                            </a>
                            <a href="{{ Storage::disk('public')->url($application->banking_document_path) }}" target="_blank" class="rounded-lg border border-gray-200 p-4 text-sm text-indigo-600">
                                Open banking proof
                            </a>
                            <a href="{{ Storage::disk('public')->url($application->proof_of_residence_path) }}" target="_blank" class="rounded-lg border border-gray-200 p-4 text-sm text-indigo-600">
                                Open proof of residence
                            </a>
                        </div>
                    </div>
                </div>

                <div class="space-y-6">
                    <div class="rounded-lg bg-white p-6 shadow-sm">
                        <h3 class="text-lg font-semibold text-gray-900">Review Status</h3>
                        <div class="mt-4 space-y-3 text-sm">
                            <div>
                                <p class="text-gray-500">Current status</p>
                                <p class="font-medium text-gray-900">{{ str_replace('_', ' ', ucfirst($application->status)) }}</p>
                            </div>
                            <div>
                                <p class="text-gray-500">Assigned onboarding role</p>
                                <p class="font-medium text-gray-900">{{ $application->assigned_role ? str_replace('_', ' ', ucfirst($application->assigned_role)) : '-' }}</p>
                            </div>
                            <div>
                                <p class="text-gray-500">Submitted</p>
                                <p class="font-medium text-gray-900">{{ optional($application->submitted_at)->format('j M Y H:i') ?: '-' }}</p>
                            </div>
                            <div>
                                <p class="text-gray-500">Reviewed</p>
                                <p class="font-medium text-gray-900">{{ optional($application->reviewed_at)->format('j M Y H:i') ?: '-' }}</p>
                            </div>
                            <div>
                                <p class="text-gray-500">Onboarded</p>
                                <p class="font-medium text-gray-900">{{ optional($application->onboarded_at)->format('j M Y H:i') ?: '-' }}</p>
                            </div>
                            <div>
                                <p class="text-gray-500">Access email sent</p>
                                <p class="font-medium text-gray-900">{{ optional($application->access_notified_at)->format('j M Y H:i') ?: '-' }}</p>
                            </div>
                            <div>
                                <p class="text-gray-500">Platform account</p>
                                <p class="font-medium text-gray-900">{{ $application->user?->email ?: 'Not linked yet' }}</p>
                            </div>
                        </div>

                        @if ($application->status === \App\Models\WriterApplication::STATUS_APPROVED && $application->user)
                            <form method="post" action="{{ route('admin.writer-applications.resend-access', $application) }}" class="mt-5">
                                @csrf
                                @if ($resendAvailableAt)
                                    <p class="mb-3 text-sm text-amber-700">Resend available after {{ $resendAvailableAt->format('H:i') }}.</p>
                                @elseif ($application->access_notified_at)
                                    <p class="mb-3 text-sm text-gray-500">Last sent {{ $application->access_notified_at->diffForHumans() }}.</p>
                                @endif
                                <button type="submit" class="rounded-md border border-indigo-200 px-4 py-2 text-sm font-medium text-indigo-700">
                                    Resend access email
                                </button>
                            </form>
                        @endif
                    </div>

                    <div class="rounded-lg bg-white p-6 shadow-sm">
                        <h3 class="text-lg font-semibold text-gray-900">Update Review</h3>
                        <form method="post" action="{{ route('admin.writer-applications.review', $application) }}" class="mt-4 space-y-4">
                            @csrf
                            <div>
                                <label for="status" class="mb-1 block text-sm font-medium text-gray-700">Decision</label>
                                <select id="status" name="status" class="w-full rounded-md border-gray-300 text-sm shadow-sm">
                                    @foreach ($decisionOptions as $status)
                                        <option value="{{ $status }}" @selected(old('status', $application->status) === $status)>
                                            {{ str_replace('_', ' ', ucfirst($status)) }}
                                        </option>
                                    @endforeach
                                </select>
                            </div>
                            <div>
                                <label for="assigned_role" class="mb-1 block text-sm font-medium text-gray-700">Onboarding role</label>
                                <select id="assigned_role" name="assigned_role" class="w-full rounded-md border-gray-300 text-sm shadow-sm">
                                    <option value="">Select role when approving</option>
                                    @foreach ($onboardingRoleOptions as $role)
                                        <option value="{{ $role }}" @selected(old('assigned_role', $application->assigned_role) === $role)>
                                            {{ str_replace('_', ' ', ucfirst($role)) }}
                                        </option>
                                    @endforeach
                                </select>
                                <p class="mt-1 text-xs text-gray-500">Required when the application is approved. Approval creates or updates the linked platform account.</p>
                            </div>
                            <div>
                                <label for="admin_notes" class="mb-1 block text-sm font-medium text-gray-700">Admin notes</label>
                                <textarea id="admin_notes" name="admin_notes" rows="8" class="w-full rounded-md border-gray-300 text-sm shadow-sm">{{ old('admin_notes', $application->admin_notes) }}</textarea>
                            </div>
                            <button type="submit" class="rounded-md bg-indigo-600 px-4 py-2 text-sm text-white">Save review decision</button>
                        </form>
                    </div>

                    <div class="rounded-lg bg-white p-6 shadow-sm">
                        <h3 class="text-lg font-semibold text-gray-900">Access Notification History</h3>
                        <div class="mt-4 space-y-4">
                            @forelse ($accessHistory as $historyItem)
                                <div class="rounded-md border border-gray-200 p-4">
                                    <div class="flex items-start justify-between gap-4">
                                        <div>
                                            <p class="font-medium text-gray-900">{{ $historyItem['action'] }}</p>
                                            <p class="mt-1 text-sm text-gray-500">{{ $historyItem['detail'] }}</p>
                                        </div>
                                        <div class="text-right text-sm text-gray-500">
                                            <p>{{ $historyItem['occurred_at']?->format('j M Y H:i') ?: '-' }}</p>
                                            <p>{{ $historyItem['actor'] }}</p>
                                        </div>
                                    </div>
                                </div>
                            @empty
                                <p class="text-sm text-gray-500">No access-email events recorded yet.</p>
                            @endforelse
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</x-app-layout>
