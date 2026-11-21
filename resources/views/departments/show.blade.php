@extends('layouts.app')
@section('title', $department->name)
@section('content')
<x-page-header :title="$department->name" :subtitle="$department->description" :back="route('departments.index')" back-label="Departments">
    @if(auth()->user()->isAdmin())<a href="{{ route('departments.edit', $department) }}" class="btn btn-white"><i class="fa-solid fa-pen" aria-hidden="true"></i>Edit</a>@endif
</x-page-header>

<div class="cc-meta">
    <div><div class="small text-muted">Doctors</div><div class="cc-stat-value" data-countup>{{ $department->doctors->count() }}</div></div>
    <div><div class="small text-muted">Appointments (all time)</div><div class="cc-stat-value" data-countup>{{ number_format($department->appointments_count) }}</div></div>
    @if($department->doctors->isNotEmpty())
        <div><div class="small text-muted">Consultation from</div><div class="cc-stat-value">{{ money($department->doctors->min('consultation_fee')) }}</div></div>
    @endif
</div>

<section class="card">
    <div class="card-header"><h2 class="card-title">Doctors in this department</h2></div>
    <div class="table-responsive">
        <table class="table table-hover">
            <thead><tr><th>Doctor</th><th>Specialization</th><th>Consulting days</th><th class="text-end">Fee</th><th class="text-end"><span class="visually-hidden">Open</span></th></tr></thead>
            <tbody>
            @forelse($department->doctors as $doc)
                <tr>
                    <td><div class="d-flex align-items-center gap-3"><x-avatar :user="$doc->user" /><a href="{{ route('doctors.show', $doc) }}" class="cc-cell-main">{{ $doc->name }}</a></div></td>
                    <td>{{ $doc->specialization }}</td>
                    <td class="small">{{ $doc->schedules->where('is_active', true)->map(fn ($s) => substr($s->day_name, 0, 3))->unique()->implode(', ') ?: '—' }}</td>
                    <td class="text-end cc-money">{{ money($doc->consultation_fee) }}</td>
                    <td class="cc-row-actions"><a href="{{ route('doctors.show', $doc) }}" class="btn btn-ghost btn-icon" aria-label="Open {{ $doc->name }}" title="Open"><i class="fa-solid fa-chevron-right"></i></a></td>
                </tr>
            @empty
                <x-empty colspan="5" message="No doctors assigned yet." icon="fa-user-doctor" />
            @endforelse
            </tbody>
        </table>
    </div>
</section>
@endsection
