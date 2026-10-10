<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('orders')) {
            Schema::create('orders', function (Blueprint $table): void {
                $table->id();
                $table->string('reference_number')->unique();
                $table->string('type');
                $table->string('table_number', 10)->nullable();
                $table->string('customer_name', 60);
                $table->string('customer_phone', 20)->nullable();
                $table->text('special_instructions')->nullable();
                $table->decimal('total_amount', 10, 2);
                $table->string('status')->default('pending')->index();
                $table->string('payment_status')->default('unpaid')->index();
                $table->string('payment_method')->nullable();
                $table->string('payment_reference', 60)->nullable();
                $table->decimal('amount_tendered', 10, 2)->nullable();
                $table->timestamp('paid_at')->nullable();
                $table->foreignId('cashier_id')->nullable()->constrained('users')->nullOnDelete();
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('order_items')) {
            Schema::create('order_items', function (Blueprint $table): void {
                $table->id();
                $table->foreignId('order_id')->constrained()->cascadeOnDelete();
                $table->foreignId('menu_item_id')->nullable()->constrained()->nullOnDelete();
                $table->string('name');
                $table->unsignedSmallInteger('quantity');
                $table->decimal('price', 8, 2);
                $table->timestamps();
            });
        }
    }

    public function down(): void
    {
        // Existing tickets may predate this migration; preserve them on rollback.
    }
};
