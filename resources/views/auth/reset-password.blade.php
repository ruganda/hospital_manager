@extends('layouts.guest')
@section('title', 'Reset password')
@section('content')
    <h1>Choose a new password</h1>
    <p class="text-muted mb-4">Use at least 8 characters.</p>
    <form method="POST" action="{{ route('password.store') }}">
        @csrf
        <input type="hidden" name="token" value="{{ $request->route('token') }}">
        <x-input name="email" type="email" label="Email address" :value="$request->email" required autofocus autocomplete="username" />
        <x-input name="password" type="password" label="New password" required autocomplete="new-password" />
        <x-input name="password_confirmation" type="password" label="Confirm password" required autocomplete="new-password" />
        <button type="submit" class="btn btn-primary btn-lg w-100 mt-2">Reset password</button>
    </form>
@endsection
