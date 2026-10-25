<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Models\Order;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin Order */
class KitchenOrderResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $history = $this->statusHistories->keyBy('to_status');
        $table = $this->table_number;

        if ($table !== null && (int) $this->batch_number > 1) {
            $table .= ' - Order '.$this->batch_number;
        }

        return [
            'id' => (string) $this->reference_number,
            'dbid' => (int) $this->id,
            'tb' => $table === null ? '' : (string) $table,
            'ty' => match ($this->type) {
                'dine_in' => 'Dine-in',
                'take_out', 'takeout' => 'Take-out',
                default => ucfirst(str_replace('_', ' ', (string) $this->type)),
            },
            'it' => $this->items->map(fn ($item): array => [
                (int) $item->quantity,
                $this->escape((string) $item->item_name),
                $this->escape((string) ($item->notes ?? '')),
            ])->values()->all(),
            't' => $this->created_at?->getTimestamp() * 1000 ?? 0,
            'st' => ucfirst((string) $this->status),
            'sp' => $this->escape((string) ($this->special_instructions ?? '')),
            'x' => false,
            'rt' => $history->get('ready')?->created_at?->getTimestamp() * 1000 ?? 0,
            'ct' => $history->get('served')?->created_at?->getTimestamp() * 1000 ?? 0,
        ];
    }

    private function escape(string $value): string
    {
        return htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }
}
