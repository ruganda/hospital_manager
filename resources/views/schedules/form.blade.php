@extends('layouts.app')
@section('title', $schedule->exists ? 'Edit hours' : 'Add hours')
@section('content')
<x-page-header :title="$schedule->exists ? 'Edit working hours' : 'Add working hours'" subtitle="One block of time on one weekday. Add several blocks for split shifts." :back="route('schedules.index')" back-label="Schedules">
    @if($schedule->exists)
        <x-delete-button :action="route('schedules.destroy', $schedule)" label="Remove block" size="md" confirm="Remove this block of working hours? Existing appointments are kept." />
    @endif
</x-page-header>

<form method="POST" action="{{ $schedule->exists ? route('schedules.update', $schedule) : route('schedules.store') }}" class="card cc-narrow">
    @csrf
    @if($schedule->exists) @method('PUT') @endif
    <div class="card-body row">
        <x-select name="doctor_id" label="Doctor" :options="$doctors->mapWithKeys(fn ($d) => [$d->id => $d->name])->all()" :value="$schedule->doctor_id" placeholder="Select doctor…" required col="col-md-8 mb-3" />
        <x-select name="day_of_week" label="Day" :options="\App\Models\DoctorSchedule::DAYS" :value="$schedule->day_of_week" placeholder="Select…" required col="col-md-4 mb-3" />
        <x-input name="start_time" type="time" label="Start" :value="substr((string) $schedule->start_time, 0, 5)" required col="col-md-4 mb-3" />
        <x-input name="end_time" type="time" label="End" :value="substr((string) $schedule->end_time, 0, 5)" required col="col-md-4 mb-3" />
        <x-select name="slot_minutes" label="Slot length" :options="[10 => '10 min', 15 => '15 min', 20 => '20 min', 30 => '30 min', 45 => '45 min', 60 => '60 min']" :value="$schedule->slot_minutes" required col="col-md-4 mb-3" />
        <div class="col-12">
            <div class="form-check form-switch">
                <input class="form-check-input" type="checkbox" role="switch" name="is_active" value="1" id="is_active" @checked(old('is_active', $schedule->is_active))>
                <label class="form-check-label" for="is_active">Accepting bookings</label>
            </div>
        </div>
    </div>
    <div class="card-footer d-flex justify-content-end gap-2">
        <a href="{{ route('schedules.index') }}" class="btn btn-ghost">Cancel</a>
        <button type="submit" class="btn btn-primary">Save hours</button>
    </div>
</form>
@endsection
