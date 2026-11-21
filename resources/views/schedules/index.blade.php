@extends('layouts.app')
@section('title', 'Doctor schedules')
@section('content')
@php
    $isAdmin = auth()->user()->isAdmin();
    $isDoctor = auth()->user()->hasRole('doctor');
    $week = [1 => 'Mon', 2 => 'Tue', 3 => 'Wed', 4 => 'Thu', 5 => 'Fri', 6 => 'Sat', 0 => 'Sun'];
    $byDoctor = $schedules->groupBy('doctor_id');
@endphp
<x-page-header :title="$isDoctor ? 'My schedule' : 'Doctor schedules'" subtitle="Weekly working hours. Bookable time slots are generated from these blocks.">
    @if($isAdmin)<a href="{{ route('schedules.create', ['doctor_id' => request('doctor_id')]) }}" class="btn btn-primary"><i class="fa-solid fa-plus" aria-hidden="true"></i>Add hours</a>@endif
</x-page-header>

<div class="card">
    <div class="table-responsive">
        <table class="table" style="min-width:900px">
            <thead><tr><th style="width:220px">Doctor</th>@foreach($week as $short)<th>{{ $short }}</th>@endforeach</tr></thead>
            <tbody class="small">
            @forelse($byDoctor as $blocks)
                @php $doc = $blocks->first()->doctor; @endphp
                <tr>
                    <td>
                        <a href="{{ route('doctors.show', $doc) }}" class="cc-cell-main">{{ $doc->name }}</a>
                        <div class="cc-cell-sub">{{ $doc->department->name }}</div>
                    </td>
                    @foreach($week as $day => $short)
                        <td class="align-top">
                            @forelse($blocks->where('day_of_week', $day) as $s)
                                <div class="mb-1 {{ $s->is_active ? '' : 'text-muted' }}">
                                    @if($isAdmin)
                                        <a href="{{ route('schedules.edit', $s) }}" class="tabular text-decoration-none {{ $s->is_active ? '' : 'text-muted text-decoration-line-through' }}" title="Edit {{ $s->day_name }} {{ $s->time_range }}">{{ $s->time_range }}</a>
                                    @else
                                        <span class="tabular {{ $s->is_active ? '' : 'text-decoration-line-through' }}">{{ $s->time_range }}</span>
                                    @endif
                                    <span class="text-muted d-block" style="font-size:.72rem">{{ $s->slot_minutes }} min{{ $s->is_active ? '' : ' · paused' }}</span>
                                </div>
                            @empty
                                <span class="text-muted">—</span>
                            @endforelse
                        </td>
                    @endforeach
                </tr>
            @empty
                <x-empty colspan="8" message="No schedules yet." icon="fa-clock" />
            @endforelse
            </tbody>
        </table>
    </div>
    @if($isAdmin)
        <div class="card-footer small text-muted"><i class="fa-solid fa-circle-info me-1" aria-hidden="true"></i>Click a time to edit or remove it. Closed days show a dash.</div>
    @endif
</div>
@endsection
