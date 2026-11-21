@extends('layouts.app')
@section('title', 'Consultation')
@section('content')
@php $c = $consultation; @endphp
<x-page-header :title="$c->diagnosis" :subtitle="$c->created_at->format('D, j M Y').' · '.$c->doctor->name.' · '.$c->doctor->department->name" :back="route('consultations.index')" back-label="Consultations">
    @can('delete', $c)<x-delete-button :action="route('consultations.destroy', $c)" label="Delete" size="md" />@endcan
    @can('update', $c)<a href="{{ route('consultations.edit', $c) }}" class="btn btn-white"><i class="fa-solid fa-pen" aria-hidden="true"></i>Edit</a>@endcan
</x-page-header>

<div class="row g-4">
    <div class="col-lg-8">
        <section class="card">
            <div class="card-body">
                <dl class="cc-dl" style="grid-template-columns:130px minmax(0,1fr);row-gap:20px">
                    @foreach(['symptoms' => 'Symptoms', 'diagnosis' => 'Diagnosis', 'treatment' => 'Treatment', 'prescription' => 'Prescription', 'notes' => 'Notes'] as $field => $label)
                        @if($c->$field)
                            <dt>{{ $label }}</dt><dd style="white-space:pre-line" @if($field === 'diagnosis') class="fw-semibold" @endif>{{ $c->$field }}</dd>
                        @endif
                    @endforeach
                </dl>
                @if($c->follow_up_date)
                    <div class="cc-callout info mt-4"><i class="fa-regular fa-calendar" aria-hidden="true"></i><div>Follow-up recommended on <strong>{{ $c->follow_up_date->format('l, j M Y') }}</strong></div></div>
                @endif
            </div>
        </section>
    </div>
    <div class="col-lg-4">
        <section class="card">
            <div class="card-header"><h2 class="card-title">Visit</h2></div>
            <div class="card-body">
                <dl class="cc-dl" style="grid-template-columns:100px minmax(0,1fr)">
                    <dt>Patient</dt><dd>@can('view', $c->patient)<a href="{{ route('patients.show', $c->patient) }}">{{ $c->patient->name }}</a>@else{{ $c->patient->name }}@endcan<div class="small text-muted">{{ $c->patient->patient_no }}</div></dd>
                    <dt>Appointment</dt><dd><a href="{{ route('appointments.show', $c->appointment) }}">{{ $c->appointment->appointment_date->format('j M Y') }} · {{ $c->appointment->time_range }}</a></dd>
                    <dt>Doctor</dt><dd>{{ $c->doctor->name }}</dd>
                </dl>
            </div>
        </section>
    </div>
</div>
@endsection
