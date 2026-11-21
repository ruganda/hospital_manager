@extends('layouts.app')
@section('title', $department->exists ? 'Edit department' : 'Add department')
@section('content')
<x-page-header :title="$department->exists ? 'Edit '.$department->name : 'Add a department'" :back="route('departments.index')" back-label="Departments" />

<form method="POST" action="{{ $department->exists ? route('departments.update', $department) : route('departments.store') }}" class="card cc-narrow">
    @csrf
    @if($department->exists) @method('PUT') @endif
    <div class="card-body row">
        <x-input name="name" label="Name" :value="$department->name" required col="col-md-8 mb-3" />
        <x-input name="icon" label="Font Awesome icon" :value="$department->icon" placeholder="fa-heart-pulse" help="e.g. fa-heart-pulse, fa-tooth, fa-baby" col="col-md-4 mb-3" />
        <x-textarea name="description" label="Description" :value="$department->description" rows="4" col="col-12 mb-0" />
    </div>
    <div class="card-footer d-flex justify-content-end gap-2">
        <a href="{{ route('departments.index') }}" class="btn btn-ghost">Cancel</a>
        <button type="submit" class="btn btn-primary">Save department</button>
    </div>
</form>
@endsection
