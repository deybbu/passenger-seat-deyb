<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('inventory_stock_lots', function (Blueprint $table): void {
            $table->string('storage_zone', 80)->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('inventory_stock_lots', function (Blueprint $table): void {
            $table->dropColumn('storage_zone');
        });
    }
};
