@extends('layouts.app')
@section('title', 'Our Story')

@section('content')
<section class="landing-story-page">
    <div class="landing-story-inner">
        <div class="landing-story-eyebrow">Our Story</div>
        @include('landing.partials.story-content')
    </div>
</section>
@endsection
