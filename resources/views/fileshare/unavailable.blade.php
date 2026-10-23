@extends('fileshare.layout')

@section('title', 'Link unavailable')
@section('container-class', 'narrow')

@section('content')
    <div class="card">
        <h1>This link isn't available</h1>
        <p class="muted" style="margin-bottom:0">
            The file may have expired, been removed, or your access may have been withdrawn.
            Please contact the person who shared it with you for a new link.
        </p>
    </div>
@endsection
