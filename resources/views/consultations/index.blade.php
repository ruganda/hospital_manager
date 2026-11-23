@extends('layouts.app')
@section('title', 'Consultations')
@section('content')
@php $u = auth()->user(); $isPatient = $u->hasRole('patient'); @endphp
<x-page-header :title="$isPatient ? 'Visit history' : 'Consultations'" subtitle="Diagnoses, treatment and prescriptions, newest first.">
    @if($u->hasRole('doctor', 'admin'))
        <a href="{{ route('consultations.create') }}" class="btn btn-primary"><i class="fa-solid fa-plus" aria-hidden="true"></i>New consultation</a>
    @endif
</x-page-header>

<div class="card">
    <div class="table-responsive">
        <table class="table table-hover">
            <thead><tr><th>Date</th>@unless($isPatient)<th>Patient</th>@endunless<th>Doctor</th><th>Diagnosis</th><th>Follow-up</th><th class="text-end"><span class="visually-hidden">Actions</span></th></tr></thead>
            <tbody>
            @forelse($consultations as $c)
                <tr>
                    <td class="text-nowrap">{{ $c->created_at->format('j M Y') }}<div class="cc-cell-sub tabular">{{ $c->created_at->format('H:i') }}</div></td>
                    @unless($isPatient)<td><div class="cc-cell-main">{{ $c->patient->name }}</div><div class="cc-cell-sub">{{ $c->patient->patient_no }}</div></td>@endunless
                    <td>{{ $c->doctor->name }}</td>
                    <td>{{ Str::limit($c->diagnosis, 60) }}</td>
                    <td>@if($c->follow_up_date)<span class="cc-chip">{{ $c->follow_up_date->format('D j M') }}</span>@else<span class="text-muted">—</span>@endif</td>
                    <td class="cc-row-actions">
                        @can('update', $c)<a href="{{ route('consultations.edit', $c) }}" class="btn btn-ghost btn-icon" title="Edit" aria-label="Edit notes" data-bs-toggle="tooltip"><i class="fa-solid fa-pen"></i></a>@endcan
                        @can('delete', $c)<x-delete-button :action="route('consultations.destroy', $c)" />@endcan
                        <a href="{{ route('consultations.show', $c) }}" class="btn btn-ghost btn-icon" title="Open" aria-label="Open notes" data-bs-toggle="tooltip"><i class="fa-solid fa-chevron-right"></i></a>
                    </td>
                </tr>
            @empty
                <x-empty :colspan="$isPatient ? 5 : 6" message="No consultations found." icon="fa-notes-medical" />
            @endforelse
            </tbody>
        </table>
    </div>
    <div class="card-footer small text-muted">Showing the latest {{ $consultations->count() }} {{ Str::plural('record', $consultations->count()) }}.</div>
</div>
@endsection
