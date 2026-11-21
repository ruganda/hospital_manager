@extends('layouts.app')
@section('title', $invoice->exists ? 'Edit invoice' : 'New invoice')
@section('content')
<x-page-header :title="$invoice->exists ? 'Edit '.$invoice->invoice_no : 'New manual invoice'" subtitle="For lab tests, procedures or other charges. Consultation invoices are created automatically."
    :back="$invoice->exists ? route('invoices.show', $invoice) : route('invoices.index')" :back-label="$invoice->exists ? $invoice->invoice_no : 'Invoices'" />

<form method="POST" action="{{ $invoice->exists ? route('invoices.update', $invoice) : route('invoices.store') }}" class="card cc-narrow">
    @csrf
    <div class="card-body">
        @if($invoice->exists)
            @method('PUT')
            <input type="hidden" name="patient_id" value="{{ $invoice->patient_id }}">
            <div class="mb-3"><label class="form-label" for="patient_display">Patient</label><input id="patient_display" class="form-control" value="{{ $invoice->patient->name }} ({{ $invoice->patient->patient_no }})" disabled></div>
        @else
            <x-patient-picker :selected="$selectedPatient" />
        @endif
        <x-input name="description" label="Description" :value="$invoice->description" required placeholder="e.g. Full blood count" />
        <div class="row">
            <x-input name="amount" type="number" step="any" min="0.01" :label="'Amount ('.config('clinic.currency').')'" :value="$invoice->amount" required class="tabular" col="col-md-6 mb-0" />
            <x-input name="due_date" type="date" label="Due date" :value="$invoice->due_date?->toDateString()" help="Optional" col="col-md-6 mb-0" />
        </div>
    </div>
    <div class="card-footer d-flex justify-content-end gap-2">
        <a href="{{ $invoice->exists ? route('invoices.show', $invoice) : route('invoices.index') }}" class="btn btn-ghost">Cancel</a>
        <button type="submit" class="btn btn-primary">Save invoice</button>
    </div>
</form>
@endsection
