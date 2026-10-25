<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('orders') && Schema::hasTable('table_sessions')
            && ! Schema::hasColumn('orders', 'table_session_id')) {
            Schema::table('orders', function (Blueprint $table): void {
                $table->foreignId('table_session_id')->nullable()
                    ->constrained('table_sessions')->nullOnDelete();
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('orders') && Schema::hasColumn('orders', 'table_session_id')) {
            Schema::table('orders', function (Blueprint $table): void {
                $table->dropConstrainedForeignId('table_session_id');
            });
        }
    }
};
