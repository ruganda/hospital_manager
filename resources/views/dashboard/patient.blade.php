@extends('layouts.app')
@section('title', 'My dashboard')
@section('content')
@php $next = $upcoming->first(); @endphp

<x-page-header :title="'Hello, '.explode(' ', auth()->user()->name)[0]" :subtitle="'Patient no. '.($patient?->patient_no ?? '—')">
    <a href="{{ route('profile.edit') }}" class="btn btn-white">My details</a>
</x-page-header>

<section class="card cc-kpis mb-4" aria-label="Your summary">
    <x-kpi label="Upcoming appointments" :value="$stats['upcoming']" :href="route('appointments.index')" />
    <x-kpi label="Completed visits" :value="$stats['visits']" :href="route('consultations.index')" />
    <x-kpi label="Balance due" :value="money($stats['balance'])" :hint="$stats['balance'] > 0 ? 'Please settle at the cash desk' : 'All bills paid'" :href="route('invoices.index')" />
    <x-kpi label="Open bills" :value="$stats['invoices_open']" :href="route('invoices.index')" />
</section>

<div class="row g-4 mb-4">
    <div class="col-lg-7">
        <section class="card h-100">
            <div class="card-header"><h2 class="card-title">Your next appointment</h2>@if($next)<x-status-badge :status="$next->status" />@endif</div>
            @if($next)
                <div class="card-body d-flex flex-wrap align-items-center gap-4">
                    <div class="text-center rounded-3 px-4 py-3" style="background:var(--cc-primary-soft);color:var(--cc-primary-ink);min-width:110px">
                        <div class="small-caps" style="color:var(--cc-primary-ink)">{{ $next->appointment_date->format('D') }}</div>
                        <div style="font-size:2.4rem;font-weight:600;letter-spacing:-.03em;line-height:1">{{ $next->appointment_date->format('j') }}</div>
                        <div class="small fw-semibold">{{ $next->appointment_date->format('M Y') }}</div>
                    </div>
                    <div class="flex-grow-1 min-w-0" style="flex-basis:220px">
                        <div class="h5 fw-semibold mb-1 tabular">{{ $next->time_range }}</div>
                        <div class="mb-1">{{ $next->doctor->name }} · {{ $next->doctor->department->name }}</div>
                        <div class="text-muted small"><i class="fa-solid fa-location-dot me-2" aria-hidden="true"></i>{{ config('clinic.address') }} · please arrive 10 minutes early</div>
                    </div>
                </div>
                <div class="card-footer d-flex justify-content-end"><a href="{{ route('appointments.show', $next) }}" class="btn btn-white btn-sm">View details</a></div>
            @else
                <div class="card-body"><x-empty :message="'No upcoming appointments. Call reception on '.config('clinic.phone').' to book.'" icon="fa-calendar-plus" /></div>
            @endif
            @if($upcoming->count() > 1)
                <ul class="cc-list border-top">
                    @foreach($upcoming->skip(1) as $a)
                        <li><span class="tabular fw-medium" style="min-width:110px">{{ $a->appointment_date->format('D j M') }}</span><span class="flex-grow-1 min-w-0 text-truncate">{{ $a->time_range }} · {{ $a->doctor->name }}</span><x-status-badge :status="$a->status" /></li>
                    @endforeach
                </ul>
            @endif
        </section>
    </div>
    <div class="col-lg-5">
        <section class="card h-100">
            <div class="card-header"><h2 class="card-title">Bills</h2><a href="{{ route('invoices.index') }}" class="small fw-medium">All bills</a></div>
            <ul class="cc-list">
                @forelse($invoices as $inv)
                    <li>
                        <div class="flex-grow-1 min-w-0"><a href="{{ route('invoices.show', $inv) }}" class="cc-cell-main">{{ $inv->invoice_no }}</a><div class="cc-cell-sub text-truncate">{{ $inv->description }}</div></div>
                        <div class="text-end"><div class="cc-money">{{ money($inv->amount) }}</div><x-status-badge :status="$inv->status" /></div>
                    </li>
                @empty
                    <li class="d-block"><x-empty message="No bills yet." icon="fa-file-invoice" /></li>
                @endforelse
            </ul>
        </section>
    </div>
</div>

<section class="card">
    <div class="card-header"><h2 class="card-title">Recent visits</h2><a href="{{ route('consultations.index') }}" class="small fw-medium">Full history</a></div>
    <div class="card-body">
        @if($history->isEmpty())
            <x-empty message="No previous visits." icon="fa-notes-medical" />
        @else
            <ul class="cc-timeline">
                @foreach($history as $a)
                    <li>
                        <div class="d-flex flex-wrap justify-content-between gap-2">
                            <strong style="color:var(--cc-ink)">{{ $a->consultation ? Str::limit($a->consultation->diagnosis, 80) : $a->status->label() }}</strong>
                            <span class="small text-muted">{{ $a->appointment_date->format('j M Y') }}</span>
                        </div>
                        <div class="small text-muted">{{ $a->doctor->name }}</div>
                        @if($a->consultation?->prescription)<div class="small mt-1">{{ Str::limit($a->consultation->prescription, 120) }}</div>@endif
                        @if($a->consultation?->follow_up_date)<div class="small mt-1" style="color:var(--cc-primary)"><i class="fa-regular fa-calendar me-1" aria-hidden="true"></i>Follow-up {{ $a->consultation->follow_up_date->format('D j M') }}</div>@endif
                    </li>
                @endforeach
            </ul>
        @endif
    </div>
</section>
@endsection
