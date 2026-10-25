<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('orders') || Schema::hasColumn('orders', 'held_at')) {
            return;
        }

        Schema::table('orders', function (Blueprint $table): void {
            $table->timestamp('held_at')->nullable()->index();
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('orders') || ! Schema::hasColumn('orders', 'held_at')) {
            return;
        }

        Schema::table('orders', function (Blueprint $table): void {
            $table->dropIndex(['held_at']);
            $table->dropColumn('held_at');
        });
    }
};
