@extends('layouts.auth')
@section('title', 'Log in')
@section('content')
    <h1>Welcome back</h1>
    <p>Log in with your e-mail and password. We'll then e-mail you a 6-digit code (two-factor authentication).</p>
    <form method="POST" action="/login" onsubmit="this.querySelector('button').disabled=true">
        @csrf
        <div class="field">
            <label for="email">E-mail</label>
            <input type="email" id="email" name="email" value="{{ old('email') }}" autocomplete="username" required autofocus>
        </div>
        <div class="field">
            <label for="password">Password</label>
            <input type="password" id="password" name="password" autocomplete="current-password" required>
        </div>
        <button type="submit" class="btn block">Continue</button>
    </form>
    <hr>
    <p class="small">New here? <a href="/register">Create a customer account</a>. Verification officers receive their accounts from the administrator.</p>
@endsection
