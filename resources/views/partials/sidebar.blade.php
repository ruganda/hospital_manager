@php
    $u = auth()->user();
    $link = function (string $route, string $icon, string $label, string|array|null $pattern = null) {
        $active = request()->routeIs(...(array) ($pattern ?? $route));
        return '<li><a href="'.route($route).'" class="cc-nav-link'.($active ? ' active' : '').'"'.($active ? ' aria-current="page"' : '').'><i class="fa-solid '.$icon.'" aria-hidden="true"></i><span>'.e($label).'</span></a></li>';
    };
@endphp
<aside class="cc-sidebar offcanvas-lg offcanvas-start" tabindex="-1" id="sidebar" aria-label="Main navigation">
    <div class="d-flex align-items-center justify-content-between px-3 pt-3 pb-2">
        <a href="{{ route('dashboard') }}" class="cc-brand">
            <span class="cc-brand-mark"><i class="fa-solid fa-house-medical" aria-hidden="true"></i></span>
            <span><span class="cc-brand-name d-block">{{ config('clinic.short_name') }}</span><span class="cc-brand-sub">Medical Centre · Kampala</span></span>
        </a>
        <button type="button" class="btn-close d-lg-none" data-bs-dismiss="offcanvas" data-bs-target="#sidebar" aria-label="Close menu"></button>
    </div>

    <nav class="cc-nav">
        <ul>
            {!! $link('dashboard', 'fa-gauge-high', 'Dashboard') !!}
        </ul>

        @if($u->hasRole('admin', 'receptionist', 'doctor', 'cashier'))
            <div class="cc-nav-section">Clinical</div>
            <ul>
                {!! $link('appointments.index', 'fa-calendar-check', $u->hasRole('doctor') ? 'My appointments' : 'Appointments', ['appointments.index', 'appointments.show', 'appointments.edit']) !!}
                @if($u->hasRole('admin', 'receptionist'))
                    {!! $link('appointments.create', 'fa-calendar-plus', 'Book appointment', 'appointments.create') !!}
                @endif
                {!! $link('patients.index', 'fa-hospital-user', $u->hasRole('doctor') ? 'My patients' : 'Patients', 'patients.*') !!}
                @if($u->hasRole('admin', 'doctor'))
                    {!! $link('consultations.index', 'fa-notes-medical', 'Consultations', 'consultations.*') !!}
                @endif
                @if($u->hasRole('admin', 'receptionist', 'doctor'))
                    {!! $link('schedules.index', 'fa-clock', $u->hasRole('doctor') ? 'My schedule' : 'Doctor schedules', 'schedules.*') !!}
                @endif
            </ul>
        @endif

        @if($u->hasRole('patient'))
            <div class="cc-nav-section">My health</div>
            <ul>
                {!! $link('appointments.index', 'fa-calendar-check', 'My appointments', 'appointments.*') !!}
                {!! $link('consultations.index', 'fa-notes-medical', 'Visit history', 'consultations.*') !!}
                {!! $link('invoices.index', 'fa-file-invoice-dollar', 'Bills & payments', 'invoices.*') !!}
                {!! $link('doctors.index', 'fa-user-doctor', 'Our doctors', 'doctors.*') !!}
            </ul>
        @endif

        @if($u->hasRole('admin', 'cashier', 'receptionist'))
            <div class="cc-nav-section">Billing</div>
            <ul>
                {!! $link('invoices.index', 'fa-file-invoice-dollar', 'Invoices', 'invoices.*') !!}
                @if($u->hasRole('admin', 'cashier'))
                    {!! $link('payments.index', 'fa-money-bill-wave', 'Payments', ['payments.index', 'payments.edit']) !!}
                    {!! $link('payments.create', 'fa-cash-register', 'Record payment', 'payments.create') !!}
                @endif
            </ul>
        @endif

        @if($u->hasRole('admin', 'receptionist', 'doctor'))
            <div class="cc-nav-section">Clinic</div>
            <ul>
                {!! $link('doctors.index', 'fa-user-doctor', 'Doctors', 'doctors.*') !!}
                {!! $link('departments.index', 'fa-building-columns', 'Departments', 'departments.*') !!}
                @if($u->isAdmin())
                    {!! $link('users.index', 'fa-users-gear', 'Users & roles', 'users.*') !!}
                @endif
            </ul>
        @endif

        @if($u->hasRole('admin', 'receptionist', 'doctor', 'cashier'))
            <div class="cc-nav-section">Insights</div>
            <ul>
                {!! $link('reports.index', 'fa-chart-pie', 'Reports', 'reports.*') !!}
            </ul>
        @endif
    </nav>

    <div class="cc-sidebar-foot">
        <x-avatar :user="$u" />
        <div class="min-w-0 flex-grow-1">
            <div class="fw-semibold text-truncate" style="color:var(--cc-ink); font-size:.875rem">{{ $u->name }}</div>
            <div class="text-muted text-truncate" style="font-size:.75rem">{{ $u->role->label() }}@if($u->hasRole('patient') && $u->patient) · {{ $u->patient->patient_no }}@endif</div>
        </div>
        <a href="{{ route('profile.edit') }}" class="btn btn-ghost btn-icon btn-sm {{ request()->routeIs('profile.*') ? 'active' : '' }}" aria-label="My profile" title="My profile"><i class="fa-solid fa-gear"></i></a>
        <form method="POST" action="{{ route('logout') }}">
            @csrf
            <button type="submit" class="btn btn-ghost btn-icon btn-sm" aria-label="Log out" title="Log out"><i class="fa-solid fa-arrow-right-from-bracket"></i></button>
        </form>
    </div>
</aside>
