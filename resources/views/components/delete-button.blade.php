@props(['action', 'label' => null, 'confirm' => 'Are you sure you want to delete this record? This cannot be undone.', 'size' => 'sm'])
<form method="POST" action="{{ $action }}" class="d-inline" data-confirm="{{ $confirm }}">
    @csrf
    @method('DELETE')
    @if($label)
        <button type="submit" class="btn btn-danger-soft {{ $size === 'sm' ? 'btn-sm' : '' }}"><i class="fa-solid fa-trash-can" aria-hidden="true"></i>{{ $label }}</button>
    @else
        <button type="submit" class="btn btn-ghost btn-icon btn-danger-hover" title="Delete" aria-label="Delete" data-bs-toggle="tooltip"><i class="fa-solid fa-trash-can"></i></button>
    @endif
</form>
