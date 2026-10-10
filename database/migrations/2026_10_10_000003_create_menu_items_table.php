<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('menu_items', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('category_id')->constrained()->cascadeOnUpdate()->restrictOnDelete();
            $table->string('name');
            $table->text('description');
            $table->decimal('price', 10, 2);
            $table->string('image_path')->nullable();
            $table->boolean('is_available')->default(true)->index();
            $table->timestamps();
            $table->unique(['category_id', 'name']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('menu_items');
    }
};
