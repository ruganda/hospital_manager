@extends('layouts.app')
@section('title', $payment->exists ? 'Edit payment' : 'Record payment')
@section('content')
<x-page-header :title="$payment->exists ? 'Edit payment' : 'Record a payment'" :subtitle="$payment->exists ? 'The invoice status updates as soon as you save.' : 'Ask the patient to pay by Mobile Money, or record a payment you have already received.'"
    :back="$invoice ? route('invoices.show', $invoice) : route('payments.index')" :back-label="$invoice ? $invoice->invoice_no : 'Payments'" />

<div class="row g-4">
    <div class="col-xl-7 d-flex flex-column gap-4">
        @unless($payment->exists)
            {{-- Collect by Mobile Money: sends a prompt to the patient's phone (MTN / Airtel via Flutterwave) --}}
            @php
                $momoInvoices = $invoice ? collect([$invoice]) : $openInvoices;
                $firstPhone = $invoice?->patient->user->phone;
            @endphp
            <form method="POST" action="{{ route('mobile-money.collect') }}" class="card" id="mobile-money">
                @csrf
                <div class="card-header"><h2 class="card-title">Collect by MTN Mobile Money or Airtel Money</h2></div>
                <div class="card-body">
                    <p class="small text-muted mt-0">The patient gets a prompt on their phone and approves it with their PIN. The payment is recorded automatically once it goes through.</p>
                    @if($invoice)
                        <input type="hidden" name="invoice_id" value="{{ $invoice->id }}">
                    @else
                        <div class="mb-3">
                            <label class="form-label" for="momo_invoice">Invoice <span class="text-danger" aria-hidden="true">*</span></label>
                            <select name="invoice_id" id="momo_invoice" class="form-select @error('invoice_id') is-invalid @enderror" required data-momo-invoice>
                                <option value="">Select an open invoice…</option>
                                @foreach($momoInvoices as $i)
                                    <option value="{{ $i->id }}" data-phone="{{ $i->patient->user->phone }}" data-balance="{{ (int) round($i->balance) }}" @selected((int) old('invoice_id') === $i->id)>{{ $i->invoice_no }} — {{ $i->patient->name }} — {{ money($i->balance) }} due</option>
                                @endforeach
                            </select>
                            @error('invoice_id')<div class="invalid-feedback">{{ $message }}</div>@enderror
                        </div>
                    @endif
                    @include('mobile-money._fields', ['prefix' => '', 'phone' => $firstPhone])
                    <x-input name="amount" type="number" step="1" min="1" :label="'Amount ('.config('clinic.currency').')'" :value="$invoice ? (int) round($invoice->balance) : null" required class="tabular" id="momo_amount" col="mb-0" />
                </div>
                <div class="card-footer d-flex flex-wrap justify-content-between align-items-center gap-2">
                    <span class="small text-muted">@if(config('services.mobile_money.driver') === 'fake')Demo mode: no real money moves.@endif</span>
                    <button type="submit" class="btn btn-primary"><i class="fa-solid fa-paper-plane" aria-hidden="true"></i>Send payment request</button>
                </div>
            </form>
        @endunless

        <form method="POST" action="{{ $payment->exists ? route('payments.update', $payment) : route('payments.store') }}" class="card">
            @csrf
            @if($payment->exists) @method('PUT') @endif
            @unless($payment->exists)
                <div class="card-header"><h2 class="card-title">Or record a payment already received</h2><span class="small text-muted">Cash, card, insurance, MoMo paid at the desk</span></div>
            @endunless
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
                <button type="submit" class="btn {{ $payment->exists ? 'btn-primary' : 'btn-white' }}"><i class="fa-solid fa-check" aria-hidden="true"></i>Save payment</button>
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
