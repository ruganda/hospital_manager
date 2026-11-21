@extends('layouts.app')
@section('title', 'Doctors')
@section('content')
@php $isAdmin = auth()->user()->isAdmin(); $canBook = auth()->user()->hasRole('admin', 'receptionist'); @endphp
<x-page-header :title="auth()->user()->hasRole('patient') ? 'Our doctors' : 'Doctors'" :subtitle="$doctors->count().' '.Str::plural('doctor', $doctors->count()).' across '.$doctors->pluck('department_id')->unique()->count().' departments.'">
    @if($isAdmin)<a href="{{ route('doctors.create') }}" class="btn btn-primary"><i class="fa-solid fa-user-plus" aria-hidden="true"></i>Add doctor</a>@endif
</x-page-header>

<div class="row g-3">
    @forelse($doctors as $doc)
        <div class="col-md-6 col-xxl-4">
            <article class="card h-100">
                <div class="card-body d-flex gap-3 position-relative">
                    <x-avatar :user="$doc->user" size="lg" />
                    <div class="flex-grow-1 min-w-0">
                        <h2 class="h6 fw-semibold mb-1"><a href="{{ route('doctors.show', $doc) }}" class="stretched-link text-decoration-none" style="color:var(--cc-ink)">{{ $doc->name }}</a></h2>
                        <div class="small text-muted mb-3">{{ $doc->specialization }} · {{ $doc->department->name }}</div>
                        <div class="d-flex flex-wrap gap-3 small">
                            <span><span class="text-muted">Fee</span> <span class="tabular fw-medium">{{ money($doc->consultation_fee) }}</span></span>
                            <span><span class="text-muted">Upcoming</span> <span class="tabular fw-medium">{{ $doc->upcoming_count }}</span></span>
                        </div>
                    </div>
                </div>
                @if($isAdmin || $canBook)
                    <div class="card-footer d-flex justify-content-between align-items-center gap-2">
                        <span class="small text-muted text-truncate">{{ $doc->license_no }}</span>
                        <div class="cc-row-actions">
                            @if($canBook)<a href="{{ route('appointments.create', ['doctor_id' => $doc->id]) }}" class="btn btn-white btn-sm">Book</a>@endif
                            @if($isAdmin)
                                <a href="{{ route('doctors.edit', $doc) }}" class="btn btn-ghost btn-icon" title="Edit" aria-label="Edit {{ $doc->name }}" data-bs-toggle="tooltip"><i class="fa-solid fa-pen"></i></a>
                                <x-delete-button :action="route('doctors.destroy', $doc)" confirm="Delete this doctor, their schedules and appointment history?" />
                            @endif
                        </div>
                    </div>
                @endif
            </article>
        </div>
    @empty
        <div class="col-12"><div class="card"><x-empty message="No doctors yet." icon="fa-user-doctor" /></div></div>
    @endforelse
</div>
@endsection
