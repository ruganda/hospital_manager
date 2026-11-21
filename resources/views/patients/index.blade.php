@extends('layouts.app')
@section('title', 'Patients')
@section('content')
@php $canManage = auth()->user()->hasRole('admin', 'receptionist'); $isAdmin = auth()->user()->isAdmin(); @endphp
<x-page-header :title="auth()->user()->hasRole('doctor') ? 'My patients' : 'Patients'" subtitle="One record per person: contact details, history and bills.">
    @if($canManage)<a href="{{ route('patients.create') }}" class="btn btn-primary"><i class="fa-solid fa-user-plus" aria-hidden="true"></i>Register patient</a>@endif
</x-page-header>

<div class="card">
    <div class="table-responsive">
        <table class="table table-hover">
            <thead><tr><th>Patient</th><th>Contact</th><th>Age / gender</th><th>Blood</th><th class="text-end">Visits</th><th class="text-end"><span class="visually-hidden">Actions</span></th></tr></thead>
            <tbody>
            @forelse($patients as $p)
                <tr>
                    <td>
                        <div class="d-flex align-items-center gap-3">
                            <x-avatar :user="$p->user" />
                            <div class="min-w-0"><a href="{{ route('patients.show', $p) }}" class="cc-cell-main">{{ $p->name }}</a><div class="cc-cell-sub">{{ $p->patient_no }}</div></div>
                        </div>
                    </td>
                    <td>{{ $p->user->phone ?? '—' }}<div class="cc-cell-sub">{{ $p->user->email }}</div></td>
                    <td>{{ $p->age ?? '—' }}@if($p->gender) · {{ ucfirst((string) $p->gender) }}@endif</td>
                    <td>{{ $p->blood_group ?? '—' }}</td>
                    <td class="text-end tabular">{{ $p->appointments_count }}</td>
                    <td class="cc-row-actions">
                        @if($canManage)
                            <a href="{{ route('appointments.create', ['patient_id' => $p->id]) }}" class="btn btn-ghost btn-icon" title="Book appointment" aria-label="Book appointment for {{ $p->name }}" data-bs-toggle="tooltip"><i class="fa-regular fa-calendar-plus"></i></a>
                            <a href="{{ route('patients.edit', $p) }}" class="btn btn-ghost btn-icon" title="Edit" aria-label="Edit {{ $p->name }}" data-bs-toggle="tooltip"><i class="fa-solid fa-pen"></i></a>
                        @endif
                        @if($isAdmin)
                            <x-delete-button :action="route('patients.destroy', $p)" confirm="Delete this patient and their entire history?" />
                        @endif
                        <a href="{{ route('patients.show', $p) }}" class="btn btn-ghost btn-icon" title="Open record" aria-label="Open record for {{ $p->name }}" data-bs-toggle="tooltip"><i class="fa-solid fa-chevron-right"></i></a>
                    </td>
                </tr>
            @empty
                <x-empty colspan="6" message="No patients found." icon="fa-hospital-user" />
            @endforelse
            </tbody>
        </table>
    </div>
    <div class="card-footer small text-muted">Showing the latest {{ $patients->count() }} {{ Str::plural('record', $patients->count()) }}.</div>
</div>
@endsection
