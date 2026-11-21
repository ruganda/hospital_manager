@extends('layouts.app')
@section('title', $patient->exists ? 'Edit patient' : 'Register patient')
@section('content')
<x-page-header :title="$patient->exists ? 'Edit '.$patient->name : 'Register a patient'"
    :subtitle="$patient->exists ? 'Patient no. '.$patient->patient_no : 'A patient number is assigned when you save.'"
    :back="$patient->exists ? route('patients.show', $patient) : route('patients.index')"
    :back-label="$patient->exists ? $patient->name : 'Patients'" />

<form method="POST" action="{{ $patient->exists ? route('patients.update', $patient) : route('patients.store') }}" class="cc-narrow" style="max-width:1000px">
    @csrf
    @if($patient->exists) @method('PUT') @endif

    @error('duplicate')
        <div class="cc-callout warn mb-4">
            <i class="fa-solid fa-user-group" aria-hidden="true"></i>
            <div>
                <div class="fw-semibold mb-1">Possible duplicate</div>
                <div class="mb-2">{{ $message }}</div>
                <div class="form-check">
                    <input class="form-check-input" type="checkbox" name="confirm_not_duplicate" value="1" id="confirm_not_duplicate">
                    <label class="form-check-label" for="confirm_not_duplicate">I checked: this is a <strong>different person</strong>, save anyway.</label>
                </div>
            </div>
        </div>
    @enderror

    <section class="card cc-form-section">
        <div class="card-header"><h2 class="card-title">Personal details</h2></div>
        <div class="card-body row">
            <x-input name="name" label="Full name" :value="$patient->user?->name" required autocomplete="off" col="col-md-6 mb-3" />
            <x-input name="date_of_birth" type="date" :max="today()->subDay()->toDateString()" min="1900-01-01" label="Date of birth" :value="$patient->date_of_birth?->toDateString()" required col="col-md-3 mb-3" />
            <x-select name="gender" label="Gender" :options="['male' => 'Male', 'female' => 'Female', 'other' => 'Other']" :value="$patient->gender" placeholder="Select…" required col="col-md-3 mb-3" />
            <x-input name="national_id" label="National ID (NIN) / passport" placeholder="e.g. CM90012345ABCD" :value="$patient->national_id" help="Optional" col="col-md-6 mb-3" />
            <x-select name="blood_group" label="Blood group" :options="array_combine(\App\Models\Patient::BLOOD_GROUPS, \App\Models\Patient::BLOOD_GROUPS)" :value="$patient->blood_group" placeholder="Unknown" col="col-md-3 mb-3" />
        </div>
    </section>

    <section class="card cc-form-section">
        <div class="card-header"><h2 class="card-title">Contact</h2></div>
        <div class="card-body row">
            <x-input name="phone" type="tel" placeholder="+256 7XX XXX XXX" label="Phone" :value="$patient->user?->phone" required col="col-md-6 mb-3" />
            <x-input name="email" type="email" label="Email (portal login)" :value="$patient->user?->email" required col="col-md-6 mb-3" />
            <x-input name="address" label="Address" :value="$patient->address" placeholder="e.g. Plot 12, Ntinda Road, Ntinda, Kampala" col="col-12 mb-3" />
            <x-input name="emergency_contact_name" label="Emergency contact name" :value="$patient->emergency_contact_name" col="col-md-6 mb-3" />
            <x-input name="emergency_contact_phone" type="tel" placeholder="+256 7XX XXX XXX" label="Emergency contact phone" :value="$patient->emergency_contact_phone" col="col-md-6 mb-3" />
        </div>
    </section>

    <section class="card cc-form-section">
        <div class="card-header"><h2 class="card-title">Medical</h2></div>
        <div class="card-body">
            <x-textarea name="allergies" label="Known allergies" :value="$patient->allergies" rows="2" placeholder="e.g. Penicillin, sulfa drugs. Leave blank if none." col="mb-0" />
        </div>
    </section>

    <section class="card cc-form-section">
        <div class="card-header"><h2 class="card-title">Patient portal password</h2></div>
        <div class="card-body row">
            <x-input name="password" type="password" label="Password" autocomplete="new-password" col="col-md-6 mb-3" />
            <x-input name="password_confirmation" type="password" label="Confirm password" autocomplete="new-password" col="col-md-6 mb-3" />
            <div class="col-12 form-text mt-0">{{ $patient->exists ? 'Leave blank to keep the current password.' : 'Optional. Leave blank to generate a random one; the patient can reset it later.' }}</div>
        </div>
    </section>

    <div class="cc-form-actions">
        <a href="{{ $patient->exists ? route('patients.show', $patient) : route('patients.index') }}" class="btn btn-ghost">Cancel</a>
        <button type="submit" class="btn btn-primary">Save patient</button>
    </div>
</form>
@endsection
