<x-guest-layout>
<div style="margin-bottom: 2rem;">
    <h1 style="margin: 0; font-size: 1.5rem; font-weight: 800; letter-spacing: -0.025em;">Welcome back</h1>
    <p style="margin: 0.5rem 0 0; color: var(--muted); font-size: 0.95rem;">Enter your credentials to access your account.</p>
</div>

<!-- Session Status -->
<x-auth-session-status class="mb-4" :status="session('status')" />

<form method="POST" action="{{ route('login') }}">
    @csrf

    <!-- Email Address -->
    <div class="auth-form-group">
        <label for="email" class="auth-label">Email Address</label>
        <input id="email" class="auth-input" type="email" name="email" value="{{ old('email') }}" required autofocus autocomplete="username" placeholder="name@company.com">
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
        <div style="display: flex; justify-content: space-between; align-items: baseline;">
            <label for="password" class="auth-label">Password</label>
            @if (Route::has('password.request'))
                <a href="{{ route('password.request') }}" style="font-size: 0.8rem; color: var(--primary); font-weight: 600;">Forgot password?</a>
            @endif
        </div>
        <input id="password" class="auth-input" type="password" name="password" required autocomplete="current-password" placeholder="••••••••">
        @if($errors->has('password'))
            <ul class="error-list">
                @foreach($errors->get('password') as $message)
                    <li>{{ $message }}</li>
                @endforeach
            </ul>
        @endif
    </div>

    <!-- Remember Me -->
    <div style="display: flex; align-items: center; gap: 0.5rem; margin-bottom: 1.5rem;">
        <input id="remember_me" type="checkbox" name="remember" style="width: auto; margin: 0;">
        <label for="remember_me" style="margin: 0; font-size: 0.85rem; color: var(--muted); font-weight: 500;">Remember me for 30 days</label>
    </div>

    <button type="submit" class="auth-btn">
        Sign in to platform
    </button>
</form>

<div class="auth-links">
    Don't have an account yet? <a href="{{ route('register') }}">Create account</a>
</div>
</x-guest-layout>
