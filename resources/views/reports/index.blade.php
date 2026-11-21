@extends('layouts.app')
@section('title', 'Reports')
@section('content')
@php
    $isDoctor = auth()->user()->hasRole('doctor');
    $available = [
        'daily-appointments' => ['Daily appointment list', 'One day, by doctor, department or status.', 'fa-calendar-day'],
        'doctor-schedule' => ['Doctor schedule', 'Bookings for a date range.', 'fa-user-doctor'],
        'payments' => ['Payment report', 'Receipts by method and cashier.', 'fa-money-bill-wave'],
        'patient-visits' => ['Patient visit report', "One patient's full history.", 'fa-hospital-user'],
    ];
    $available = array_intersect_key($available, array_flip($reports->all()));
    $first = array_key_first($available);
@endphp
<x-page-header title="Reports" subtitle="Pick a report, set the filters, then download it as PDF, Excel or CSV." />

@if(empty($available))
    <div class="card"><x-empty message="No reports are available for your role." icon="fa-chart-pie" /></div>
@else
<div class="row g-4">
    <div class="col-lg-4 col-xl-3">
        <nav class="card" aria-label="Report types">
            <div class="list-group list-group-flush rounded-3" role="tablist">
                @foreach($available as $key => [$title, $desc, $icon])
                    <button type="button" class="list-group-item list-group-item-action d-flex gap-3 py-3 {{ $key === $first ? 'active' : '' }}" id="tab-{{ $key }}" data-bs-toggle="list" data-bs-target="#report-{{ $key }}" role="tab" aria-controls="report-{{ $key }}" aria-selected="{{ $key === $first ? 'true' : 'false' }}">
                        <i class="fa-solid {{ $icon }} mt-1" style="width:18px" aria-hidden="true"></i>
                        <span><span class="d-block fw-semibold">{{ $title }}</span><span class="small opacity-75">{{ $desc }}</span></span>
                    </button>
                @endforeach
            </div>
        </nav>
    </div>

    <div class="col-lg-8 col-xl-9">
        <div class="tab-content">
            @php
                $formats = '<div class="d-flex flex-wrap gap-2">
                    <button type="submit" name="format" value="csv" class="btn btn-white"><i class="fa-solid fa-file-csv" aria-hidden="true"></i>CSV</button>
                    <button type="submit" name="format" value="xlsx" class="btn btn-white"><i class="fa-solid fa-file-excel" aria-hidden="true"></i>Excel</button>
                    <button type="submit" name="format" value="pdf" class="btn btn-primary"><i class="fa-solid fa-file-pdf" aria-hidden="true"></i>Download PDF</button>
                </div>';
                $doctorOptions = $doctors->mapWithKeys(fn ($d) => [$d->id => $d->name])->all();
            @endphp

            @isset($available['daily-appointments'])
                <form method="GET" action="{{ route('reports.export', 'daily-appointments') }}" class="card tab-pane fade {{ $first === 'daily-appointments' ? 'show active' : '' }}" id="report-daily-appointments" role="tabpanel" aria-labelledby="tab-daily-appointments">
                    <div class="card-header"><h2 class="card-title">Daily appointment list</h2></div>
                    <div class="card-body row">
                        <x-input name="date" type="date" label="Date" :value="today()->toDateString()" id="da_date" col="col-sm-6 col-xl-3 mb-3" />
                        @unless($isDoctor)
                            <x-select name="doctor_id" label="Doctor" :options="$doctorOptions" placeholder="All doctors" id="da_doctor" col="col-sm-6 col-xl-3 mb-3" />
                            <x-select name="department_id" label="Department" :options="$departments->pluck('name', 'id')->all()" placeholder="All departments" id="da_department" col="col-sm-6 col-xl-3 mb-3" />
                        @endunless
                        <x-select name="status" label="Status" :options="collect($statuses)->mapWithKeys(fn ($s) => [$s->value => $s->label()])->all()" placeholder="Any status" id="da_status" col="col-sm-6 col-xl-3 mb-3" />
                    </div>
                    <div class="card-footer d-flex justify-content-end">{!! $formats !!}</div>
                </form>
            @endisset

            @isset($available['doctor-schedule'])
                <form method="GET" action="{{ route('reports.export', 'doctor-schedule') }}" class="card tab-pane fade {{ $first === 'doctor-schedule' ? 'show active' : '' }}" id="report-doctor-schedule" role="tabpanel" aria-labelledby="tab-doctor-schedule">
                    <div class="card-header"><h2 class="card-title">Doctor schedule</h2></div>
                    <div class="card-body row">
                        @unless($isDoctor)
                            <x-select name="doctor_id" label="Doctor" :options="$doctorOptions" placeholder="All doctors" id="ds_doctor" col="col-sm-6 col-xl-4 mb-3" />
                        @endunless
                        <x-input name="date_from" type="date" label="From" :value="today()->toDateString()" id="ds_from" col="col-sm-6 col-xl-4 mb-3" />
                        <x-input name="date_to" type="date" label="To" :value="today()->addDays(6)->toDateString()" id="ds_to" col="col-sm-6 col-xl-4 mb-3" />
                    </div>
                    <div class="card-footer d-flex justify-content-end">{!! $formats !!}</div>
                </form>
            @endisset

            @isset($available['payments'])
                <form method="GET" action="{{ route('reports.export', 'payments') }}" class="card tab-pane fade {{ $first === 'payments' ? 'show active' : '' }}" id="report-payments" role="tabpanel" aria-labelledby="tab-payments">
                    <div class="card-header"><h2 class="card-title">Payment report</h2></div>
                    <div class="card-body row">
                        <x-input name="date_from" type="date" label="From" :value="today()->startOfMonth()->toDateString()" id="pay_from" col="col-sm-6 col-xl-3 mb-3" />
                        <x-input name="date_to" type="date" label="To" :value="today()->toDateString()" id="pay_to" col="col-sm-6 col-xl-3 mb-3" />
                        <x-select name="method" label="Method" :options="collect($methods)->mapWithKeys(fn ($m) => [$m->value => $m->label()])->all()" placeholder="All methods" id="pay_method" col="col-sm-6 col-xl-3 mb-3" />
                        <x-select name="received_by" label="Cashier" :options="$cashiers->pluck('name', 'id')->all()" placeholder="All cashiers" id="pay_cashier" col="col-sm-6 col-xl-3 mb-3" />
                    </div>
                    <div class="card-footer d-flex justify-content-end">{!! $formats !!}</div>
                </form>
            @endisset

            @isset($available['patient-visits'])
                <form method="GET" action="{{ route('reports.export', 'patient-visits') }}" class="card tab-pane fade {{ $first === 'patient-visits' ? 'show active' : '' }}" id="report-patient-visits" role="tabpanel" aria-labelledby="tab-patient-visits">
                    <div class="card-header"><h2 class="card-title">Patient visit report</h2></div>
                    <div class="card-body row">
                        <div class="col-12"><x-patient-picker :selected="$selectedPatient" /></div>
                        <x-input name="date_from" type="date" label="From" help="Optional" id="pv_from" col="col-sm-6 mb-3" />
                        <x-input name="date_to" type="date" label="To" help="Optional" id="pv_to" col="col-sm-6 mb-3" />
                    </div>
                    <div class="card-footer d-flex justify-content-end">{!! $formats !!}</div>
                </form>
            @endisset
        </div>
    </div>
</div>
@endif

@push('scripts')
<script>
    // Open the report named in the URL hash (e.g. /reports#payments from the Payments page).
    document.addEventListener('DOMContentLoaded', () => {
        const key = location.hash.replace('#', '');
        const tab = key && document.getElementById('tab-' + key);
        if (tab && window.bootstrap) window.bootstrap.Tab.getOrCreateInstance(tab).show();
    });
</script>
@endpush
@endsection
