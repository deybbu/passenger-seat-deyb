<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Models\InventoryStockLot;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class InventoryBootstrapPackageResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        /** @var InventoryStockLot|null $latestLot */
        $latestLot = $this->stockLots->sortByDesc('received_at')->first();
        $purchaseOrder = $latestLot?->purchaseOrderItem?->purchaseOrder;

        return [
            'code' => (string) $this->barcode,
            'manualCode' => (string) ($this->manual_entry_code ?? ''),
            'itemId' => (string) $this->ingredient_id,
            'qty' => (float) $this->package_quantity,
            'batch' => (string) ($latestLot?->batch_number ?? ''),
            'bestBefore' => (string) ($latestLot?->expires_at?->toDateString() ?? ''),
            'manufacturer' => (string) ($latestLot?->manufacturer ?? ''),
            'serial' => (string) ($latestLot?->serial_number ?? ''),
            'commissary' => (string) ($latestLot?->storage_zone ?? 'Restaurant'),
            'bin' => (string) ($latestLot?->storage_bin ?? ''),
            'poId' => (string) ($purchaseOrder?->reference ?? ''),
            'receivedAt' => $latestLot?->received_at?->toIso8601String(),
        ];
    }
}
