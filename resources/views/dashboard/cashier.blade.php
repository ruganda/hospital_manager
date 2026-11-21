@extends('layouts.app')
@section('title', 'Dashboard')
@section('content')
<div data-charts-url="{{ route('ajax.dashboard.charts') }}"></div>

<x-page-header title="Cash desk" :eyebrow="now()->format('l, j F')" subtitle="Collections and open invoices.">
    <a href="{{ route('invoices.index') }}" class="btn btn-white">Invoices</a>
    <a href="{{ route('payments.create') }}" class="btn btn-primary"><i class="fa-solid fa-cash-register" aria-hidden="true"></i>Record payment</a>
</x-page-header>

<section class="card cc-kpis mb-4" aria-label="Collections">
    <x-kpi label="Collected today" :value="money($stats['today'])" :hint="$stats['today_count'].' '.Str::plural('payment', $stats['today_count'])" :href="route('payments.index')" />
    <x-kpi label="Collected this month" :value="money($stats['month'])" />
    <x-kpi label="Outstanding" :value="money($stats['outstanding'])" hint="Across all open invoices" :href="route('invoices.index')" />
    <x-kpi label="Unpaid / part-paid" :value="$stats['unpaid'].' / '.$stats['partial']" hint="Invoices" :href="route('invoices.index')" />
</section>

<div class="row g-4 mb-4">
    <div class="col-xl-8">
        <section class="card h-100">
            <div class="card-header"><h2 class="card-title">Awaiting payment</h2><span class="small text-muted">Oldest first</span></div>
            <div class="table-responsive">
                <table class="table table-hover">
                    <thead><tr><th>Invoice</th><th>Patient</th><th class="text-end">Balance</th><th>Status</th><th class="text-end"><span class="visually-hidden">Actions</span></th></tr></thead>
                    <tbody>
                    @forelse($openInvoices as $inv)
                        <tr>
                            <td><a href="{{ route('invoices.show', $inv) }}" class="cc-cell-main">{{ $inv->invoice_no }}</a><div class="cc-cell-sub">{{ $inv->created_at->format('j M Y') }}</div></td>
                            <td>{{ $inv->patient->name }}<div class="cc-cell-sub">{{ $inv->patient->patient_no }}</div></td>
                            <td class="text-end cc-money">{{ money($inv->balance) }}</td>
                            <td><x-status-badge :status="$inv->status" /></td>
                            <td class="cc-row-actions"><a href="{{ route('payments.create', ['invoice_id' => $inv->id]) }}" class="btn btn-outline-primary btn-sm">Take payment</a></td>
                        </tr>
                    @empty
                        <x-empty colspan="5" message="All invoices are settled." icon="fa-circle-check" />
                    @endforelse
                    </tbody>
                </table>
            </div>
            <div class="card-footer"><a href="{{ route('invoices.index') }}" class="small fw-medium">All invoices</a></div>
        </section>
    </div>
    <div class="col-xl-4">
        <section class="card h-100">
            <div class="card-header"><h2 class="card-title">Latest receipts</h2><a href="{{ route('payments.index') }}" class="small fw-medium">All</a></div>
            <ul class="cc-list">
                @forelse($recentPayments as $p)
                    <li>
                        <div class="flex-grow-1 min-w-0">
                            <div class="cc-cell-main text-truncate">{{ $p->invoice->patient->name }}</div>
                            <div class="cc-cell-sub text-truncate"><i class="fa-solid {{ $p->method->icon() }} me-1" aria-hidden="true"></i>{{ $p->method->label() }}@if($p->reference) · {{ $p->reference }}@endif · {{ $p->paid_at->format('H:i') }}</div>
                        </div>
                        <span class="cc-money">{{ money($p->amount) }}</span>
                    </li>
                @empty
                    <li class="d-block"><x-empty message="No payments yet." icon="fa-receipt" /></li>
                @endforelse
            </ul>
        </section>
    </div>
</div>

<div class="row g-4">
    <div class="col-xl-6"><x-chart-card title="Daily collections" chart="revenueDaily" note="Last 30 days" /></div>
    <div class="col-xl-3 col-md-6"><x-chart-card title="By method" chart="paymentMethods" note="90 days" /></div>
    <div class="col-xl-3 col-md-6"><x-chart-card title="Invoice status" chart="invoiceStatus" /></div>
</div>
@endsection
