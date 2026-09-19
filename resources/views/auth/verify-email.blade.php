@extends('layouts.auth')

@section('content')
    <h1>Verify your email</h1>

    <p>Please verify your email address before entering protected VeloraPlus areas.</p>

    <form method="POST" action="{{ route('verification.send') }}">
        @csrf
        <button type="submit">Resend verification email</button>
    </form>

    <form method="POST" action="{{ route('logout') }}">
        @csrf
        <button type="submit">Sign out</button>
    </form>
@endsection
