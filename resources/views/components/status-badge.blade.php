@props(['status'])
@if($status)
    <span {{ $attributes->merge(['class' => 'cc-badge '.$status->color()]) }}>{{ $status->label() }}</span>
@endif
