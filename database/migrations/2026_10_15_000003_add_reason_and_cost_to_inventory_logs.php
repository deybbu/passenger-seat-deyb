<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('inventory_logs', function (Blueprint $table): void {
            if (! Schema::hasColumn('inventory_logs', 'reason')) {
                $table->string('reason', 80)->nullable();
            }

            if (! Schema::hasColumn('inventory_logs', 'cost_amount')) {
                $table->decimal('cost_amount', 12, 4)->nullable();
            }
        });
    }

    public function down(): void
    {
        Schema::table('inventory_logs', function (Blueprint $table): void {
            $columns = array_filter([
                Schema::hasColumn('inventory_logs', 'reason') ? 'reason' : null,
                Schema::hasColumn('inventory_logs', 'cost_amount') ? 'cost_amount' : null,
            ]);

            if ($columns !== []) {
                $table->dropColumn($columns);
            }
        });
    }
};
