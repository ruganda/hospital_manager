<form method="post" action="{{ route('password.update') }}" class="card">
    @csrf
    @method('put')
    <div class="card-body">
        @if (session('status') === 'password-updated')
            <div class="cc-callout ok small mb-3" role="status"><i class="fa-solid fa-circle-check" aria-hidden="true"></i><div>Password updated.</div></div>
        @endif
        <div class="row">
            <div class="col-12 mb-3">
                <label class="form-label" for="current_password">Current password</label>
                <input type="password" name="current_password" id="current_password" class="form-control @error('current_password', 'updatePassword') is-invalid @enderror" autocomplete="current-password">
                @error('current_password', 'updatePassword')<div class="invalid-feedback">{{ $message }}</div>@enderror
            </div>
            <div class="col-md-6 mb-3">
                <label class="form-label" for="password">New password</label>
                <input type="password" name="password" id="password" class="form-control @error('password', 'updatePassword') is-invalid @enderror" autocomplete="new-password">
                @error('password', 'updatePassword')<div class="invalid-feedback">{{ $message }}</div>@enderror
            </div>
            <div class="col-md-6 mb-0">
                <label class="form-label" for="password_confirmation">Confirm new password</label>
                <input type="password" name="password_confirmation" id="password_confirmation" class="form-control" autocomplete="new-password">
            </div>
        </div>
    </div>
    <div class="card-footer d-flex justify-content-end"><button type="submit" class="btn btn-white">Update password</button></div>
</form>
