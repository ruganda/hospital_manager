@props(['label', 'value', 'hint' => null, 'href' => null])
{{-- One figure inside a <div class="card cc-kpis"> strip. --}}
<div class="cc-kpi">
    <div class="small text-muted mb-2">{{ $label }}</div>
    <div class="cc-stat-value" data-countup>{{ $value }}</div>
    @if($hint)<div class="small text-muted mt-1 text-truncate">{{ $hint }}</div>@endif
    @if($href)<a href="{{ $href }}" class="stretched-link" aria-label="{{ $label }}"></a>@endif
</div>
