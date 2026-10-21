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
            return;
        }

        Schema::table('orders', function (Blueprint $table): void {
            if (Schema::hasTable('restaurant_tables') && ! Schema::hasColumn('orders', 'table_id')) {
                $table->foreignId('table_id')->nullable()
                    ->constrained('restaurant_tables')->nullOnDelete();
            }

            if (! Schema::hasColumn('orders', 'source')) {
                $table->string('source', 32)->default('staff')->index();
            }

            if (! Schema::hasColumn('orders', 'vat_amount')) {
                $table->decimal('vat_amount', 10, 2)->default(0);
            }
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('orders')) {
            return;
        }

        Schema::table('orders', function (Blueprint $table): void {
            if (Schema::hasColumn('orders', 'table_id')) {
                $table->dropConstrainedForeignId('table_id');
            }

            if (Schema::hasColumn('orders', 'source')) {
                $table->dropIndex(['source']);
                $table->dropColumn('source');
            }

            if (Schema::hasColumn('orders', 'vat_amount')) {
                $table->dropColumn('vat_amount');
            }
        });
    }
};
