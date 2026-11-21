@extends('layouts.app')
@section('title', 'Departments')
@section('content')
@php $isAdmin = auth()->user()->isAdmin(); $max = max(1, $departments->max('appointments_count') ?? 1); @endphp
<x-page-header title="Departments" subtitle="Clinics patients can book into.">
    @if($isAdmin)<a href="{{ route('departments.create') }}" class="btn btn-primary"><i class="fa-solid fa-plus" aria-hidden="true"></i>Add department</a>@endif
</x-page-header>

<div class="card">
    <div class="table-responsive">
        <table class="table table-hover">
            <thead><tr><th>Department</th><th class="text-end">Doctors</th><th style="width:30%">Appointments</th><th class="text-end"><span class="visually-hidden">Actions</span></th></tr></thead>
            <tbody>
            @forelse($departments as $d)
                <tr>
                    <td>
                        <div class="d-flex align-items-start gap-3">
                            <i class="fa-solid {{ $d->icon ?: 'fa-stethoscope' }} text-muted mt-1" style="width:18px;text-align:center" aria-hidden="true"></i>
                            <div class="min-w-0"><a href="{{ route('departments.show', $d) }}" class="cc-cell-main">{{ $d->name }}</a><div class="cc-cell-sub">{{ Str::limit($d->description, 90) }}</div></div>
                        </div>
                    </td>
                    <td class="text-end tabular">{{ $d->doctors_count }}</td>
                    <td>
                        <div class="d-flex align-items-center gap-3">
                            <div class="cc-bar flex-grow-1" role="img" aria-label="{{ $d->appointments_count }} appointments"><span style="width:{{ round($d->appointments_count / $max * 100) }}%"></span></div>
                            <span class="tabular small" style="min-width:40px">{{ number_format($d->appointments_count) }}</span>
                        </div>
                    </td>
                    <td class="cc-row-actions">
                        @if($isAdmin)
                            <a href="{{ route('departments.edit', $d) }}" class="btn btn-ghost btn-icon" title="Edit" aria-label="Edit {{ $d->name }}" data-bs-toggle="tooltip"><i class="fa-solid fa-pen"></i></a>
                            <x-delete-button :action="route('departments.destroy', $d)" />
                        @endif
                        <a href="{{ route('departments.show', $d) }}" class="btn btn-ghost btn-icon" title="Open" aria-label="Open {{ $d->name }}" data-bs-toggle="tooltip"><i class="fa-solid fa-chevron-right"></i></a>
                    </td>
                </tr>
            @empty
                <x-empty colspan="4" message="No departments yet." icon="fa-building-columns" />
            @endforelse
            </tbody>
        </table>
    </div>
</div>
@endsection
