@php $u = auth()->user(); @endphp
<header class="cc-topbar">
    <button class="btn btn-ghost btn-icon d-lg-none" type="button" data-bs-toggle="offcanvas" data-bs-target="#sidebar" aria-controls="sidebar" aria-label="Open menu">
        <i class="fa-solid fa-bars"></i>
    </button>
    <a href="{{ route('dashboard') }}" class="cc-brand d-lg-none"><span class="cc-brand-name">{{ config('clinic.short_name') }}</span></a>

    <div class="ms-auto d-flex align-items-center gap-3">
        <span class="d-none d-md-inline text-muted small"><i class="fa-regular fa-calendar me-2" aria-hidden="true"></i>{{ now()->format('l, j F') }}</span>
        @if($u->hasRole('admin', 'receptionist'))
            <a href="{{ route('appointments.create') }}" class="btn btn-white btn-sm d-none d-sm-inline-flex"><i class="fa-solid fa-plus" aria-hidden="true"></i>Book</a>
        @endif
        <div class="dropdown">
            <button type="button" class="btn btn-ghost d-flex align-items-center gap-2 px-1" data-bs-toggle="dropdown" aria-expanded="false" aria-label="Account menu">
                <x-avatar :user="$u" size="sm" />
                <span class="d-none d-md-inline text-start lh-sm">
                    <span class="fw-semibold d-block small" style="color:var(--cc-ink)">{{ $u->name }}</span>
                    <span class="text-muted" style="font-size:.72rem">{{ $u->role->label() }}</span>
                </span>
                <i class="fa-solid fa-chevron-down small text-muted" aria-hidden="true"></i>
            </button>
            <ul class="dropdown-menu dropdown-menu-end">
                <li><a class="dropdown-item" href="{{ route('profile.edit') }}"><i class="fa-solid fa-id-badge"></i>My profile</a></li>
                <li><hr class="dropdown-divider"></li>
                <li>
                    <form method="POST" action="{{ route('logout') }}">
                        @csrf
                        <button type="submit" class="dropdown-item text-danger"><i class="fa-solid fa-right-from-bracket"></i>Log out</button>
                    </form>
                </li>
            </ul>
        </div>
    </div>
</header>
