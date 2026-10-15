@extends('layouts.public-landing')

@section('content')
    @include('landing.partials.hero')
    @include('landing.partials.menu')
    @include('landing.partials.whats-new')
    @include('landing.partials.story')
    @include('landing.partials.gallery')
    @include('landing.partials.call-to-action')
    @include('landing.partials.event-inquiry')
@endsection

@push('scripts')
    @vite('resources/js/landing-events.js')
@endpush
