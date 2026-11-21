@props(['title', 'icon' => null, 'subtitle' => null, 'back' => null, 'backLabel' => null, 'eyebrow' => null])
{{-- Page title + optional breadcrumb-style back link + right-aligned actions (slot). `icon` is accepted for compatibility but not shown. --}}
<div class="cc-page-head">
    <div class="min-w-0">
        @if($back)
            <a href="{{ $back }}" class="d-inline-flex align-items-center gap-2 small text-muted text-decoration-none mb-2"><i class="fa-solid fa-arrow-left" aria-hidden="true"></i>{{ $backLabel ?? 'Back' }}</a>
        @elseif($eyebrow)
            <div class="small-caps mb-2">{{ $eyebrow }}</div>
        @endif
        <h1>{{ $title }}</h1>
        @if($subtitle)<p class="cc-sub">{{ $subtitle }}</p>@endif
    </div>
    @if(trim($slot) !== '')
        <div class="cc-page-actions">{{ $slot }}</div>
    @endif
</div>
