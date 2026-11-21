@extends('layouts.guest')
@section('title', 'Patient registration')
@section('card_class', 'wide')
@section('aside_title', 'Register once, book in minutes.')
@section('content')
    <h1>Create your patient account</h1>
    <p class="text-muted mb-4">See your appointments, visit history and bills. Already registered at the front desk? <a class="fw-semibold" href="{{ route('login') }}">Log in</a></p>

    <form method="POST" action="{{ route('register') }}">
        @csrf
        <div class="row">
            <x-input name="name" label="Full name" required autofocus autocomplete="name" col="col-md-6 mb-3" />
            <x-input name="email" type="email" label="Email" required autocomplete="email" col="col-md-6 mb-3" />
            <x-input name="phone" type="tel" placeholder="+256 7XX XXX XXX" label="Phone" required autocomplete="tel" col="col-md-5 mb-3" />
            <x-input name="date_of_birth" type="date" :max="today()->subDay()->toDateString()" min="1900-01-01" label="Date of birth" required col="col-md-4 mb-3" />
            <x-select name="gender" label="Gender" :options="['male' => 'Male', 'female' => 'Female', 'other' => 'Other']" placeholder="Select…" required col="col-md-3 mb-3" />
            <x-input name="password" type="password" label="Password" required autocomplete="new-password" help="At least 8 characters." col="col-md-6 mb-3" />
            <x-input name="password_confirmation" type="password" label="Confirm password" required autocomplete="new-password" col="col-md-6 mb-3" />
        </div>
        <button type="submit" class="btn btn-primary btn-lg w-100 mt-2">Create account</button>
    </form>
    <p class="small text-muted mt-3 mb-0 text-center">Only CityCare staff involved in your care can see your records.</p>
@endsection
