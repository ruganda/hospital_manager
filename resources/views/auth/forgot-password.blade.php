@extends('layouts.guest')
@section('title', 'Forgot password')
@section('content')
    <h1>Forgot your password?</h1>
    <p class="text-muted mb-4">Enter your email and we will send you a link to choose a new one.</p>
    @if (session('status'))<div class="cc-callout ok mb-3"><i class="fa-solid fa-circle-check" aria-hidden="true"></i><div>{{ session('status') }}</div></div>@endif
    <form method="POST" action="{{ route('password.email') }}">
        @csrf
        <x-input name="email" type="email" label="Email address" required autofocus autocomplete="email" class="form-control-lg" />
        <button type="submit" class="btn btn-primary btn-lg w-100">Email reset link</button>
    </form>
    <p class="text-center small mt-4 mb-0"><a class="fw-medium" href="{{ route('login') }}"><i class="fa-solid fa-arrow-left me-1" aria-hidden="true"></i>Back to log in</a></p>
@endsection
