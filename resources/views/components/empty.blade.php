@props(['message' => 'No records found.', 'icon' => 'fa-folder-open', 'colspan' => null])
@if($colspan)
    <tr><td colspan="{{ $colspan }}" class="p-0"><div class="cc-empty"><div class="ic"><i class="fa-solid {{ $icon }}" aria-hidden="true"></i></div>{{ $message }}</div></td></tr>
@else
    <div class="cc-empty"><div class="ic"><i class="fa-solid {{ $icon }}" aria-hidden="true"></i></div>{{ $message }}</div>
@endif
