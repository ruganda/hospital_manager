@extends('layouts.app')
@section('title', $doctor->exists ? 'Edit doctor' : 'Add doctor')
@section('content')
<x-page-header :title="$doctor->exists ? 'Edit '.$doctor->name : 'Add a doctor'" subtitle="Creates the doctor's login and professional profile."
    :back="$doctor->exists ? route('doctors.show', $doctor) : route('doctors.index')" :back-label="$doctor->exists ? $doctor->name : 'Doctors'" />

<form method="POST" action="{{ $doctor->exists ? route('doctors.update', $doctor) : route('doctors.store') }}" style="max-width:1000px">
    @csrf
    @if($doctor->exists) @method('PUT') @endif

    <section class="card cc-form-section">
        <div class="card-header"><h2 class="card-title">Professional profile</h2></div>
        <div class="card-body row">
            <x-select name="department_id" label="Department" :options="$departments->pluck('name', 'id')->all()" :value="$doctor->department_id" placeholder="Select department…" required col="col-md-6 mb-3" />
            <x-input name="specialization" label="Specialization" :value="$doctor->specialization" required col="col-md-6 mb-3" />
            <x-input name="license_no" label="Licence number" :value="$doctor->license_no" required col="col-md-6 mb-3" />
            <x-input name="consultation_fee" type="number" step="any" min="0" :label="'Consultation fee ('.config('clinic.currency').')'" :value="$doctor->consultation_fee" required col="col-md-6 mb-3" />
            <x-textarea name="bio" label="Short bio" :value="$doctor->bio" rows="3" col="col-12 mb-3" />
            @unless($doctor->exists)
                <div class="col-12">
                    <div class="form-check form-switch">
                        <input type="checkbox" class="form-check-input" role="switch" name="default_schedule" value="1" id="default_schedule" checked>
                        <label for="default_schedule" class="form-check-label">Create a default Mon–Fri 08:00–17:00 schedule (30-minute slots)</label>
                    </div>
                </div>
            @endunless
        </div>
    </section>

    <section class="card cc-form-section">
        <div class="card-header"><h2 class="card-title">Login account</h2></div>
        <div class="card-body row">
            <x-input name="name" label="Full name (without “Dr”)" :value="$doctor->user?->name" required col="col-md-6 mb-3" />
            <x-input name="email" type="email" label="Email" :value="$doctor->user?->email" required col="col-md-6 mb-3" />
            <x-input name="phone" type="tel" placeholder="+256 7XX XXX XXX" label="Phone" :value="$doctor->user?->phone" col="col-md-6 mb-3" />
            <div class="w-100"></div>
            <x-input name="password" type="password" :label="$doctor->exists ? 'New password (optional)' : 'Password'" :required="! $doctor->exists" autocomplete="new-password" col="col-md-6 mb-3" />
            <x-input name="password_confirmation" type="password" label="Confirm password" :required="! $doctor->exists" autocomplete="new-password" col="col-md-6 mb-3" />
        </div>
    </section>

    <div class="cc-form-actions">
        <a href="{{ $doctor->exists ? route('doctors.show', $doctor) : route('doctors.index') }}" class="btn btn-ghost">Cancel</a>
        <button type="submit" class="btn btn-primary">Save doctor</button>
    </div>
</form>
@endsection
