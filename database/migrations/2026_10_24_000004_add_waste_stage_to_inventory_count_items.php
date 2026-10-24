<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        if (Schema::hasTable('inventory_physical_count_items') && ! Schema::hasColumn('inventory_physical_count_items', 'waste_stage')) {
            Schema::table('inventory_physical_count_items', function (Blueprint $table): void {
                $table->string('waste_stage', 60)->nullable()->after('variance_reason');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('inventory_physical_count_items') && Schema::hasColumn('inventory_physical_count_items', 'waste_stage')) {
            Schema::table('inventory_physical_count_items', function (Blueprint $table): void {
                $table->dropColumn('waste_stage');
            });
        }
    }
};
