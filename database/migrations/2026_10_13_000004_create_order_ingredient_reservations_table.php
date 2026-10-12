<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('order_ingredient_reservations', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('order_id')->constrained()->cascadeOnDelete();
            $table->foreignId('ingredient_id')->constrained()->restrictOnDelete();
            $table->string('ingredient_name', 150);
            $table->string('unit', 20);
            $table->decimal('quantity', 12, 3);
            $table->string('status', 20)->default('reserved')->index();
            $table->timestamps();

            $table->unique(['order_id', 'ingredient_id']);
            $table->index(['ingredient_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('order_ingredient_reservations');
    }
};
