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
    <div class="col-lg-4 d-flex flex-column gap-4">
        <section class="card">
            <div class="card-header"><h2 class="card-title">Visit</h2></div>
            <div class="card-body">
                <dl class="cc-dl" style="grid-template-columns:100px minmax(0,1fr)">
                    <dt>Patient</dt><dd>@can('view', $c->patient)<a href="{{ route('patients.show', $c->patient) }}">{{ $c->patient->name }}</a>@else{{ $c->patient->name }}@endcan<div class="small text-muted">{{ $c->patient->patient_no }}</div></dd>
                    <dt>Appointment</dt><dd><a href="{{ route('appointments.show', $c->appointment) }}">{{ $c->appointment->appointment_date->format('j M Y') }} · {{ $c->appointment->time_range }}</a></dd>
                    <dt>Doctor</dt><dd>{{ $c->doctor->name }}</dd>
                    @if($c->recorder && $c->recorder->id !== $c->doctor->user_id)
                        <dt>Entered by</dt><dd>{{ $c->recorder->name }} <span class="text-muted small">({{ $c->recorder->role->label() }})</span></dd>
                    @endif
                </dl>
            </div>
        </section>
        @if($invoice && auth()->user()->can('followMobileMoney', $invoice))
            @php $pendingMomo = $invoice->mobileMoneyRequests->first(fn ($r) => $r->isPending()); @endphp
            <section class="card" id="payment">
                <div class="card-header"><h2 class="card-title">Payment</h2><x-status-badge :status="$invoice->status" /></div>
                <div class="card-body">
                    <dl class="cc-dl" style="grid-template-columns:100px minmax(0,1fr)">
                        <dt>Invoice</dt><dd>@can('view', $invoice)<a href="{{ route('invoices.show', $invoice) }}">{{ $invoice->invoice_no }}</a>@else{{ $invoice->invoice_no }}@endcan</dd>
                        <dt>Fee</dt><dd class="tabular">{{ money($invoice->amount) }}</dd>
                        <dt>Balance</dt><dd class="cc-money" @if($invoice->balance > 0) style="color:#a8251b" @endif>{{ money($invoice->balance) }}</dd>
                    </dl>
                </div>
                @if(! $pendingMomo && auth()->user()->can('payMobileMoney', $invoice))
                    <form method="POST" action="{{ route('mobile-money.store', $invoice) }}" class="border-top px-4 pt-3 pb-4">
                        @csrf
                        <input type="hidden" name="amount" value="{{ (int) round($invoice->balance) }}">
                        @php $isPatient = auth()->user()->hasRole('patient'); @endphp
                        <div class="small fw-semibold mb-2" style="color:var(--cc-ink)">{{ $isPatient ? 'Pay with Mobile Money' : 'Request payment by Mobile Money' }}</div>
                        @include('mobile-money._fields', ['prefix' => '', 'phone' => $consultation->patient->user->phone])
                        <button type="submit" class="btn btn-primary w-100"><i class="fa-solid fa-paper-plane" aria-hidden="true"></i>{{ $isPatient ? 'Pay '.money($invoice->balance) : 'Send '.money($invoice->balance).' request' }}</button>
                    </form>
                @endif
            </section>
            @if($invoice->mobileMoneyRequests->isNotEmpty())
                @include('mobile-money._requests', ['requests' => $invoice->mobileMoneyRequests])
            @endif
        @endif
    </div>
</div>
@endsection
