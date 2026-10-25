<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Support\TableQrUrl;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class PosRestaurantTableResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $qrUrl = TableQrUrl::forToken($this->qr_token);

        return [
            'n' => 'Table '.(string) $this->table_number,
            't' => (string) $this->qr_token,
            'en' => (bool) $this->is_enabled,
            'u' => $qrUrl,
            'img' => $qrUrl !== null
                ? route('staff.pos.table-qr.image', ['restaurantTable' => $this->resource], false)
                : null,
            'table_number' => (string) $this->table_number,
        ];
    }
}
