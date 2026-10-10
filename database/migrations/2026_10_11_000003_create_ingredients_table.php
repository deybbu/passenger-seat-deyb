<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ingredients', function (Blueprint $table) {
            $table->id();
            $table->string('name')->unique();
            $table->decimal('current_stock', 10, 3)->default(0);
            $table->string('unit', 20);
            $table->decimal('min_threshold', 10, 3)->default(0);
            $table->timestamps();
        });

        // Recipe: how much of each ingredient one serving of a menu item uses.
        Schema::create('ingredient_menu_item', function (Blueprint $table) {
            $table->foreignId('menu_item_id')->constrained()->cascadeOnDelete();
            $table->foreignId('ingredient_id')->constrained()->cascadeOnDelete();
            $table->decimal('quantity', 10, 3);
            $table->primary(['menu_item_id', 'ingredient_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ingredient_menu_item');
        Schema::dropIfExists('ingredients');
    }
};
