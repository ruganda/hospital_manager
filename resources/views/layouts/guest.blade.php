<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@hasSection('title')@yield('title') · @endif{{ config('clinic.name') }}</title>
    @include('partials.head')
</head>
<body>
<div class="cc-auth">
    <aside class="cc-auth-aside">
        <a href="{{ route('home') }}" class="cc-brand" style="color:#fff">
            <span class="cc-brand-mark" style="background:#fff;color:var(--cc-deep)"><i class="fa-solid fa-house-medical" aria-hidden="true"></i></span>
            <span><span class="cc-brand-name d-block" style="color:#fff">{{ config('clinic.short_name') }}</span><span class="cc-brand-sub" style="color:#9cc8c4">Medical Centre · Kampala</span></span>
        </a>
        <div>
            <h2 class="mb-4" style="max-width:440px">@yield('aside_title', 'One place for every visit, record and receipt.')</h2>
            <div class="cc-auth-points d-flex flex-column gap-4" style="max-width:420px">
                <div class="cc-auth-point"><i class="fa-solid fa-calendar-check" aria-hidden="true"></i><div><div class="fw-semibold text-white">No double-booking</div><div class="small">Only free times in a doctor's schedule can be booked.</div></div></div>
                <div class="cc-auth-point"><i class="fa-solid fa-notes-medical" aria-hidden="true"></i><div><div class="fw-semibold text-white">Your full treatment history</div><div class="small">Diagnosis, prescription and follow-up in one timeline.</div></div></div>
                <div class="cc-auth-point"><i class="fa-solid fa-mobile-screen" aria-hidden="true"></i><div><div class="fw-semibold text-white">Pay your way</div><div class="small">Cash, MTN or Airtel Mobile Money, card or insurance.</div></div></div>
            </div>
        </div>
        <div class="cc-auth-foot small" style="color:#9cc8c4">{{ config('clinic.hours') }} · {{ config('clinic.phone') }}</div>
    </aside>
    <main class="cc-auth-main">
        <div class="cc-auth-card @yield('card_class')">
            @yield('content')
        </div>
    </main>
</div>
</body>
</html>
