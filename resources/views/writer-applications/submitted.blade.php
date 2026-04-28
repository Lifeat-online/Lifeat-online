@extends('layouts.public')

@section('title', 'Eastern Freestate | Application Received')

@push('styles')
    <style>
        .submitted-shell {
            display: grid;
            gap: 1.5rem;
            grid-template-columns: minmax(0, 1.25fr) minmax(280px, 0.85fr);
            margin-bottom: 2rem;
        }
        .submitted-panel {
            padding: 2rem;
            border-radius: 24px;
            color: #eff6ff;
            background:
                radial-gradient(circle at top right, rgba(147, 197, 253, 0.28), transparent 35%),
                linear-gradient(135deg, #0f172a, #1d4ed8 60%, #1e40af);
            box-shadow: 0 24px 50px rgba(15, 23, 42, 0.16);
        }
        html[data-theme="dark"] .submitted-panel {
            background:
                radial-gradient(circle at top right, rgba(96, 165, 250, 0.18), transparent 35%),
                linear-gradient(135deg, #020617, #0f172a 60%, #1e3a8a);
        }
        .next-steps {
            display: grid;
            gap: 0.85rem;
        }
        .helper {
            margin: 0.35rem 0 0;
            color: var(--muted);
            font-size: 0.92rem;
        }
        .step-card {
            padding: 1rem;
            border-radius: 18px;
            border: 1px solid var(--border);
            background: var(--surface);
        }
        @media (max-width: 900px) {
            .submitted-shell {
                grid-template-columns: 1fr;
            }
        }
    </style>
@endpush

@section('content')
    <section class="submitted-shell">
        <div class="submitted-panel">
            <span class="badge" style="background:rgba(255,255,255,0.14); color:#eff6ff;">Application received</span>
            <h1 style="margin:1rem 0 0.75rem;">Your staff-signup application is now pending review.</h1>
            <p style="max-width:40rem; color:rgba(239, 246, 255, 0.88);">
                The team now has your profile, supporting documents, and sample content. If the application is a good fit, the next step is a formal onboarding into the writer or staff workflow.
            </p>
            @if ($submittedEmail)
                <p style="margin:1rem 0 0;">
                    Review contact email: <strong>{{ $submittedEmail }}</strong>
                </p>
            @endif
            <div style="display:flex; gap:0.75rem; flex-wrap:wrap; margin-top:1.5rem;">
                <a href="{{ route('home') }}" class="button" style="background:#ffffff; color:#0f172a; text-decoration:none;">Return Home</a>
                <a href="{{ route('articles.index') }}" class="button" style="background:rgba(255,255,255,0.12); color:#ffffff; text-decoration:none;">Browse Local Stories</a>
            </div>
        </div>

        <div class="card">
            <h2 style="margin-top:0;">What Happens Next</h2>
            <div class="next-steps">
                <div class="step-card">
                    <strong>Application screening</strong>
                    <p class="helper">The admin team reviews your writing sample, advert sample, and documents together instead of treating them as disconnected uploads.</p>
                </div>
                <div class="step-card">
                    <strong>Role fit check</strong>
                    <p class="helper">Applications may be routed into writer, sales-staff, or hybrid onboarding depending on the quality of the submission and platform needs.</p>
                </div>
                <div class="step-card">
                    <strong>Onboarding contact</strong>
                    <p class="helper">Approved applicants are contacted for the next setup step before they start working inside the platform.</p>
                </div>
            </div>
        </div>
    </section>
@endsection
