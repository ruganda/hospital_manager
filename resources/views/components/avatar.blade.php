@props(['user', 'size' => null])
{{-- Uploaded photo when there is one, otherwise neutral initials (no external avatar service). --}}
@php
    $hasPhoto = $user?->photo_path && \Illuminate\Support\Facades\Storage::disk('public')->exists($user->photo_path);
    $initials = $user ? mb_strtoupper($user->initials) : '?';
@endphp
<span {{ $attributes->merge(['class' => 'cc-avatar'.($size ? ' '.$size : '')]) }} @unless($hasPhoto) aria-hidden="true" @endunless>
    @if($hasPhoto)
        <img src="{{ $user->photo_url }}" alt="{{ $user->name }}">
    @else
        {{ $initials }}
    @endif
</span>
