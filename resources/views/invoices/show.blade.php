@extends('layouts.app')
@section('title', $invoice->invoice_no)
@section('content')
@php
    $canManage = auth()->user()->hasRole('admin', 'cashier');
    $paidPct = $invoice->amount > 0 ? min(100, round($invoice->amount_paid / $invoice->amount * 100)) : 0;
@endphp
<div class="cc-page-head">
    <div class="min-w-0">
        <a href="{{ route('invoices.index') }}" class="d-inline-flex align-items-center gap-2 small text-muted text-decoration-none mb-2"><i class="fa-solid fa-arrow-left" aria-hidden="true"></i>Invoices</a>
        <div class="d-flex flex-wrap align-items-center gap-3"><h1>{{ $invoice->invoice_no }}</h1><x-status-badge :status="$invoice->status" /></div>
        <p class="cc-sub">Issued {{ $invoice->created_at->format('j M Y') }}@if($invoice->due_date) · due {{ $invoice->due_date->format('j M Y') }}@endif · {{ $invoice->patient->name }}</p>
    </div>
    <div class="cc-page-actions">
        <a href="{{ route('invoices.pdf', $invoice) }}" class="btn btn-white"><i class="fa-solid fa-file-arrow-down" aria-hidden="true"></i>PDF</a>
        @if($canManage)<a href="{{ route('invoices.edit', $invoice) }}" class="btn btn-white"><i class="fa-solid fa-pen" aria-hidden="true"></i>Edit</a>@endif
        @if($canManage && $invoice->balance > 0)<a href="#record-payment" class="btn btn-primary"><i class="fa-solid fa-plus" aria-hidden="true"></i>Add payment</a>@endif
    </div>
</div>

<div class="row g-4">
    <div class="col-xl-7 d-flex flex-column gap-4">
        <section class="card">
            <div class="card-body p-4 p-md-5">
                <div class="d-flex flex-wrap justify-content-between gap-4 mb-5">
                    <div>
                        <div class="d-flex align-items-center gap-2 mb-2"><span class="cc-brand-mark" style="width:30px;height:30px;font-size:.85rem"><i class="fa-solid fa-house-medical" aria-hidden="true"></i></span><span class="fw-semibold" style="color:#111">{{ config('clinic.name') }}</span></div>
                        <div class="small text-muted">{{ config('clinic.address') }}<br>{{ config('clinic.phone') }}</div>
                    </div>
                    <div class="text-md-end">
                        <div class="small-caps mb-1">Billed to</div>
                        <div class="fw-semibold" style="color:#111">{{ $invoice->patient->name }}</div>
                        <div class="small text-muted">{{ $invoice->patient->patient_no }}<br>{{ $invoice->patient->user->phone }}</div>
                    </div>
                </div>
                <table class="table mb-4">
                    <thead><tr><th>Description</th><th class="text-end">Amount</th></tr></thead>
                    <tbody>
                        <tr>
                            <td><div class="cc-cell-main">{{ $invoice->description }}</div>
                                @if($invoice->appointment)<div class="cc-cell-sub">{{ $invoice->appointment->appointment_date->format('j M Y') }} · {{ $invoice->appointment->time_range }} · {{ $invoice->appointment->doctor->name }}</div>@endif</td>
                            <td class="text-end tabular">{{ money($invoice->amount) }}</td>
                        </tr>
                    </tbody>
                </table>
                <div class="ms-auto" style="max-width:340px">
                    <div class="d-flex justify-content-between py-1"><span class="text-muted">Total</span><span class="tabular">{{ money($invoice->amount) }}</span></div>
                    <div class="d-flex justify-content-between py-1"><span class="text-muted">Paid</span><span class="tabular">− {{ money($invoice->amount_paid) }}</span></div>
                    <div class="cc-divider my-2"></div>
                    <div class="d-flex justify-content-between align-items-baseline gap-3"><span class="fw-semibold" style="color:#111">Balance due</span><span class="cc-stat-value" style="font-size:1.5rem;{{ $invoice->balance > 0 ? 'color:#a8251b' : '' }}">{{ money($invoice->balance) }}</span></div>
                </div>
            </div>
        </section>

        <section class="card">
            <div class="card-header"><h2 class="card-title">Payments</h2><span class="small text-muted">{{ $paidPct }}% paid</span></div>
            <div class="px-4 pt-3"><div class="cc-bar" style="height:6px" role="img" aria-label="{{ $paidPct }}% paid"><span style="width:{{ $paidPct }}%"></span></div></div>
            <div class="table-responsive">
                <table class="table">
                    <thead><tr><th>Date</th><th>Method</th><th>Reference</th><th>Received by</th><th class="text-end">Amount</th>@if($canManage)<th class="text-end"><span class="visually-hidden">Actions</span></th>@endif</tr></thead>
                    <tbody>
                    @forelse($invoice->payments as $p)
                        <tr>
                            <td class="small text-nowrap">{{ $p->paid_at->format('j M Y, H:i') }}</td>
                            <td class="text-nowrap"><i class="fa-solid {{ $p->method->icon() }} me-2 text-muted" aria-hidden="true"></i>{{ $p->method->label() }}</td>
                            <td class="small tabular">{{ $p->reference ?? '—' }}</td>
                            <td class="small">{{ $p->receiver?->name ?? '—' }}</td>
                            <td class="text-end cc-money">{{ money($p->amount) }}</td>
                            @if($canManage)
                                <td class="cc-row-actions">
                                    <a href="{{ route('payments.edit', $p) }}" class="btn btn-ghost btn-icon" title="Edit" aria-label="Edit payment" data-bs-toggle="tooltip"><i class="fa-solid fa-pen"></i></a>
                                    <x-delete-button :action="route('payments.destroy', $p)" confirm="Remove this payment? The invoice balance will be recalculated." />
                                </td>
                            @endif
                        </tr>
                    @empty
                        <x-empty :colspan="$canManage ? 6 : 5" message="No payments recorded." icon="fa-receipt" />
                    @endforelse
                    </tbody>
                </table>
            </div>
        </section>
    </div>

    @if($canManage && $invoice->balance > 0)
        <div class="col-xl-5">
            <form method="POST" action="{{ route('payments.store') }}" class="card" id="record-payment">
                @csrf
                <input type="hidden" name="invoice_id" value="{{ $invoice->id }}">
                <div class="card-header"><h2 class="card-title">Record a payment</h2><span class="small text-muted">{{ money($invoice->balance) }} due</span></div>
                <div class="card-body">
                    @include('payments._method-picker', ['selected' => old('method', 'cash')])
                    <x-input name="amount" type="number" step="any" min="0.01" :max="$invoice->balance" :label="'Amount ('.config('clinic.currency').')'" :value="$invoice->balance" required class="tabular" />
                    <x-input name="reference" label="Reference / transaction ID" placeholder="e.g. MoMo transaction ID" />
                    <x-input name="paid_at" type="datetime-local" label="Paid at" :value="now()->format('Y-m-d\TH:i')" required col="mb-0" />
                </div>
                <div class="card-footer"><button type="submit" class="btn btn-primary w-100"><i class="fa-solid fa-check" aria-hidden="true"></i>Save payment</button></div>
            </form>
        </div>
    @endif
</div>
@endsection
