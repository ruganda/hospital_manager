@extends('layouts.app')
@section('title', 'Appointment')
@section('content')
@use('App\Enums\AppointmentStatus', 'S')
@php
    $a = $appointment;
    $u = auth()->user();
    $stepIndex = match ($a->status) {
        S::Scheduled => 1,
        S::CheckedIn => 2,
        S::Completed => $a->invoice?->status?->value === 'paid' ? 5 : ($a->invoice ? 4 : 3),
        default => 0,
    };
    $steps = ['Booked', 'Checked in', 'Seen', 'Invoiced', 'Paid'];
@endphp
<x-page-header :title="$a->appointment_date->format('D, j M Y').' · '.$a->time_range" :subtitle="'Booked by '.($a->bookedBy?->name ?? 'System').' on '.$a->created_at->format('j M Y, H:i')" :back="route('appointments.index')" back-label="Appointments">
    @can('delete', $a)<x-delete-button :action="route('appointments.destroy', $a)" label="Delete" size="md" />@endcan
    @can('update', $a)
        <button type="button" class="btn btn-danger-soft" data-bs-toggle="modal" data-bs-target="#cancelModal"><i class="fa-solid fa-ban" aria-hidden="true"></i>Cancel</button>
        <a href="{{ route('appointments.edit', $a) }}" class="btn btn-white"><i class="fa-regular fa-calendar" aria-hidden="true"></i>Reschedule</a>
    @endcan
    @can('changeStatus', $a)
        @if($a->status === S::Scheduled)
            <form method="POST" action="{{ route('appointments.status', $a) }}">@csrf @method('PATCH')<input type="hidden" name="status" value="checked_in">
                <button type="submit" class="btn btn-primary"><i class="fa-solid fa-check" aria-hidden="true"></i>Check in</button></form>
        @elseif($a->isEditable() && $u->hasRole('doctor'))
            <a href="{{ route('consultations.create', ['appointment_id' => $a->id]) }}" class="btn btn-primary"><i class="fa-solid fa-notes-medical" aria-hidden="true"></i>Start consultation</a>
        @endif
    @endcan
</x-page-header>

<div class="card mb-4">
    <div class="card-body d-flex flex-wrap align-items-center justify-content-between gap-3">
        @if($stepIndex > 0)
            <ol class="cc-steps" aria-label="Visit progress">
                @foreach($steps as $i => $label)
                    @if($i > 0)<li class="sep" aria-hidden="true"></li>@endif
                    <li class="{{ $i + 1 < $stepIndex ? 'done' : ($i + 1 === $stepIndex ? 'current' : '') }}" @if($i + 1 === $stepIndex) aria-current="step" @endif>
                        <span class="n">@if($i + 1 < $stepIndex)<i class="fa-solid fa-check" aria-hidden="true"></i>@else{{ $i + 1 }}@endif</span>{{ $label }}
                    </li>
                @endforeach
            </ol>
        @else
            <span class="small text-muted">This visit did not go ahead.</span>
        @endif
        <x-status-badge :status="$a->status" />
    </div>
</div>

@if($a->cancellation_reason)
    <div class="cc-callout mb-4"><i class="fa-solid fa-ban" aria-hidden="true"></i><div><strong>Cancellation reason:</strong> {{ $a->cancellation_reason }}</div></div>
@endif

<div class="row g-4">
    <div class="col-lg-7 d-flex flex-column gap-4">
        <div class="row g-4">
            <div class="col-md-6">
                <section class="card h-100">
                    <div class="card-header"><h2 class="card-title">Patient</h2>@can('view', $a->patient)<a href="{{ route('patients.show', $a->patient) }}" class="small fw-medium">Open record</a>@endcan</div>
                    <div class="card-body">
                        <div class="d-flex align-items-center gap-3 mb-3"><x-avatar :user="$a->patient->user" size="lg" /><div class="min-w-0"><div class="fw-semibold" style="color:var(--cc-ink)">{{ $a->patient->name }}</div><div class="small text-muted">{{ $a->patient->patient_no }}</div></div></div>
                        <dl class="cc-dl" style="grid-template-columns:90px minmax(0,1fr)">
                            <dt>Phone</dt><dd>{{ $a->patient->user->phone ?? '—' }}</dd>
                            <dt>Age</dt><dd>{{ $a->patient->age ? $a->patient->age.' yrs' : '—' }}@if($a->patient->gender) · {{ ucfirst($a->patient->gender) }}@endif</dd>
                            <dt>Allergies</dt><dd @if($a->patient->allergies) class="fw-semibold" style="color:#a8251b" @endif>{{ $a->patient->allergies ?: 'None recorded' }}</dd>
                        </dl>
                    </div>
                </section>
            </div>
            <div class="col-md-6">
                <section class="card h-100">
                    <div class="card-header"><h2 class="card-title">Visit</h2></div>
                    <div class="card-body">
                        <div class="d-flex align-items-center gap-3 mb-3"><x-avatar :user="$a->doctor->user" size="lg" /><div class="min-w-0"><div class="fw-semibold" style="color:var(--cc-ink)">{{ $a->doctor->name }}</div><div class="small text-muted">{{ $a->doctor->department->name }}</div></div></div>
                        <dl class="cc-dl" style="grid-template-columns:90px minmax(0,1fr)">
                            <dt>Reason</dt><dd>{{ $a->reason ?? '—' }}</dd>
                            <dt>Fee</dt><dd>{{ money($a->doctor->consultation_fee) }}</dd>
                        </dl>
                    </div>
                </section>
            </div>
        </div>

        @can('changeStatus', $a)
            @if($a->isEditable())
                <section class="card">
                    <div class="card-header"><h2 class="card-title">Update status</h2></div>
                    <div class="card-body d-flex flex-wrap gap-2">
                        @if($u->hasRole('doctor'))
                            <a href="{{ route('consultations.create', ['appointment_id' => $a->id]) }}" class="btn btn-white"><i class="fa-solid fa-notes-medical" aria-hidden="true"></i>Write consultation notes</a>
                        @else
                            <form method="POST" action="{{ route('appointments.status', $a) }}">@csrf @method('PATCH')<input type="hidden" name="status" value="completed">
                                <button type="submit" class="btn btn-white"><i class="fa-solid fa-circle-check" aria-hidden="true"></i>Mark completed</button></form>
                        @endif
                        <form method="POST" action="{{ route('appointments.status', $a) }}" data-confirm="Mark this patient as a no-show?">@csrf @method('PATCH')<input type="hidden" name="status" value="no_show">
                            <button type="submit" class="btn btn-danger-soft"><i class="fa-solid fa-user-xmark" aria-hidden="true"></i>No show</button></form>
                    </div>
                </section>
            @endif
        @endcan

        @if($a->consultation)
            @can('view', $a->consultation)
                <section class="card">
                    <div class="card-header"><h2 class="card-title">Consultation notes</h2><a href="{{ route('consultations.show', $a->consultation) }}" class="small fw-medium">Open</a></div>
                    <div class="card-body">
                        <dl class="cc-dl" style="grid-template-columns:110px minmax(0,1fr)">
                            <dt>Diagnosis</dt><dd class="fw-semibold">{{ $a->consultation->diagnosis }}</dd>
                            @if($a->consultation->treatment)<dt>Treatment</dt><dd>{{ $a->consultation->treatment }}</dd>@endif
                            @if($a->consultation->prescription)<dt>Prescription</dt><dd style="white-space:pre-line">{{ $a->consultation->prescription }}</dd>@endif
                        </dl>
                    </div>
                </section>
            @endcan
        @endif
    </div>

    <div class="col-lg-5 d-flex flex-column gap-4">
        @if($a->invoice && ! $u->hasRole('doctor'))
            <section class="card">
                <div class="card-header"><h2 class="card-title">Billing</h2><x-status-badge :status="$a->invoice->status" /></div>
                <div class="card-body">
                    <dl class="cc-dl" style="grid-template-columns:90px minmax(0,1fr)">
                        <dt>Invoice</dt><dd><a href="{{ route('invoices.show', $a->invoice) }}">{{ $a->invoice->invoice_no }}</a></dd>
                        <dt>Amount</dt><dd class="cc-money">{{ money($a->invoice->amount) }}</dd>
                        <dt>Paid</dt><dd class="tabular">{{ money($a->invoice->amount_paid) }}</dd>
                    </dl>
                </div>
            </section>
        @endif

        @if($u->hasRole('doctor', 'admin') && $history->isNotEmpty())
            <section class="card">
                <div class="card-header"><h2 class="card-title">Previous visits</h2></div>
                <div class="card-body">
                    <ul class="cc-timeline small">
                        @foreach($history as $h)
                            <li>
                                <div class="d-flex flex-wrap justify-content-between gap-2"><strong style="color:var(--cc-ink)">{{ $h->diagnosis }}</strong><span class="text-muted">{{ $h->created_at->format('j M Y') }}</span></div>
                                <div class="text-muted">{{ $h->doctor->name }}@if($h->treatment) · {{ Str::limit($h->treatment, 70) }}@endif</div>
                            </li>
                        @endforeach
                    </ul>
                </div>
            </section>
        @endif
    </div>
</div>

@can('update', $a)
<div class="modal fade" id="cancelModal" tabindex="-1" aria-labelledby="cancelModalTitle" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <form method="POST" action="{{ route('appointments.cancel', $a) }}" class="modal-content">
            @csrf @method('PATCH')
            <div class="modal-header"><h2 class="modal-title" id="cancelModalTitle">Cancel this appointment?</h2><button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button></div>
            <div class="modal-body">
                <label class="form-label" for="cancellation_reason">Reason <span class="text-danger" aria-hidden="true">*</span></label>
                <input type="text" name="cancellation_reason" id="cancellation_reason" class="form-control" required maxlength="255" placeholder="e.g. Patient asked to cancel by phone">
            </div>
            <div class="modal-footer"><button type="button" class="btn btn-ghost" data-bs-dismiss="modal">Keep appointment</button><button type="submit" class="btn btn-danger">Cancel appointment</button></div>
        </form>
    </div>
</div>
@endcan
@endsection
