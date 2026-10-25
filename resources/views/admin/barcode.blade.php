@extends('layouts.admin')

@section('title', 'Scan package')
@section('page-title', 'Scan package')

@section('content')
    <div class="admin-barcode-page" data-inventory-scanner>
        <div class="admin-inventory-grid">
            <section class="admin-card">
                <div class="admin-card-header"><span class="admin-card-title">Scan package</span></div>
                <form id="barcodeLookupForm" data-lookup-url="{{ route('admin.barcode.lookup') }}">
                    @csrf
                    <div class="admin-barcode-search">
                        <label class="admin-field" for="code">Type or scan a barcode
                            <input type="text" id="code" name="barcode" maxlength="64" autocomplete="off" aria-label="Barcode">
                        </label>
                        <button type="submit" class="btn-red" id="manualLookup">Manual lookup</button>
                        <button type="button" class="btn-outline" id="camBtn" data-start-text="Scan with camera">Scan with camera</button>
                    </div>
                </form>
                <div class="admin-scanner-frame" id="frame">
                    <video id="vid" class="hide" autoplay playsinline aria-label="Camera barcode preview"></video>
                    <span class="admin-scanner-corners" aria-hidden="true"></span>
                </div>
                <p class="admin-muted" id="hint">Point the rear camera at the package barcode. You can also type it above.</p>
                <div class="admin-inventory-message" id="msg" role="status" aria-live="polite">Stock changes only after you confirm.</div>
                @if($ingredients->isNotEmpty())
                    <div class="admin-inventory-samples">
                        <span class="admin-muted">Registered barcodes:</span>
                        @foreach($ingredients as $ingredient)
                            <button type="button" class="btn-outline" data-barcode-sample="{{ $ingredient->barcode }}">{{ $ingredient->name }}</button>
                        @endforeach
                    </div>
                @endif
            </section>

            <section class="admin-card" id="matchedDelivery" hidden>
                <div class="admin-card-header"><span class="admin-card-title"><span id="matchedIngredient"></span> · expected on <span id="matchedPo"></span></span></div>
                <p class="admin-muted" id="matchedOrderSummary"></p>
                <p>Supplier: <strong id="matchedSupplier"></strong></p>
                <p>Current stock: <strong id="matchedCurrentStock"></strong></p>
                <form id="receiptForm" data-receive-url="{{ route('admin.barcode.receive') }}">
                    @csrf
                    <input type="hidden" name="barcode">
                    <input type="hidden" name="purchase_order_item_id">
                    <input type="hidden" name="receipt_token" value="{{ $receiptToken }}">
                    <div class="admin-form-grid">
                        <label class="admin-field">Invoice no.
                            <input type="text" name="invoice_number" maxlength="80" placeholder="INV-0000" required>
                        </label>
                        <label class="admin-field">Unit cost (invoice)
                            <input type="number" name="unit_cost" min="0" step="0.0001">
                        </label>
                        <label class="admin-field">Received quantity
                            <input type="number" name="quantity" min="0.001" step="0.001" required>
                        </label>
                        <label class="admin-field">Batch code
                            <input type="text" name="batch_number" maxlength="120" required>
                        </label>
                        <label class="admin-field">Best before / Expiry
                            <input type="date" name="expires_at" required>
                        </label>
                        <label class="admin-field">Bin
                            <input type="text" name="storage_zone" maxlength="120">
                        </label>
                    </div>
                    <div class="admin-inventory-dialog-actions">
                        <button type="button" class="btn-outline" data-rescan>Rescan</button>
                        <button type="submit" class="btn-red">Confirm receipt</button>
                    </div>
                </form>
            </section>
        </div>

        <section class="admin-card">
            <div class="admin-card-header">
                <span class="admin-card-title">Purchase orders</span>
                <span class="admin-muted">Only open, matching orders can receive stock.</span>
            </div>
            @include('admin.partials.purchase-orders-table')
        </section>
    </div>
@endsection
