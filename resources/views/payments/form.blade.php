@extends('layouts.app')
@section('title', $payment->exists ? 'Edit payment' : 'Record payment')
@section('content')
<x-page-header :title="$payment->exists ? 'Edit payment' : 'Record a payment'" subtitle="The invoice status updates as soon as you save."
    :back="$invoice ? route('invoices.show', $invoice) : route('payments.index')" :back-label="$invoice ? $invoice->invoice_no : 'Payments'" />

<div class="row g-4">
    <div class="col-xl-7">
        <form method="POST" action="{{ $payment->exists ? route('payments.update', $payment) : route('payments.store') }}" class="card">
            @csrf
            @if($payment->exists) @method('PUT') @endif
            <div class="card-body">
                @if($invoice)
                    <input type="hidden" name="invoice_id" value="{{ $invoice->id }}">
                @else
                    <x-select name="invoice_id" label="Invoice" :options="$openInvoices->mapWithKeys(fn ($i) => [$i->id => $i->invoice_no.' — '.$i->patient->name.' — '.money($i->balance).' due'])->all()" placeholder="Select an open invoice…" required />
                @endif

                @include('payments._method-picker', ['selected' => old('method', $payment->method ?? 'cash')])

                <div class="row">
                    <x-input name="amount" type="number" step="any" min="0.01" :label="'Amount ('.config('clinic.currency').')'" :value="$payment->amount ?? ($invoice && ! $payment->exists ? $invoice->balance : null)" required class="tabular" col="col-md-6 mb-3" />
                    <x-input name="reference" label="Reference / transaction ID" :value="$payment->reference" placeholder="e.g. MoMo transaction ID" col="col-md-6 mb-3" />
                    <x-input name="paid_at" type="datetime-local" label="Paid at" :value="$payment->paid_at?->format('Y-m-d\TH:i') ?? now()->format('Y-m-d\TH:i')" required col="col-md-6 mb-3" />
                    <x-input name="notes" label="Note" :value="$payment->notes" help="Optional" col="col-md-6 mb-3" />
                </div>
            </div>
            <div class="card-footer d-flex justify-content-end gap-2">
                <a href="{{ $invoice ? route('invoices.show', $invoice) : route('payments.index') }}" class="btn btn-ghost">Cancel</a>
                <button type="submit" class="btn btn-primary"><i class="fa-solid fa-check" aria-hidden="true"></i>Save payment</button>
            </div>
        </form>
    </div>
    @if($invoice)
        <div class="col-xl-5">
            <section class="card">
                <div class="card-header"><h2 class="card-title">{{ $invoice->invoice_no }}</h2><x-status-badge :status="$invoice->status" /></div>
                <div class="card-body">
                    <dl class="cc-dl" style="grid-template-columns:110px minmax(0,1fr)">
                        <dt>Patient</dt><dd>{{ $invoice->patient->name }}<div class="small text-muted">{{ $invoice->patient->patient_no }}</div></dd>
                        <dt>For</dt><dd>{{ $invoice->description }}</dd>
                        <dt>Invoiced</dt><dd class="tabular">{{ money($invoice->amount) }}</dd>
                        <dt>Paid so far</dt><dd class="tabular">{{ money($invoice->amount_paid) }}</dd>
                    </dl>
                    <div class="cc-divider"></div>
                    <div class="d-flex justify-content-between align-items-baseline"><span class="text-muted">Balance due</span><span class="cc-money fs-5" @if($invoice->balance > 0) style="color:#a8251b" @endif>{{ money($invoice->balance) }}</span></div>
                </div>
            </section>
        </div>
    @endif
</div>
@endsection
