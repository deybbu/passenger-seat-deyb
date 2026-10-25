<div class="admin-table-wrap">
    <table class="admin-data-table">
        <thead>
            <tr><th>PO</th><th>Item</th><th>Ordered</th><th>Received</th><th>Status</th><th></th></tr>
        </thead>
        <tbody>
            @php
                $hasRows = false;
            @endphp
            @foreach($purchaseOrders as $purchaseOrder)
                @foreach($purchaseOrder->items as $item)
                    @php
                        $hasRows = true;
                        $received = (float) $item->received_quantity;
                        $ordered = (float) $item->ordered_quantity;
                        $status = $purchaseOrder->status === 'closed_short'
                            ? 'Closed short'
                            : ($received >= $ordered ? 'Complete' : ($received > 0 ? 'Partial' : 'Open'));
                        $statusClass = $status === 'Complete' ? 'status-ok' : ($status === 'Closed short' ? 'status-out' : 'status-low');
                    @endphp
                    <tr>
                        <td>
                            <strong>{{ $purchaseOrder->reference }}</strong><br>
                            <small class="admin-muted">{{ $purchaseOrder->supplier_name }}</small>
                        </td>
                        <td>{{ $item->ingredient->name ?? 'Deleted ingredient' }}</td>
                        <td>{{ number_format($ordered, 3) }} {{ $item->ingredient->unit ?? '' }}</td>
                        <td>{{ number_format($received, 3) }} {{ $item->ingredient->unit ?? '' }}</td>
                        <td><span class="admin-inventory-status {{ $statusClass }}">{{ $status }}</span></td>
                        <td>
                            @if($received > 0 && $received < $ordered && $purchaseOrder->closed_at === null)
                                <form method="POST" action="{{ route('admin.purchase-orders.close-short', $item) }}" data-confirm-message="Close this order short and draft a credit memo for the remaining quantity?">
                                    @csrf
                                    @method('PATCH')
                                    <button type="submit" class="btn-outline">Close short</button>
                                </form>
                            @endif
                        </td>
                    </tr>
                @endforeach
            @endforeach
            @unless($hasRows)
                <tr><td colspan="6" class="admin-muted">No purchase orders yet.</td></tr>
            @endunless
        </tbody>
    </table>
</div>
