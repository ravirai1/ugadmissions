@extends('layouts.admin')
@section('title', 'Change Password')
@section('content')
<div class="row justify-content-center">
    <div class="col-md-6">
        <div class="card">
            <div class="card-header"><div class="card-title">Change Password</div></div>
            <div class="card-body">
                @if (auth('admin')->user()->must_change_password)
                    <div class="alert alert-warning">You must set a new password before continuing.</div>
                @endif
                <form method="POST" action="{{ route('admin.password.update') }}">
                    @csrf @method('PUT')
                    <div class="form-group px-0">
                        <label for="current_password">Current password</label>
                        <input type="password" class="form-control @error('current_password') is-invalid @enderror" id="current_password" name="current_password" required autocomplete="current-password">
                        @error('current_password')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
                    </div>
                    <div class="form-group px-0">
                        <label for="password">New password</label>
                        <input type="password" class="form-control @error('password') is-invalid @enderror" id="password" name="password" required autocomplete="new-password">
                        @error('password')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
                        <small class="form-text text-muted">At least 8 characters with upper and lower case letters, a number and a symbol.</small>
                    </div>
                    <div class="form-group px-0">
                        <label for="password_confirmation">Confirm new password</label>
                        <input type="password" class="form-control" id="password_confirmation" name="password_confirmation" required autocomplete="new-password">
                    </div>
                    <button class="btn btn-primary" type="submit">Update Password</button>
                </form>
            </div>
        </div>
    </div>
</div>
@endsection
