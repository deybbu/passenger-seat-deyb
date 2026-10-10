```blade
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>Passenger Seat Restaurant | Menu</title>

    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=DM+Sans:wght@400;500;600;700&family=Manrope:wght@400;500;600;700;800&display=swap" rel="stylesheet">

    <link rel="stylesheet" href="{{ asset('css/menu.css') }}">
    <script src="{{ asset('js/menu.js') }}" defer></script>
</head>
<body>
    <header class="topbar">
        <a href="{{ url('/') }}" class="brand">
            <span class="brand-mark">PS</span>
            <span class="brand-copy">
                <strong>PASSENGER SEAT</strong>
                <small>RESTAURANT · BOARDING NOW</small>
            </span>
        </a>

        <nav class="top-nav">
            <a href="{{ url('/menu') }}" class="nav-link active">Menu</a>
            <a href="{{ url('/cart') }}" class="cart-button">
                <span>Cart</span>
                <span class="cart-count" id="headerCartCount">0</span>
            </a>
        </nav>
    </header>

    <main>
        <section class="hero">
            <div class="hero-content">
                <div class="eyebrow">
                    <span class="status-dot"></span>
                    ONLINE ORDERING IS OPEN
                </div>

                <h1>Your next food destination
                    <span>is ready for boarding.</span>
                </h1>

                <p class="hero-description">
                    Explore Filipino-inspired favorites, satisfying rice meals,
                    refreshing drinks, and sides made for sharing.
                    Choose your meal and we’ll prepare your order.
                </p>

                <div class="hero-actions">
                    <a href="#flight-menu" class="primary-button">
                        Explore the menu <span aria-hidden="true">↗</span>
                    </a>
                    <span class="hero-note">Dine-in & Take-out · Pay at the counter</span>
                </div>

                <div class="hero-stats">
                    <div>
                        <strong>{{ $menuItems->count() }}</strong>
                        <span>Available choices</span>
                    </div>
                    <div class="stat-divider"></div>
                    <div>
                        <strong>Fresh</strong>
                        <span>Made to order</span>
                    </div>
                    <div class="stat-divider"></div>
                    <div>
                        <strong>Easy</strong>
                        <span>Counter payment</span>
                    </div>
                </div>
            </div>

            <div class="boarding-pass">
                <div class="pass-top">
                    <div>
                        <span class="pass-label">PASSENGER MEAL PASS</span>
                        <strong>PSR · 001</strong>
                    </div>
                    <span class="plane-icon" aria-hidden="true">✈</span>
                </div>

                <div class="pass-route">
                    <div>
                        <span class="pass-label">SERVICE</span>
                        <strong>Dine-in / Take-out</strong>
                    </div>
                    <div class="route-line"><span></span></div>
                    <div class="route-plane" aria-hidden="true">✈</div>
                </div>

                <div class="pass-details">
                    <div>
                        <span class="pass-label">PAYMENT</span>
                        <strong>At the counter</strong>
                    </div>
                    <div>
                        <span class="pass-label">ORDERING</span>
                        <strong>Online</strong>
                    </div>
                </div>

                <div class="barcode" aria-hidden="true"></div>
                <p class="pass-footer">
                    Select your items, review your cart, and submit your order
                    to receive a reference number.
                </p>
            </div>

            <div class="hero-decoration decoration-one"></div>
            <div class="hero-decoration decoration-two"></div>
        </section>

        <section class="menu-section" id="flight-menu">
            <div class="section-heading">
                <div>
                    <div class="eyebrow section-eyebrow">DEPARTURE BOARD</div>
                    <h2>Explore the menu<span class="heading-period">.</span></h2>
                    <p>Fresh choices for every passenger.</p>
                </div>
                <div class="menu-total">
                    <span class="total-indicator"></span>
                    <span>{{ $menuItems->count() }} available menu items</span>
                </div>
            </div>

            <div class="filter-bar" role="group" aria-label="Filter menu">
                <button class="filter-chip active" data-filter="all" type="button">
                    All flights <span>{{ $menuItems->count() }}</span>
                </button>
                @php
                    $categories = $menuItems->pluck('category')
                        ->filter()
                        ->unique()
                        ->sort()
                        ->values();
                @endphp

                @foreach ($categories as $category)
                    <button class="filter-chip"
                            data-filter="{{ \Illuminate\Support\Str::slug($category) }}"
                            type="button">
                        {{ $category }}
                        <span>{{ $menuItems->where('category', $category)->count() }}</span>
                    </button>
                @endforeach
            </div>

            <div class="menu-grid" id="menuGrid">
                @forelse ($menuItems as $item)
                    @php
                        $categoryName = $item->category ?? 'Favorites';
                        $slug = \Illuminate\Support\Str::slug($categoryName);

                        $descriptions = [
                            'Calamansi Juice' => 'Fresh calamansi over ice.',
                            'Iced Tea' => 'House-brewed, lightly sweet.',
                            'Beef Tapa' => 'Garlic-cured beef strips with egg and garlic rice.',
                            'Chicken Inasal' => 'Grilled chicken marinated in calamansi and annatto, served with rice.',
                            'Fries' => 'Crispy fries with house dip.',
                            'Garlic Rice' => 'Freshly fried rice with toasted garlic.',
                        ];

                        $description = $item->description
                            ?? ($descriptions[$item->name] ?? 'A Passenger Seat favorite, prepared fresh for you.');

                        $images = [
                            'Calamansi Juice' => 'photo-1513558161293-cdaf765edfd7',
                            'Iced Tea' => 'photo-1556679343-c7306c1976bc',
                            'Beef Tapa' => 'photo-1544025162-d76694265947',
                            'Chicken Inasal' => 'photo-1532550907401-a500c9a57435',
                            'Fries' => 'photo-1573080496219-bb080dd4f877',
                            'Garlic Rice' => 'photo-1512058564366-18510be2db19',
                        ];

                        $imageUrl = $images[$item->name] ?? null;
                    @endphp

                    <article class="menu-card"
                             data-category="{{ $slug }}"
                             data-name="{{ strtolower($item->name) }}">
                        <div class="food-image-wrap">
                            @if ($imageUrl)
                                <img
                                    class="food-image"
                                    src="https://images.unsplash.com/{{ $imageUrl }}?auto=format&fit=crop&w=800&q=85"
                                    alt="{{ $item->name }}"
                                    loading="lazy"
                                    onerror="this.style.display='none'; this.nextElementSibling.style.display='grid';"
                                >
                            @endif

                            <div class="food-placeholder" @if($imageUrl) style="display:none" @endif>
                                <span>PS</span>
                            </div>

                            <span class="availability-badge">
                                <span></span> Available
                            </span>

                            <span class="food-category">{{ $categoryName }}</span>
                        </div>

                        <div class="food-content">
                            <div class="food-title-row">
                                <h3>{{ $item->name }}</h3>
                                <span class="food-price">
                                    ₱{{ number_format((float) $item->price, 2) }}
                                </span>
                            </div>

                            <p class="food-description">{{ $description }}</p>

                            <div class="food-card-footer">
                                <span class="fresh-label">
                                    <span aria-hidden="true">✦</span> Passenger favorite
                                </span>

                                <form action="{{ url('/cart/add/' . $item->id) }}"
                                      method="POST"
                                      class="add-to-cart-form">
                                    @csrf
                                    <input type="hidden" name="quantity" value="1">
                                    <button class="add-button" type="submit">
                                        <span aria-hidden="true">+</span> Add to cart
                                    </button>
                                </form>
                            </div>
                        </div>
                    </article>
                @empty
                    <div class="empty-menu">
                        <span class="empty-icon">✈</span>
                        <h3>No meals ready for boarding</h3>
                        <p>There are currently no available menu items. Please check again soon.</p>
                    </div>
                @endforelse
            </div>

            <div class="no-filter-results" id="noFilterResults" hidden>
                <h3>No meals in this category</h3>
                <p>Choose another category to explore more options.</p>
            </div>
        </section>

        <section class="order-notice">
            <div class="notice-icon" aria-hidden="true">✦</div>
            <div>
                <strong>Ready for your next food destination?</strong>
                <p>Add your favorites to the cart, submit your order, and pay at the counter.</p>
            </div>
            <a href="{{ url('/cart') }}" class="notice-link">
                Review cart <span aria-hidden="true">→</span>
            </a>
        </section>
    </main>

    <footer class="site-footer">
        <a href="{{ url('/') }}" class="footer-brand">
            <span class="brand-mark small-mark">PS</span>
            <span>PASSENGER SEAT RESTAURANT</span>
        </a>
        <p>Good food. Smooth journey.</p>
        <span class="footer-payment">PLEASE PROCEED TO THE COUNTER FOR PAYMENT.</span>
    </footer>

    @if (session('success'))
        <div class="toast toast-success" role="status">{{ session('success') }}</div>
    @endif

    @if (session('error'))
        <div class="toast toast-error" role="alert">{{ session('error') }}</div>
    @endif

    @if ($errors->any())
        <div class="toast toast-error" role="alert">
            {{ $errors->first() }}
        </div>
    @endif
</body>
</html>
```