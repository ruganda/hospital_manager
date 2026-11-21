<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ config('clinic.name') }} · Kampala</title>
    <meta name="description" content="{{ config('clinic.name') }}: book a doctor, keep your treatment history in one place and pay with Mobile Money. Kampala Road, Kampala.">
    @include('partials.head')
</head>
<body class="cc-public">

<header class="cc-topbar">
    <div class="cc-wrap d-flex flex-wrap align-items-center gap-3 w-100 py-2">
        <a href="{{ route('home') }}" class="cc-brand"><span class="cc-brand-mark"><i class="fa-solid fa-house-medical" aria-hidden="true"></i></span><span class="cc-brand-name">{{ config('clinic.short_name') }}</span></a>
        <nav class="d-none d-md-flex gap-1 ms-md-3" aria-label="Sections">
            @if(count($availability))<a class="btn btn-ghost" href="#availability">Availability</a>@endif
            <a class="btn btn-ghost" href="#departments">Departments</a>
            <a class="btn btn-ghost" href="#visit">Visit us</a>
        </nav>
        <div class="ms-auto d-flex gap-2">
            <a class="btn btn-ghost" href="{{ route('login') }}">Log in</a>
            <a class="btn btn-primary" href="{{ route('register') }}">Book a visit</a>
        </div>
    </div>
</header>

<main>
    {{-- Hero: one statement + a heartbeat line --}}
    <section class="cc-section pb-0">
        <div class="cc-wrap">
            <h1 class="cc-hero-title">See the right doctor, on time.</h1>
            <div class="d-flex flex-wrap align-items-end justify-content-between gap-4">
                <p class="cc-lede m-0">{{ config('clinic.name') }} on Kampala Road. Book a free slot, keep your history in one place, and pay with Mobile Money.</p>
                <div class="d-flex flex-wrap gap-2">
                    <a class="btn btn-primary btn-lg" href="{{ count($availability) ? '#availability' : route('register') }}">Find a free slot</a>
                    <a class="btn btn-white btn-lg" href="{{ route('login') }}">Patient log in</a>
                </div>
            </div>
        </div>
        <div class="cc-wrap" style="margin-top:72px">
            <svg class="cc-ecg d-block" viewBox="0 0 1160 120" width="100%" height="120" preserveAspectRatio="none" aria-hidden="true">
                <line x1="0" y1="60" x2="1160" y2="60" stroke="#e6e8ec" stroke-width="1"></line>
                <path class="trace" d="M0 60 H330 L350 60 L362 44 L374 60 L392 60 L404 8 L420 112 L434 60 L452 60 L470 36 L492 60 H760 L776 60 L786 48 L796 60 L812 60 L822 18 L836 100 L848 60 L864 60 L880 42 L898 60 H1160" fill="none" stroke="#0b6e6e" stroke-width="2" stroke-linejoin="round" stroke-linecap="round" vector-effect="non-scaling-stroke"></path>
                <circle class="blip" cx="1152" cy="60" r="4" fill="#0b6e6e"></circle>
            </svg>
            <div class="d-flex flex-wrap justify-content-between gap-3 small text-muted pt-3">
                <span><i class="fa-regular fa-clock me-2" aria-hidden="true"></i>{{ config('clinic.hours') }}</span>
                <span><i class="fa-solid fa-phone me-2" aria-hidden="true"></i>{{ config('clinic.phone') }}</span>
            </div>
        </div>
    </section>

    {{-- Figures that count up when scrolled into view --}}
    <section class="cc-section" aria-label="{{ config('clinic.short_name') }} in numbers" style="padding-top:88px">
        <div class="cc-wrap cc-figures">
            <div><div class="cc-figure" data-countup="1400">{{ $figures['departments'] }}</div><div class="text-muted mt-3">Departments under one roof</div></div>
            <div><div class="cc-figure" data-countup="1400">{{ $figures['doctors'] }}</div><div class="text-muted mt-3">Doctors on staff</div></div>
            <div><div class="cc-figure" data-countup="1400">{{ number_format($figures['patients']) }}</div><div class="text-muted mt-3">Patients registered with us</div></div>
            <div><div class="cc-figure" data-countup="1400">24/7</div><div class="text-muted mt-3">Emergency cover</div></div>
        </div>
    </section>

    {{-- Interactive free-slot chart, built from the doctors' real schedules --}}
    @if(count($availability))
        <section id="availability" class="cc-section" style="background:#fff;border-top:1px solid var(--cc-line);border-bottom:1px solid var(--cc-line)" data-availability>
            <script type="application/json">@json($availability)</script>
            <div class="cc-wrap">
                <div class="d-flex flex-wrap justify-content-between align-items-end gap-4 mb-5">
                    <div>
                        <h2 class="cc-h2">Free slots this week</h2>
                        <p class="text-muted m-0" style="max-width:52ch">Pick a department. Taller bars mean more open times that day.</p>
                    </div>
                    <div class="d-flex flex-wrap gap-2" role="group" aria-label="Department">
                        @foreach($availability as $i => $a)
                            <button type="button" class="cc-dept-btn" data-dept="{{ $i }}" aria-pressed="{{ $i === 0 ? 'true' : 'false' }}">{{ $a['name'] }}</button>
                        @endforeach
                    </div>
                </div>

                <div class="d-flex flex-wrap" style="gap:48px">
                    <div style="flex:2 1 520px;min-width:0;--cc-days:{{ count($days) }}">
                        <div class="cc-avail-grid mb-1">
                            <span></span>
                            @foreach($days as $day)<span class="small text-muted text-center">{{ $day }}</span>@endforeach
                        </div>
                        @foreach($availability as $i => $a)
                            <div class="cc-avail-grid cc-avail-row {{ $i === 0 ? 'is-on' : 'is-dim' }}" data-dept-row="{{ $i }}">
                                <span class="text-truncate" style="color:var(--cc-text)">{{ $a['name'] }}</span>
                                @foreach($a['free'] as $d => $n)
                                    <div class="cc-avail-cell" title="{{ $days[$d] }}: {{ $n ? $n.' free '.Str::plural('slot', $n) : 'no free slots' }}">
                                        <i class="{{ $n ? '' : 'zero' }}" style="height:{{ $n ? 8 + (int) round($n / $maxFree * 40) : 3 }}px"></i>
                                    </div>
                                @endforeach
                            </div>
                        @endforeach
                    </div>

                    <div style="flex:1 1 280px;max-width:380px" aria-live="polite">
                        <div class="small-caps mb-3">Next opening</div>
                        <div data-out="when" style="font-size:2.1rem;font-weight:600;letter-spacing:-.03em;color:#111;line-height:1.1">{{ $availability[0]['next']['when'] ?? 'No free slots this week' }}</div>
                        <div data-out="doctor" class="mt-2 mb-4">{{ isset($availability[0]['next']) ? $availability[0]['next']['doctor'].' · ' : '' }}{{ $availability[0]['name'] }}</div>
                        <dl class="cc-dl mb-4" style="grid-template-columns:130px minmax(0,1fr)">
                            <dt>Consultation from</dt><dd class="tabular" data-out="fee">{{ $availability[0]['fee'] }}</dd>
                            <dt>Open this week</dt><dd class="tabular" data-out="total">{{ $availability[0]['total'] }} {{ Str::plural('slot', $availability[0]['total']) }}</dd>
                        </dl>
                        <a class="btn btn-primary btn-lg w-100" href="{{ route('register') }}">Book a slot</a>
                        <p class="small text-muted mt-3 mb-0">Only times inside a doctor's schedule are shown, so no one is double-booked.</p>
                    </div>
                </div>
            </div>
        </section>
    @endif

    {{-- Departments as a list, not cards --}}
    <section id="departments" class="cc-section">
        <div class="cc-wrap d-flex flex-wrap" style="gap:64px">
            <div style="flex:1 1 280px;max-width:340px">
                <h2 class="cc-h2">{{ $figures['departments'] }} clinics, one front desk.</h2>
                <p class="text-muted">Every department books through the same schedule, so you get one record and one bill.</p>
            </div>
            <div style="flex:2 1 520px;min-width:0">
                @foreach($departments as $d)
                    <div class="cc-dept-row">
                        <i class="fa-solid {{ $d['icon'] }} text-muted" aria-hidden="true"></i>
                        <span class="fw-semibold" style="color:#111">{{ $d['name'] }}</span>
                        <span class="text-muted">{{ $d['description'] }}</span>
                        <span class="small text-muted tabular text-nowrap">{{ $d['fee'] ? 'from '.$d['fee'] : '' }}</span>
                    </div>
                @endforeach
            </div>
        </div>
    </section>

    {{-- Full-bleed statement band --}}
    <section class="cc-section cc-band">
        <div class="cc-wrap">
            <p style="font-size:clamp(2rem,4vw,3.25rem);font-weight:500;letter-spacing:-.035em;line-height:1.12;color:#fff;max-width:20ch;margin:0 0 72px">One record, from first visit to last receipt.</p>
            <ol class="list-unstyled m-0 d-flex flex-wrap" style="gap:48px">
                <li class="cc-band-step" style="flex:1 1 220px"><div class="small mb-2" style="color:#8fd3cc">01</div><div class="fw-semibold text-white mb-1">Create an account</div><div class="small">You get your own patient number.</div></li>
                <li class="cc-band-step" style="flex:1 1 220px"><div class="small mb-2" style="color:#8fd3cc">02</div><div class="fw-semibold text-white mb-1">Book a free slot</div><div class="small">Straight from each doctor's weekly schedule.</div></li>
                <li class="cc-band-step" style="flex:1 1 220px"><div class="small mb-2" style="color:#8fd3cc">03</div><div class="fw-semibold text-white mb-1">See your notes</div><div class="small">Diagnosis, prescription and follow-up date.</div></li>
                <li class="cc-band-step" style="flex:1 1 220px"><div class="small mb-2" style="color:#8fd3cc">04</div><div class="fw-semibold text-white mb-1">Pay your way</div><div class="small">Cash, MTN or Airtel MoMo, card or insurance.</div></li>
            </ol>
        </div>
    </section>

    {{-- Visit us: simple geometric map --}}
    <section id="visit" class="cc-section">
        <div class="cc-wrap d-flex flex-wrap align-items-center" style="gap:64px">
            <div style="flex:1 1 300px;max-width:400px">
                <h2 class="cc-h2 mb-4">Visit us</h2>
                <dl class="cc-dl" style="grid-template-columns:90px minmax(0,1fr)">
                    <dt>Address</dt><dd>{{ config('clinic.address') }}<br>{{ config('clinic.po_box') }}</dd>
                    <dt>Hours</dt><dd>{{ config('clinic.hours') }}</dd>
                    <dt>Phone</dt><dd><a href="tel:{{ preg_replace('/\s+/', '', config('clinic.phone')) }}">{{ config('clinic.phone') }}</a><br><a href="tel:{{ preg_replace('/\s+/', '', config('clinic.mobile')) }}">{{ config('clinic.mobile') }}</a></dd>
                    <dt>Email</dt><dd><a href="mailto:{{ config('clinic.email') }}">{{ config('clinic.email') }}</a></dd>
                </dl>
            </div>
            <div style="flex:2 1 460px;min-width:0">
                <svg viewBox="0 0 680 380" width="100%" role="img" aria-label="Simplified map: {{ config('clinic.short_name') }} is on Kampala Road" style="display:block;border:1px solid var(--cc-line);border-radius:10px;background:#fff">
                    <g stroke="#eef0f3" stroke-width="1">
                        <line x1="0" y1="60" x2="680" y2="60"></line><line x1="0" y1="140" x2="680" y2="140"></line><line x1="0" y1="260" x2="680" y2="260"></line><line x1="0" y1="330" x2="680" y2="330"></line>
                        <line x1="90" y1="0" x2="90" y2="380"></line><line x1="210" y1="0" x2="210" y2="380"></line><line x1="470" y1="0" x2="470" y2="380"></line><line x1="590" y1="0" x2="590" y2="380"></line>
                    </g>
                    <path d="M-10 300 C 120 250, 220 230, 340 200 S 560 120, 700 90" fill="none" stroke="#dfe3e8" stroke-width="18" stroke-linecap="round"></path>
                    <path d="M-10 300 C 120 250, 220 230, 340 200 S 560 120, 700 90" fill="none" stroke="#fff" stroke-width="2" stroke-dasharray="8 10"></path>
                    <path d="M330 -10 C 335 80, 345 140, 340 200 S 330 320, 350 400" fill="none" stroke="#eceff2" stroke-width="10" stroke-linecap="round"></path>
                    <rect x="250" y="90" width="60" height="40" rx="4" fill="#f4f6f8"></rect><rect x="380" y="230" width="80" height="56" rx="4" fill="#f4f6f8"></rect><rect x="120" y="170" width="54" height="40" rx="4" fill="#f4f6f8"></rect><rect x="500" y="170" width="64" height="44" rx="4" fill="#f4f6f8"></rect>
                    <text x="556" y="78" font-family="Geist, sans-serif" font-size="12" fill="#5a6678" transform="rotate(-12 556 78)">Kampala Road</text>
                    <circle class="cc-pin-ring" cx="340" cy="200" r="14" fill="#0b6e6e" opacity=".35"></circle>
                    <circle cx="340" cy="200" r="8" fill="#0b6e6e" stroke="#fff" stroke-width="3"></circle>
                    <g font-family="Geist, sans-serif"><rect x="358" y="160" width="84" height="30" rx="6" fill="#111"></rect><text x="372" y="180" font-size="12" font-weight="600" fill="#fff">{{ config('clinic.short_name') }}</text></g>
                </svg>
            </div>
        </div>
    </section>
</main>

<footer style="border-top:1px solid var(--cc-line);background:#fff">
    <div class="cc-wrap d-flex flex-wrap justify-content-between gap-3 small text-muted py-4">
        <span>&copy; {{ date('Y') }} {{ config('clinic.name') }}</span>
        <a href="{{ route('login') }}">Staff log in</a>
    </div>
</footer>
</body>
</html>
