@extends('layouts.plain')

@section('title', 'API')

@section('content')
    <div class="card">
        <h1>RIVALO platform</h1>
        <p>This server hosts the RIVALO mobile API and the staff admin panel. Players use the Android and iPhone apps.</p>
        <p><a href="{{ url('/admin') }}">Staff sign-in</a> · <a href="{{ url('/up') }}">Health check</a></p>
    </div>
@endsection
