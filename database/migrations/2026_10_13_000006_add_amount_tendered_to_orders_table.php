<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('orders') && ! Schema::hasColumn('orders', 'amount_tendered')) {
            Schema::table('orders', function (Blueprint $table): void {
                $table->decimal('amount_tendered', 10, 2)->nullable();
            });
        }
    }

    public function down(): void
    {
        // Preserve recorded payment data on rollback.
    }
};
