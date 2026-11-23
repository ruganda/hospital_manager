@extends('layouts.app')
@section('title', 'New consultation')
@section('content')
@php $isAdmin = auth()->user()->isAdmin(); @endphp
<x-page-header title="New consultation" :subtitle="$isAdmin ? 'Notes belong to a visit. Pick the visit to record notes for; you can choose the doctor who saw the patient on the next screen.' : 'Notes belong to a visit. Pick the patient you are seeing.'" :back="route('consultations.index')" back-label="Consultations" />

@if($isAdmin)
    <form method="GET" action="{{ route('consultations.create') }}" class="d-flex flex-wrap align-items-end gap-2 mb-4" style="max-width:960px">
        <div style="min-width:260px">
            <label class="form-label" for="doctor_filter">Doctor</label>
            <select name="doctor_id" id="doctor_filter" class="form-select" data-autosubmit>
                <option value="">All doctors</option>
                @foreach($doctors as $d)
                    <option value="{{ $d->id }}" @selected($doctorFilter === $d->id)>{{ $d->name }}</option>
                @endforeach
            </select>
        </div>
        <noscript><button type="submit" class="btn btn-white">Filter</button></noscript>
    </form>
@endif

@if($today->isEmpty() && $earlier->isEmpty())
    <div class="card">
        <x-empty :message="$isAdmin ? 'No visits are waiting for notes.' : 'You have no visits waiting for notes. Patients booked with you appear here on the day of their appointment.'" icon="fa-notes-medical" />
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
                                    <div class="cc-cell-sub text-truncate">@if($isAdmin){{ $a->doctor->name }} · @endif{{ $a->patient->patient_no }}@if($a->reason) · {{ $a->reason }}@endif</div>
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
