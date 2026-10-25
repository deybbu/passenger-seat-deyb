<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('ingredients')) {
            Schema::create('ingredients', function (Blueprint $table): void {
                $table->id();
                $table->string('name')->unique();
                $table->decimal('current_stock', 10, 3)->default(0);
                $table->string('unit', 20);
                $table->decimal('min_threshold', 10, 3)->default(0);
                $table->boolean('manual_override')->default(false);
                $table->timestamps();
            });
        } elseif (! Schema::hasColumn('ingredients', 'manual_override')) {
            Schema::table('ingredients', function (Blueprint $table): void {
                $table->boolean('manual_override')->default(false);
            });
        }
    }

    public function down(): void
    {
        // Ingredients may predate this compatibility migration; preserve existing inventory data.
    }
};
