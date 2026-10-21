<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\RestaurantTable;
use App\Models\TableSession;
use Illuminate\Support\Collection;

final class TableSessionStatusService
{
    /**
     * @param Collection<int, RestaurantTable> $tables
     * @return Collection<int, array{
     *     table_number:string,
     *     table_name:string,
     *     qr_enabled:bool,
     *     table_status:string,
     *     table_status_label:string,
     *     table_order_count:int,
     *     active_session_id:?int,
     *     bill_requested_at:?string
     * }>
     */
    public function forTables(Collection $tables): Collection
    {
        if ($tables->isEmpty()) {
            return collect();
        }

        $sessions = TableSession::query()
            ->whereIn('status', ['open', 'bill_requested'])
            ->whereNull('closed_at')
            ->where(fn ($query) => $query
                ->whereIn('table_id', $tables->pluck('id')->all())
                ->orWhereIn('table_number', $tables->pluck('table_number')->all()))
            ->withCount(['orders' => fn ($orders) => $orders->where('status', '!=', 'cancelled')])
            ->orderByDesc('opened_at')
            ->get(['id', 'table_id', 'table_number', 'status', 'bill_requested_at']);

        return $tables->mapWithKeys(function (RestaurantTable $table) use ($sessions): array {
            $session = $sessions->first(
                fn (TableSession $candidate): bool => (int) $candidate->table_id === (int) $table->id,
            ) ?? $sessions->first(
                fn (TableSession $candidate): bool => $candidate->table_number === $table->table_number,
            );
            $status = $session === null
                ? 'free'
                : ($session->status === 'bill_requested' || $session->bill_requested_at !== null
                    ? 'bill_requested'
                    : 'open');

            return [$table->id => [
                'table_number' => (string) $table->table_number,
                'table_name' => (string) $table->name,
                'qr_enabled' => (bool) $table->is_enabled,
                'table_status' => $status,
                'table_status_label' => match ($status) {
                    'bill_requested' => 'Bill requested',
                    'open' => 'Open tab',
                    default => 'Free',
                },
                'table_order_count' => (int) ($session?->orders_count ?? 0),
                'active_session_id' => $session?->id,
                'bill_requested_at' => $session?->bill_requested_at?->toIso8601String(),
            ]];
        });
    }
}
