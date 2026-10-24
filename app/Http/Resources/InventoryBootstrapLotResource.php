<?php

declare(strict_types=1);

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class InventoryBootstrapLotResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        $package = $this->package;
        $purchaseOrder = $this->purchaseOrderItem?->purchaseOrder;
        $openingBalance = str_starts_with((string) $this->receipt_token, 'opening-stock-');

        return [
            'id' => (string) $this->id,
            'itemId' => (string) $this->ingredient_id,
            'code' => (string) ($package?->barcode ?? ''),
            'manualCode' => (string) ($package?->manual_entry_code ?? ''),
            'batch' => (string) ($this->batch_number ?: ($openingBalance ? 'Opening balance' : '')),
            'qty' => (float) $this->received_quantity,
            'remainingQty' => (float) $this->remaining_quantity,
            'bestBefore' => (string) ($this->expires_at?->toDateString() ?? ''),
            'receivedAt' => $this->received_at?->toIso8601String() ?? '',
            'scannedAt' => $this->scanned_at?->toIso8601String() ?? '',
            'manufacturer' => (string) ($this->manufacturer ?? ''),
            'serial' => (string) ($this->serial_number ?? ''),
            'commissary' => (string) ($this->storage_zone ?? 'Restaurant'),
            'bin' => (string) ($this->storage_bin ?? ''),
            'poId' => (string) ($purchaseOrder?->reference ?? ''),
            'source' => $openingBalance ? 'Opening balance' : ($this->receipt_id ? 'QR receipt' : 'Stock movement'),
        ];
    }
}
