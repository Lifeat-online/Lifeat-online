<x-guest-layout>
<div style="margin-bottom: 2rem;">
    <h1 style="margin: 0; font-size: 1.5rem; font-weight: 800; letter-spacing: -0.025em;">Create an account</h1>
    <p style="margin: 0.5rem 0 0; color: var(--muted); font-size: 0.95rem;">Join the Life Platform community today.</p>
</div>

<form method="POST" action="{{ route('register') }}">
    @csrf

    <!-- Name -->
    <div class="auth-form-group">
        <label for="name" class="auth-label">Full Name</label>
        <input id="name" class="auth-input" type="text" name="name" value="{{ old('name') }}" required autofocus autocomplete="name" placeholder="John Doe">
        @if($errors->has('name'))
            <ul class="error-list">
                @foreach($errors->get('name') as $message)
                    <li>{{ $message }}</li>
                @endforeach
            </ul>
        @endif
    </div>

    <!-- Email Address -->
    <div class="auth-form-group">
        <label for="email" class="auth-label">Email Address</label>
        <input id="email" class="auth-input" type="email" name="email" value="{{ old('email') }}" required autocomplete="username" placeholder="name@company.com">
        @if($errors->has('email'))
            <ul class="error-list">
                @foreach($errors->get('email') as $message)
                    <li>{{ $message }}</li>
                @endforeach
            </ul>
        @endif
    </div>

    <!-- Password -->
    <div class="auth-form-group">
        <label for="password" class="auth-label">Password</label>
        <input id="password" class="auth-input" type="password" name="password" required autocomplete="new-password" placeholder="••••••••">
        @if($errors->has('password'))
            <ul class="error-list">
                @foreach($errors->get('password') as $message)
                    <li>{{ $message }}</li>
                @endforeach
            </ul>
        @endif
    </div>

    <!-- Confirm Password -->
    <div class="auth-form-group">
        <label for="password_confirmation" class="auth-label">Confirm Password</label>
        <input id="password_confirmation" class="auth-input" type="password" name="password_confirmation" required autocomplete="new-password" placeholder="••••••••">
        @if($errors->has('password_confirmation'))
            <ul class="error-list">
                @foreach($errors->get('password_confirmation') as $message)
                    <li>{{ $message }}</li>
                @endforeach
            </ul>
        @endif
    </div>

    <button type="submit" class="auth-btn">
        Create account
    </button>
</form>

<div class="auth-links">
    Already have an account? <a href="{{ route('login') }}">Sign in instead</a>
</div>
</x-guest-layout>
