<div class="card" style="border-color:#f3c7c2">
    <div class="card-body d-flex flex-wrap justify-content-between align-items-center gap-3">
        <span class="small">You'll be asked for your password to confirm.</span>
        <button type="button" class="btn btn-danger-soft" data-bs-toggle="modal" data-bs-target="#deleteAccount">Delete my account</button>
    </div>
</div>

<div class="modal fade" id="deleteAccount" tabindex="-1" aria-labelledby="deleteAccountTitle" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <form method="post" action="{{ route('profile.destroy') }}" class="modal-content">
            @csrf
            @method('delete')
            <div class="modal-header"><h2 class="modal-title" id="deleteAccountTitle">Delete your account?</h2><button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button></div>
            <div class="modal-body">
                <p class="small text-muted">This cannot be undone. Enter your password to confirm.</p>
                <label class="form-label" for="delete_password">Password</label>
                <input type="password" name="password" id="delete_password" class="form-control @error('password', 'userDeletion') is-invalid @enderror" autocomplete="current-password">
                @error('password', 'userDeletion')<div class="invalid-feedback">{{ $message }}</div>@enderror
            </div>
            <div class="modal-footer"><button type="button" class="btn btn-ghost" data-bs-dismiss="modal">Cancel</button><button type="submit" class="btn btn-danger">Delete account</button></div>
        </form>
    </div>
</div>
@if($errors->userDeletion->isNotEmpty())
    @push('scripts')<script>document.addEventListener('DOMContentLoaded', () => new bootstrap.Modal('#deleteAccount').show());</script>@endpush
@endif
