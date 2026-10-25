<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('payments') && ! Schema::hasColumn('payments', 'reversed_at')) {
            Schema::table('payments', function (Blueprint $table): void {
                $table->timestamp('reversed_at')->nullable()->after('paid_at');
                $table->foreignId('reversed_by')->nullable()->after('reversed_at')
                    ->constrained('users')->nullOnDelete();
                $table->string('reversal_reason', 500)->nullable()->after('reversed_by');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('payments') && Schema::hasColumn('payments', 'reversed_at')) {
            Schema::table('payments', function (Blueprint $table): void {
                $table->dropConstrainedForeignId('reversed_by');
                $table->dropColumn(['reversed_at', 'reversal_reason']);
            });
        }
    }
};
