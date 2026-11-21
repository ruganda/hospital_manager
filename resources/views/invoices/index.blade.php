@extends('layouts.app')
@section('title', 'Invoices')
@section('content')
@php
    $u = auth()->user();
    $canManage = $u->hasRole('admin', 'cashier');
    $isPatient = $u->hasRole('patient');
@endphp
<x-page-header :title="$isPatient ? 'Bills & payments' : 'Invoices'" subtitle="Consultation invoices are raised automatically when a visit is completed.">
    @if($canManage)
        <a href="{{ route('invoices.create') }}" class="btn btn-white"><i class="fa-solid fa-plus" aria-hidden="true"></i>Manual invoice</a>
        <a href="{{ route('payments.create') }}" class="btn btn-primary"><i class="fa-solid fa-cash-register" aria-hidden="true"></i>Record payment</a>
    @endif
</x-page-header>

<div class="cc-meta">
    <div><div class="small text-muted">{{ $isPatient ? 'Balance due' : 'Outstanding' }}</div><div class="cc-stat-value" data-countup>{{ money($totals->billed - $totals->paid) }}</div></div>
    <div><div class="small text-muted">Billed</div><div class="cc-stat-value" data-countup>{{ money($totals->billed) }}</div></div>
    <div><div class="small text-muted">Paid</div><div class="cc-stat-value" data-countup>{{ money($totals->paid) }}</div></div>
</div>

<div class="card">
    <div class="cc-toolbar">
        <div class="cc-tabs" role="group" aria-label="Filter by status" data-filter-tabs="#invoices-table">
            <button type="button" data-filter="all" aria-pressed="true">All <span class="count">{{ $invoices->count() }}</span></button>
            <button type="button" data-filter="unpaid" aria-pressed="false">Unpaid <span class="count">{{ $invoices->where('status.value', 'unpaid')->count() }}</span></button>
            <button type="button" data-filter="partial" aria-pressed="false">Partial <span class="count">{{ $invoices->where('status.value', 'partial')->count() }}</span></button>
            <button type="button" data-filter="paid" aria-pressed="false">Paid <span class="count">{{ $invoices->where('status.value', 'paid')->count() }}</span></button>
        </div>
    </div>
    <div class="table-responsive">
        <table class="table table-hover" id="invoices-table">
            <thead><tr><th>Invoice</th>@unless($isPatient)<th>Patient</th>@endunless<th>Description</th><th class="text-end">Amount</th><th class="text-end">Paid</th><th class="text-end">Balance</th><th>Status</th><th class="text-end"><span class="visually-hidden">Actions</span></th></tr></thead>
            <tbody>
            @forelse($invoices as $inv)
                <tr data-filter-value="{{ $inv->status->value }}">
                    <td class="text-nowrap"><a href="{{ route('invoices.show', $inv) }}" class="cc-cell-main">{{ $inv->invoice_no }}</a><div class="cc-cell-sub">{{ $inv->created_at->format('j M Y') }}</div></td>
                    @unless($isPatient)<td>{{ $inv->patient->name }}<div class="cc-cell-sub">{{ $inv->patient->patient_no }}</div></td>@endunless
                    <td class="small">{{ Str::limit($inv->description, 50) }}</td>
                    <td class="text-end tabular text-nowrap">{{ money($inv->amount) }}</td>
                    <td class="text-end tabular text-nowrap text-muted">{{ money($inv->amount_paid) }}</td>
                    <td class="text-end cc-money" @if($inv->balance > 0) style="color:#a8251b" @endif>{{ money($inv->balance) }}</td>
                    <td><x-status-badge :status="$inv->status" /></td>
                    <td class="cc-row-actions">
                        @if($canManage && $inv->balance > 0)
                            <a href="{{ route('payments.create', ['invoice_id' => $inv->id]) }}" class="btn btn-outline-primary btn-sm">Take payment</a>
                        @endif
                        <a href="{{ route('invoices.pdf', $inv) }}" class="btn btn-ghost btn-icon" title="Download PDF" aria-label="Download PDF" data-bs-toggle="tooltip"><i class="fa-solid fa-file-arrow-down"></i></a>
                        @if($canManage)
                            <a href="{{ route('invoices.edit', $inv) }}" class="btn btn-ghost btn-icon" title="Edit" aria-label="Edit invoice" data-bs-toggle="tooltip"><i class="fa-solid fa-pen"></i></a>
                            <x-delete-button :action="route('invoices.destroy', $inv)" confirm="Delete this invoice and its payments?" />
                        @endif
                    </td>
                </tr>
            @empty
                <x-empty :colspan="$isPatient ? 7 : 8" message="No invoices found." icon="fa-file-invoice" />
            @endforelse
            @if($invoices->isNotEmpty())
                <tr data-filter-empty hidden><td colspan="{{ $isPatient ? 7 : 8 }}" class="p-0"><x-empty message="No invoices with this status." icon="fa-filter" /></td></tr>
            @endif
            </tbody>
        </table>
    </div>
    <div class="card-footer small text-muted">Showing the latest {{ $invoices->count() }} {{ Str::plural('invoice', $invoices->count()) }}. Totals above cover all invoices.</div>
</div>
@endsection
