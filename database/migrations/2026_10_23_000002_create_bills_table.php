<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('bills')) {
            return;
        }

        Schema::create('bills', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('table_session_id')->constrained()->restrictOnDelete();
            $table->string('reference_number', 60)->unique();
            $table->unsignedSmallInteger('bill_number');
            $table->string('split_method', 16)->nullable();
            $table->string('status', 16)->default('open')->index();
            $table->decimal('subtotal', 10, 2)->default(0);
            $table->decimal('discount', 10, 2)->default(0);
            $table->decimal('vat_exemption', 10, 2)->default(0);
            $table->decimal('vat', 10, 2)->default(0);
            $table->decimal('total', 10, 2)->default(0);
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('paid_at')->nullable();
            $table->timestamps();

            $table->unique(['table_session_id', 'bill_number'], 'bills_session_number_unique');
            $table->index(['table_session_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('bills');
    }
};
