@extends('layouts.app')
@section('title', 'Dashboard')
@section('content')
@php
    $hour = now()->hour;
    $greeting = $hour < 12 ? 'Good morning' : ($hour < 17 ? 'Good afternoon' : 'Good evening');
    $firstName = explode(' ', auth()->user()->name)[0];
    $collectionRate = $stats['billed_month'] > 0 ? round($stats['revenue_month'] / $stats['billed_month'] * 100) : null;
@endphp
<div data-charts-url="{{ route('ajax.dashboard.charts') }}"></div>

<x-page-header :title="$greeting.', '.$firstName" :eyebrow="now()->format('l, j F')">
    <a href="{{ route('reports.index') }}" class="btn btn-white">Reports</a>
    <a href="{{ route('appointments.create') }}" class="btn btn-primary"><i class="fa-solid fa-plus" aria-hidden="true"></i>Book appointment</a>
</x-page-header>

<section class="card cc-kpis mb-4" aria-label="Key figures">
    <x-kpi label="Appointments today" :value="number_format($stats['today'])" :hint="$stats['today_checked_in'].' waiting · '.$stats['today_completed'].' done'" :href="route('appointments.index')" />
    <x-kpi label="Revenue this month" :value="money($stats['revenue_month'])" :hint="$collectionRate !== null ? $collectionRate.'% of '.money($stats['billed_month']).' billed' : 'Nothing billed yet'" :href="route('payments.index')" />
    <x-kpi label="Outstanding" :value="money($stats['outstanding'])" hint="Unpaid and part-paid invoices" :href="route('invoices.index')" />
    <x-kpi label="Patients" :value="number_format($stats['patients'])" :hint="'+'.$stats['new_patients'].' registered this month'" :href="route('patients.index')" />
</section>

<section class="card mb-4">
    <div class="card-header">
        <h2 class="card-title">Appointments <span class="text-muted fw-normal small ms-1" data-chart-range-label="appointmentsTrend">last 30 days</span></h2>
        <div class="cc-tabs" role="group" aria-label="Date range" data-chart-range="appointmentsTrend">
            <button type="button" data-days="7" data-label="last 7 days" aria-pressed="false">7 days</button>
            <button type="button" data-days="30" data-label="last 30 days" aria-pressed="true">30 days</button>
            <button type="button" data-days="90" data-label="last 90 days" aria-pressed="false">90 days</button>
        </div>
    </div>
    <div class="card-body d-flex flex-wrap" style="gap:32px">
        <div class="chart-box flex-grow-1" style="flex-basis:520px;min-width:0;height:260px">
            <div class="chart-loading text-muted small position-absolute top-50 start-50 translate-middle"><span class="spinner-border spinner-border-sm me-2" aria-hidden="true"></span>Loading…</div>
            <canvas data-chart="appointmentsTrend" role="img" aria-label="Appointments booked and completed per day"></canvas>
        </div>
        <dl class="m-0 d-flex flex-column gap-4" style="flex:0 1 180px" data-chart-summary="appointmentsTrend" aria-live="polite">
            <div><dt class="small text-muted fw-normal">Booked</dt><dd class="cc-stat-value m-0" data-summary="booked">–</dd></div>
            <div><dt class="small text-muted fw-normal">Completed</dt><dd class="m-0 fs-5 fw-semibold tabular" style="color:var(--cc-ink)" data-summary="completed">–</dd></div>
            <div><dt class="small text-muted fw-normal">Completion rate</dt><dd class="m-0 fs-5 fw-semibold tabular" style="color:var(--cc-ink)" data-summary="rate">–</dd></div>
        </dl>
    </div>
</section>

<div class="row g-4 mb-4">
    <div class="col-xl-7">
        @include('dashboard._today-table', ['queueTitle' => 'Today', 'queueId' => 'admin-queue'])
    </div>
    <div class="col-xl-5">
        <x-chart-card title="Billed vs collected" chart="revenueMonthly" note="Last 6 months" :height="300" />
    </div>
</div>

<div class="row g-4 mb-4">
    <div class="col-xl-7"><x-chart-card title="Attendance" chart="attendance" note="Attended, no-show and cancelled per month" /></div>
    <div class="col-xl-5"><x-chart-card title="Busiest departments" chart="byDepartment" note="Last 90 days" /></div>
</div>

<div class="row g-4">
    <div class="col-xl-4"><x-chart-card title="Status mix" chart="statusBreakdown" note="Last 30 days" /></div>
    <div class="col-xl-4"><x-chart-card title="Doctor workload" chart="doctorWorkload" note="This month" /></div>
    <div class="col-xl-4">
        <section class="card h-100">
            <div class="card-header"><h2 class="card-title">Latest payments</h2><a href="{{ route('payments.index') }}" class="small fw-medium">All</a></div>
            <ul class="cc-list">
                @forelse($recentPayments as $p)
                    <li>
                        <div class="flex-grow-1 min-w-0">
                            <div class="cc-cell-main text-truncate">{{ $p->invoice->patient->name }}</div>
                            <div class="cc-cell-sub"><i class="fa-solid {{ $p->method->icon() }} me-1" aria-hidden="true"></i>{{ $p->method->label() }} · {{ $p->paid_at->diffForHumans() }}</div>
                        </div>
                        <span class="cc-money">{{ money($p->amount) }}</span>
                    </li>
                @empty
                    <li><x-empty message="No payments yet." icon="fa-receipt" class="w-100" /></li>
                @endforelse
            </ul>
        </section>
    </div>
</div>
@endsection
