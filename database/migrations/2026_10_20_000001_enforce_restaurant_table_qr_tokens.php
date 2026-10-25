<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('restaurant_tables') || ! Schema::hasColumn('restaurant_tables', 'qr_token')) {
            return;
        }

        DB::table('restaurant_tables')
            ->where(fn ($query) => $query->whereNull('qr_token')->orWhere('qr_token', ''))
            ->orderBy('id')
            ->chunkById(100, function ($tables): void {
                foreach ($tables as $table) {
                    do {
                        $token = Str::random(64);
                    } while (DB::table('restaurant_tables')->where('qr_token', $token)->exists());

                    DB::table('restaurant_tables')->where('id', $table->id)->update([
                        'qr_token' => $token,
                    ]);
                }
            });

        Schema::table('restaurant_tables', function (Blueprint $table): void {
            $table->string('qr_token', 64)->nullable(false)->change();
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('restaurant_tables') || ! Schema::hasColumn('restaurant_tables', 'qr_token')) {
            return;
        }

        Schema::table('restaurant_tables', function (Blueprint $table): void {
            $table->string('qr_token', 64)->nullable()->change();
        });
    }
};
