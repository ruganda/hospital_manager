@extends('layouts.app')
@section('title', 'New consultation')
@section('content')
<x-page-header title="New consultation" subtitle="Notes belong to a visit. Pick the patient you are seeing." :back="route('consultations.index')" back-label="Consultations" />

@if($today->isEmpty() && $earlier->isEmpty())
    <div class="card">
        <x-empty message="You have no visits waiting for notes. Patients booked with you appear here on the day of their appointment." icon="fa-notes-medical" />
        <div class="card-footer small text-muted">Seeing a patient without a booking? Ask reception to book them in with you first, then they will appear here.</div>
    </div>
@else
    <div class="d-flex flex-column gap-4" style="max-width:960px">
        @foreach(['Today' => $today, 'Earlier visits still without notes' => $earlier] as $heading => $list)
            @if($list->isNotEmpty())
                <section class="card">
                    <div class="card-header"><h2 class="card-title">{{ $heading }}</h2><span class="small text-muted">{{ $list->count() }}</span></div>
                    <ul class="cc-list">
                        @foreach($list as $a)
                            <li>
                                <span class="tabular fw-medium text-nowrap" style="min-width:96px;color:var(--cc-ink)">
                                    @if($a->appointment_date->isToday()){{ substr($a->start_time, 0, 5) }}@else{{ $a->appointment_date->format('D j M') }}@endif
                                </span>
                                <x-avatar :user="$a->patient->user" size="sm" />
                                <div class="flex-grow-1 min-w-0">
                                    <div class="cc-cell-main text-truncate">{{ $a->patient->name }}</div>
                                    <div class="cc-cell-sub text-truncate">{{ $a->patient->patient_no }}@if($a->reason) · {{ $a->reason }}@endif</div>
                                </div>
                                <x-status-badge :status="$a->status" />
                                <a href="{{ route('consultations.create', ['appointment_id' => $a->id]) }}" class="btn btn-outline-primary btn-sm">Write notes</a>
                            </li>
                        @endforeach
                    </ul>
                </section>
            @endif
        @endforeach
    </div>
@endif
@endsection
