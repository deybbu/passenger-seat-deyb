<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('table_sessions', 'vat_exemption')) {
            Schema::table('table_sessions', function (Blueprint $table): void {
                $table->decimal('vat_exemption', 10, 2)->default(0);
            });
        }
        if (Schema::hasTable('orders') && ! Schema::hasColumn('orders', 'vat_exemption')) {
            Schema::table('orders', function (Blueprint $table): void {
                $table->decimal('vat_exemption', 10, 2)->default(0);
            });
        }

        Schema::create('table_session_payments', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('table_session_id')->constrained()->restrictOnDelete();
            $table->string('type', 16);
            $table->string('payment_method', 20);
            $table->string('payment_reference', 60)->unique();
            $table->decimal('amount', 10, 2);
            $table->decimal('amount_tendered', 10, 2)->nullable();
            $table->decimal('change_given', 10, 2)->nullable();
            $table->decimal('subtotal', 10, 2);
            $table->decimal('discount', 10, 2)->default(0);
            $table->decimal('vat_exemption', 10, 2)->default(0);
            $table->decimal('vat', 10, 2)->default(0);
            $table->decimal('total', 10, 2);
            $table->string('discount_type', 16)->nullable();
            $table->decimal('discount_rate', 5, 2)->nullable();
            $table->foreignId('performed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('reversal_of_id')->nullable()->unique()
                ->constrained('table_session_payments')->restrictOnDelete();
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->index(['table_session_id', 'type', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('table_session_payments');

        if (Schema::hasColumn('table_sessions', 'vat_exemption')) {
            Schema::table('table_sessions', function (Blueprint $table): void {
                $table->dropColumn('vat_exemption');
            });
        }
        if (Schema::hasColumn('orders', 'vat_exemption')) {
            Schema::table('orders', function (Blueprint $table): void {
                $table->dropColumn('vat_exemption');
            });
        }
    }
};
