@extends('layouts.app')
@section('title', 'Mobile Money')
@section('content')
@php
    $statuses = \App\Enums\MobileMoneyStatus::cases();
    $counts = $requests->countBy(fn ($r) => $r->status->value);
@endphp
<x-page-header title="Mobile Money" subtitle="MTN Mobile Money and Airtel Money requests sent to patients' phones, newest first.">
    <a href="{{ route('payments.index') }}" class="btn btn-white">All payments</a>
    <a href="{{ route('payments.create') }}#mobile-money" class="btn btn-primary"><i class="fa-solid fa-paper-plane" aria-hidden="true"></i>Request payment</a>
</x-page-header>

@if(config('services.mobile_money.driver') === 'fake')
    <div class="cc-callout warn mb-4"><i class="fa-solid fa-flask" aria-hidden="true"></i><div><strong>Demo mode.</strong> No real money moves: requests approve after a few seconds and numbers ending in 000 are declined. Set <code>MOBILE_MONEY_DRIVER=flutterwave</code> with your Flutterwave keys to go live.</div></div>
@endif

<section class="card cc-kpis mb-4" aria-label="Mobile Money summary">
    <x-kpi label="Collected today" :value="money($stats['today_amount'])" :hint="$stats['today_count'].' '.Str::plural('payment', $stats['today_count'])" />
    <x-kpi label="Waiting for approval" :value="$stats['pending']" hint="Prompt sent, not yet approved" />
    <x-kpi label="Needs review" :value="$stats['review']" hint="Money received but not recorded automatically" />
    <x-kpi label="Shown below" :value="$requests->count()" hint="Latest requests" />
</section>

<div class="card">
    <div class="cc-toolbar">
        <div class="cc-tabs" role="group" aria-label="Filter by status" data-filter-tabs="#momo-table">
            <button type="button" data-filter="all" aria-pressed="true">All <span class="count">{{ $requests->count() }}</span></button>
            @foreach($statuses as $st)
                <button type="button" data-filter="{{ $st->value }}" aria-pressed="false">{{ $st->label() }} <span class="count">{{ $counts[$st->value] ?? 0 }}</span></button>
            @endforeach
        </div>
    </div>
    <div class="table-responsive">
        <table class="table table-hover" id="momo-table">
            <thead><tr><th>Sent</th><th>Patient</th><th>Invoice</th><th>Network &amp; number</th><th>Requested by</th><th class="text-end">Amount</th><th>Status</th><th class="text-end"><span class="visually-hidden">Actions</span></th></tr></thead>
            <tbody>
            @forelse($requests as $mm)
                <tr data-filter-value="{{ $mm->status->value }}" @if($mm->isPending()) data-momo-poll="{{ route('mobile-money.status', $mm) }}" @endif>
                    <td class="text-nowrap">{{ $mm->created_at->format('j M Y') }}<div class="cc-cell-sub tabular">{{ $mm->created_at->format('H:i') }}</div></td>
                    <td class="cc-cell-main">{{ $mm->invoice->patient->name }}<div class="cc-cell-sub fw-normal">{{ $mm->invoice->patient->patient_no }}</div></td>
                    <td><a href="{{ route('invoices.show', $mm->invoice) }}">{{ $mm->invoice->invoice_no }}</a></td>
                    <td class="text-nowrap">{{ $mm->network->label() }}<div class="cc-cell-sub tabular">{{ $mm->display_phone }}</div></td>
                    <td class="small">{{ $mm->requester?->name ?? '—' }}@if($mm->requester)<div class="cc-cell-sub">{{ $mm->requester->role->label() }}</div>@endif</td>
                    <td class="text-end cc-money">{{ money($mm->amount) }}</td>
                    <td>
                        <span class="cc-badge {{ $mm->status->color() }}">{{ $mm->status->label() }}</span>
                        @if($mm->message && ! $mm->isPending())<div class="cc-cell-sub mt-1" style="max-width:240px" data-momo-message>{{ Str::limit($mm->message, 90) }}</div>@endif
                    </td>
                    <td class="cc-row-actions">
                        @if($mm->isPending())
                            <form method="POST" action="{{ route('mobile-money.refresh', $mm) }}">@csrf<button type="submit" class="btn btn-ghost btn-sm">Check now</button></form>
                        @endif
                        <a href="{{ route('invoices.show', $mm->invoice) }}" class="btn btn-ghost btn-icon" title="Open invoice" aria-label="Open invoice {{ $mm->invoice->invoice_no }}" data-bs-toggle="tooltip"><i class="fa-solid fa-chevron-right"></i></a>
                    </td>
                </tr>
            @empty
                <x-empty colspan="8" message="No Mobile Money requests yet. Send one from an invoice, a consultation or Record payment." icon="fa-mobile-screen" />
            @endforelse
            @if($requests->isNotEmpty())
                <tr data-filter-empty hidden><td colspan="8" class="p-0"><x-empty message="No requests with this status." icon="fa-filter" /></td></tr>
            @endif
            </tbody>
        </table>
    </div>
    <div class="card-footer small text-muted">Showing the latest {{ $requests->count() }} {{ Str::plural('request', $requests->count()) }}. "Needs review" means money arrived but did not match the invoice; check it in the Flutterwave dashboard and record or refund it manually.</div>
</div>
@endsection
