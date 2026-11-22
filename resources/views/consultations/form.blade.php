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

                {{-- Payment: the visit's invoice (raised on save) can be sent to the patient's Mobile Money --}}
                @php
                    $fee = $invoice ? $invoice->balance : (float) $appointment->doctor->consultation_fee;
                    $pendingMomo = $invoice?->mobileMoneyRequests->first(fn ($r) => $r->isPending());
                    $canOffer = ! $invoice || ($invoice->balance > 0 && ! $pendingMomo);
                @endphp
                <div class="cc-divider"></div>
                <section aria-labelledby="payment-heading">
                    <div class="d-flex flex-wrap justify-content-between align-items-baseline gap-2 mb-2">
                        <h2 class="h6 fw-semibold m-0" id="payment-heading">Payment</h2>
                        @if($invoice)
                            <span class="d-flex align-items-center gap-2 small"><span class="text-muted">{{ $invoice->invoice_no }}</span><x-status-badge :status="$invoice->status" /></span>
                        @else
                            <span class="small text-muted">Invoice of {{ money($fee) }} is raised on save</span>
                        @endif
                    </div>

                    @if($pendingMomo)
                        <div class="cc-callout info small"><i class="fa-solid fa-mobile-screen" aria-hidden="true"></i><div>A Mobile Money request for {{ money($pendingMomo->amount) }} to {{ $pendingMomo->display_phone }} is waiting for the patient to approve.</div></div>
                    @elseif($invoice && $invoice->balance <= 0)
                        <div class="cc-callout ok small"><i class="fa-solid fa-circle-check" aria-hidden="true"></i><div>This visit is paid.</div></div>
                    @elseif($canOffer)
                        <div class="form-check form-switch mb-3">
                            <input class="form-check-input" type="checkbox" role="switch" name="request_payment" value="1" id="request_payment" data-toggle-target="#momo-fields" @checked(old('request_payment'))>
                            <label class="form-check-label" for="request_payment">Send a Mobile Money request for <strong>{{ money($fee) }}</strong> to the patient when I save</label>
                        </div>
                        <div id="momo-fields" class="row" @unless(old('request_payment')) hidden @endunless>
                            <div class="col-md-12">
                                @include('mobile-money._fields', ['prefix' => 'momo_', 'phone' => $p->user->phone, 'col' => 'mb-1'])
                            </div>
                            <div class="col-12 small text-muted">The patient approves it on their phone with their PIN; the invoice updates by itself. They can also pay at the cash desk.</div>
                        </div>
                    @endif
                </section>
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
