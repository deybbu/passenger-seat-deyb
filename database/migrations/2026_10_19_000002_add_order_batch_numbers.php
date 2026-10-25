<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('orders')) {
            return;
        }

        if (! Schema::hasColumn('orders', 'batch_number')) {
            Schema::table('orders', function (Blueprint $table): void {
                $table->unsignedInteger('batch_number')->nullable();
            });
        }

        if (Schema::hasColumn('orders', 'table_session_id')) {
            $orders = DB::table('orders')
                ->whereNotNull('table_session_id')
                ->orderBy('table_session_id')
                ->orderBy('created_at')
                ->orderBy('id')
                ->get(['id', 'table_session_id']);

            foreach ($orders->groupBy('table_session_id') as $sessionOrders) {
                foreach ($sessionOrders->values() as $index => $order) {
                    if ($order->id !== null) {
                        DB::table('orders')->where('id', $order->id)->update([
                            'batch_number' => $index + 1,
                        ]);
                    }
                }
            }
        }

        $hasBatchUniqueIndex = collect(Schema::getIndexes('orders'))->contains(
            fn (array $index): bool => ($index['unique'] ?? false)
                && $index['columns'] === ['table_session_id', 'batch_number'],
        );
        if (Schema::hasColumn('orders', 'table_session_id') && ! $hasBatchUniqueIndex) {
            Schema::table('orders', function (Blueprint $table): void {
                $table->unique(
                    ['table_session_id', 'batch_number'],
                    'orders_table_session_batch_unique',
                );
            });
        }
    }

    public function down(): void
    {
        if (! Schema::hasTable('orders') || ! Schema::hasColumn('orders', 'batch_number')) {
            return;
        }

        $index = collect(Schema::getIndexes('orders'))->first(
            fn (array $index): bool => ($index['unique'] ?? false)
                && $index['columns'] === ['table_session_id', 'batch_number'],
        );
        if ($index !== null) {
            Schema::table('orders', function (Blueprint $table) use ($index): void {
                $table->dropUnique($index['name']);
            });
        }

        Schema::table('orders', function (Blueprint $table): void {
            $table->dropColumn('batch_number');
        });
    }
};
