@extends('layouts.plain')

@section('title', 'Choose a new password')

@section('content')
    <div class="card">
        <h1>Choose a new password</h1>
        <p>Use at least 10 characters with letters and numbers. All devices will be signed out.</p>

        <form method="POST" action="{{ route('password.update') }}" novalidate>
            @csrf
            <input type="hidden" name="token" value="{{ $token }}">

            <label for="email">Email</label>
            <input id="email" name="email" type="email" autocomplete="username" required value="{{ old('email', $email) }}"
                   @error('email') aria-invalid="true" aria-describedby="email-error" @enderror>
            @error('email')<div class="error" id="email-error">{{ $message }}</div>@enderror

            <label for="password">New password</label>
            <input id="password" name="password" type="password" autocomplete="new-password" required minlength="10"
                   aria-describedby="password-hint @error('password') password-error @enderror"
                   @error('password') aria-invalid="true" @enderror>
            <div class="hint" id="password-hint">At least 10 characters, including a letter and a number.</div>
            @error('password')<div class="error" id="password-error">{{ $message }}</div>@enderror

            <label for="password_confirmation">Confirm new password</label>
            <input id="password_confirmation" name="password_confirmation" type="password" autocomplete="new-password" required>

            <button type="submit">Update password</button>
        </form>
    </div>
@endsection
