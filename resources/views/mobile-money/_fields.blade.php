{{--
    Network (MTN / Airtel) + phone number for a Mobile Money request.
    $prefix: '' → fields "network"/"phone"; 'momo_' → "momo_network"/"momo_phone" (inside the consultation form).
    $phone: number to pre-fill. The network is guessed from it (and again as the user types, see ui.js).
--}}
@php
    $prefix = $prefix ?? '';
    $netField = $prefix.'network';
    $phoneField = $prefix.'phone';
    $guess = (\App\Enums\MobileMoneyNetwork::guessFromPhone(old($phoneField, $phone ?? null)) ?? \App\Enums\MobileMoneyNetwork::Mtn)->value;
@endphp
<fieldset class="mb-3">
    <legend class="form-label">Network <span class="text-danger" aria-hidden="true">*</span></legend>
    <div class="row g-2">
        @foreach(\App\Enums\MobileMoneyNetwork::cases() as $net)
            <div class="col-6">
                <input type="radio" class="btn-check" name="{{ $netField }}" id="{{ $prefix }}net_{{ $net->value }}" value="{{ $net->value }}" autocomplete="off" @checked(old($netField, $guess) === $net->value)>
                <label class="btn btn-outline-primary w-100" for="{{ $prefix }}net_{{ $net->value }}">{{ $net->label() }}</label>
            </div>
        @endforeach
    </div>
    @error($netField)<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
</fieldset>
<x-input :name="$phoneField" type="tel" label="Mobile Money number" :value="$phone ?? null" placeholder="0772 123 456" autocomplete="off" data-momo-phone help="The number registered for MTN Mobile Money or Airtel Money." :col="$col ?? 'mb-3'" />
