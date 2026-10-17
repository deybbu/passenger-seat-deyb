<!doctype html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <meta name="robots" content="noindex,nofollow">
    @include('partials.favicon')
    <title>Passenger Seat · Kitchen Inventory</title>
    @include('partials.theme-init')
    @vite(['resources/css/app-theme.css', 'resources/js/app-theme.js', 'resources/js/inventory-scanner.js'])
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="{{ asset('css/inventory.css') }}">
</head>
<body data-role="{{ $isManager ? 'mgr' : 'staff' }}">
<header class="top">
    <div>
        <h1>Passenger Seat</h1>
        <div class="crumb">Kitchen inventory · FEFO</div>
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
                        @if ($ingredient->usableStock() <= 0)
                            @php $hasNotifications = true; @endphp
                            <div class="notification-item notification-critical">
                                <span class="notification-dot" aria-hidden="true"></span>
                                <span><strong>Out of Stock</strong><small>{{ $ingredient->name }} · Restock required</small></span>
                            </div>
                        @elseif ($ingredient->isLowStock())
                            @php $hasNotifications = true; @endphp
                            <div class="notification-item notification-low">
                                <span class="notification-dot" aria-hidden="true"></span>
                                <span><strong>Low Stock</strong><small>{{ $ingredient->name }} · {{ number_format($ingredient->usableStock(), 3) }} {{ $ingredient->unit }} usable</small></span>
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
        <span class="user-name">{{ auth()->user()->name }}<small>{{ $isManager ? 'Manager / Owner' : 'Kitchen Staff' }}</small></span>
        <form method="POST" action="{{ route('staff.logout') }}">
            @csrf
            <button class="btn signout" type="submit">Sign out</button>
        </form>
    </div>
</header>

<main
    id="inventoryApp"
    data-lookup-url="{{ route('staff.inventory.receive.lookup') }}"
    data-preview-url="{{ route('staff.inventory.receive.preview') }}"
    data-confirm-url="{{ route('staff.inventory.receive.confirm') }}"
    data-register-url="{{ route('staff.inventory.packages.store') }}"
    data-initial-tab="{{ old('name') !== null ? 'fresh' : (in_array(request('tab'), ['dash', 'prep', 'rcp', 'log'], true) ? request('tab') : 'rec') }}"
>
    @if (session('success'))
        <div class="msg ok" role="status">{{ session('success') }}</div>
    @endif
    @if (session()->has('waste_estimate'))
        <div class="msg" role="status">
            @if (session('waste_estimate') === null)
                No ingredient cost is recorded, so an estimated waste cost is unavailable.
            @else
                Estimated cost for the recorded waste: ₱{{ number_format((float) session('waste_estimate'), 2) }}.
            @endif
        </div>
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
        <button type="button" role="tab" id="tab-rec" aria-controls="view-rec" data-tab="rec" aria-selected="true">Receive</button>
        <button type="button" role="tab" id="tab-fresh" aria-controls="view-fresh" data-tab="fresh" aria-selected="false">Fresh intake</button>
        <button type="button" role="tab" id="tab-dash" aria-controls="view-dash" data-tab="dash" aria-selected="false">Stock board</button>
        <button type="button" role="tab" id="tab-prep" aria-controls="view-prep" data-tab="prep" aria-selected="false">Prep and waste</button>
        @if ($isManager)
        <button type="button" role="tab" id="tab-rcp" aria-controls="view-rcp" data-tab="rcp" class="mg" aria-selected="false">Recipes and costs</button>
        @endif
        <button type="button" role="tab" id="tab-log" aria-controls="view-log" data-tab="log" aria-selected="false">History</button>
    </div>

    <section id="view-rec" data-view="rec" role="tabpanel" aria-labelledby="tab-rec">
        <div class="card scanner">
            <h2>Scan package</h2>
            <div class="cam">
                <video id="vid" playsinline muted class="hide"></video>
                <div class="frame" id="frame" aria-hidden="true"></div>
                <div class="hint" id="hint">Point the rear camera at the package barcode</div>
            </div>
            <div class="btns">
                <button class="btn red" id="camBtn" type="button">Start camera</button>
                <button class="btn" id="sample" type="button" @disabled($packages->isEmpty())>Try sample scan</button>
            </div>
            <form class="manual" id="manual">
                <input id="code" autocomplete="off" aria-label="Package barcode" placeholder="Type or scan a barcode">
                <button class="btn" type="submit">Look up</button>
            </form>
            <div class="chips" id="chips">
                <span>Sample barcodes:</span>
                @forelse ($packages as $package)
                    <button class="chip" type="button" data-code="{{ $package->barcode }}" data-barcode="{{ $package->barcode }}" data-sample-package>{{ $package->label }} · {{ $package->ingredient->name }}</button>
                @empty
                    <span>No registered package barcodes yet.</span>
                @endforelse
                @foreach ($ingredients->whereNotNull('barcode')->take(8) as $ingredient)
                    <button class="chip" type="button" data-code="{{ $ingredient->barcode }}" data-barcode="{{ $ingredient->barcode }}">Ingredient barcode · {{ $ingredient->name }}</button>
                @endforeach
            </div>
            <div class="msg" id="msg" role="status" aria-live="polite">Waiting for a barcode. Stock changes only after you confirm.</div>

            <section class="pkg hide" id="barcodeMapping" aria-labelledby="mappingTitle">
                <h3 id="mappingTitle">Register this package barcode</h3>
                <p id="unmappedBarcode"></p>
                <form id="packageForm">
                    <input type="hidden" id="unmappedBarcodeValue" name="barcode">
                    <div class="row">
                        <label>Ingredient
                            <select name="ingredient_id" required>
                                <option value="">Choose the matching ingredient</option>
                                @foreach ($ingredients as $ingredient)
                                    <option value="{{ $ingredient->id }}">{{ $ingredient->name }} · {{ $ingredient->unit }}</option>
                                @endforeach
                            </select>
                        </label>
                        <label>Package label
                            <input name="label" required maxlength="120" placeholder="e.g. 1 kg bag">
                        </label>
                        <label>Package quantity (ingredient units)
                            <input name="package_quantity" type="number" min="0.001" step="0.001" required>
                        </label>
                    </div>
                    <button class="btn red" type="submit" @disabled($ingredients->isEmpty())>Register package barcode</button>
                </form>
            </section>

            <section class="pkg hide" id="pkg" aria-labelledby="pName">
                <h3 id="pName"></h3>
                <p id="pMeta"></p>
                <dl>
                    <dt>Current stock</dt><dd id="pCur"></dd>
                    <dt>Package quantity</dt><dd id="pQtyDisplay"></dd>
                    <dt>Expected stock after receipt</dt><dd id="pAfter"></dd>
                </dl>
                <div class="row">
                    <label>Quantity to receive
                        <input id="pQtyInput" type="number" min="0.001" step="0.001" required>
                    </label>
                    <label>Batch number
                        <input id="batchNumber" maxlength="120" required autocomplete="off">
                    </label>
                    <label>Expiry date
                        <input id="expiresAt" type="date" required>
                    </label>
                    <label>Storage zone
                        <select id="storageZone" required>
                            <option value="">Choose a zone</option>
                            <option>Walk-in Freezer</option>
                            <option>Chiller</option>
                            <option>Dry Pantry Room</option>
                        </select>
                    </label>
                </div>
                <p class="mute">The quantity preview is calculated by the server. Scanning alone never changes stock.</p>
                <div class="btns">
                    <button class="btn red" id="confirm" type="button" disabled>Confirm receipt</button>
                    <button class="btn" id="rescan" type="button">Rescan</button>
                </div>
            </section>
        </div>
    </section>

    <section id="view-fresh" data-view="fresh" class="hide" role="tabpanel" aria-labelledby="tab-fresh">
        <div class="card fresh-card">
            <h2>Fresh goods (no barcode)</h2>
            <form method="POST" action="{{ route('staff.inventory.receive.fresh') }}">
                @csrf
                <input type="hidden" name="receipt_token" value="{{ old('receipt_token', $freshReceiptToken) }}">
                <div class="row">
                    <label>Name
                        <input name="name" value="{{ old('name') }}" required maxlength="120" placeholder="e.g. Fresh squid">
                    </label>
                    <label>Category
                        <select name="category" required>
                            <option value="">Choose a category</option>
                            <option @selected(old('category') === 'Seafood')>Seafood</option>
                            <option @selected(old('category') === 'Produce')>Produce</option>
                            <option @selected(old('category') === 'Meat')>Meat</option>
                            <option @selected(old('category') === 'Other')>Other</option>
                        </select>
                    </label>
                    <label>Batch number
                        <input name="batch_number" value="{{ old('batch_number') }}" required maxlength="120">
                    </label>
                </div>
                <div class="row">
                    <label>Quantity
                        <input name="quantity" value="{{ old('quantity') }}" type="number" min="0.001" step="0.001" required>
                    </label>
                    <label>Unit
                        <select name="unit" required>
                            <option value="">Choose a unit</option>
                            <option @selected(old('unit') === 'kg')>kg</option>
                            <option @selected(old('unit') === 'pc')>pc</option>
                        </select>
                    </label>
                    <label>Expires
                        <input name="expires_at" value="{{ old('expires_at') }}" type="date" required>
                    </label>
                </div>
                <div class="row">
                    <label>Zone
                        <select name="storage_zone" required>
                            <option value="">Choose a zone</option>
                            <option @selected(old('storage_zone') === 'Walk-in Freezer')>Walk-in Freezer</option>
                            <option @selected(old('storage_zone') === 'Chiller')>Chiller</option>
                            <option @selected(old('storage_zone') === 'Dry Pantry Room')>Dry Pantry Room</option>
                        </select>
                    </label>
                    <button class="btn red" type="submit">Add to stock</button>
                </div>
            </form>
        </div>
    </section>
    <section id="view-dash" data-view="dash" class="hide" role="tabpanel" aria-labelledby="tab-dash">
        <div class="card">
            <h2>Stock by expiry (oldest used first)</h2>
            <p class="mute">Undated opening or adjustment lots are kept after dated stock. Expired lots are not available for use.</p>
            <div class="al">
                @php $hasExpiryAlerts = false; @endphp
                @foreach ($ingredients as $ingredient)
                    @foreach ($ingredient->stockLots as $lot)
                        @if ($lot->expires_at !== null)
                            @php $daysUntilExpiry = (int) today()->diffInDays($lot->expires_at, false); @endphp
                            @if ($daysUntilExpiry < 0)
                                @php $hasExpiryAlerts = true; @endphp
                                <div>Expired: {{ $ingredient->name }} · batch {{ $lot->batch_number ?: 'Batch not recorded' }} · {{ $lot->remaining_quantity }} {{ $ingredient->unit }} · {{ $lot->expires_at->format('M j, Y') }}</div>
                            @elseif ($daysUntilExpiry <= 3)
                                @php $hasExpiryAlerts = true; @endphp
                                <div class="w">Expires {{ $daysUntilExpiry <= 0 ? 'today' : "in {$daysUntilExpiry} days" }}: {{ $ingredient->name }} · batch {{ $lot->batch_number ?: 'Batch not recorded' }} · {{ $lot->remaining_quantity }} {{ $ingredient->unit }}</div>
                            @endif
                        @endif
                    @endforeach
                @endforeach
                @unless ($hasExpiryAlerts)
                    <div class="w">No expired or near-expiry lots.</div>
                @endunless
            </div>
            <div class="cards">
                @forelse ($ingredients as $ingredient)
                    @php
                        $lots = $ingredient->stockLots;
                        $usableStock = (float) $ingredient->usable_stock;
                    @endphp
                    <article class="ic {{ $lots->isNotEmpty() && $usableStock <= 0 ? 'r' : '' }}">
                        <h3>{{ $ingredient->name }}</h3>
                        <div class="big">{{ number_format((float) $ingredient->current_stock, 3) }} {{ $ingredient->unit }}</div>
                        <p class="mute">Usable unexpired stock: {{ number_format($usableStock, 3) }} {{ $ingredient->unit }}</p>
                        @forelse ($lots as $lot)
                            @php
                                $isExpired = $lot->expires_at !== null && $lot->expires_at->toDateString() < today()->toDateString();
                                $daysUntilExpiry = $lot->expires_at === null ? null : (int) today()->diffInDays($lot->expires_at, false);
                            @endphp
                            <div class="bt {{ $isExpired ? 'r lock' : ($daysUntilExpiry !== null && $daysUntilExpiry <= 3 ? 'y' : '') }}">
                                <span>
                                    {{ $lot->batch_number ?: 'Batch not recorded' }}
                                    · {{ number_format((float) $lot->remaining_quantity, 3) }} {{ $ingredient->unit }}
                                    · {{ $lot->storage_zone ?: 'Zone not recorded' }}
                                    @if ($lot->package)
                                        · {{ $lot->package->label }}
                                    @endif
                                </span>
                                <span>
                                    @if ($isExpired)
                                        EXPIRED · {{ $lot->expires_at->format('M j, Y') }}
                                    @elseif ($lot->expires_at === null)
                                        Expiry not recorded
                                    @else
                                        {{ $daysUntilExpiry }}d · {{ $lot->expires_at->format('M j, Y') }}
                                    @endif
                                </span>
                            </div>
                        @empty
                            <p class="mute">No stock lots recorded.</p>
                        @endforelse
                    </article>
                @empty
                    <p class="mute">No ingredients or stock lots yet.</p>
                @endforelse
            </div>
        </div>
    </section>
    <section id="view-prep" data-view="prep" class="hide" role="tabpanel" aria-labelledby="tab-prep">
        <div class="inventory-grid">
            <article class="card">
                <h2>Raw to prep</h2>
                <p class="mute">Preparation uses configured batch yields and consumes available raw lots by earliest expiry.</p>
                @if ($preparationRecipes->isEmpty())
                    <p class="mute">No preparation recipes are configured yet.</p>
                @else
                    <form method="POST" action="{{ route('staff.inventory.prepare') }}">
                        @csrf
                        <div class="row">
                            <label>
                                Preparation recipe
                                <select name="preparation_recipe_id" required>
                                    @foreach ($preparationRecipes as $recipe)
                                        <option value="{{ $recipe->id }}" @selected(old('preparation_recipe_id') == $recipe->id)>
                                            {{ $recipe->outputIngredient->name }} · yields {{ number_format((float) $recipe->yield_quantity, 3) }} {{ $recipe->outputIngredient->unit }} per batch
                                        </option>
                                    @endforeach
                                </select>
                            </label>
                            <label>
                                Batches
                                <input type="number" name="batches" min="0.001" max="999999" step="0.001" value="{{ old('batches', 1) }}" required>
                            </label>
                            <button class="btn red" type="submit">Log prep</button>
                        </div>
                    </form>
                    <div class="prep-recipes">
                        @foreach ($preparationRecipes as $recipe)
                            <p>
                                <strong>{{ $recipe->outputIngredient->name }}</strong>
                                @foreach ($recipe->lines as $line)
                                    {{ number_format((float) $line->quantity, 3) }} {{ $line->ingredient->unit }} {{ $line->ingredient->name }}@if (! $loop->last), @endif
                                @endforeach
                                → {{ number_format((float) $recipe->yield_quantity, 3) }} {{ $recipe->outputIngredient->unit }} per batch
                            </p>
                        @endforeach
                    </div>
                @endif
            </article>

            <article class="card">
                <h2>Log waste</h2>
                <form method="POST" action="{{ route('staff.inventory.waste') }}">
                    @csrf
                    <div class="row">
                        <label>
                            Item
                            <select name="ingredient_id" required>
                                @foreach ($ingredients as $ingredient)
                                    <option value="{{ $ingredient->id }}" @selected(old('ingredient_id') == $ingredient->id)>
                                        {{ $ingredient->name }} · {{ number_format((float) $ingredient->usable_stock, 3) }} {{ $ingredient->unit }} usable
                                    </option>
                                @endforeach
                            </select>
                        </label>
                        <label>
                            Quantity
                            <input type="number" name="quantity" min="0.001" step="0.001" value="{{ old('quantity') }}" required>
                        </label>
                        <label>
                            Reason
                            <select name="reason" required>
                                @foreach (['Pre-Cook Spoilage', 'Over-Prep Waste', 'Boodle Return', 'Other'] as $reason)
                                    <option value="{{ $reason }}" @selected(old('reason') === $reason)>{{ $reason }}</option>
                                @endforeach
                            </select>
                        </label>
                    </div>
                    <label class="block-label">
                        Reason details
                        <input type="text" name="notes" maxlength="500" value="{{ old('notes') }}" required>
                    </label>
                    <p class="mute">Recorded under {{ auth()->user()->name }}. Stock availability and reserved orders are checked on the server.</p>
                    <button class="btn red" type="submit">Log waste</button>
                </form>
            </article>
        </div>

        @if ($isManager)
            <article class="card manager-panel mg">
                <h2>Preparation recipes · managers</h2>
                <p class="mute">Set raw ingredient quantities and the prepared yield for one batch. Saving an existing prepared item replaces its recipe lines.</p>
                <form method="POST" action="{{ route('staff.inventory.preparation-recipes.store') }}">
                    @csrf
                    <div class="row">
                        <label>
                            Prepared item
                            <select name="output_ingredient_id" required>
                                @foreach ($ingredients as $ingredient)
                                    <option value="{{ $ingredient->id }}" @selected(old('output_ingredient_id') == $ingredient->id)>
                                        {{ $ingredient->name }} · {{ $ingredient->unit }}
                                    </option>
                                @endforeach
                            </select>
                        </label>
                        <label>
                            Yield per batch
                            <input type="number" name="yield_quantity" min="0.001" step="0.001" value="{{ old('yield_quantity') }}" required>
                        </label>
                    </div>
                    <div class="prep-recipe-lines" data-prep-recipe-lines>
                        <div class="row prep-recipe-line" data-prep-recipe-line>
                            <label>
                                Raw ingredient
                                <select name="lines[0][ingredient_id]" required>
                                    @foreach ($ingredients as $ingredient)
                                        <option value="{{ $ingredient->id }}">{{ $ingredient->name }} · {{ $ingredient->unit }}</option>
                                    @endforeach
                                </select>
                            </label>
                            <label>
                                Quantity per batch
                                <input type="number" name="lines[0][quantity]" min="0.001" step="0.001" required>
                            </label>
                            <button class="btn" type="button" data-remove-prep-line aria-label="Remove raw ingredient">Remove</button>
                        </div>
                    </div>
                    <div class="row">
                        <button class="btn" type="button" data-add-prep-line>Add raw ingredient</button>
                        <button class="btn red" type="submit">Save preparation recipe</button>
                    </div>
                </form>
            </article>

            <article class="card manager-panel mg">
                <h2>Loss report (managers)</h2>
                <p class="mute">
                    Latest 100 recorded waste movements · estimated costs use the ingredient cost recorded at the time.
                    All-time known estimated cost: ₱{{ number_format($knownWasteCost, 2) }}.
                    @if ($uncostedWasteCount > 0)
                        {{ $uncostedWasteCount }} movement(s) have no cost recorded.
                    @endif
                </p>
                <div class="table-wrap">
                    <table class="inventory-table">
                        <thead>
                            <tr><th>Time</th><th>Staff</th><th>Ingredient</th><th>Quantity</th><th>Reason</th><th>Estimated cost</th></tr>
                        </thead>
                        <tbody>
                            @forelse ($lossReport as $movement)
                                <tr>
                                    <td>{{ $movement->created_at?->format('M j, Y H:i') }}</td>
                                    <td>{{ $movement->user?->name ?? 'Unknown staff' }}</td>
                                    <td>{{ $movement->ingredient?->name ?? 'Deleted ingredient' }}</td>
                                    <td>{{ number_format(abs((float) $movement->quantity), 3) }} {{ $movement->ingredient?->unit }}</td>
                                    <td>{{ $movement->reason ?? 'Reason not recorded' }}@if ($movement->notes) · {{ $movement->notes }}@endif</td>
                                    <td>{{ $movement->cost_amount === null ? 'Not available' : '₱'.number_format((float) $movement->cost_amount, 2) }}</td>
                                </tr>
                            @empty
                                <tr><td colspan="6" class="mute">No waste logged.</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </article>
        @endif
    </section>
    <section id="view-rcp" data-view="rcp" class="hide" role="tabpanel" aria-labelledby="tab-rcp">
        @if ($isManager)
            <article class="card manager-panel mg">
                <h2>Recipe matrix, costs and par levels</h2>
                <p class="mute">This matrix reads the existing POS recipes. Portion quantities stay linked to the menu items used by POS and Kitchen; yield loss increases the raw quantity reserved and deducted.</p>
                <div class="table-wrap">
                    <table class="inventory-table recipe-table">
                        <thead>
                            <tr>
                                <th>Ingredient</th>
                                <th>Unit</th>
                                <th>Existing menu recipes</th>
                                <th>Cost per unit (₱)</th>
                                <th>Yield loss (%)</th>
                                <th>Par minimum</th>
                                <th>Save</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse ($recipeMatrix as $ingredient)
                                @php $settingsForm = 'recipe-settings-'.$ingredient->id; @endphp
                                <tr>
                                    <td>
                                        <form id="{{ $settingsForm }}" method="POST" action="{{ route('staff.inventory.recipe-settings', $ingredient) }}">
                                            @csrf
                                            @method('PATCH')
                                        </form>
                                        <strong>{{ $ingredient->name }}</strong>
                                    </td>
                                    <td>{{ $ingredient->unit }}</td>
                                    <td>
                                        @forelse ($ingredient->menuItems as $menuItem)
                                            <div>{{ $menuItem->name }} · {{ number_format((float) $menuItem->pivot->quantity, 3) }} {{ $ingredient->unit }} per serving</div>
                                        @empty
                                            <span class="mute">No POS recipe uses this ingredient.</span>
                                        @endforelse
                                    </td>
                                    <td><input form="{{ $settingsForm }}" type="number" name="unit_cost" min="0" max="99999999.9999" step="0.0001" value="{{ $ingredient->unit_cost }}" aria-label="Cost per {{ $ingredient->unit }} for {{ $ingredient->name }}"></td>
                                    <td><input form="{{ $settingsForm }}" type="number" name="yield_loss_percent" min="0" max="99.99" step="0.01" value="{{ $ingredient->yield_loss_percent }}" required aria-label="Yield loss percentage for {{ $ingredient->name }}"></td>
                                    <td><input form="{{ $settingsForm }}" type="number" name="min_threshold" min="0" max="9999999.999" step="0.001" value="{{ $ingredient->min_threshold }}" required aria-label="Par minimum for {{ $ingredient->name }}"></td>
                                    <td><button class="btn" type="submit" form="{{ $settingsForm }}">Save</button></td>
                                </tr>
                            @empty
                                <tr><td colspan="7" class="mute">No ingredients to configure.</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
                <p class="mute">A blank unit cost means cost is not recorded. Par minimum is the low-stock threshold and uses unexpired usable stock.</p>
            </article>

            <article class="card manager-panel">
                <h2>Estimated recipe cost per serving</h2>
                <p class="mute">Costs use the current ingredient unit cost and yield-loss-adjusted portion. A total is shown only when every recipe ingredient has a recorded cost.</p>
                <div class="table-wrap">
                    <table class="inventory-table">
                        <thead><tr><th>Menu item</th><th>Recipe cost estimate</th></tr></thead>
                        <tbody>
                            @forelse ($menuRecipeCosts as $recipeCost)
                                <tr>
                                    <td>{{ $recipeCost['item']->name }}</td>
                                    <td>
                                        @if (! $recipeCost['has_recipe'])
                                            No recipe linked
                                        @elseif ($recipeCost['estimated_cost'] === null)
                                            Cost unavailable · ingredient cost missing
                                        @else
                                            ₱{{ number_format($recipeCost['estimated_cost'], 2) }}
                                        @endif
                                    </td>
                                </tr>
                            @empty
                                <tr><td colspan="2" class="mute">No menu items have been configured.</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </article>
        @else
            <div class="card">
                <h2>Recipe matrix, costs and par levels</h2>
                <p class="mute">Manager access is required for recipe costs and par-level settings.</p>
            </div>
        @endif
    </section>
    <section id="view-log" data-view="log" class="hide" role="tabpanel" aria-labelledby="tab-log">
        <div class="card">
            <h2>Movement history</h2>
            <p class="mute">All inventory changes are recorded with their staff member and reason. Showing 50 movements per page.</p>
            <div class="table-wrap">
                <table class="inventory-table">
                    <thead><tr><th>Time</th><th>By</th><th>Action</th><th>Item</th><th>Change</th><th>Reason / note</th></tr></thead>
                    <tbody>
                        @forelse ($movementHistory as $movement)
                            @php
                                $action = match ($movement->type) {
                                    'stock_in' => 'Receive',
                                    'stock_out' => 'Use',
                                    'prep_in' => 'Prep in',
                                    'prep_out' => 'Prep out',
                                    'waste' => 'Waste',
                                    default => 'Adjustment',
                                };
                                $isDecrease = in_array($movement->type, ['stock_out', 'prep_out', 'waste'], true)
                                    || ($movement->type === 'adjustment' && (float) $movement->quantity < 0);
                                $details = collect([$movement->reason, $movement->notes, $movement->stockLot?->batch_number])
                                    ->filter()
                                    ->implode(' · ');
                            @endphp
                            <tr>
                                <td>{{ $movement->created_at?->format('M j, Y H:i') }}</td>
                                <td>{{ $movement->user?->name ?? 'System' }}</td>
                                <td>{{ $action }}</td>
                                <td>{{ $movement->ingredient?->name ?? 'Deleted ingredient' }}</td>
                                <td>{{ $isDecrease ? '−' : '+' }}{{ number_format(abs((float) $movement->quantity), 3) }} {{ $movement->ingredient?->unit }}</td>
                                <td>{{ $details !== '' ? $details : '—' }}</td>
                            </tr>
                        @empty
                            <tr><td colspan="6" class="mute">No stock movements recorded.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
            <div class="movement-pagination">{{ $movementHistory->links() }}</div>
        </div>
    </section>
</main>

<div id="toast" role="status" aria-live="polite"></div>
<script src="{{ asset('js/inventory.js') }}" defer></script>
</body>
</html>
