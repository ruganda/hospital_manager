@props(['title', 'chart', 'icon' => null, 'height' => 280, 'accent' => null, 'note' => null])
<div {{ $attributes->merge(['class' => 'card h-100']) }}>
    <div class="card-header">
        <h2 class="card-title">{{ $title }}</h2>
        @if($note)<span class="small text-muted fw-normal">{{ $note }}</span>@endif
        {{ $actions ?? '' }}
    </div>
    <div class="card-body">
        <div class="chart-box" style="height: {{ $height }}px">
            <div class="chart-loading text-muted small position-absolute top-50 start-50 translate-middle"><span class="spinner-border spinner-border-sm me-2" aria-hidden="true"></span>Loading…</div>
            <canvas data-chart="{{ $chart }}" role="img" aria-label="{{ $title }}"></canvas>
        </div>
    </div>
</div>
