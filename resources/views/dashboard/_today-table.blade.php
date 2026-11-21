{{-- Today's queue. Expects $todayAppointments; optional $showDoctor (bool), $queueTitle, $queueId. --}}
@php
    $showDoctor = $showDoctor ?? true;
    $queueId = $queueId ?? 'queue';
    $groupOf = fn ($s) => match ($s) {
        \App\Enums\AppointmentStatus::Scheduled => 'arrive',
        \App\Enums\AppointmentStatus::CheckedIn => 'waiting',
        \App\Enums\AppointmentStatus::Completed => 'done',
        default => 'missed',
    };
    $counts = $todayAppointments->countBy(fn ($a) => $groupOf($a->status));
    $nowTime = now()->format('H:i:s');
    $nowPlaced = false;
@endphp
<section class="card h-100">
    <div class="card-header">
        <h2 class="card-title">{{ $queueTitle ?? 'Today' }}</h2>
        <div class="cc-tabs" role="group" aria-label="Filter today's appointments" data-filter-tabs="#{{ $queueId }}">
            <button type="button" data-filter="all" aria-pressed="true">All <span class="count">{{ $todayAppointments->count() }}</span></button>
            <button type="button" data-filter="waiting" aria-pressed="false">Waiting <span class="count">{{ $counts['waiting'] ?? 0 }}</span></button>
            <button type="button" data-filter="arrive" aria-pressed="false">To arrive <span class="count">{{ $counts['arrive'] ?? 0 }}</span></button>
            <button type="button" data-filter="done" aria-pressed="false">Done <span class="count">{{ $counts['done'] ?? 0 }}</span></button>
        </div>
    </div>
    <div id="{{ $queueId }}">
        @forelse($todayAppointments as $a)
            @if(! $nowPlaced && $a->start_time > $nowTime)
                @php $nowPlaced = true; @endphp
                <div class="cc-now" data-filter-hide-when-filtered>{{ now()->format('H:i') }} now</div>
            @endif
            <div class="cc-queue-row" data-filter-value="{{ $groupOf($a->status) }}">
                <span class="tabular fw-medium" style="color:var(--cc-ink)">{{ substr($a->start_time, 0, 5) }}<span class="d-block cc-cell-sub">{{ substr($a->end_time, 0, 5) }}</span></span>
                <div class="d-flex align-items-center gap-3 min-w-0">
                    <x-avatar :user="$a->patient->user" size="sm" class="d-none d-sm-inline-grid" />
                    <div class="min-w-0">
                        <a href="{{ route('appointments.show', $a) }}" class="cc-cell-main d-block text-truncate">{{ $a->patient->name }}</a>
                        <div class="cc-cell-sub text-truncate">@if($showDoctor){{ $a->doctor->name }} · {{ $a->doctor->department->name }}@else{{ $a->patient->patient_no }}@if($a->reason) · {{ $a->reason }}@endif @endif</div>
                    </div>
                </div>
                <x-status-badge :status="$a->status" />
                <div class="cc-row-actions">
                    @if($a->status === \App\Enums\AppointmentStatus::Scheduled)
                        @can('changeStatus', $a)
                            <form method="POST" action="{{ route('appointments.status', $a) }}">@csrf @method('PATCH')<input type="hidden" name="status" value="checked_in">
                                <button type="submit" class="btn btn-outline-primary btn-sm">Check in</button></form>
                        @endcan
                    @endif
                    <a href="{{ route('appointments.show', $a) }}" class="btn btn-ghost btn-icon btn-sm" aria-label="Open appointment for {{ $a->patient->name }}" title="Open"><i class="fa-solid fa-chevron-right"></i></a>
                </div>
            </div>
        @empty
            <x-empty message="No appointments today." icon="fa-calendar-day" />
        @endforelse
        @if($todayAppointments->isNotEmpty())
            <div data-filter-empty hidden><x-empty message="Nothing in this group right now." icon="fa-filter" /></div>
        @endif
    </div>
    <div class="card-footer"><a class="small fw-medium" href="{{ route('appointments.index') }}">All appointments</a></div>
</section>
