@extends('layouts.app')
@section('title', 'Dashboard')
@section('content')
@php
    $doctor = auth()->user()->doctor;
    $hour = now()->hour;
    $greeting = $hour < 12 ? 'Good morning' : ($hour < 17 ? 'Good afternoon' : 'Good evening');
    $next = $todayAppointments->first(fn ($a) => $a->isEditable());
@endphp
<div data-charts-url="{{ route('ajax.dashboard.charts') }}"></div>

<x-page-header :title="$greeting.', Dr '.Str::afterLast(auth()->user()->name, ' ')" :eyebrow="now()->format('l, j F')" :subtitle="trim(($doctor?->specialization ?? '').' · '.($doctor?->department?->name ?? ''), ' ·')">
    <a href="{{ route('schedules.index') }}" class="btn btn-white"><i class="fa-regular fa-clock" aria-hidden="true"></i>My schedule</a>
</x-page-header>

<section class="card cc-kpis mb-4" aria-label="Your numbers">
    <x-kpi label="Today" :value="$stats['today']" :hint="$stats['pending_today'].' still to see'" :href="route('appointments.index')" />
    <x-kpi label="Upcoming" :value="$stats['upcoming']" hint="Booked from today on" :href="route('appointments.index')" />
    <x-kpi label="Completed this month" :value="$stats['month_completed']" />
    <x-kpi label="My patients" :value="$stats['patients']" :href="route('patients.index')" />
</section>

<div class="row g-4 mb-4">
    <div class="col-xl-8 d-flex flex-column gap-4">
        @if($next)
            <section class="card" style="border-color:var(--bs-primary-border-subtle)" aria-label="Next patient">
                <div class="card-body d-flex flex-wrap align-items-center gap-4">
                    <x-avatar :user="$next->patient->user" size="lg" />
                    <div class="flex-grow-1 min-w-0" style="flex-basis:240px">
                        <div class="small-caps mb-1" style="color:var(--cc-primary)">Up next · {{ $next->time_range }}</div>
                        <div class="h4 fw-semibold mb-1">{{ $next->patient->name }}</div>
                        <div class="small text-muted">{{ $next->patient->patient_no }}@if($next->patient->age) · {{ $next->patient->age }} yrs @endif @if($next->patient->gender)· {{ ucfirst($next->patient->gender) }}@endif
                            @if($next->patient->allergies)<span class="fw-semibold" style="color:#a8251b"> · Allergies: {{ Str::limit($next->patient->allergies, 40) }}</span>@endif</div>
                        @if($next->reason)<div class="small mt-2"><span class="text-muted">Reason:</span> {{ $next->reason }}</div>@endif
                    </div>
                    <div class="d-flex flex-column gap-2">
                        <a href="{{ route('consultations.create', ['appointment_id' => $next->id]) }}" class="btn btn-primary"><i class="fa-solid fa-notes-medical" aria-hidden="true"></i>Start consultation</a>
                        <a href="{{ route('patients.show', $next->patient) }}" class="btn btn-white">Open record</a>
                    </div>
                </div>
            </section>
        @endif

        <section class="card">
            <div class="card-header"><h2 class="card-title">Today's patients</h2><span class="small text-muted">{{ $todayAppointments->count() }} booked</span></div>
            <div class="table-responsive">
                <table class="table table-hover">
                    <thead><tr><th>Time</th><th>Patient</th><th>Reason</th><th>Status</th><th class="text-end"><span class="visually-hidden">Actions</span></th></tr></thead>
                    <tbody>
                    @forelse($todayAppointments as $a)
                        <tr>
                            <td class="tabular fw-medium text-nowrap">{{ $a->time_range }}</td>
                            <td><a href="{{ route('patients.show', $a->patient) }}" class="cc-cell-main">{{ $a->patient->name }}</a><div class="cc-cell-sub">{{ $a->patient->patient_no }}@if($a->patient->age) · {{ $a->patient->age }} yrs @endif</div></td>
                            <td class="small">{{ $a->reason ? Str::limit($a->reason, 50) : '—' }}</td>
                            <td><x-status-badge :status="$a->status" /></td>
                            <td class="cc-row-actions">
                                @if($a->isEditable())
                                    <a href="{{ route('consultations.create', ['appointment_id' => $a->id]) }}" class="btn btn-outline-primary btn-sm">Consult</a>
                                @elseif($a->consultation)
                                    <a href="{{ route('consultations.show', $a->consultation) }}" class="btn btn-ghost btn-sm">Notes</a>
                                @endif
                                <a href="{{ route('appointments.show', $a) }}" class="btn btn-ghost btn-icon btn-sm" aria-label="Open appointment" title="Open"><i class="fa-solid fa-chevron-right"></i></a>
                            </td>
                        </tr>
                    @empty
                        <x-empty colspan="5" message="No patients booked for today." icon="fa-mug-hot" />
                    @endforelse
                    </tbody>
                </table>
            </div>
        </section>
    </div>

    <div class="col-xl-4">
        <section class="card h-100">
            <div class="card-header"><h2 class="card-title">Coming up</h2><a href="{{ route('appointments.index') }}" class="small fw-medium">All</a></div>
            <ul class="cc-list">
                @forelse($nextAppointments as $a)
                    <li>
                        <div class="text-center rounded-2 px-2 py-1 flex-shrink-0" style="background:var(--cc-primary-soft);color:var(--cc-primary-ink);min-width:52px">
                            <div class="small fw-semibold">{{ $a->appointment_date->format('D') }}</div>
                            <div class="fw-semibold tabular">{{ $a->appointment_date->format('j') }}</div>
                        </div>
                        <div class="min-w-0">
                            <div class="cc-cell-main text-truncate">{{ $a->patient->name }}</div>
                            <div class="cc-cell-sub text-truncate">{{ $a->time_range }}@if($a->reason) · {{ $a->reason }}@endif</div>
                        </div>
                    </li>
                @empty
                    <li class="d-block"><x-empty message="Nothing scheduled yet." icon="fa-calendar" /></li>
                @endforelse
            </ul>
        </section>
    </div>
</div>

<div class="row g-4">
    <div class="col-lg-7"><x-chart-card title="Patients seen" chart="patientsWeekly" note="Per week" /></div>
    <div class="col-lg-5"><x-chart-card title="My appointment outcomes" chart="statusBreakdown" note="All time" /></div>
</div>
@endsection
