<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    @include('partials.head', ['title' => trim($__env->yieldContent('title', 'Admin'))])
    <meta name="robots" content="noindex,nofollow">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700&display=swap" rel="stylesheet">
    @vite(['resources/css/admin.css', 'resources/css/admin-pos.css', 'resources/css/admin-inventory.css', 'resources/js/admin.js'])
</head>
<body>
    @php
        $user = auth()->user();
        $currentRoute = request()->route()?->getName() ?? '';
        $brand = $adminSettings['restaurant_name'] ?? 'Passenger Seat';
        $tagline = $adminSettings['tagline'] ?? 'Restaurant Operations';
        $alerts = $adminAlerts ?? collect();
        $unread = $adminUnreadCount ?? 0;
    @endphp

    <div class="admin-shell shell">
        <div class="admin-overlay" id="adminOverlay"></div>

        <aside class="admin-sidebar side" id="adminSidebar">
            <div class="admin-sidebar-header brand" role="img" aria-label="{{ $brand }}">
                <img class="logo" src="{{ asset('images/landing-logo.png') }}" alt="">
            </div>

            <nav class="admin-sidebar-nav" id="nav" aria-label="Admin Navigation">
                <div class="admin-nav-group grp">
                    <span class="admin-group-label">Main</span>
                    <a href="{{ route('admin.dashboard') }}" class="admin-nav-item nav {{ str_starts_with($currentRoute, 'admin.dashboard') || $currentRoute === 'admin.dashboard' ? 'active on' : '' }}">
                        <svg viewBox="0 0 24 24" fill="none" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round">
                            <rect x="3" y="3" width="7" height="7" rx="1.5"/>
                            <rect x="14" y="3" width="7" height="7" rx="1.5"/>
                            <rect x="14" y="14" width="7" height="7" rx="1.5"/>
                            <rect x="3" y="14" width="7" height="7" rx="1.5"/>
                        </svg>
                        <span>Dashboard</span>
                    </a>
                </div>

                <div class="admin-nav-group grp">
                    <span class="admin-group-label">Restaurant operations</span>

                    <a href="{{ route('admin.orders') }}" class="admin-nav-item nav {{ $currentRoute === 'admin.orders' ? 'active on' : '' }}">
                        <svg viewBox="0 0 24 24" fill="none" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round">
                            <line x1="8" y1="6" x2="21" y2="6"/>
                            <line x1="8" y1="12" x2="21" y2="12"/>
                            <line x1="8" y1="18" x2="21" y2="18"/>
                            <circle cx="4" cy="6" r="1.5" fill="currentColor"/>
                            <circle cx="4" cy="12" r="1.5" fill="currentColor"/>
                            <circle cx="4" cy="18" r="1.5" fill="currentColor"/>
                        </svg>
                        <span>Order management</span>
                        @if(($orderBadge ?? 0) > 0)
                            <span class="admin-nav-pill-badge">{{ $orderBadge }}</span>
                        @endif
                    </a>

                    <a href="{{ route('admin.pos') }}" class="admin-nav-item nav {{ $currentRoute === 'admin.pos' ? 'active on' : '' }}">
                        <svg viewBox="0 0 24 24" fill="none" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round">
                            <rect x="2" y="4" width="20" height="16" rx="2"/>
                            <line x1="2" y1="10" x2="22" y2="10"/>
                        </svg>
                        <span>Register (POS)</span>
                    </a>

                    <a href="{{ route('admin.billing') }}" class="admin-nav-item nav {{ $currentRoute === 'admin.billing' ? 'active on' : '' }}">
                        <svg viewBox="0 0 24 24" fill="none" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round">
                            <rect x="3" y="4" width="18" height="16" rx="2"/>
                            <path d="M7 9h10M7 13h6"/>
                            <path d="M8 2v4m8-4v4"/>
                        </svg>
                        <span>Billing</span>
                        @if(($adminBillRequestCount ?? 0) > 0)
                            <span class="admin-nav-pill-badge">{{ $adminBillRequestCount }}</span>
                        @endif
                    </a>

                    <a href="{{ route('admin.table-qr') }}" class="admin-nav-item nav {{ $currentRoute === 'admin.table-qr' ? 'active on' : '' }}">
                        <svg viewBox="0 0 24 24" fill="none" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round">
                            <rect x="3" y="3" width="7" height="7"/>
                            <rect x="14" y="3" width="7" height="7"/>
                            <rect x="3" y="14" width="7" height="7"/>
                            <path d="M14 14h3v3h-3zm4 4h3v3h-3zm0-4h3"/>
                        </svg>
                        <span>Table QR control</span>
                    </a>

                    <a href="{{ route('admin.menu-management') }}" class="admin-nav-item nav {{ $currentRoute === 'admin.menu-management' ? 'active on' : '' }}">
                        <svg viewBox="0 0 24 24" fill="none" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round">
                            <path d="M18 2v6a3 3 0 0 1-3 3 3 3 0 0 1-3-3V2"/>
                            <path d="M15 11v11"/>
                            <path d="M5 2v20"/>
                            <path d="M9 2v4a2 2 0 0 1-2 2H5"/>
                        </svg>
                        <span>Menu management</span>
                    </a>

                    <a href="{{ route('admin.inventory') }}" class="admin-nav-item nav {{ $currentRoute === 'admin.inventory' ? 'active on' : '' }}">
                        <svg viewBox="0 0 24 24" fill="none" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round">
                            <path d="M21 16V8a2 2 0 0 0-1-1.73l-7-4a2 2 0 0 0-2 0l-7 4A2 2 0 0 0 3 8v8a2 2 0 0 0 1 1.73l7 4a2 2 0 0 0 2 0l7-4A2 2 0 0 0 21 16z"/>
                            <polyline points="3.27 6.96 12 12.01 20.73 6.96"/>
                            <line x1="12" y1="22.08" x2="12" y2="12"/>
                        </svg>
                        <span>Inventory</span>
                        @if(($lowStockCount ?? 0) > 0)
                            <span class="admin-nav-pill-badge">{{ $lowStockCount }}</span>
                        @endif
                    </a>

                    <a href="{{ route('admin.recipes') }}" class="admin-nav-item nav {{ $currentRoute === 'admin.recipes' ? 'active on' : '' }}">
                        <svg viewBox="0 0 24 24" fill="none" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round">
                            <path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/>
                            <polyline points="14 2 14 8 20 8"/>
                            <line x1="16" y1="13" x2="8" y2="13"/>
                            <line x1="16" y1="17" x2="8" y2="17"/>
                        </svg>
                        <span>Recipe & food cost</span>
                    </a>

                    <a href="{{ route('admin.barcode') }}" class="admin-nav-item nav {{ $currentRoute === 'admin.barcode' ? 'active on' : '' }}">
                        <svg viewBox="0 0 24 24" fill="none" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round">
                            <rect x="3" y="3" width="7" height="7"/>
                            <rect x="14" y="3" width="7" height="7"/>
                            <rect x="3" y="14" width="7" height="7"/>
                            <line x1="14" y1="14" x2="14" y2="21"/>
                            <line x1="18" y1="14" x2="18" y2="21"/>
                            <line x1="21" y1="14" x2="21" y2="21"/>
                        </svg>
                        <span>Scan package</span>
                    </a>

                    <a href="{{ route('admin.purchase-orders') }}" class="admin-nav-item nav {{ $currentRoute === 'admin.purchase-orders' ? 'active on' : '' }}">
                        <svg viewBox="0 0 24 24" fill="none" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round">
                            <path d="M4 4h16v16H4z"/>
                            <path d="M8 8h8M8 12h8M8 16h5"/>
                        </svg>
                        <span>Purchase orders</span>
                    </a>

                    <a href="{{ route('admin.lots') }}" class="admin-nav-item nav {{ $currentRoute === 'admin.lots' ? 'active on' : '' }}">
                        <svg viewBox="0 0 24 24" fill="none" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round">
                            <path d="M12 3 3 8l9 5 9-5-9-5z"/>
                            <path d="M3 12l9 5 9-5M3 16l9 5 9-5"/>
                        </svg>
                        <span>Lots &amp; traceability</span>
                    </a>

                    <a href="{{ route('admin.waste') }}" class="admin-nav-item nav {{ $currentRoute === 'admin.waste' ? 'active on' : '' }}">
                        <svg viewBox="0 0 24 24" fill="none" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round">
                            <polyline points="3 6 5 6 21 6"/>
                            <path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"/>
                        </svg>
                        <span>Waste</span>
                    </a>
                </div>

                <div class="admin-nav-group grp">
                    <span class="admin-group-label">Business intelligence</span>

                    <a href="{{ route('admin.analytics') }}" class="admin-nav-item nav {{ $currentRoute === 'admin.analytics' ? 'active on' : '' }}">
                        <svg viewBox="0 0 24 24" fill="none" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round">
                            <line x1="18" y1="20" x2="18" y2="10"/>
                            <line x1="12" y1="20" x2="12" y2="4"/>
                            <line x1="6" y1="20" x2="6" y2="14"/>
                        </svg>
                        <span>Analytics & reports</span>
                    </a>

                    <a href="{{ route('admin.forecasting') }}" class="admin-nav-item nav {{ $currentRoute === 'admin.forecasting' ? 'active on' : '' }}">
                        <svg viewBox="0 0 24 24" fill="none" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round">
                            <polyline points="23 6 13.5 15.5 8.5 10.5 1 18"/>
                            <polyline points="17 6 23 6 23 12"/>
                        </svg>
                        <span>Predictive forecasting</span>
                    </a>
                    <a href="{{ route('admin.slow-moving') }}" class="admin-nav-item nav {{ $currentRoute === 'admin.slow-moving' ? 'active on' : '' }}">
                        <svg viewBox="0 0 24 24" fill="none" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round">
                            <polyline points="3 17 9 11 13 15 21 7"/>
                            <polyline points="15 7 21 7 21 13"/>
                        </svg>
                        <span>Slow-Moving Items</span>
                    </a>
                    <a href="{{ route('admin.archived') }}" class="admin-nav-item nav {{ $currentRoute === 'admin.archived' ? 'active on' : '' }}">
                        <svg viewBox="0 0 24 24" fill="none" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round">
                            <path d="M3 7h18v14H3zM2 3h8l2 4M7 12h10"/>
                        </svg>
                        <span>Archived Products</span>
                    </a>
                </div>

                <div class="admin-nav-group grp">
                    <span class="admin-group-label">Customer services</span>

                    <a href="{{ route('admin.reservations') }}" class="admin-nav-item nav {{ $currentRoute === 'admin.reservations' ? 'active on' : '' }}">
                        <svg viewBox="0 0 24 24" fill="none" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round">
                            <rect x="3" y="4" width="18" height="18" rx="2" ry="2"/>
                            <line x1="16" y1="2" x2="16" y2="6"/>
                            <line x1="8" y1="2" x2="8" y2="6"/>
                            <line x1="3" y1="10" x2="21" y2="10"/>
                        </svg>
                        <span>Reservations & events</span>
                    </a>

                    <a href="{{ route('admin.chatbot') }}" class="admin-nav-item nav {{ $currentRoute === 'admin.chatbot' ? 'active on' : '' }}">
                        <svg viewBox="0 0 24 24" fill="none" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round">
                            <path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"/>
                        </svg>
                        <span>AI chatbot & knowledge base</span>
                    </a>
                </div>

                <div class="admin-nav-group grp">
                    <span class="admin-group-label">Administration</span>

                    <a href="{{ route('admin.users') }}" class="admin-nav-item nav {{ $currentRoute === 'admin.users' ? 'active on' : '' }}">
                        <svg viewBox="0 0 24 24" fill="none" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round">
                            <path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/>
                            <circle cx="12" cy="7" r="4"/>
                        </svg>
                        <span>User & role management</span>
                    </a>

                    <a href="{{ route('admin.notifications') }}" class="admin-nav-item nav {{ $currentRoute === 'admin.notifications' ? 'active on' : '' }}">
                        <svg viewBox="0 0 24 24" fill="none" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round">
                            <path d="M18 8A6 6 0 0 0 6 8c0 7-3 9-3 9h18s-3-2-3-9"/>
                            <path d="M13.73 21a2 2 0 0 1-3.46 0"/>
                        </svg>
                        <span>Notifications & alerts</span>
                        @if($unread > 0)
                            <span class="admin-nav-pill-badge" id="adminNotifyNavBadge">{{ $unread }}</span>
                        @endif
                    </a>

                    <a href="{{ route('admin.activity') }}" class="admin-nav-item nav {{ $currentRoute === 'admin.activity' ? 'active on' : '' }}">
                        <svg viewBox="0 0 24 24" fill="none" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round">
                            <circle cx="12" cy="12" r="10"/>
                            <polyline points="12 6 12 12 16 14"/>
                        </svg>
                        <span>Activity logs</span>
                    </a>

                    <a href="{{ route('admin.settings') }}" class="admin-nav-item nav {{ $currentRoute === 'admin.settings' ? 'active on' : '' }}">
                        <svg viewBox="0 0 24 24" fill="none" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round">
                            <circle cx="12" cy="12" r="3"/>
                            <path d="M19.4 15a1.65 1.65 0 0 0 .33 1.82l.06.06a2 2 0 0 1 0 2.83 2 2 0 0 1-2.83 0l-.06-.06a1.65 1.65 0 0 0-1.82-.33 1.65 1.65 0 0 0-1 1.51V21a2 2 0 0 1-4 0v-.09A1.65 1.65 0 0 0 9 19.4a1.65 1.65 0 0 0-1.82.33l-.06.06a2 2 0 0 1-2.83 0 2 2 0 0 1 0-2.83l.06-.06a1.65 1.65 0 0 0 .33-1.82 1.65 1.65 0 0 0-1.51-1H3a2 2 0 0 1 0-4h.09A1.65 1.65 0 0 0 4.6 9a1.65 1.65 0 0 0-.33-1.82l-.06-.06a2 2 0 0 1 2.83-2.83l.06.06a1.65 1.65 0 0 0 1.82.33H9a1.65 1.65 0 0 0 1-1.51V3a2 2 0 0 1 4 0v.09a1.65 1.65 0 0 0 1 1.51 1.65 1.65 0 0 0 1.82-.33l.06-.06a2 2 0 0 1 2.83 2.83l-.06.06a1.65 1.65 0 0 0-.33 1.82V9a1.65 1.65 0 0 0 1.51 1H21a2 2 0 0 1 0 4h-.09a1.65 1.65 0 0 0-1.51 1z"/>
                        </svg>
                        <span>System settings</span>
                    </a>
                </div>
            </nav>

            <div class="admin-sidebar-footer">
                <div class="admin-footer-user-row">
                <div class="admin-footer-user">
                    <div class="admin-avatar">
                        {{ strtoupper(substr($user->name ?? 'AD', 0, 2)) }}
                    </div>
                    <div class="admin-footer-user-details">
                        <span class="admin-footer-user-name">{{ $user ? $user->name : 'Administrator' }}</span>
                        <span class="admin-footer-user-role">Super Admin</span>
                    </div>
                </div>

                <form method="POST" action="{{ route('staff.logout') }}" style="margin:0;">
                    @csrf
                    <button type="submit" class="admin-signout-btn" title="Sign out" aria-label="Sign out">
                        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                            <path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"/>
                            <polyline points="16 17 21 12 16 7"/>
                            <line x1="21" y1="12" x2="9" y2="12"/>
                        </svg>
                    </button>
                </form>
                </div>
            </div>
        </aside>

        <div class="admin-main-wrap">
            <header class="admin-topbar">
                <div class="admin-topbar-leading">
                    <button class="admin-mobile-toggle btn" id="adminSidebarToggle" type="button" aria-label="Open sidebar" aria-expanded="false" aria-controls="adminSidebar">
                        <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round">
                            <line x1="3" y1="6" x2="21" y2="6"/>
                            <line x1="3" y1="12" x2="21" y2="12"/>
                            <line x1="3" y1="18" x2="21" y2="18"/>
                        </svg>
                    </button>
                    <div class="admin-topbar-title-wrap">
                        <span class="admin-topbar-heading">@yield('page-title', 'Dashboard')</span>
                        <span class="admin-topbar-breadcrumb">{{ $brand }} / @yield('page-title', 'Dashboard')</span>
                    </div>
                </div>

                <div class="admin-topbar-actions">
                    <form class="admin-search-box search" method="GET" action="{{ route('admin.orders') }}">
                        <input type="text" name="q" value="{{ request('q') }}" placeholder="Search orders, items...">
                        <button type="submit" aria-label="Search orders and items">
                            <svg viewBox="0 0 24 24" aria-hidden="true"><circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/></svg>
                        </button>
                    </form>

                    <div class="admin-notify-wrap">
                        <button class="admin-icon-btn round" type="button" id="adminNotifyBtn" title="Notifications" aria-label="Notifications" aria-expanded="false" aria-controls="adminNotifyPanel">
                            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M18 8A6 6 0 0 0 6 8c0 7-3 9-3 9h18s-3-2-3-9"/><path d="M13.73 21a2 2 0 0 1-3.46 0"/></svg>
                            <span class="admin-notify-badge" id="adminNotifyBadge" @if($unread < 1) hidden @endif>{{ $unread }}</span>
                        </button>

                        <div class="admin-notify-panel" id="adminNotifyPanel" hidden>
                            <div class="admin-notify-head">
                                <strong>Notifications</strong>
                                <button type="button" class="admin-notify-markall" id="adminNotifyMarkAll" data-mark-all-read data-url="{{ route('admin.notifications.read-all') }}">Mark all read</button>
                            </div>
                            <div class="admin-notify-list" id="adminNotifyList">
                                @forelse($alerts as $alert)
                                    <div class="admin-notify-item {{ $alert['unread'] ? 'unread' : '' }}" data-key="{{ $alert['key'] }}">
                                        <div class="admin-notify-item-top">
                                            <span class="admin-notify-type">{{ $alert['title'] }}</span>
                                            <span class="admin-notify-time">{{ $alert['time'] }}</span>
                                        </div>
                                        <p>{{ $alert['body'] }}</p>
                                        <div class="admin-notify-item-actions">
                                            <a href="{{ $alert['href'] }}">Open</a>
                                            @if($alert['unread'])
                                                <button type="button" class="admin-notify-read" data-key="{{ $alert['key'] }}" data-url="{{ route('admin.notifications.read') }}">Mark read</button>
                                            @endif
                                        </div>
                                    </div>
                                @empty
                                    <p class="admin-notify-empty">No alerts right now.</p>
                                @endforelse
                            </div>
                            <a class="admin-notify-foot" href="{{ route('admin.notifications') }}">View all alerts</a>
                        </div>
                    </div>

                    <div class="tabs admin-theme-tabs" role="group" aria-label="Color mode">
                        <button type="button" data-admin-theme="light" aria-label="Light theme">
                            <svg viewBox="0 0 24 24" aria-hidden="true"><circle cx="12" cy="12" r="4"/><path d="M12 2v2m0 16v2M4.93 4.93l1.42 1.42m11.3 11.3 1.42 1.42M2 12h2m16 0h2M4.93 19.07l1.42-1.42m11.3-11.3 1.42-1.42"/></svg>
                            <span>Light</span>
                        </button>
                        <button type="button" data-admin-theme="dark" aria-label="Dark theme">
                            <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M21 12.8A9 9 0 1 1 11.2 3a7 7 0 0 0 9.8 9.8z"/></svg>
                            <span>Dark</span>
                        </button>
                    </div>
                </div>
            </header>

            <main class="admin-content-area" id="view">
                <p class="admin-client-status" id="adminClientStatus" role="status" aria-live="polite" hidden></p>
                <x-flash />
                @yield('content')
            </main>
        </div>
    </div>
</body>
</html>
