<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('orders', function (Blueprint $table) {
            $table->id();
            $table->string('reference_number')->unique();           // PSR-2026-0001
            $table->string('type');                                  // dine_in | take_out
            $table->string('table_number', 10)->nullable();
            $table->string('customer_name', 60);
            $table->string('customer_phone', 20)->nullable();
            $table->text('special_instructions')->nullable();
            $table->decimal('total_amount', 10, 2);
            $table->string('status')->default('pending')->index();   // pending | preparing | completed | cancelled
            $table->string('payment_status')->default('unpaid')->index(); // unpaid | paid
            $table->string('payment_method')->nullable();            // cash | card | gcash
            $table->string('payment_reference', 60)->nullable();     // e.g. GCash reference
            $table->decimal('amount_tendered', 10, 2)->nullable();
            $table->timestamp('paid_at')->nullable();
            $table->foreignId('cashier_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        Schema::create('order_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('order_id')->constrained()->cascadeOnDelete();
            $table->foreignId('menu_item_id')->nullable()->constrained()->nullOnDelete();
            $table->string('name');                                  // snapshot of the menu name
            $table->unsignedSmallInteger('quantity');
            $table->decimal('price', 8, 2);                          // snapshot of the unit price
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('order_items');
        Schema::dropIfExists('orders');
    }
};
