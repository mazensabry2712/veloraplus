@extends('layouts.auth')

@section('content')
    <h1>Reset password</h1>

    <form method="POST" action="{{ route('password.email') }}">
        @csrf

        <label for="email">Email</label>
        <input id="email" type="email" name="email" value="{{ old('email') }}" required autofocus autocomplete="email">

        <button type="submit">Send reset link</button>
    </form>

    <div class="links">
        <span></span>
        <a href="{{ route('login') }}">Back to sign in</a>
    </div>
@endsection
