<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('reservations')) {
            Schema::create('reservations', function (Blueprint $table): void {
                $table->id();
                $table->string('guest_name', 120);
                $table->string('guest_phone', 30)->nullable();
                $table->unsignedTinyInteger('party_size');
                $table->dateTime('reserved_at');
                $table->string('status')->default('upcoming')->index();
                $table->string('notes', 500)->nullable();
                $table->timestamps();
            });
        }
    }

    public function down(): void
    {
        // Existing reservations may predate this migration; preserve them on rollback.
    }
};
