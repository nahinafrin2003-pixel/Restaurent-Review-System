@extends('layouts.auth')
@section('title', 'Register')
@section('content')
    <h1>Create your account</h1>
    <p>Customers can review restaurants and file consumer complaints. Your e-mail, phone and username are encrypted at rest.</p>
    <form method="POST" action="/register" onsubmit="this.querySelector('button').disabled=true">
        @csrf
        <div class="field">
            <label for="username">Username</label>
            <input type="text" id="username" name="username" value="{{ old('username') }}" maxlength="10" minlength="10" placeholder="e.g. Rahim@2026" required>
            <div class="hint">Exactly 10 characters with letters, numbers and a symbol.</div>
        </div>
        <div class="field">
            <label for="email">E-mail</label>
            <input type="email" id="email" name="email" value="{{ old('email') }}" required>
        </div>
        <div class="field">
            <label for="contact_info">Mobile number</label>
            <input type="text" id="contact_info" name="contact_info" value="{{ old('contact_info') }}" inputmode="numeric" pattern="\d{11}" maxlength="11" placeholder="01XXXXXXXXX" required>
            <div class="hint">11 digits.</div>
        </div>
        <div class="form-grid">
            <div class="field">
                <label for="password">Password</label>
                <input type="password" id="password" name="password" minlength="12" autocomplete="new-password" required>
                <div class="hint">At least 12 characters.</div>
            </div>
            <div class="field">
                <label for="password_confirmation">Confirm password</label>
                <input type="password" id="password_confirmation" name="password_confirmation" minlength="12" autocomplete="new-password" required>
            </div>
        </div>
        <button type="submit" class="btn block">Create account</button>
    </form>
    <hr>
    <p class="small">Already registered? <a href="/login">Log in</a></p>
@endsection
