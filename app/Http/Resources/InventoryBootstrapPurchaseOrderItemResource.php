<?php

declare(strict_types=1);

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class InventoryBootstrapPurchaseOrderItemResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        $purchaseOrder = $this->purchaseOrder;

        return [
            'id' => (string) $purchaseOrder->reference,
            'orderId' => (string) $purchaseOrder->id,
            'lineId' => (string) $this->id,
            'commissary' => (string) ($purchaseOrder->storage_zone ?? 'Restaurant'),
            'itemId' => (string) $this->ingredient_id,
            'qty' => (float) $this->ordered_quantity,
            'received' => (float) $this->received_quantity,
            'supplier' => (string) $purchaseOrder->supplier_name,
            'due' => (string) ($purchaseOrder->expected_at?->toDateString() ?? ''),
            'createdAt' => $purchaseOrder->ordered_at?->toIso8601String() ?? $purchaseOrder->created_at?->toIso8601String(),
            'status' => (string) $purchaseOrder->status,
        ];
    }
}
