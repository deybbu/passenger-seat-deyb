<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('payments')) {
            Schema::create('payments', function (Blueprint $table): void {
                $table->id();
                $table->string('idempotency_key', 64)->unique();
                $table->string('payment_reference', 60)->unique();
                $table->string('payment_method', 20);
                $table->string('reference_number', 100)->nullable();
                $table->decimal('amount', 10, 2);
                $table->decimal('amount_tendered', 10, 2)->nullable();
                $table->decimal('change_given', 10, 2)->nullable();
                $table->decimal('subtotal', 10, 2)->default(0);
                $table->decimal('discount', 10, 2)->default(0);
                $table->decimal('vat_exemption', 10, 2)->default(0);
                $table->decimal('vat', 10, 2)->default(0);
                $table->decimal('total', 10, 2);
                $table->string('discount_type', 16)->nullable();
                $table->decimal('discount_rate', 5, 2)->nullable();
                $table->string('discount_cardholder_name', 120)->nullable();
                $table->string('discount_id_number', 80)->nullable();
                $table->string('discount_tin', 30)->nullable();
                $table->foreignId('cashier_id')->nullable()->constrained('users')->nullOnDelete();
                $table->foreignId('table_session_id')->nullable()->constrained()->restrictOnDelete();
                $table->foreignId('bill_id')->nullable()->unique()->constrained('bills')->restrictOnDelete();
                $table->timestamp('paid_at');
                $table->timestamps();

                $table->index(['payment_method', 'paid_at']);
                $table->index(['cashier_id', 'paid_at']);
                $table->index('table_session_id');
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('payments');
    }
};
