@extends('layouts.public')

@section('title', 'Eastern Freestate | Staff Signup')

@push('styles')
    <style>
        .signup-hero {
            display: grid;
            gap: 1.5rem;
            grid-template-columns: minmax(0, 1.35fr) minmax(280px, 0.85fr);
            margin-bottom: 2rem;
        }
        .signup-panel {
            padding: 2rem;
            border-radius: 24px;
            color: #eff6ff;
            background:
                radial-gradient(circle at top right, rgba(147, 197, 253, 0.28), transparent 35%),
                linear-gradient(135deg, #0f172a, #1d4ed8 60%, #1e40af);
            box-shadow: 0 24px 50px rgba(15, 23, 42, 0.16);
        }
        html[data-theme="dark"] .signup-panel {
            background:
                radial-gradient(circle at top right, rgba(96, 165, 250, 0.18), transparent 35%),
                linear-gradient(135deg, #020617, #0f172a 60%, #1e3a8a);
        }
        .signup-kicker {
            display: inline-flex;
            padding: 0.35rem 0.7rem;
            border-radius: 999px;
            background: rgba(255, 255, 255, 0.12);
            font-size: 0.85rem;
        }
        .signup-copy {
            max-width: 44rem;
            color: rgba(239, 246, 255, 0.88);
        }
        .checklist {
            display: grid;
            gap: 0.75rem;
        }
        .checklist-item {
            padding: 1rem;
            border-radius: 18px;
            border: 1px solid var(--border);
            background: var(--surface);
        }
        .info-grid,
        .form-columns {
            display: grid;
            gap: 1rem;
            grid-template-columns: repeat(2, minmax(0, 1fr));
        }
        .form-card {
            margin-bottom: 1.5rem;
        }
        .form-card h2,
        .form-card h3 {
            margin-top: 0;
        }
        .helper {
            margin: 0.35rem 0 0;
            color: var(--muted);
            font-size: 0.92rem;
        }
        .field + .field {
            margin-top: 1rem;
        }
        .checkbox-row {
            display: flex;
            gap: 0.75rem;
            align-items: flex-start;
            margin-top: 1rem;
        }
        .checkbox-row input {
            width: auto;
            margin-top: 0.2rem;
        }
        .alert {
            margin-bottom: 1.5rem;
            padding: 1rem 1.15rem;
            border-radius: 16px;
            border: 1px solid #fecaca;
            background: #fff1f2;
            color: #9f1239;
        }
        html[data-theme="dark"] .alert {
            border-color: #7f1d1d;
            background: rgba(127, 29, 29, 0.22);
            color: #fecdd3;
        }
        .upload-grid {
            display: grid;
            gap: 1rem;
            grid-template-columns: repeat(3, minmax(0, 1fr));
        }
        .summary-strip {
            display: grid;
            gap: 1rem;
            grid-template-columns: repeat(3, minmax(0, 1fr));
            margin-top: 1rem;
        }
        .summary-tile {
            padding: 1rem;
            border-radius: 18px;
            background: rgba(255, 255, 255, 0.1);
            border: 1px solid rgba(255, 255, 255, 0.12);
        }
        @media (max-width: 960px) {
            .signup-hero,
            .info-grid,
            .form-columns,
            .upload-grid,
            .summary-strip {
                grid-template-columns: 1fr;
            }
        }
    </style>
@endpush

@section('content')
    <section class="signup-hero">
        <div class="signup-panel">
            <span class="signup-kicker">Writer and staff application</span>
            <h1 style="margin:1rem 0 0.75rem;">Apply to contribute stories, help local businesses grow, and earn through the platform.</h1>
            <p class="signup-copy">
                This application is for people who want to write local content, support advertiser onboarding, or contribute to the broader Life@ growth model.
                Share your profile, writing sample, advert sample, and support documents so the team can review you properly.
            </p>
            <div class="summary-strip">
                <div class="summary-tile">
                    <strong>1. Apply</strong>
                    <p style="margin:0.35rem 0 0;">Complete the profile, sample content, and document checks in one submission.</p>
                </div>
                <div class="summary-tile">
                    <strong>2. Review</strong>
                    <p style="margin:0.35rem 0 0;">The admin team reviews writing quality, commercial fit, and compliance documents.</p>
                </div>
                <div class="summary-tile">
                    <strong>3. Onboard</strong>
                    <p style="margin:0.35rem 0 0;">Approved applicants move into the existing writer or staff workflow inside the platform.</p>
                </div>
            </div>
        </div>

        <div class="card">
            <h2 style="margin-top:0;">What You Need</h2>
            <div class="checklist">
                <div class="checklist-item">
                    <strong>Strong local profile</strong>
                    <p class="helper">Tell us who you are, where you work from, and the kind of community coverage or sales support you can offer.</p>
                </div>
                <div class="checklist-item">
                    <strong>Sample content</strong>
                    <p class="helper">Submit one article sample and one advert-style sample so we can assess tone, clarity, and commercial awareness.</p>
                </div>
                <div class="checklist-item">
                    <strong>Verification documents</strong>
                    <p class="helper">Upload your ID, banking proof, and proof of residence as PDF or image files.</p>
                </div>
            </div>
        </div>
    </section>

    @if ($errors->any())
        <div class="alert">
            <strong>Please fix the highlighted fields and submit again.</strong>
            <ul style="margin:0.75rem 0 0; padding-left:1.2rem;">
                @foreach ($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    <form method="post" action="{{ route('staff-signup.store') }}" enctype="multipart/form-data">
        @csrf

        <section class="card form-card">
            <h2>Personal Profile</h2>
            <p class="helper">Start with the details the editorial and admin teams need to identify and contact you.</p>

            <div class="info-grid">
                <div class="field">
                    <label for="first_name">First name</label>
                    <input id="first_name" name="first_name" value="{{ old('first_name') }}" required>
                </div>
                <div class="field">
                    <label for="last_name">Last name</label>
                    <input id="last_name" name="last_name" value="{{ old('last_name') }}" required>
                </div>
                <div class="field">
                    <label for="email">Email</label>
                    <input id="email" type="email" name="email" value="{{ old('email', $user?->email) }}" required>
                </div>
                <div class="field">
                    <label for="phone">Phone number</label>
                    <input id="phone" name="phone" value="{{ old('phone', $user?->phone) }}" required>
                </div>
            </div>

            <div class="form-columns" style="margin-top:1rem;">
                <div class="field">
                    <label for="username">Preferred username</label>
                    <input id="username" name="username" value="{{ old('username', $user?->username) }}" required>
                    <p class="helper">Use letters, numbers, dashes, or underscores only.</p>
                </div>
                <div class="field">
                    <label for="profile_photo_upload">Profile photo</label>
                    <input id="profile_photo_upload" type="file" name="profile_photo_upload" accept=".jpg,.jpeg,.png,.webp" required>
                    <p class="helper">Upload a clear profile image for your future contributor or staff profile.</p>
                </div>
            </div>

            <div class="field">
                <label for="profile_bio">Short professional bio</label>
                <textarea id="profile_bio" name="profile_bio" rows="6" required>{{ old('profile_bio', $user?->bio) }}</textarea>
                <p class="helper">Include your area, strengths, community knowledge, writing background, and how you want to contribute.</p>
            </div>

            <label class="checkbox-row" for="available_on_whatsapp">
                <input id="available_on_whatsapp" type="checkbox" name="available_on_whatsapp" value="1" @checked(old('available_on_whatsapp'))>
                <span>
                    <strong>Available on WhatsApp</strong>
                    <span class="helper" style="display:block;">Use this if the team may contact you on WhatsApp during the review process.</span>
                </span>
            </label>
        </section>

        <section class="card form-card">
            <h2>Sample Content</h2>
            <p class="helper">The application stays tied to both editorial quality and real local business promotion, so we need one sample of each.</p>

            <div class="field">
                <label for="sample_article_title">Sample article headline</label>
                <input id="sample_article_title" name="sample_article_title" value="{{ old('sample_article_title') }}" required>
            </div>
            <div class="field">
                <label for="sample_article_body">Sample article</label>
                <textarea id="sample_article_body" name="sample_article_body" rows="12" required>{{ old('sample_article_body') }}</textarea>
                <p class="helper">Aim for a strong local article opening and enough body copy to show tone, structure, and reporting ability.</p>
            </div>

            <div class="field">
                <label for="sample_advert_title">Sample advert headline</label>
                <input id="sample_advert_title" name="sample_advert_title" value="{{ old('sample_advert_title') }}" required>
            </div>
            <div class="field">
                <label for="sample_advert_body">Sample advert copy</label>
                <textarea id="sample_advert_body" name="sample_advert_body" rows="8" required>{{ old('sample_advert_body') }}</textarea>
                <p class="helper">Write this like a business promotion or campaign message that could appear on the platform.</p>
            </div>
        </section>

        <section class="card form-card">
            <h2>Banking And Verification</h2>
            <p class="helper">These details support compliance checks and future payout preparation if your application is approved.</p>

            <div class="info-grid">
                <div class="field">
                    <label for="bank_name">Bank name</label>
                    <input id="bank_name" name="bank_name" value="{{ old('bank_name') }}" required>
                </div>
                <div class="field">
                    <label for="account_holder_name">Account holder name</label>
                    <input id="account_holder_name" name="account_holder_name" value="{{ old('account_holder_name') }}" required>
                </div>
                <div class="field">
                    <label for="account_number">Account number</label>
                    <input id="account_number" name="account_number" value="{{ old('account_number') }}" required>
                </div>
                <div class="field">
                    <label for="branch_code">Branch code</label>
                    <input id="branch_code" name="branch_code" value="{{ old('branch_code') }}" required>
                </div>
            </div>

            <div class="upload-grid" style="margin-top:1rem;">
                <div class="field">
                    <label for="id_document_upload">ID document</label>
                    <input id="id_document_upload" type="file" name="id_document_upload" accept=".pdf,.jpg,.jpeg,.png" required>
                </div>
                <div class="field">
                    <label for="banking_document_upload">Banking confirmation</label>
                    <input id="banking_document_upload" type="file" name="banking_document_upload" accept=".pdf,.jpg,.jpeg,.png" required>
                </div>
                <div class="field">
                    <label for="proof_of_residence_upload">Proof of residence</label>
                    <input id="proof_of_residence_upload" type="file" name="proof_of_residence_upload" accept=".pdf,.jpg,.jpeg,.png" required>
                </div>
            </div>
        </section>

        <section class="card form-card">
            <h2>Submit Application</h2>
            <p class="helper">By submitting, you confirm that the information is accurate and that the uploaded documents belong to you.</p>
            <div style="display:flex; gap:0.75rem; flex-wrap:wrap;">
                <button type="submit" class="button">Submit staff application</button>
                <a href="{{ route('home') }}" class="button" style="background:transparent; color:var(--primary); border:1px solid var(--border);">Back to home</a>
            </div>
        </section>
    </form>
@endsection
