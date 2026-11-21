@extends('layouts.guest')
@section('title', 'Confirm password')
@section('content')
    <h1>Confirm your password</h1>
    <p class="text-muted mb-4">This is a secure area. Please confirm your password before continuing.</p>
    <form method="POST" action="{{ route('password.confirm') }}">
        @csrf
        <x-input name="password" type="password" label="Password" required autofocus autocomplete="current-password" />
        <button type="submit" class="btn btn-primary btn-lg w-100">Confirm</button>
    </form>
@endsection
