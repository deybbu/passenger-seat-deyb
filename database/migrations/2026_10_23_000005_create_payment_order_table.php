<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('payment_order')) {
            return;
        }

        Schema::create('payment_order', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('payment_id')->constrained('payments')->cascadeOnDelete();
            $table->foreignId('order_id')->constrained('orders')->restrictOnDelete();
            $table->decimal('allocated_amount', 10, 2);
            $table->timestamps();

            $table->unique(['payment_id', 'order_id'], 'payment_order_payment_order_unique');
            $table->index('order_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payment_order');
    }
};
