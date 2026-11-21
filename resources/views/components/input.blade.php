@props(['name', 'label' => null, 'type' => 'text', 'value' => null, 'required' => false, 'help' => null, 'icon' => null, 'col' => null])
@php $id = $attributes->get('id', str_replace(['[', ']'], ['_', ''], $name)); @endphp
<div class="{{ $col ?? 'mb-3' }}">
    @if($label)<label for="{{ $id }}" class="form-label">{{ $label }} @if($required)<span class="text-danger">*</span>@endif</label>@endif
    @if($icon)<div class="input-group"><span class="input-group-text"><i class="fa-solid {{ $icon }}"></i></span>@endif
    <input type="{{ $type }}" name="{{ $name }}" id="{{ $id }}"
           value="{{ $type === 'password' ? '' : old($name, $value) }}"
           @if($required) required @endif
           {{ $attributes->except("id")->merge(['class' => 'form-control'.($errors->has($name) ? ' is-invalid' : '')]) }}>
    @if($icon)</div>@endif
    @error($name)<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
    @if($help)<div class="form-text">{{ $help }}</div>@endif
</div>
