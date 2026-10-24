<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('inventory_packages', function (Blueprint $table): void {
            $table->string('manual_entry_code', 64)->nullable()->unique();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
        });

        Schema::create('inventory_receipts', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('package_id')->unique()->constrained('inventory_packages')->restrictOnDelete();
            $table->uuid('idempotency_key')->unique();
            $table->string('status', 20)->default('scanned')->index();
            $table->foreignId('scanned_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('scanned_at');
            $table->foreignId('received_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('received_at')->nullable();
            $table->timestamp('cancelled_at')->nullable();
            $table->timestamps();
        });

        Schema::table('inventory_stock_lots', function (Blueprint $table): void {
            $table->foreignId('receipt_id')->nullable()->unique()->constrained('inventory_receipts')->nullOnDelete();
            $table->timestamp('scanned_at')->nullable();
            $table->string('manufacturer', 120)->nullable();
            $table->string('serial_number', 120)->nullable();
            $table->string('storage_bin', 80)->nullable();
        });

        Schema::table('purchase_orders', function (Blueprint $table): void {
            $table->string('storage_zone', 80)->nullable();
            $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('approved_at')->nullable();
        });

        Schema::create('inventory_idempotency_keys', function (Blueprint $table): void {
            $table->id();
            $table->uuid('idempotency_key')->unique();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('operation', 80);
            $table->char('request_hash', 64);
            $table->string('status', 20)->default('processing')->index();
            $table->unsignedSmallInteger('response_code')->nullable();
            $table->json('response_payload')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();
        });

        Schema::create('inventory_physical_counts', function (Blueprint $table): void {
            $table->id();
            $table->string('storage_zone', 80)->nullable();
            $table->string('status', 20)->default('draft')->index();
            $table->foreignId('counted_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('submitted_at')->nullable();
            $table->timestamp('approved_at')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();
        });

        Schema::create('inventory_physical_count_items', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('physical_count_id')->constrained('inventory_physical_counts')->cascadeOnDelete();
            $table->foreignId('ingredient_id')->constrained()->restrictOnDelete();
            $table->decimal('system_quantity', 12, 3);
            $table->decimal('counted_quantity', 12, 3)->nullable();
            $table->decimal('variance_quantity', 12, 3)->nullable();
            $table->string('variance_reason', 80)->nullable();
            $table->boolean('adjusted')->default(false);
            $table->foreignId('inventory_log_id')->nullable()->constrained('inventory_logs')->nullOnDelete();
            $table->timestamps();

            $table->unique(['physical_count_id', 'ingredient_id'], 'inventory_count_ingredient_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('inventory_physical_count_items');
        Schema::dropIfExists('inventory_physical_counts');
        Schema::dropIfExists('inventory_idempotency_keys');

        Schema::table('purchase_orders', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('approved_by');
            $table->dropColumn(['storage_zone', 'approved_at']);
        });

        Schema::table('inventory_stock_lots', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('receipt_id');
            $table->dropColumn(['scanned_at', 'manufacturer', 'serial_number', 'storage_bin']);
        });

        Schema::dropIfExists('inventory_receipts');

        Schema::table('inventory_packages', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('created_by');
            $table->dropUnique(['manual_entry_code']);
            $table->dropColumn('manual_entry_code');
        });
    }
};
