<!doctype html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <meta name="robots" content="noindex,nofollow">
    <title>Barcode receiving · Passenger Seat</title>
    @include('partials.theme-init')
    @vite(['resources/css/app-theme.css', 'resources/js/app-theme.js'])
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="{{ asset('css/inventory.css') }}">
</head>
<body>
    <header class="top">
        <div>
            <h1 id="title">Barcode receiving</h1>
            <div class="crumb" id="crumb">Passenger Seat / Inventory</div>
        </div>
        <div class="who">
            <x-theme-toggle class="btn" />
            <div class="notification-wrap">
                <button class="bell" type="button" data-notification-toggle aria-controls="inventoryNotifications" aria-expanded="false" aria-label="View {{ $lowStockCount }} low-stock alerts" title="View low-stock alerts">
                    &#9888;<i>{{ $lowStockCount }}</i>
                </button>
                <div class="notification-menu" id="inventoryNotifications" role="region" aria-label="Inventory notifications" hidden>
                    <h2>Notifications</h2>
                    <div class="notification-list">
                        @php $hasNotifications = false; @endphp
                        @foreach ($ingredients as $ingredient)
                            @if ((float) $ingredient->current_stock <= 0)
                                @php $hasNotifications = true; @endphp
                                <div class="notification-item notification-critical">
                                    <span class="notification-dot" aria-hidden="true"></span>
                                    <span><strong>Out of Stock</strong><small>{{ $ingredient->name }} · Restock required</small></span>
                                </div>
                            @elseif ($ingredient->isLowStock())
                                @php $hasNotifications = true; @endphp
                                <div class="notification-item notification-low">
                                    <span class="notification-dot" aria-hidden="true"></span>
                                    <span><strong>Low Stock</strong><small>{{ $ingredient->name }} · {{ number_format((float) $ingredient->current_stock, 3) }} {{ $ingredient->unit }} remaining</small></span>
                                </div>
                            @endif
                        @endforeach
                        @unless ($hasNotifications)
                            <p class="notification-empty">You're all caught up. No stock alerts.</p>
                        @endunless
                    </div>
                </div>
            </div>
            <span class="av" aria-hidden="true">{{ mb_strtoupper(mb_substr(auth()->user()->name, 0, 1)) }}</span>
            <span class="user-name">{{ auth()->user()->name }}<small>Inventory staff</small></span>
            <form method="POST" action="{{ route('staff.logout') }}">
                @csrf
                <button class="btn signout" type="submit">Sign out</button>
            </form>
        </div>
    </header>

    <main id="inventoryApp" data-index-url="{{ route('inventory.index') }}">
        @if (session('success'))
            <div class="msg ok" role="status">{{ session('success') }}</div>
        @endif
        @if ($errors->any())
            <div class="msg bad" role="alert">
                <strong>Please check the form:</strong>
                <ul>
                    @foreach ($errors->all() as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
            </div>
        @endif

        <div class="tabs" id="tabs" role="tablist" aria-label="Inventory sections">
            <button type="button" role="tab" id="tab-in" aria-controls="scanView" data-t="in" aria-selected="true">Receive</button>
            <button type="button" role="tab" id="tab-out" aria-controls="scanView" data-t="out" aria-selected="false">Stock out</button>
            <button type="button" role="tab" id="tab-count" aria-controls="countView" data-t="count" aria-selected="false">Count and waste</button>
            <button type="button" role="tab" id="tab-log" aria-controls="logView" data-t="log" aria-selected="false">History</button>
            <button type="button" role="tab" id="tab-inv" aria-controls="invView" data-t="inv" aria-selected="false">Inventory</button>
        </div>

        <section id="scanView" role="tabpanel" aria-labelledby="tab-in">
            <div class="card scanner">
                <h2 id="scanTitle">Scan package</h2>
                <div class="cam">
                    <video id="vid" playsinline muted class="hide"></video>
                    <div class="frame" id="frame" aria-hidden="true"></div>
                    <div class="hint" id="hint">Point the rear camera at an ingredient barcode</div>
                </div>
                <div class="btns">
                    <button class="btn red" id="camBtn" type="button">Start camera</button>
                    <button class="btn" id="sample" type="button" @disabled($ingredients->isEmpty())>Try sample scan</button>
                </div>
                <form class="manual" id="manual">
                    <input id="code" autocomplete="off" aria-label="Package barcode or ingredient code" placeholder="Scan package UPC/EAN or an ING-#### code">
                    <button class="btn" type="submit">Look up</button>
                </form>
                <div class="chips" id="chips">
                    <span>Quick select:</span>
                    @forelse ($ingredients->take(8) as $ingredient)
                        <button class="chip" type="button" data-code="ING-{{ str_pad((string) $ingredient->id, 4, '0', STR_PAD_LEFT) }}">{{ $ingredient->name }}</button>
                    @empty
                        <span>No ingredients have been added yet.</span>
                    @endforelse
                </div>
                <p class="msg" id="msg" role="status" aria-live="polite">Waiting for a barcode. Stock changes only after you confirm.</p>

                <div class="pkg hide" id="barcodeMapping">
                    <h3>Register this package barcode</h3>
                    <p id="unmappedBarcode"></p>
                    <form method="POST" action="{{ route('staff.inventory.barcode') }}" class="stack">
                        @csrf
                        <input type="hidden" id="unmappedBarcodeValue" name="barcode">
                        <label>Ingredient
                            <select name="ingredient_id" required>
                                <option value="">Choose the matching ingredient</option>
                                @foreach ($ingredients as $ingredient)
                                    <option value="{{ $ingredient->id }}">{{ $ingredient->name }}</option>
                                @endforeach
                            </select>
                        </label>
                        <button class="btn red" type="submit" @disabled($ingredients->isEmpty())>Save barcode mapping</button>
                    </form>
                </div>

                <div class="pkg hide" id="pkg">
                    <h3 id="pName"></h3>
                    <p id="pMeta"></p>
                    <p id="pCur"></p>
                    <label class="qty" for="pQtyInput">
                        <span id="pQtyLabel">Quantity</span>
                        <input id="pQtyInput" type="number" min="0.001" step="0.001" value="1" required>
                        <span id="pUnit"></span>
                    </label>
                    <p class="big" id="pAfter"></p>
                    <form method="POST" id="confirmForm" action="{{ route('inventory.index') }}">
                        @csrf
                        <input type="hidden" name="type" id="movementType" value="stock_in">
                        <input type="hidden" name="quantity" id="movementQuantity" value="1">
                        <input type="hidden" name="notes" id="movementNotes">
                        <button class="btn red" id="confirm" type="submit" disabled>Confirm receipt</button>
                        <button class="btn" id="rescan" type="button">Rescan</button>
                    </form>
                </div>
            </div>
        </section>

        <section id="countView" class="hide" role="tabpanel" aria-labelledby="tab-count">
            <div class="grid">
                <div class="card">
                    <h2>Physical count</h2>
                    <p class="mute">Enter the quantity physically counted. Saving records the difference from the current system stock.</p>
                    <div class="wrap">
                        <table>
                            <thead><tr><th>Ingredient</th><th>System</th><th>Counted</th><th>Variance</th><th>Reason / note</th><th></th></tr></thead>
                            <tbody>
                                @forelse ($ingredients as $ingredient)
                                    <tr>
                                        <td>{{ $ingredient->name }}<small class="sub">{{ $ingredient->unit }}</small></td>
                                        <td>{{ number_format((float) $ingredient->current_stock, 3) }}</td>
                                        <td>
                                            <form method="POST" id="count-{{ $ingredient->id }}" action="{{ route('staff.inventory.log', $ingredient) }}">
                                                @csrf
                                                <input type="hidden" name="type" value="adjustment">
                                            </form>
                                            <input form="count-{{ $ingredient->id }}" name="quantity" type="number" min="0" step="0.001" value="{{ $ingredient->current_stock }}" required aria-label="Counted {{ $ingredient->name }}" data-counted>
                                        </td>
                                        <td data-variance data-system="{{ $ingredient->current_stock }}">0.000</td>
                                        <td><input form="count-{{ $ingredient->id }}" name="notes" maxlength="500" placeholder="Optional note" aria-label="Count note for {{ $ingredient->name }}"></td>
                                        <td><button class="btn" form="count-{{ $ingredient->id }}" type="submit">Save count</button></td>
                                    </tr>
                                @empty
                                    <tr><td colspan="6" class="mute">No ingredients to count.</td></tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </div>
                <div class="card">
                    <h2>Record waste</h2>
                    <p class="mute">Waste is recorded as its own stock movement and reduces available stock.</p>
                    <form method="POST" id="wasteForm" action="{{ route('inventory.index') }}" class="stack">
                        @csrf
                        <input type="hidden" name="type" value="waste">
                        <label>Ingredient
                            <select id="wasteIngredient" required>
                                <option value="">Choose ingredient</option>
                                @foreach ($ingredients as $ingredient)
                                    <option value="{{ route('staff.inventory.log', $ingredient) }}">{{ $ingredient->name }} · {{ number_format((float) $ingredient->current_stock, 3) }} {{ $ingredient->unit }}</option>
                                @endforeach
                            </select>
                        </label>
                        <label>Quantity wasted
                            <input name="quantity" type="number" min="0.001" step="0.001" required>
                        </label>
                        <label>Reason
                            <input name="notes" maxlength="500" required placeholder="For example, spoiled or damaged">
                        </label>
                        <button class="btn red" type="submit" @disabled($ingredients->isEmpty())>Record waste</button>
                    </form>
                </div>
            </div>
        </section>

        <section id="logView" class="hide" role="tabpanel" aria-labelledby="tab-log">
            <div class="card">
                <div class="row">
                    <h2>Stock movement history</h2>
                    <label>Movement
                        <select id="lf">
                            <option value="">All movements</option>
                            <option value="stock_in">Received</option>
                            <option value="stock_out">Stock out</option>
                            <option value="waste">Waste</option>
                            <option value="adjustment">Count adjustment</option>
                        </select>
                    </label>
                    <button class="btn" id="lCsv" type="button">Copy CSV</button>
                    <button class="btn" id="lPrint" type="button">Print</button>
                </div>
                <div class="wrap">
                    <table>
                        <thead><tr><th>Time</th><th>Staff</th><th>Movement</th><th>Ingredient</th><th>Change</th><th>Note</th></tr></thead>
                        <tbody id="lBody">
                            @forelse ($logs as $log)
                                @php
                                    $delta = match ($log->type) {
                                        'stock_in' => abs((float) $log->quantity),
                                        'stock_out', 'waste' => -abs((float) $log->quantity),
                                        default => (float) $log->quantity,
                                    };
                                    $movementLabel = match ($log->type) {
                                        'stock_in' => 'Received',
                                        'stock_out' => 'Stock out',
                                        'waste' => 'Waste',
                                        'adjustment' => 'Count adjustment',
                                        default => str_replace('_', ' ', ucfirst($log->type)),
                                    };
                                @endphp
                                <tr data-type="{{ $log->type }}">
                                    <td data-sort="{{ $log->created_at->timestamp }}">{{ $log->created_at->format('M d, Y g:i A') }}</td>
                                    <td>{{ $log->user?->name ?? 'System' }}</td>
                                    <td>{{ $movementLabel }}</td>
                                    <td>{{ $log->ingredient?->name ?? 'Deleted ingredient' }}</td>
                                    <td>{{ $delta > 0 ? '+' : '' }}{{ number_format($delta, 3) }} {{ $log->ingredient?->unit }}</td>
                                    <td>{{ $log->notes }}</td>
                                </tr>
                            @empty
                                <tr><td colspan="6" class="mute">No stock movements recorded yet.</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
                <p class="mute">Showing the latest {{ $logs->count() }} stock movements.</p>
            </div>
        </section>

        <section id="invView" class="hide" role="tabpanel" aria-labelledby="tab-inv">
            <div class="ovg" id="ov">
                <div class="ov"><b>{{ $ingredients->count() }}</b>Ingredients</div>
                <div class="ov"><b>{{ $lowStockCount }}</b>Low stock</div>
                <div class="ov"><b>{{ $outOfStockCount }}</b>Out of stock</div>
                <div class="ov"><b>{{ $logs->count() }}</b>Recent movements</div>
            </div>

            <div class="card" id="alerts" tabindex="-1" aria-label="Inventory alerts">
                <h2>Alerts and restocking</h2>
                <div class="al">
                    @php $hasAlerts = false; @endphp
                    @foreach ($ingredients as $ingredient)
                        @if ((float) $ingredient->current_stock <= 0)
                            @php $hasAlerts = true; @endphp
                            <div class="c">&#9888; Critical: {{ $ingredient->name }} is out of stock</div>
                        @elseif ($ingredient->isLowStock())
                            @php $hasAlerts = true; @endphp
                            <div class="w">&#9888; {{ $ingredient->name }} is below its minimum ({{ number_format((float) $ingredient->current_stock, 3) }} of {{ number_format((float) $ingredient->min_threshold, 3) }} {{ $ingredient->unit }})</div>
                        @endif
                    @endforeach
                    @unless ($hasAlerts)
                        <div class="i">No alerts. All stock is above its minimum.</div>
                    @endunless
                </div>
            </div>

            <div class="card">
                <div class="row">
                    <h2>Ingredient directory</h2>
                    <div class="fl">
                        <input id="iq" type="search" placeholder="Search name or code" aria-label="Search ingredients">
                        <select id="is" aria-label="Filter by stock status">
                            <option value="">All statuses</option>
                            <option value="OK">OK</option>
                            <option value="Low">Low</option>
                            <option value="Out">Out</option>
                        </select>
                    </div>
                </div>
                <div class="wrap">
                    <table>
                        <thead><tr><th>Ingredient</th><th>Category</th><th>Barcode</th><th>Stock</th><th>Min</th><th>Status</th><th>Settings</th></tr></thead>
                        <tbody id="invBody">
                            @forelse ($ingredients as $ingredient)
                                @php
                                    $status = (float) $ingredient->current_stock <= 0 ? 'Out' : ($ingredient->isLowStock() ? 'Low' : 'OK');
                                    $code = 'ING-'.str_pad((string) $ingredient->id, 4, '0', STR_PAD_LEFT);
                                @endphp
                                <tr
                                    data-id="{{ $ingredient->id }}"
                                    data-name="{{ mb_strtolower($ingredient->name) }}"
                                    data-code="{{ mb_strtolower($code) }}"
                                    data-barcode="{{ mb_strtolower((string) $ingredient->barcode) }}"
                                    data-url="{{ route('staff.inventory.log', $ingredient) }}"
                                    data-current="{{ $ingredient->current_stock }}"
                                    data-unit="{{ $ingredient->unit }}"
                                    data-status="{{ $status }}">
                                    <td>{{ $ingredient->name }}</td>
                                    <td class="mute">Not tracked</td>
                                    <td>
                                        @if ($ingredient->barcode)
                                            <code>{{ $ingredient->barcode }}</code>
                                        @else
                                            <span class="mute">Not assigned</span>
                                        @endif
                                        <small class="sub">Internal: {{ $code }}</small>
                                    </td>
                                    <td>{{ number_format((float) $ingredient->current_stock, 3) }} {{ $ingredient->unit }}</td>
                                    <td>{{ number_format((float) $ingredient->min_threshold, 3) }} {{ $ingredient->unit }}</td>
                                    <td><span class="badge {{ $status === 'OK' ? 'b-ok' : ($status === 'Low' ? 'b-w' : 'b-low') }}">{{ $status }}</span></td>
                                    <td>
                                        <details>
                                            <summary class="chip">Edit</summary>
                                            <form method="POST" action="{{ route('staff.inventory.update', $ingredient) }}" class="stack settings-form">
                                                @csrf
                                                @method('PATCH')
                                                <label>Name<input name="name" required maxlength="120" value="{{ $ingredient->name }}"></label>
                                                <label>Package barcode / UPC / EAN<input name="barcode" maxlength="64" value="{{ $ingredient->barcode }}" placeholder="Scan or enter package barcode"></label>
                                                <label>Unit<input name="unit" required maxlength="20" value="{{ $ingredient->unit }}"></label>
                                                <label>Minimum stock<input name="min_threshold" required type="number" min="0" step="0.001" value="{{ $ingredient->min_threshold }}"></label>
                                                <label class="inline"><input type="checkbox" name="manual_override" value="1" @checked($ingredient->manual_override)> Override low-stock alert</label>
                                                <button class="btn" type="submit">Save settings</button>
                                            </form>
                                        </details>
                                    </td>
                                </tr>
                            @empty
                                <tr><td colspan="7" class="mute">No ingredients have been added yet.</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>

            <div class="card">
                <h2>Add ingredient</h2>
                <form method="POST" action="{{ route('staff.inventory.store') }}" class="row">
                    @csrf
                    <label>Name<input name="name" maxlength="120" required value="{{ old('name') }}"></label>
                    <label>Package barcode / UPC / EAN<input name="barcode" maxlength="64" value="{{ old('barcode') }}" placeholder="Optional · scan or enter barcode"></label>
                    <label>Opening stock<input name="current_stock" type="number" min="0" step="0.001" required value="{{ old('current_stock') }}"></label>
                    <label>Unit<input name="unit" maxlength="20" required placeholder="kg, g, L, pcs" value="{{ old('unit') }}"></label>
                    <label>Minimum stock<input name="min_threshold" type="number" min="0" step="0.001" required value="{{ old('min_threshold') }}"></label>
                    <button class="btn red" type="submit">Create ingredient</button>
                </form>
            </div>
        </section>
    </main>

    <div id="toast" role="status" aria-live="polite"></div>
    @vite('resources/js/inventory-scanner.js')
    <script src="{{ asset('js/inventory.js') }}" defer></script>
</body>
</html>
