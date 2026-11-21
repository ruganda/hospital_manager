@extends('layouts.app')
@section('title', 'My profile')
@section('content')
<x-page-header title="My profile" subtitle="Your sign-in details, photo and password." />

<div class="d-flex flex-column gap-4" style="max-width:1040px">
    <section class="row g-4 pb-4" style="border-bottom:1px solid var(--cc-line)">
        <div class="col-md-4">
            <h2 class="h6 fw-semibold mb-1">Profile</h2>
            <p class="small text-muted mb-3">Shown in the sidebar and on records you create.</p>
            <div class="d-flex align-items-center gap-3">
                <x-avatar :user="$user" size="lg" />
                <div class="min-w-0 small">
                    <span class="cc-badge no-dot secondary mb-1"><i class="fa-solid {{ $user->role->icon() }}" aria-hidden="true"></i>{{ $user->role->label() }}</span>
                    @if($user->patient)<div class="text-muted">Patient no. <strong style="color:var(--cc-ink)">{{ $user->patient->patient_no }}</strong></div>@endif
                    @if($user->doctor)<div class="text-muted">{{ $user->doctor->specialization }} · {{ $user->doctor->department->name }}</div>@endif
                    <div class="text-muted">Member since {{ $user->created_at->format('M Y') }}</div>
                </div>
            </div>
        </div>
        <div class="col-md-8">@include('profile.partials.update-profile-information-form')</div>
    </section>

    <section class="row g-4 pb-4" @unless($user->isAdmin()) style="border-bottom:1px solid var(--cc-line)" @endunless>
        <div class="col-md-4">
            <h2 class="h6 fw-semibold mb-1">Password</h2>
            <p class="small text-muted m-0">Use at least 8 characters. You stay signed in on this device.</p>
        </div>
        <div class="col-md-8">@include('profile.partials.update-password-form')</div>
    </section>

    @unless($user->isAdmin())
        <section class="row g-4">
            <div class="col-md-4">
                <h2 class="h6 fw-semibold mb-1" style="color:#a8251b">Delete account</h2>
                <p class="small text-muted m-0">Removes your login and personal data permanently.</p>
            </div>
            <div class="col-md-8">@include('profile.partials.delete-user-form')</div>
        </section>
    @endunless
</div>
@endsection
