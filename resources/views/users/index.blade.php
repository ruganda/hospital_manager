@extends('layouts.app')
@section('title', 'Users & roles')
@section('content')
@php $roleBadge = ['admin' => 'purple', 'receptionist' => 'info', 'doctor' => 'primary', 'cashier' => 'warning', 'patient' => 'secondary']; @endphp
<x-page-header title="Users & roles" subtitle="Every login account. Doctors and patients are managed from their own pages.">
    <a href="{{ route('users.create') }}" class="btn btn-primary"><i class="fa-solid fa-user-plus" aria-hidden="true"></i>Add staff user</a>
</x-page-header>

<div class="card">
    <div class="cc-toolbar">
        <div class="cc-tabs" role="group" aria-label="Filter by role" data-filter-tabs="#users-table">
            <button type="button" data-filter="all" aria-pressed="true">All <span class="count">{{ $users->count() }}</span></button>
            @foreach(\App\Enums\Role::cases() as $r)
                @if($users->contains(fn ($x) => $x->role === $r))
                    <button type="button" data-filter="{{ $r->value }}" aria-pressed="false">{{ $r->label() }}</button>
                @endif
            @endforeach
            @if($users->contains(fn ($x) => ! $x->is_active))
                <button type="button" data-filter="inactive" aria-pressed="false">Inactive</button>
            @endif
        </div>
    </div>
    <div class="table-responsive">
        <table class="table table-hover" id="users-table">
            <thead><tr><th>User</th><th>Role</th><th>Phone</th><th>Status</th><th>Joined</th><th class="text-end"><span class="visually-hidden">Actions</span></th></tr></thead>
            <tbody>
            @forelse($users as $user)
                <tr data-filter-value="{{ $user->role->value }}{{ $user->is_active ? '' : ' inactive' }}" @unless($user->is_active) style="opacity:.75" @endunless>
                    <td>
                        <div class="d-flex align-items-center gap-3">
                            <x-avatar :user="$user" />
                            <div class="min-w-0"><div class="cc-cell-main">{{ $user->name }}@if($user->is(auth()->user())) <span class="text-muted fw-normal small">(you)</span>@endif</div><div class="cc-cell-sub">{{ $user->email }}</div></div>
                        </div>
                    </td>
                    <td><span class="cc-badge no-dot {{ $roleBadge[$user->role->value] ?? 'secondary' }}"><i class="fa-solid {{ $user->role->icon() }}" aria-hidden="true"></i>{{ $user->role->label() }}</span></td>
                    <td class="tabular small">{{ $user->phone ?? '—' }}</td>
                    <td>@if($user->is_active)<span class="cc-badge success">Active</span>@else<span class="cc-badge secondary">Deactivated</span>@endif</td>
                    <td class="small text-muted">{{ $user->created_at->format('j M Y') }}</td>
                    <td class="cc-row-actions">
                        @unless($user->is(auth()->user()))
                            <form method="POST" action="{{ route('users.toggle', $user) }}">@csrf @method('PATCH')
                                <button type="submit" class="btn btn-ghost btn-sm">{{ $user->is_active ? 'Deactivate' : 'Reactivate' }}</button>
                            </form>
                        @endunless
                        <a href="{{ route('users.edit', $user) }}" class="btn btn-ghost btn-icon" title="Edit" aria-label="Edit {{ $user->name }}" data-bs-toggle="tooltip"><i class="fa-solid fa-pen"></i></a>
                        @unless($user->is(auth()->user()))
                            <x-delete-button :action="route('users.destroy', $user)" confirm="Delete this account and all linked records?" />
                        @endunless
                    </td>
                </tr>
            @empty
                <x-empty colspan="6" message="No users." icon="fa-users" />
            @endforelse
            @if($users->isNotEmpty())
                <tr data-filter-empty hidden><td colspan="6" class="p-0"><x-empty message="No users in this group." icon="fa-filter" /></td></tr>
            @endif
            </tbody>
        </table>
    </div>
    <div class="card-footer small text-muted">Showing the latest {{ $users->count() }} {{ Str::plural('account', $users->count()) }}.</div>
</div>
@endsection
