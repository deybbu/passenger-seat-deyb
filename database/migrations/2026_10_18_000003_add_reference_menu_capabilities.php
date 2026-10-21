<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('menu_item_variants', function (Blueprint $table): void {
            $table->decimal('portion_factor', 8, 3)->default(1)->after('price');
            $table->decimal('stock_quantity', 12, 3)->default(0)->after('portion_factor');
            $table->decimal('min_stock_quantity', 12, 3)->default(0)->after('stock_quantity');
        });

        Schema::create('menu_modifier_groups', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('menu_item_id')->constrained()->cascadeOnDelete();
            $table->string('name', 100);
            $table->boolean('is_required')->default(false);
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();
            $table->unique(['menu_item_id', 'name']);
        });

        Schema::create('menu_modifier_options', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('menu_modifier_group_id')->constrained()->cascadeOnDelete();
            $table->string('name', 100);
            $table->decimal('price_delta', 10, 2)->default(0);
            $table->boolean('is_active')->default(true);
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();
            $table->unique(['menu_modifier_group_id', 'name']);
        });

        Schema::create('menu_item_bundles', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('menu_item_id')->constrained()->cascadeOnDelete();
            $table->foreignId('included_menu_item_id')->constrained('menu_items')->cascadeOnDelete();
            $table->decimal('quantity', 10, 3)->default(1);
            $table->timestamps();
            $table->unique(['menu_item_id', 'included_menu_item_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('menu_item_bundles');
        Schema::dropIfExists('menu_modifier_options');
        Schema::dropIfExists('menu_modifier_groups');

        Schema::table('menu_item_variants', function (Blueprint $table): void {
            $table->dropColumn(['portion_factor', 'stock_quantity', 'min_stock_quantity']);
        });
    }
};
