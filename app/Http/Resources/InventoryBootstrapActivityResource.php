<?php

declare(strict_types=1);

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class InventoryBootstrapActivityResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        $delta = $this->stock_before !== null && $this->stock_after !== null
            ? (float) $this->stock_after - (float) $this->stock_before
            : match ($this->type) {
                'stock_in' => abs((float) $this->quantity),
                'stock_out' => -abs((float) $this->quantity),
                default => (float) $this->quantity,
            };
        $packageCode = $this->stockLot?->package?->barcode;
        $orderReference = $this->order?->reference_number;
        $action = match ($this->type) {
            'stock_in' => $packageCode ? 'QR received' : 'Stock received',
            'stock_out' => $this->order_id ? 'Recipe sale' : 'Stock out',
            'waste' => 'Waste',
            'adjustment' => 'Count adjustment',
            'prep_out' => 'Preparation use',
            default => (string) $this->type,
        };

        $audit = [
            'id' => (string) $this->id,
            'timestamp' => $this->created_at?->toIso8601String() ?? '',
            'staff' => $this->user ? (string) $this->user->id : 'System',
            'staffName' => (string) ($this->user?->name ?? 'System'),
            'action' => $action,
            'itemId' => (string) $this->ingredient_id,
            'itemName' => (string) ($this->ingredient?->name ?? ''),
            'change' => round($delta, 3),
            'commissary' => (string) ($this->stockLot?->storage_zone ?? 'Restaurant'),
            'bin' => (string) ($this->stockLot?->storage_bin ?? '—'),
            'note' => (string) ($this->notes ?? ''),
            'ref' => (string) ($orderReference ?: $packageCode ?: ('LOG-'.$this->id)),
            'cost' => (float) ($this->cost_amount ?? 0),
        ];

        return [
            'audit' => $audit,
            'movement' => [
                'id' => (string) $this->id,
                'kind' => $action === 'Recipe sale' ? 'sale_usage' : ($action === 'Count adjustment' ? 'count_adjust' : strtolower(str_replace(' ', '_', $action))),
                'itemId' => (string) $this->ingredient_id,
                'change' => round($delta, 3),
                'timestamp' => $audit['timestamp'],
                'ref' => $audit['ref'],
                'note' => $audit['note'],
                'commissary' => $audit['commissary'],
            ],
            'waste' => $this->type === 'waste' ? [
                'id' => 'WST-'.$this->id,
                'timestamp' => $audit['timestamp'],
                'staff' => $audit['staff'],
                'itemId' => $audit['itemId'],
                'qty' => abs($audit['change']),
                'reason' => (string) ($this->reason ?? 'Unspecified'),
                'commissary' => $audit['commissary'],
                'note' => $audit['note'],
                'unitCost' => $this->stockLot?->unit_cost !== null
                    ? (float) $this->stockLot->unit_cost
                    : (float) ($this->ingredient?->unit_cost ?? 0),
                'loss' => (float) ($this->cost_amount ?? 0),
            ] : null,
        ];
    }
}
