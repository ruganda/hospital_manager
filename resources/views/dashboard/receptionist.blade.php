@extends('layouts.app')
@section('title', 'Dashboard')
@section('content')
<div data-charts-url="{{ route('ajax.dashboard.charts') }}"></div>

<x-page-header title="Front desk" :eyebrow="now()->format('l, j F')" subtitle="Today's queue and who is on duty.">
    <a href="{{ route('patients.create') }}" class="btn btn-white"><i class="fa-solid fa-user-plus" aria-hidden="true"></i>Register patient</a>
    <a href="{{ route('appointments.create') }}" class="btn btn-primary"><i class="fa-solid fa-plus" aria-hidden="true"></i>Book appointment</a>
</x-page-header>

<section class="card cc-kpis mb-4" aria-label="Today in numbers" style="--cc-kpi-cols:5">
    <x-kpi label="Booked today" :value="$stats['today']" :href="route('appointments.index')" />
    <x-kpi label="Still to arrive" :value="$stats['waiting']" hint="Scheduled, not checked in" />
    <x-kpi label="Waiting" :value="$stats['checked_in']" hint="Checked in, not yet seen" />
    <x-kpi label="Completed" :value="$stats['completed']" />
    <x-kpi label="Cancelled" :value="$stats['cancelled']" />
</section>

<div class="row g-4 mb-4">
    <div class="col-xl-8">
        @include('dashboard._today-table', ['queueTitle' => "Today's queue", 'queueId' => 'reception-queue'])
    </div>
    <div class="col-xl-4">
        <section class="card h-100">
            <div class="card-header"><h2 class="card-title">On duty today</h2><a href="{{ route('schedules.index') }}" class="small fw-medium">Schedules</a></div>
            <ul class="cc-list">
                @forelse($doctorsToday as $d)
                    <li>
                        <x-avatar :user="$d->user" />
                        <div class="flex-grow-1 min-w-0">
                            <div class="cc-cell-main text-truncate">{{ $d->name }}</div>
                            <div class="cc-cell-sub">{{ $d->department->name }} · {{ $d->today_count }} booked</div>
                        </div>
                        <a href="{{ route('appointments.create', ['doctor_id' => $d->id]) }}" class="btn btn-white btn-sm" aria-label="Book with {{ $d->name }}">Book</a>
                    </li>
                @empty
                    <li class="d-block"><x-empty message="No doctors scheduled today." icon="fa-user-doctor" /></li>
                @endforelse
            </ul>
        </section>
    </div>
</div>

<div class="row g-4">
    <div class="col-lg-6"><x-chart-card title="Bookings" chart="upcomingWeek" note="Next 7 days" /></div>
    <div class="col-lg-6"><x-chart-card title="Attendance" chart="attendance" note="Per month" /></div>
</div>
@endsection
