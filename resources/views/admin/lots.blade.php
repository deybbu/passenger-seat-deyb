@extends('layouts.admin')

@section('title', 'Lots & traceability')
@section('page-title', 'Lots & traceability')

@section('content')
    <section class="admin-card">
        <div class="admin-card-header"><span class="admin-card-title">Lots &amp; traceability</span></div>
        <div class="admin-table-wrap">
            <table class="admin-data-table">
                <thead>
                    <tr>
                        <th>Time</th>
                        <th>Item</th>
                        <th>Batch</th>
                        <th>Qty</th>
                        <th>Expiry</th>
                        <th>Supplier</th>
                        <th>Invoice</th>
                        <th>Cost var.</th>
                        <th>Bin</th>
                        <th></th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($lots as $lot)
                        @php
                            $orderItem = $lot->purchaseOrderItem;
                            $expectedCost = $orderItem?->expected_unit_cost;
                            $variance = $expectedCost !== null && (float) $expectedCost > 0 && $lot->unit_cost !== null
                                ? (((float) $lot->unit_cost - (float) $expectedCost) / (float) $expectedCost) * 100
                                : null;
                            $varianceClass = $variance === null || abs($variance) < 0.5 ? 'status-ok' : ($variance > 0 ? 'status-out' : 'status-ok');
                        @endphp
                        <tr>
                            <td>{{ $lot->received_at?->format('M d, H:i') ?? $lot->created_at?->format('M d, H:i') ?? '—' }}</td>
                            <td class="admin-strong">{{ $lot->ingredient->name ?? 'Deleted ingredient' }}</td>
                            <td>{{ $lot->batch_number ?: '—' }}</td>
                            <td>{{ number_format((float) $lot->received_quantity, 3) }} {{ $lot->ingredient->unit ?? '' }}</td>
                            <td>{{ $lot->expires_at?->format('Y-m-d') ?? '—' }}</td>
                            <td>{{ $orderItem?->purchaseOrder?->supplier_name ?? '—' }}</td>
                            <td>{{ $lot->supplier_invoice_number ?: ($orderItem?->purchaseOrder?->invoice_number ?? '—') }}</td>
                            <td>
                                @if($variance === null)
                                    —
                                @else
                                    <span class="admin-inventory-status {{ $varianceClass }}">
                                        {{ $variance > 0 ? '+' : '' }}{{ number_format($variance, 1) }}%
                                    </span>
                                @endif
                            </td>
                            <td>{{ $lot->storage_zone ?: '—' }}</td>
                            <td>
                                <button
                                    type="button"
                                    class="btn-outline"
                                    data-print-lot
                                    data-ingredient="{{ $lot->ingredient->name ?? 'Deleted ingredient' }}"
                                    data-batch="{{ $lot->batch_number ?: '—' }}"
                                    data-expiry="{{ $lot->expires_at?->format('Y-m-d') ?? '—' }}"
                                    data-bin="{{ $lot->storage_zone ?: '—' }}"
                                    data-received="{{ $lot->received_at?->format('Y-m-d') ?? $lot->created_at?->format('Y-m-d') ?? '—' }}"
                                >Print label</button>
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="10" class="admin-muted">No receipts yet.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </section>

    <dialog class="admin-inventory-dialog" id="lotLabelDialog">
        <div class="admin-inventory-dialog-content">
            <button type="button" class="admin-inventory-dialog-close" data-close-label aria-label="Close">×</button>
            <h2>Internal label</h2>
            <div class="admin-lot-label" id="lotPrintLabel">
                <strong id="labelIngredient"></strong>
                <span>Received <span id="labelReceived"></span></span>
                <span>Lot <span id="labelBatch"></span> · Exp <span id="labelExpiry"></span></span>
                <span>Store: <span id="labelBin"></span></span>
                <code id="labelBarcode"></code>
            </div>
            <div class="admin-inventory-dialog-actions">
                <button type="button" class="btn-outline" data-close-label>Close</button>
                <button type="button" class="btn-red" id="printLotLabel">Print</button>
            </div>
        </div>
    </dialog>
@endsection
