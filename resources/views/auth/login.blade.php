@extends('layouts.auth')

@section('content')
    <h1>Sign in</h1>

    <form method="POST" action="{{ route('login') }}">
        @csrf

        <label for="email">Email</label>
        <input id="email" type="email" name="email" value="{{ old('email') }}" required autofocus autocomplete="username">

        <label for="password">Password</label>
        <input id="password" type="password" name="password" required autocomplete="current-password">

        <label>
            <input type="checkbox" name="remember" value="1" style="width:auto">
            Remember me
        </label>

        <button type="submit">Sign in</button>
    </form>

    <div class="links">
        <a href="{{ route('password.request') }}">Forgot password?</a>
        <a href="{{ route('register') }}">Create account</a>
    </div>
@endsection
