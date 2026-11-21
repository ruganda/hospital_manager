@extends('layouts.app')
@section('title', 'Appointments')
@section('content')
@php
    $u = auth()->user();
    $canManage = $u->hasRole('admin', 'receptionist');
    $isPatient = $u->hasRole('patient');
    $isDoctor = $u->hasRole('doctor');
    $today = today();
    $groups = [
        'today' => $appointments->filter(fn ($a) => $a->appointment_date->isSameDay($today)),
        'upcoming' => $appointments->filter(fn ($a) => $a->appointment_date->gt($today)),
        'past' => $appointments->filter(fn ($a) => $a->appointment_date->lt($today)),
    ];
    $whenOf = fn ($a) => $a->appointment_date->isSameDay($today) ? 'today' : ($a->appointment_date->gt($today) ? 'upcoming' : 'past');
    $cols = 5 + ($isPatient ? 0 : 1) + ($isDoctor ? 0 : 1);
@endphp
<x-page-header :title="$isDoctor || $isPatient ? 'My appointments' : 'Appointments'" subtitle="View, check in, reschedule or cancel bookings.">
    @if($canManage)<a href="{{ route('appointments.create') }}" class="btn btn-primary"><i class="fa-solid fa-plus" aria-hidden="true"></i>Book appointment</a>@endif
</x-page-header>

<div class="card">
    <div class="cc-toolbar">
        <div class="cc-tabs" role="group" aria-label="Filter by date" data-filter-tabs="#appointments-table">
            <button type="button" data-filter="all" aria-pressed="true">All <span class="count">{{ $appointments->count() }}</span></button>
            <button type="button" data-filter="today" aria-pressed="false">Today <span class="count">{{ $groups['today']->count() }}</span></button>
            <button type="button" data-filter="upcoming" aria-pressed="false">Upcoming <span class="count">{{ $groups['upcoming']->count() }}</span></button>
            <button type="button" data-filter="past" aria-pressed="false">Past <span class="count">{{ $groups['past']->count() }}</span></button>
        </div>
    </div>
    <div class="table-responsive">
        <table class="table table-hover" id="appointments-table">
            <thead><tr><th>Date &amp; time</th>@unless($isPatient)<th>Patient</th>@endunless @unless($isDoctor)<th>Doctor</th>@endunless<th>Reason</th><th>Status</th><th>Billing</th><th class="text-end"><span class="visually-hidden">Actions</span></th></tr></thead>
            <tbody>
            @forelse($appointments as $a)
                <tr data-filter-value="{{ $whenOf($a) }}">
                    <td class="text-nowrap"><div class="cc-cell-main">{{ $a->appointment_date->format('D, j M Y') }}</div><div class="cc-cell-sub tabular">{{ $a->time_range }}</div></td>
                    @unless($isPatient)
                        <td>
                            <div class="d-flex align-items-center gap-2">
                                <x-avatar :user="$a->patient->user" size="sm" />
                                <div class="min-w-0"><div class="cc-cell-main">{{ $a->patient->name }}</div><div class="cc-cell-sub">{{ $a->patient->patient_no }} · {{ $a->patient->user->phone }}</div></div>
                            </div>
                        </td>
                    @endunless
                    @unless($isDoctor)
                        <td>{{ $a->doctor->name }}<div class="cc-cell-sub">{{ $a->doctor->department->name }}</div></td>
                    @endunless
                    <td class="small">{{ $a->reason ? Str::limit($a->reason, 40) : '—' }}</td>
                    <td><x-status-badge :status="$a->status" /></td>
                    <td>@if($a->invoice)<x-status-badge :status="$a->invoice->status" />@else<span class="text-muted small">—</span>@endif</td>
                    <td class="cc-row-actions">
                        @if($isDoctor && $a->isEditable())
                            <a href="{{ route('consultations.create', ['appointment_id' => $a->id]) }}" class="btn btn-outline-primary btn-sm">Consult</a>
                        @endif
                        @can('update', $a)
                            <a href="{{ route('appointments.edit', $a) }}" class="btn btn-ghost btn-icon" title="Reschedule" aria-label="Reschedule" data-bs-toggle="tooltip"><i class="fa-regular fa-calendar"></i></a>
                        @endcan
                        @can('delete', $a)<x-delete-button :action="route('appointments.destroy', $a)" />@endcan
                        <a href="{{ route('appointments.show', $a) }}" class="btn btn-ghost btn-icon" title="Open" aria-label="Open appointment" data-bs-toggle="tooltip"><i class="fa-solid fa-chevron-right"></i></a>
                    </td>
                </tr>
            @empty
                <x-empty :colspan="$cols" message="No appointments yet." icon="fa-calendar-xmark" />
            @endforelse
            @if($appointments->isNotEmpty())
                <tr data-filter-empty hidden><td colspan="{{ $cols }}" class="p-0"><x-empty message="No appointments in this group." icon="fa-filter" /></td></tr>
            @endif
            </tbody>
        </table>
    </div>
    <div class="card-footer small text-muted">Showing the latest {{ $appointments->count() }} {{ Str::plural('record', $appointments->count()) }}.</div>
</div>
@endsection
