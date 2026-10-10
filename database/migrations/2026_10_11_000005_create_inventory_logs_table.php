<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('inventory_logs')) {
            Schema::create('inventory_logs', function (Blueprint $table): void {
                $table->id();
                $table->foreignId('ingredient_id')->constrained()->cascadeOnDelete();
                $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
                $table->string('type');
                $table->decimal('quantity', 10, 3);
                $table->decimal('stock_before', 10, 3)->nullable();
                $table->decimal('stock_after', 10, 3)->nullable();
                $table->string('notes', 500)->nullable();
                $table->timestamps();
            });
        }
    }

    public function down(): void
    {
        // Existing inventory history may predate this migration; preserve it on rollback.
    }
};
