@extends('layouts.app')
@section('title', $patient->name)
@section('content')
@php $u = auth()->user(); $canManage = $u->hasRole('admin', 'receptionist'); @endphp

<div class="cc-page-head">
    <div class="min-w-0">
        @unless($u->hasRole('patient'))
            <a href="{{ route('patients.index') }}" class="d-inline-flex align-items-center gap-2 small text-muted text-decoration-none mb-2"><i class="fa-solid fa-arrow-left" aria-hidden="true"></i>Patients</a>
        @endunless
        <div class="d-flex align-items-center gap-3">
            <x-avatar :user="$patient->user" size="lg" />
            <div class="min-w-0">
                <h1>{{ $patient->name }}</h1>
                <p class="cc-sub">{{ $patient->patient_no }}@if($patient->age) · {{ $patient->age }} yrs @endif @if($patient->gender)· {{ ucfirst((string) $patient->gender) }} @endif · registered {{ $patient->created_at->format('j M Y') }}</p>
            </div>
        </div>
    </div>
    <div class="cc-page-actions">
        @if($u->hasRole('admin', 'receptionist', 'doctor'))
            <a href="{{ route('reports.export', ['report' => 'patient-visits', 'patient_id' => $patient->id, 'format' => 'pdf']) }}" class="btn btn-white"><i class="fa-solid fa-file-pdf" aria-hidden="true"></i>Visit report</a>
        @endif
        @if($canManage)
            <a href="{{ route('patients.edit', $patient) }}" class="btn btn-white"><i class="fa-solid fa-pen" aria-hidden="true"></i>Edit</a>
            <a href="{{ route('appointments.create', ['patient_id' => $patient->id]) }}" class="btn btn-primary"><i class="fa-solid fa-plus" aria-hidden="true"></i>Book appointment</a>
        @endif
    </div>
</div>

<div class="cc-callout {{ $patient->allergies ? 'danger' : '' }} mb-4">
    <i class="fa-solid fa-triangle-exclamation" aria-hidden="true"></i>
    <div><strong>Allergies:</strong> {{ $patient->allergies ?: 'None recorded.' }} @unless($patient->allergies)<span class="text-muted">Confirm with the patient at every visit.</span>@endunless</div>
</div>

<div class="row g-4">
    <div class="col-lg-4 d-flex flex-column gap-4">
        <section class="card">
            <div class="card-header"><h2 class="card-title">Details</h2></div>
            <div class="card-body">
                <dl class="cc-dl" style="grid-template-columns:110px minmax(0,1fr)">
                    <dt>Phone</dt><dd>{{ $patient->user->phone ?? '—' }}</dd>
                    <dt>Email</dt><dd>{{ $patient->user->email }}</dd>
                    <dt>Date of birth</dt><dd>{{ $patient->date_of_birth?->format('j M Y') ?? '—' }}</dd>
                    <dt>National ID</dt><dd class="tabular">{{ $patient->national_id ?? '—' }}</dd>
                    <dt>Blood group</dt><dd class="fw-semibold">{{ $patient->blood_group ?? '—' }}</dd>
                    <dt>Address</dt><dd>{{ $patient->address ?? '—' }}</dd>
                </dl>
            </div>
        </section>
        <section class="card">
            <div class="card-header"><h2 class="card-title">Emergency contact</h2></div>
            <div class="card-body">
                @if($patient->emergency_contact_name || $patient->emergency_contact_phone)
                    <div class="fw-semibold" style="color:var(--cc-ink)">{{ $patient->emergency_contact_name ?? '—' }}</div>
                    <div class="small text-muted">{{ $patient->emergency_contact_phone }}</div>
                @else
                    <span class="text-muted small">None recorded.</span>
                @endif
            </div>
        </section>
        @unless($u->hasRole('doctor'))
            <section class="card">
                <div class="card-header"><h2 class="card-title">Billing</h2><span class="cc-money {{ $balance > 0 ? '' : 'text-muted' }}" @if($balance > 0) style="color:#a8251b" @endif>{{ money($balance) }} due</span></div>
                <ul class="cc-list">
                    @forelse($invoices as $inv)
                        <li class="small">
                            <a href="{{ route('invoices.show', $inv) }}" class="fw-medium">{{ $inv->invoice_no }}</a>
                            <span class="ms-auto tabular">{{ money($inv->amount) }}</span>
                            <x-status-badge :status="$inv->status" />
                        </li>
                    @empty
                        <li class="text-muted small">No invoices.</li>
                    @endforelse
                </ul>
            </section>
        @endunless
    </div>

    <div class="col-lg-8 d-flex flex-column gap-4">
        <section class="card">
            <div class="card-header"><h2 class="card-title">Treatment history</h2><span class="small text-muted">{{ $consultations->count() }} {{ Str::plural('consultation', $consultations->count()) }}</span></div>
            <div class="card-body">
                @if($consultations->isEmpty())
                    <x-empty message="No consultations recorded yet." icon="fa-notes-medical" />
                @else
                    <ul class="cc-timeline">
                        @foreach($consultations as $c)
                            <li>
                                <div class="d-flex flex-wrap justify-content-between gap-2 mb-1">
                                    <strong style="color:var(--cc-ink)">{{ $c->diagnosis }}</strong>
                                    <span class="small text-muted">{{ $c->created_at->format('j M Y') }} · {{ $c->doctor->name }} · {{ $c->doctor->department->name }}</span>
                                </div>
                                @if($c->symptoms || $c->treatment || $c->prescription)
                                    <dl class="cc-dl small" style="grid-template-columns:100px minmax(0,1fr);row-gap:6px">
                                        @if($c->symptoms)<dt>Symptoms</dt><dd>{{ $c->symptoms }}</dd>@endif
                                        @if($c->treatment)<dt>Treatment</dt><dd>{{ $c->treatment }}</dd>@endif
                                        @if($c->prescription)<dt>Prescription</dt><dd style="white-space:pre-line">{{ $c->prescription }}</dd>@endif
                                    </dl>
                                @endif
                                <div class="d-flex flex-wrap gap-3 mt-2 small">
                                    @if($c->follow_up_date)<span style="color:var(--cc-primary)"><i class="fa-regular fa-calendar me-1" aria-hidden="true"></i>Follow-up {{ $c->follow_up_date->format('D j M Y') }}</span>@endif
                                    @can('view', $c)<a href="{{ route('consultations.show', $c) }}">Open notes</a>@endcan
                                </div>
                            </li>
                        @endforeach
                    </ul>
                @endif
            </div>
        </section>

        <section class="card">
            <div class="card-header"><h2 class="card-title">Appointments</h2><span class="small text-muted">Latest {{ $appointments->count() }}</span></div>
            <div class="table-responsive">
                <table class="table table-hover">
                    <thead><tr><th>Date</th><th>Doctor</th><th>Reason</th><th>Status</th><th class="text-end"><span class="visually-hidden">Open</span></th></tr></thead>
                    <tbody>
                    @forelse($appointments as $a)
                        <tr>
                            <td class="text-nowrap">{{ $a->appointment_date->format('j M Y') }}<div class="cc-cell-sub tabular">{{ $a->time_range }}</div></td>
                            <td>{{ $a->doctor->name }}<div class="cc-cell-sub">{{ $a->doctor->department->name }}</div></td>
                            <td class="small">{{ $a->reason ?? '—' }}</td>
                            <td><x-status-badge :status="$a->status" /></td>
                            <td class="cc-row-actions"><a href="{{ route('appointments.show', $a) }}" class="btn btn-ghost btn-icon" aria-label="Open appointment" title="Open"><i class="fa-solid fa-chevron-right"></i></a></td>
                        </tr>
                    @empty
                        <x-empty colspan="5" message="No appointments." icon="fa-calendar" />
                    @endforelse
                    </tbody>
                </table>
            </div>
        </section>
    </div>
</div>
@endsection
