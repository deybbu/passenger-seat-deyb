<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('table_sessions', function (Blueprint $table): void {
            $table->id();
            $table->string('table_number', 10);
            $table->string('public_token', 64)->unique();
            $table->string('active_table_number', 10)->nullable()->unique();
            $table->unsignedTinyInteger('guest_count')->nullable();
            $table->foreignId('opened_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('closed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('opened_at');
            $table->timestamp('expires_at')->nullable();
            $table->timestamp('bill_requested_at')->nullable();
            $table->timestamp('closed_at')->nullable();
            $table->timestamps();

            $table->index(['table_number', 'closed_at', 'expires_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('table_sessions');
    }
};
