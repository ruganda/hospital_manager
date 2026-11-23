@extends('layouts.app')
@section('title', 'Payments')
@section('content')
<x-page-header title="Payments" subtitle="Every receipt, newest first.">
    <a href="{{ route('reports.index') }}#payments" class="btn btn-white"><i class="fa-solid fa-file-arrow-down" aria-hidden="true"></i>Export</a>
    <a href="{{ route('mobile-money.index') }}" class="btn btn-white"><i class="fa-solid fa-mobile-screen" aria-hidden="true"></i>Mobile Money</a>
    <a href="{{ route('payments.create') }}" class="btn btn-primary"><i class="fa-solid fa-plus" aria-hidden="true"></i>Record payment</a>
</x-page-header>

<div class="cc-meta">
    <div><div class="small text-muted">Total collected (all payments)</div><div class="cc-stat-value" data-countup>{{ money($total) }}</div></div>
</div>

<div class="card">
    <div class="cc-toolbar">
        <div class="cc-tabs" role="group" aria-label="Filter by method" data-filter-tabs="#payments-table">
            <button type="button" data-filter="all" aria-pressed="true">All</button>
            @foreach(\App\Enums\PaymentMethod::cases() as $m)
                <button type="button" data-filter="{{ $m->value }}" aria-pressed="false">{{ str_contains($m->label(), '(') ? trim(explode('(', $m->label())[0]) : $m->label() }}</button>
            @endforeach
        </div>
    </div>
    <div class="table-responsive">
        <table class="table table-hover" id="payments-table">
            <thead><tr><th>Received</th><th>Patient</th><th>Invoice</th><th>Method</th><th>Reference</th><th>Recorded by</th><th class="text-end">Amount</th><th class="text-end"><span class="visually-hidden">Actions</span></th></tr></thead>
            <tbody>
            @forelse($payments as $p)
                <tr data-filter-value="{{ $p->method->value }}">
                    <td class="text-nowrap">{{ $p->paid_at->format('j M Y') }}<div class="cc-cell-sub tabular">{{ $p->paid_at->format('H:i') }}</div></td>
                    <td class="cc-cell-main">{{ $p->invoice->patient->name }}</td>
                    <td><a href="{{ route('invoices.show', $p->invoice) }}">{{ $p->invoice->invoice_no }}</a></td>
                    <td class="text-nowrap"><i class="fa-solid {{ $p->method->icon() }} me-2 text-muted" aria-hidden="true"></i>{{ $p->method->label() }}</td>
                    <td class="small tabular text-muted">{{ $p->reference ?? '—' }}</td>
                    <td class="small">{{ $p->receiver?->name ?? '—' }}</td>
                    <td class="text-end cc-money">{{ money($p->amount) }}</td>
                    <td class="cc-row-actions">
                        <a href="{{ route('payments.edit', $p) }}" class="btn btn-ghost btn-icon" title="Edit" aria-label="Edit payment" data-bs-toggle="tooltip"><i class="fa-solid fa-pen"></i></a>
                        <x-delete-button :action="route('payments.destroy', $p)" confirm="Remove this payment? The invoice balance will be recalculated." />
                    </td>
                </tr>
            @empty
                <x-empty colspan="8" message="No payments found." icon="fa-receipt" />
            @endforelse
            @if($payments->isNotEmpty())
                <tr data-filter-empty hidden><td colspan="8" class="p-0"><x-empty message="No payments by this method in the list." icon="fa-filter" /></td></tr>
            @endif
            </tbody>
        </table>
    </div>
    <div class="card-footer small text-muted">Showing the latest {{ $payments->count() }} {{ Str::plural('payment', $payments->count()) }}.</div>
</div>
@endsection
