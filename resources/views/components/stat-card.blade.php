@props(['label', 'value', 'icon' => null, 'color' => 'primary', 'hint' => null, 'href' => null])
{{-- Standalone stat tile. Monochrome icon; the number counts up when it scrolls into view. --}}
<div class="card cc-stat h-100">
    <div class="cc-stat-top">
        <span class="cc-stat-label">{{ $label }}</span>
        @if($icon)<i class="fa-solid {{ $icon }} cc-stat-icon" aria-hidden="true"></i>@endif
    </div>
    <div class="cc-stat-value" data-countup>{{ $value }}</div>
    @if($hint)<div class="cc-stat-hint">{{ $hint }}</div>@endif
    @if($href)<a href="{{ $href }}" class="stretched-link" aria-label="{{ $label }}"></a>@endif
</div>
