<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('orders')) {
            return;
        }

        if (! Schema::hasColumn('orders', 'type')) {
            Schema::table('orders', function (Blueprint $table): void {
                $table->string('type')->default('dine_in');
            });

            if (Schema::hasColumn('orders', 'order_type')) {
                DB::table('orders')->update(['type' => DB::raw('order_type')]);
            }
        }

        if (! Schema::hasColumn('orders', 'total_amount')) {
            Schema::table('orders', function (Blueprint $table): void {
                $table->decimal('total_amount', 10, 2)->default(0);
            });

            if (Schema::hasColumn('orders', 'total')) {
                DB::table('orders')->update(['total_amount' => DB::raw('total')]);
            }
        }

        if (! Schema::hasColumn('orders', 'payment_status')) {
            Schema::table('orders', function (Blueprint $table): void {
                $table->string('payment_status')->default('unpaid')->index();
            });
        }

        if (! Schema::hasColumn('orders', 'payment_method')) {
            Schema::table('orders', function (Blueprint $table): void {
                $table->string('payment_method')->nullable();
            });
        }

        if (! Schema::hasColumn('orders', 'paid_at')) {
            Schema::table('orders', function (Blueprint $table): void {
                $table->timestamp('paid_at')->nullable();
            });
        }

        if (! Schema::hasColumn('orders', 'cashier_id') && Schema::hasTable('users')) {
            Schema::table('orders', function (Blueprint $table): void {
                $table->foreignId('cashier_id')->nullable()->constrained('users')->nullOnDelete();
            });
        }

        if (Schema::hasTable('order_items') && ! Schema::hasColumn('order_items', 'name')
            && Schema::hasColumn('order_items', 'item_name')) {
            Schema::table('order_items', function (Blueprint $table): void {
                $table->string('name')->default('');
            });

            DB::table('order_items')->update(['name' => DB::raw('item_name')]);
        }
    }

    public function down(): void
    {
        // Preserve order and payment data; these columns are safe additive compatibility fields.
    }
};
