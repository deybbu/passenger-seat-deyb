@extends('layouts.app')

@section('title', 'Menu')

@section('content')
<section class="landing-menu-page">
    <div class="landing-menu-inner">
        <header class="landing-menu-heading">
            <p class="landing-eyebrow">THE MENU</p>
            <h1>Good food, <em>great journeys.</em></h1>
            <p>Browse by category and find your next favorite dish.</p>
        </header>

        <p class="landing-menu-note" role="note">
            Sample dishes and prices for layout only. The live menu loads from the restaurant's database.
        </p>

        <div class="landing-menu-tools">
            <nav class="landing-menu-categories" aria-label="Menu categories">
                <a
                    href="{{ route('menu', array_filter(['q' => $search ?: null])) }}"
                    @if ($selectedCategory === '') aria-current="page" @endif
                >All</a>
                @foreach ($categories as $category)
                    <a
                        href="{{ route('menu', array_filter(['category' => $category->slug, 'q' => $search ?: null])) }}"
                        @if ($selectedCategory === $category->slug) aria-current="page" @endif
                    >
                        {{ $category->name }}
                        <span>({{ $category->active_items_count }})</span>
                    </a>
                @endforeach
            </nav>

            <form class="landing-menu-search" action="{{ route('menu') }}" method="GET" role="search">
                @if ($selectedCategory !== '')
                    <input type="hidden" name="category" value="{{ $selectedCategory }}">
                @endif
                <label class="sr-only" for="menu-search">Search dishes</label>
                <input
                    id="menu-search"
                    type="search"
                    name="q"
                    value="{{ $search }}"
                    placeholder="Search dishes"
                >
                <button type="submit">Search</button>
            </form>
        </div>

        <div class="landing-menu-grid">
            @forelse ($menuItems as $item)
                <x-landing.menu-card :item="$item" />
            @empty
                <div class="landing-menu-empty">
                    <h2>No dishes found</h2>
                    <p>Try another category or search term.</p>
                </div>
            @endforelse
        </div>
    </div>
</section>
@endsection
