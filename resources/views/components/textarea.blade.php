@props(['name', 'label' => null, 'value' => null, 'required' => false, 'rows' => 3, 'help' => null, 'col' => null])
<div class="{{ $col ?? 'mb-3' }}">
    @if($label)<label for="{{ $name }}" class="form-label">{{ $label }} @if($required)<span class="text-danger">*</span>@endif</label>@endif
    <textarea name="{{ $name }}" id="{{ $name }}" rows="{{ $rows }}" @if($required) required @endif
        {{ $attributes->merge(['class' => 'form-control'.($errors->has($name) ? ' is-invalid' : '')]) }}>{{ old($name, $value) }}</textarea>
    @error($name)<div class="invalid-feedback">{{ $message }}</div>@enderror
    @if($help)<div class="form-text">{{ $help }}</div>@endif
</div>
