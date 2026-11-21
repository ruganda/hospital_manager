{{-- Payment method as four large radio buttons. Expects $selected (string value). --}}
@php $selected = $selected instanceof \BackedEnum ? $selected->value : (string) $selected; @endphp
<fieldset class="mb-3">
    <legend class="form-label">Method <span class="text-danger" aria-hidden="true">*</span></legend>
    <div class="row g-2">
        @foreach(\App\Enums\PaymentMethod::cases() as $m)
            <div class="col-6 col-sm-3 col-xl-6 col-xxl-3">
                <input type="radio" class="btn-check" name="method" id="method_{{ $m->value }}" value="{{ $m->value }}" autocomplete="off" @checked($selected === $m->value) required>
                <label class="btn btn-outline-primary w-100 h-100 flex-column py-2 px-1" for="method_{{ $m->value }}" style="font-size:.8125rem">
                    <i class="fa-solid {{ $m->icon() }}" aria-hidden="true"></i>
                    <span>{{ str_contains($m->label(), '(') ? trim(explode('(', $m->label())[0]) : $m->label() }}</span>
                </label>
            </div>
        @endforeach
    </div>
    @error('method')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
</fieldset>
