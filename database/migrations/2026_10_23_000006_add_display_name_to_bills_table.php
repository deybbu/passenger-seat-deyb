<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('bills') && ! Schema::hasColumn('bills', 'display_name')) {
            Schema::table('bills', function (Blueprint $table): void {
                $table->string('display_name', 80)->nullable()->after('bill_number');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('bills') && Schema::hasColumn('bills', 'display_name')) {
            Schema::table('bills', function (Blueprint $table): void {
                $table->dropColumn('display_name');
            });
        }
    }
};
