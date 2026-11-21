@extends('layouts.app')
@section('title', $appointment->exists ? 'Reschedule appointment' : 'Book appointment')
@section('content')
<x-page-header :title="$appointment->exists ? 'Reschedule appointment' : 'Book an appointment'"
    subtitle="Only free times inside the doctor's schedule are shown, so no one is double-booked."
    :back="$appointment->exists ? route('appointments.show', $appointment) : route('appointments.index')"
    :back-label="$appointment->exists ? 'Appointment' : 'Appointments'" />

<form method="POST" action="{{ $appointment->exists ? route('appointments.update', $appointment) : route('appointments.store') }}">
    @csrf
    @if($appointment->exists) @method('PUT') @endif

    <div class="row g-4"
         data-slot-picker
         data-url-template="{{ route('ajax.slots', ['doctor' => '__ID__']) }}"
         data-ignore="{{ $appointment->id }}">
        <div class="col-lg-5 d-flex flex-column gap-4">
            <section class="card">
                <div class="card-header"><h2 class="card-title"><span class="cc-step-n">1</span>Patient</h2></div>
                <div class="card-body">
                    <x-patient-picker :selected="$selectedPatient" />
                    <div class="small text-muted">Not registered yet? <a href="{{ route('patients.create') }}" target="_blank" rel="noopener">Register a new patient <i class="fa-solid fa-arrow-up-right-from-square fa-xs" aria-hidden="true"></i></a></div>
                </div>
            </section>
            <section class="card">
                <div class="card-header"><h2 class="card-title"><span class="cc-step-n">2</span>Doctor &amp; date</h2></div>
                <div class="card-body">
                    <div class="mb-3">
                        <label class="form-label" for="doctor_id">Doctor <span class="text-danger" aria-hidden="true">*</span></label>
                        <select name="doctor_id" id="doctor_id" class="form-select @error('doctor_id') is-invalid @enderror" data-doctor required>
                            <option value="">Select a doctor…</option>
                            @foreach($departments as $dep)
                                @if($dep->doctors->isNotEmpty())
                                    <optgroup label="{{ $dep->name }}">
                                        @foreach($dep->doctors->sortBy('user.name') as $doc)
                                            <option value="{{ $doc->id }}" @selected(old('doctor_id', $appointment->doctor_id) == $doc->id)>{{ $doc->name }} — {{ $doc->specialization }}</option>
                                        @endforeach
                                    </optgroup>
                                @endif
                            @endforeach
                        </select>
                        @error('doctor_id')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>
                    <div data-doctor-info class="mb-3"></div>
                    <x-input name="appointment_date" type="date" label="Date" :value="$appointment->appointment_date?->toDateString()" :min="today()->toDateString()" data-date required />
                    <x-input name="reason" label="Reason for visit" :value="$appointment->reason" placeholder="e.g. Fever and headache" col="mb-0" />
                </div>
            </section>
        </div>
        <div class="col-lg-7">
            <section class="card h-100">
                <div class="card-header"><h2 class="card-title"><span class="cc-step-n">3</span>Time</h2><span class="small text-muted fw-normal">Live availability</span></div>
                <div class="card-body">
                    <input type="hidden" name="start_time" value="{{ old('start_time', $appointment->start_time ? substr($appointment->start_time, 0, 5) : '') }}" data-slot-value>
                    @error('start_time')<div class="cc-callout danger mb-3"><i class="fa-solid fa-triangle-exclamation" aria-hidden="true"></i><div>{{ $message }}</div></div>@enderror
                    <div data-slots aria-live="polite"></div>
                </div>
                <div class="card-footer d-flex flex-wrap justify-content-end gap-2">
                    <a href="{{ $appointment->exists ? route('appointments.show', $appointment) : route('appointments.index') }}" class="btn btn-ghost">Cancel</a>
                    <button type="submit" class="btn btn-primary btn-lg"><i class="fa-solid fa-check" aria-hidden="true"></i>{{ $appointment->exists ? 'Save changes' : 'Confirm booking' }}</button>
                </div>
            </section>
        </div>
    </div>
</form>
@endsection
