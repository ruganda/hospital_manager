<form id="send-verification" method="post" action="{{ route('verification.send') }}">@csrf</form>

<form method="post" action="{{ route('profile.update') }}" enctype="multipart/form-data" class="card">
    @csrf
    @method('patch')
    <div class="card-body">
        <div class="d-flex flex-wrap align-items-center gap-3 mb-4">
            <img src="{{ $user->photo_url }}" id="photo-preview" class="avatar-lg" alt="Profile photo preview" @unless($user->photo_path) style="opacity:.9" @endunless>
            <div class="flex-grow-1" style="min-width:220px">
                <label for="photo" class="form-label">Profile photo</label>
                <input type="file" name="photo" id="photo" accept="image/png,image/jpeg,image/webp" class="form-control @error('photo') is-invalid @enderror" data-photo-input="#photo-preview">
                @error('photo')<div class="invalid-feedback">{{ $message }}</div>@enderror
                <div class="form-text">JPG, PNG or WEBP, up to 2 MB.</div>
                @if($user->photo_path)
                    <div class="form-check mt-1">
                        <input class="form-check-input" type="checkbox" name="remove_photo" value="1" id="remove_photo">
                        <label class="form-check-label small" for="remove_photo">Remove current photo</label>
                    </div>
                @endif
            </div>
        </div>

        <div class="row">
            <x-input name="name" label="Full name" :value="$user->name" required autocomplete="name" col="col-md-6 mb-3" />
            <x-input name="phone" type="tel" placeholder="+256 7XX XXX XXX" label="Phone" :value="$user->phone" autocomplete="tel" col="col-md-6 mb-3" />
            <x-input name="email" type="email" label="Email" :value="$user->email" required autocomplete="email" col="col-12 mb-3" />
        </div>

        @if ($user instanceof \Illuminate\Contracts\Auth\MustVerifyEmail && ! $user->hasVerifiedEmail())
            <div class="cc-callout warn small mb-3"><i class="fa-solid fa-envelope" aria-hidden="true"></i><div>Your email address is not verified. <button form="send-verification" class="btn btn-link btn-sm p-0 align-baseline">Re-send the verification email.</button></div></div>
        @endif

        @if($user->patient)
            @php $p = $user->patient; @endphp
            <div class="cc-divider"></div>
            <h3 class="h6 fw-semibold mb-3">Medical &amp; contact details</h3>
            <div class="row">
                <x-input name="date_of_birth" type="date" :max="today()->subDay()->toDateString()" min="1900-01-01" label="Date of birth" :value="$p->date_of_birth?->toDateString()" col="col-md-4 mb-3" />
                <x-select name="gender" label="Gender" :options="['male' => 'Male', 'female' => 'Female', 'other' => 'Other']" :value="$p->gender" placeholder="—" col="col-md-4 mb-3" />
                <x-select name="blood_group" label="Blood group" :options="array_combine(\App\Models\Patient::BLOOD_GROUPS, \App\Models\Patient::BLOOD_GROUPS)" :value="$p->blood_group" placeholder="Unknown" col="col-md-4 mb-3" />
                <x-input name="address" label="Address" :value="$p->address" col="col-12 mb-3" />
                <x-textarea name="allergies" label="Allergies" :value="$p->allergies" rows="2" col="col-12 mb-3" />
                <x-input name="emergency_contact_name" label="Emergency contact" :value="$p->emergency_contact_name" col="col-md-6 mb-3" />
                <x-input name="emergency_contact_phone" type="tel" placeholder="+256 7XX XXX XXX" label="Emergency phone" :value="$p->emergency_contact_phone" col="col-md-6 mb-3" />
            </div>
        @endif
    </div>
    <div class="card-footer d-flex justify-content-end"><button type="submit" class="btn btn-primary">Save changes</button></div>
</form>
