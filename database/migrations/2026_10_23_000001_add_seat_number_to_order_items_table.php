<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('order_items', 'seat_number')) {
            Schema::table('order_items', function (Blueprint $table): void {
                $table->unsignedSmallInteger('seat_number')->nullable()->after('quantity');
                $table->index(['order_id', 'seat_number'], 'order_items_order_seat_index');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('order_items', 'seat_number')) {
            Schema::table('order_items', function (Blueprint $table): void {
                $table->dropIndex('order_items_order_seat_index');
                $table->dropColumn('seat_number');
            });
        }
    }
};
