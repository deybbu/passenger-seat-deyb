<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('orders', function (Blueprint $table) {
            $table->id();
            $table->string('reference_number')->unique();
            $table->string('order_type');                 // dine_in | take_out
            $table->string('table_number', 10)->nullable();
            $table->string('customer_name');
            $table->string('customer_phone', 20)->nullable();
            $table->text('special_instructions')->nullable();
            $table->decimal('subtotal', 10, 2);
            $table->decimal('total', 10, 2);
            $table->string('status')->default('pending'); // pending | preparing | ready | completed | cancelled
            $table->timestamps();
        });

        Schema::create('order_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('order_id')->constrained()->cascadeOnDelete();
            $table->foreignId('menu_item_id')->nullable()->constrained()->nullOnDelete();
            $table->string('name');                       // snapshot, so old orders survive menu edits
            $table->decimal('price', 8, 2);               // snapshot
            $table->unsignedSmallInteger('quantity');
            $table->decimal('line_total', 10, 2);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('order_items');
        Schema::dropIfExists('orders');
    }
};
