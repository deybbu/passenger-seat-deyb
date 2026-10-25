<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('order_items') && ! Schema::hasColumn('order_items', 'cancelled_at')) {
            Schema::table('order_items', function (Blueprint $table): void {
                $table->timestamp('cancelled_at')->nullable();
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('order_items') && Schema::hasColumn('order_items', 'cancelled_at')) {
            Schema::table('order_items', function (Blueprint $table): void {
                $table->dropColumn('cancelled_at');
            });
        }
    }
};
