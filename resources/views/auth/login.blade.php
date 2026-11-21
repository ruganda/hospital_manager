@extends('layouts.guest')
@section('title', 'Log in')
@section('content')
    <h1>Welcome back</h1>
    <p class="text-muted mb-4">Sign in to continue to your dashboard.</p>

    @if (session('status'))<div class="cc-callout ok mb-3"><i class="fa-solid fa-circle-check" aria-hidden="true"></i><div>{{ session('status') }}</div></div>@endif

    <form method="POST" action="{{ route('login') }}">
        @csrf
        <x-input name="email" type="email" label="Email address" required autofocus autocomplete="username" class="form-control-lg" placeholder="you@example.com" />

        <div class="mb-3">
            <div class="d-flex justify-content-between align-items-baseline">
                <label class="form-label" for="password">Password <span class="text-danger" aria-hidden="true">*</span></label>
                @if (Route::has('password.request'))
                    <a class="small fw-medium" href="{{ route('password.request') }}">Forgot password?</a>
                @endif
            </div>
            <input type="password" name="password" id="password" required autocomplete="current-password" class="form-control form-control-lg @error('password') is-invalid @enderror">
            @error('password')<div class="invalid-feedback">{{ $message }}</div>@enderror
        </div>

        <div class="form-check mb-4">
            <input class="form-check-input" type="checkbox" name="remember" id="remember">
            <label class="form-check-label small" for="remember">Keep me signed in on this device</label>
        </div>

        <button type="submit" class="btn btn-primary btn-lg w-100">Log in</button>
    </form>

    <div class="cc-divider my-4"></div>
    <p class="small text-muted text-center m-0">New patient? <a class="fw-semibold" href="{{ route('register') }}">Create an account</a></p>

    @if (app()->environment('local'))
        <div class="cc-callout mt-4 small"><i class="fa-solid fa-flask" style="color:var(--cc-primary)" aria-hidden="true"></i><div>Demo logins: <strong>admin@</strong>, <strong>reception@</strong>, <strong>doctor@</strong>, <strong>cashier@</strong>, <strong>patient@citycare.test</strong>. Password <strong>password</strong>.</div></div>
    @endif
@endsection
