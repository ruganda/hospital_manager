@extends('layouts.app')
@section('title', 'Consultation notes')
@section('content')
@php $p = $appointment->patient; @endphp
<x-page-header :title="$p->name" :subtitle="($consultation->exists ? 'Editing notes · ' : '').$appointment->appointment_date->format('D, j M Y').' · '.$appointment->time_range.($appointment->reason ? ' · '.$appointment->reason : '')" :back="route('appointments.show', $appointment)" back-label="Appointment">
    <a href="{{ route('patients.show', $p) }}" class="btn btn-white" target="_blank" rel="noopener"><i class="fa-solid fa-folder-open" aria-hidden="true"></i>Full history</a>
</x-page-header>

<div class="row g-4">
    <div class="col-xl-8">
        <form method="POST" action="{{ $consultation->exists ? route('consultations.update', $consultation) : route('consultations.store') }}" class="card">
            @csrf
            @if($consultation->exists) @method('PUT') @else <input type="hidden" name="appointment_id" value="{{ $appointment->id }}"> @endif
            <div class="card-body">
                <x-textarea name="symptoms" label="Presenting symptoms" :value="$consultation->symptoms" rows="2" />
                <x-textarea name="diagnosis" label="Diagnosis" :value="$consultation->diagnosis" rows="2" required />
                <x-textarea name="treatment" label="Treatment given / plan" :value="$consultation->treatment" rows="3" />
                <x-textarea name="prescription" label="Prescription" :value="$consultation->prescription" rows="3" placeholder="Drug, dose, frequency, duration" />
                @if($p->allergies)
                    <div class="cc-callout danger mb-3" style="background:#fff"><i class="fa-solid fa-triangle-exclamation" aria-hidden="true"></i><div>Allergies on record: <strong>{{ $p->allergies }}</strong></div></div>
                @endif
                <div class="row">
                    <x-input name="follow_up_date" type="date" label="Follow-up date" :value="$consultation->follow_up_date?->toDateString()" :min="today()->addDay()->toDateString()" help="The patient sees this in their portal." col="col-md-5 mb-3" />
                    <x-textarea name="notes" label="Additional notes" :value="$consultation->notes" rows="2" col="col-md-7 mb-3" />
                </div>
            </div>
            <div class="card-footer d-flex flex-wrap justify-content-between align-items-center gap-2">
                <span class="small text-muted">@unless($consultation->exists)Saving marks the visit completed and sends the consultation fee to billing.@endunless</span>
                <div class="d-flex gap-2">
                    <a href="{{ route('appointments.show', $appointment) }}" class="btn btn-ghost">Cancel</a>
                    <button type="submit" class="btn btn-primary"><i class="fa-solid fa-check" aria-hidden="true"></i>{{ $consultation->exists ? 'Update notes' : 'Save & complete visit' }}</button>
                </div>
            </div>
        </form>
    </div>
    <div class="col-xl-4">
        <section class="card">
            <div class="card-header"><h2 class="card-title">Patient</h2><a href="{{ route('patients.show', $p) }}" class="small fw-medium">Record</a></div>
            <div class="card-body">
                <dl class="cc-dl" style="grid-template-columns:100px minmax(0,1fr)">
                    <dt>Patient no.</dt><dd>{{ $p->patient_no }}</dd>
                    <dt>Age</dt><dd>{{ $p->age ? $p->age.' yrs' : '—' }}@if($p->gender) · {{ ucfirst((string) $p->gender) }}@endif</dd>
                    <dt>Blood group</dt><dd>{{ $p->blood_group ?? '—' }}</dd>
                    <dt>Allergies</dt><dd @if($p->allergies) class="fw-semibold" style="color:#a8251b" @endif>{{ $p->allergies ?: 'None recorded' }}</dd>
                    <dt>Reason</dt><dd>{{ $appointment->reason ?? '—' }}</dd>
                </dl>
            </div>
        </section>
    </div>
</div>
@endsection
