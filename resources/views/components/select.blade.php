@props(['name', 'label' => null, 'options' => [], 'value' => null, 'required' => false, 'placeholder' => null, 'help' => null, 'col' => null])
@php $id = $attributes->get('id', $name); $selected = (string) old($name, $value instanceof \BackedEnum ? $value->value : $value); @endphp
<div class="{{ $col ?? 'mb-3' }}">
    @if($label)<label for="{{ $id }}" class="form-label">{{ $label }} @if($required)<span class="text-danger">*</span>@endif</label>@endif
    <select name="{{ $name }}" id="{{ $id }}" @if($required) required @endif
        {{ $attributes->except("id")->merge(['class' => 'form-select'.($errors->has($name) ? ' is-invalid' : '')]) }}>
        @if($placeholder !== null)<option value="">{{ $placeholder }}</option>@endif
        @foreach($options as $key => $text)
            <option value="{{ $key }}" @selected($selected === (string) $key)>{{ $text }}</option>
        @endforeach
        {{ $slot }}
    </select>
    @error($name)<div class="invalid-feedback">{{ $message }}</div>@enderror
    @if($help)<div class="form-text">{{ $help }}</div>@endif
</div>
