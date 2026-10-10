<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('inventory_logs', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('ingredient_id')->constrained()->cascadeOnUpdate()->restrictOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnUpdate()->restrictOnDelete();
            $table->string('type', 20);
            $table->decimal('quantity', 12, 3);
            $table->decimal('stock_before', 12, 3);
            $table->decimal('stock_after', 12, 3);
            $table->string('notes', 500)->nullable();
            $table->timestamps();
            $table->index(['ingredient_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('inventory_logs');
    }
};
