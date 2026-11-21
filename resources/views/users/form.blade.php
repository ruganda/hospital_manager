@extends('layouts.app')
@section('title', $user->exists ? 'Edit user' : 'Add staff user')
@section('content')
<x-page-header :title="$user->exists ? 'Edit '.$user->name : 'Add a staff user'" subtitle="Doctors and patients are created from the Doctors and Patients pages." :back="route('users.index')" back-label="Users & roles" />

<form method="POST" action="{{ $user->exists ? route('users.update', $user) : route('users.store') }}" class="card cc-narrow" style="max-width:860px">
    @csrf
    @if($user->exists) @method('PUT') @endif
    <div class="card-body row">
        <x-input name="name" label="Full name" :value="$user->name" required col="col-md-6 mb-3" />
        <x-input name="email" type="email" label="Email" :value="$user->email" required col="col-md-6 mb-3" />
        <x-input name="phone" type="tel" placeholder="+256 7XX XXX XXX" label="Phone" :value="$user->phone" col="col-md-6 mb-3" />
        <x-select name="role" label="Role" :options="collect($roles)->mapWithKeys(fn ($r) => [$r->value => $r->label()])->all()" :value="$user->role" required col="col-md-6 mb-3"
                  :disabled="$user->is(auth()->user())" :help="$user->is(auth()->user()) ? 'You cannot change your own role.' : null" />
        <x-input name="password" type="password" :label="$user->exists ? 'New password' : 'Password'" :required="! $user->exists" :help="$user->exists ? 'Leave blank to keep the current password.' : null" autocomplete="new-password" col="col-md-6 mb-3" />
        <x-input name="password_confirmation" type="password" label="Confirm password" :required="! $user->exists" autocomplete="new-password" col="col-md-6 mb-3" />
        @if($user->is(auth()->user()))<input type="hidden" name="role" value="admin">@endif
        <div class="col-12">
            <div class="form-check form-switch">
                <input class="form-check-input" type="checkbox" role="switch" name="is_active" value="1" id="is_active" @checked(old('is_active', $user->is_active)) @disabled($user->is(auth()->user()))>
                <label class="form-check-label" for="is_active">Account active (can log in)</label>
            </div>        </div>
    </div>
    <div class="card-footer d-flex justify-content-end gap-2">
        <a href="{{ route('users.index') }}" class="btn btn-ghost">Cancel</a>
        <button type="submit" class="btn btn-primary">Save user</button>
    </div>
</form>
@endsection
