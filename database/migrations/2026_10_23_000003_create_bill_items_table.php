<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('bill_items')) {
            return;
        }

        Schema::create('bill_items', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('bill_id')->constrained('bills')->restrictOnDelete();
            $table->foreignId('order_item_id')->constrained('order_items')->restrictOnDelete();
            $table->unsignedSmallInteger('quantity');
            $table->decimal('amount', 10, 2);
            $table->timestamps();

            $table->unique(['bill_id', 'order_item_id'], 'bill_items_bill_order_item_unique');
            $table->index('order_item_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('bill_items');
    }
};
