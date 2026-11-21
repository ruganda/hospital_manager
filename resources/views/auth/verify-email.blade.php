@extends('layouts.guest')
@section('title', 'Verify email')
@section('content')
    <h1>Check your inbox</h1>
    <p class="text-muted mb-4">Thanks for signing up. Please verify your email address with the link we just sent you.</p>
    @if (session('status') == 'verification-link-sent')
        <div class="cc-callout ok mb-3"><i class="fa-solid fa-circle-check" aria-hidden="true"></i><div>A new verification link has been sent.</div></div>
    @endif
    <div class="d-flex flex-wrap justify-content-between gap-2">
        <form method="POST" action="{{ route('verification.send') }}">@csrf<button type="submit" class="btn btn-primary">Resend verification email</button></form>
        <form method="POST" action="{{ route('logout') }}">@csrf<button type="submit" class="btn btn-ghost">Log out</button></form>
    </div>
@endsection
