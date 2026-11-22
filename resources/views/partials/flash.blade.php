@php
    $flashTypes = [
        'success' => ['fa-circle-check', '#0f6b3c'],
        'error' => ['fa-triangle-exclamation', '#a8251b'],
        'warning' => ['fa-circle-exclamation', '#8a4f00'],
        'info' => ['fa-circle-info', '#0b6e6e'],
    ];
@endphp
<div class="toast-container position-fixed top-0 end-0 p-3" style="z-index: 1100">
    @foreach ($flashTypes as $type => [$icon, $color])
        @if (session($type))
            <div class="toast" role="{{ $type === 'error' ? 'alert' : 'status' }}" aria-live="{{ $type === 'error' ? 'assertive' : 'polite' }}" aria-atomic="true">
                <div class="d-flex">
                    <div class="toast-body"><i class="fa-solid {{ $icon }}" style="color: {{ $color }}; margin-top: 3px" aria-hidden="true"></i><span>{{ session($type) }}</span></div>
                    <button type="button" class="btn-close me-2 m-auto" data-bs-dismiss="toast" aria-label="Close"></button>
                </div>
            </div>
        @endif
    @endforeach
</div>

@php
    // Errors that a form already shows in context (e.g. the duplicate-patient callout) are left out of the summary.
    $summaryErrors = collect($errors->getMessages())->except(['duplicate'])->flatten();
@endphp
@if ($summaryErrors->isNotEmpty())
    <div class="cc-callout danger mb-4" role="alert">
        <i class="fa-solid fa-triangle-exclamation" aria-hidden="true"></i>
        <div>
            <div class="fw-semibold mb-1">Please fix the following:</div>
            <ul class="mb-0 ps-3">
                @foreach ($summaryErrors as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
    </div>
@endif
