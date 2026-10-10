<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('orders', function (Blueprint $table): void {
            $table->id();
            $table->string('reference_number', 32)->unique();
            $table->string('type', 20);
            $table->string('table_number', 20)->nullable();
            $table->text('special_instructions')->nullable();
            $table->decimal('total_amount', 10, 2)->default(0);
            $table->string('status', 20)->default('pending')->index();
            $table->string('payment_status', 20)->default('unpaid')->index();
            $table->string('payment_method', 20)->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('paid_at')->nullable();
            $table->timestamps();
            $table->index(['created_at', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('orders');
    }
};
