@extends('layouts.app')
@section('title', $doctor->name)
@section('content')
@php $u = auth()->user(); @endphp

<div class="cc-page-head">
    <div class="min-w-0">
        <a href="{{ route('doctors.index') }}" class="d-inline-flex align-items-center gap-2 small text-muted text-decoration-none mb-2"><i class="fa-solid fa-arrow-left" aria-hidden="true"></i>Doctors</a>
        <div class="d-flex align-items-center gap-3">
            <x-avatar :user="$doctor->user" size="xl" />
            <div class="min-w-0">
                <h1>{{ $doctor->name }}</h1>
                <p class="cc-sub">{{ $doctor->specialization }} · {{ $doctor->department->name }}</p>
            </div>
        </div>
    </div>
    <div class="cc-page-actions">
        @if($u->isAdmin())
            <a href="{{ route('schedules.create', ['doctor_id' => $doctor->id]) }}" class="btn btn-white"><i class="fa-regular fa-clock" aria-hidden="true"></i>Add hours</a>
            <a href="{{ route('doctors.edit', $doctor) }}" class="btn btn-white"><i class="fa-solid fa-pen" aria-hidden="true"></i>Edit</a>
        @endif
        @if($u->hasRole('admin', 'receptionist'))
            <a href="{{ route('appointments.create', ['doctor_id' => $doctor->id]) }}" class="btn btn-primary"><i class="fa-solid fa-plus" aria-hidden="true"></i>Book appointment</a>
        @endif
    </div>
</div>

@unless($u->hasRole('patient'))
    <section class="card cc-kpis mb-4" aria-label="Appointments">
        <x-kpi label="Appointments (all time)" :value="$stats['total']" />
        <x-kpi label="Upcoming" :value="$stats['upcoming']" />
        <x-kpi label="Completed" :value="$stats['completed']" />
        <x-kpi label="Patients" :value="$stats['patients']" />
    </section>
@endunless

<div class="row g-4">
    <div class="col-lg-5 d-flex flex-column gap-4">
        <section class="card">
            <div class="card-header"><h2 class="card-title">Weekly hours</h2></div>
            <ul class="cc-list small">
                @forelse($doctor->schedules as $s)
                    <li class="{{ $s->is_active ? '' : 'text-muted' }}">
                        <span style="width:100px" class="{{ $s->is_active ? 'text-muted' : '' }}">{{ $s->day_name }}</span>
                        <span class="tabular flex-grow-1 {{ $s->is_active ? '' : 'text-decoration-line-through' }}">{{ $s->time_range }}</span>
                        <span class="text-muted">{{ $s->slot_minutes }} min</span>
                        @unless($s->is_active)<span class="cc-badge secondary">Paused</span>@endunless
                    </li>
                @empty
                    <li class="text-muted">No schedule defined.</li>
                @endforelse
            </ul>
        </section>
        <section class="card">
            <div class="card-header"><h2 class="card-title">About</h2></div>
            <div class="card-body">
                @if($doctor->bio)<p class="small mb-3">{{ $doctor->bio }}</p>@endif
                <dl class="cc-dl" style="grid-template-columns:110px minmax(0,1fr)">
                    <dt>Consultation fee</dt><dd class="cc-money">{{ money($doctor->consultation_fee) }}</dd>
                    <dt>Licence</dt><dd>{{ $doctor->license_no }}</dd>
                    <dt>Email</dt><dd>{{ $doctor->user->email }}</dd>
                    @if($doctor->user->phone)<dt>Phone</dt><dd>{{ $doctor->user->phone }}</dd>@endif
                </dl>
            </div>
        </section>
    </div>
    @unless($u->hasRole('patient'))
        <div class="col-lg-7">
            <section class="card h-100">
                <div class="card-header"><h2 class="card-title">Upcoming appointments</h2></div>
                <div class="table-responsive">
                    <table class="table table-hover">
                        <thead><tr><th>When</th><th>Patient</th><th>Status</th></tr></thead>
                        <tbody>
                        @forelse($upcoming as $a)
                            <tr>
                                <td class="text-nowrap">{{ $a->appointment_date->format('D, j M Y') }}<div class="cc-cell-sub tabular">{{ $a->time_range }}</div></td>
                                <td><a href="{{ route('appointments.show', $a) }}" class="cc-cell-main">{{ $a->patient->name }}</a></td>
                                <td><x-status-badge :status="$a->status" /></td>
                            </tr>
                        @empty
                            <x-empty colspan="3" message="No upcoming appointments." icon="fa-calendar" />
                        @endforelse
                        </tbody>
                    </table>
                </div>
            </section>
        </div>
    @endunless
</div>
@endsection
