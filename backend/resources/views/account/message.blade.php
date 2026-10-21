@extends('layouts.plain')

@section('title', $title)

@section('content')
    <div class="card" role="status">
        <div class="status {{ $status }}">
            @if ($status === 'success')
                <svg width="20" height="20" viewBox="0 0 24 24" aria-hidden="true"><path d="M5 12.5 10 17 19 7" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"/></svg>
                Done
            @else
                <svg width="20" height="20" viewBox="0 0 24 24" aria-hidden="true"><path d="M12 7v6m0 4h.01" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round"/></svg>
                Something went wrong
            @endif
        </div>
        <h1>{{ $title }}</h1>
        <p>{{ $message }}</p>
    </div>
@endsection
