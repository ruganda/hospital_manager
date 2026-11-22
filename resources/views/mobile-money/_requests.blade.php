{{-- Mobile Money requests for one invoice, newest first. Pending ones poll for updates (ui.js). Expects $requests. --}}
<section class="card">
    <div class="card-header"><h2 class="card-title">Mobile Money requests</h2></div>
    <ul class="cc-list">
        @foreach($requests->take(5) as $mm)
            <li class="d-block" @if($mm->isPending()) data-momo-poll="{{ route('mobile-money.status', $mm) }}" @endif>
                <div class="d-flex flex-wrap justify-content-between align-items-center gap-2">
                    <div class="min-w-0">
                        <div class="cc-cell-main">{{ money($mm->amount) }} · {{ $mm->network->label() }}</div>
                        <div class="cc-cell-sub">{{ $mm->display_phone }} · {{ $mm->created_at->format('j M, H:i') }}</div>
                    </div>
                    <span class="cc-badge {{ $mm->status->color() }} {{ $mm->isPending() ? 'no-dot' : '' }}">@if($mm->isPending())<span class="spinner-border spinner-border-sm" style="width:.7rem;height:.7rem" aria-hidden="true"></span>@endif{{ $mm->status->label() }}</span>
                </div>
                @if($mm->message)<div class="small text-muted mt-1" data-momo-message>{{ $mm->message }}</div>@endif
                @if($mm->isPending())
                    <div class="d-flex flex-wrap gap-2 mt-2">
                        @if($mm->redirect_url)
                            <a href="{{ $mm->redirect_url }}" target="_blank" rel="noopener" class="btn btn-white btn-sm"><i class="fa-solid fa-arrow-up-right-from-square" aria-hidden="true"></i>Complete test payment</a>
                        @endif
                        <form method="POST" action="{{ route('mobile-money.refresh', $mm) }}">@csrf<button type="submit" class="btn btn-ghost btn-sm">Check now</button></form>
                    </div>
                @elseif($mm->payment_id)
                    <div class="small mt-1">Recorded as payment <span class="tabular">{{ $mm->payment?->reference }}</span></div>
                @endif
            </li>
        @endforeach
    </ul>
</section>
