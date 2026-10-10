<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('ingredients', function (Blueprint $table): void {
            $table->id();
            $table->string('name')->unique();
            $table->decimal('current_stock', 12, 3)->default(0);
            $table->string('unit', 20);
            $table->decimal('min_threshold', 12, 3)->default(0);
            $table->boolean('manual_override')->default(false);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ingredients');
    }
};
