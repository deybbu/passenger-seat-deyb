<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('inventory_logs')) {
            return;
        }

        if (Schema::hasColumn('inventory_logs', 'user_id')) {
            $column = collect(Schema::getColumns('inventory_logs'))->firstWhere('name', 'user_id');
            $nullable = (bool) ($column['nullable'] ?? false);
            $foreignKey = collect(Schema::getForeignKeys('inventory_logs'))
                ->first(fn (array $key): bool => in_array('user_id', $key['columns'], true));
            $setsNullOnDelete = strtolower((string) ($foreignKey['on_delete'] ?? '')) === 'set null';

            if (! ($nullable && $setsNullOnDelete)) {
                if ($foreignKey !== null) {
                    Schema::table('inventory_logs', function (Blueprint $table) use ($foreignKey): void {
                        $table->dropForeign($foreignKey['name']);
                    });
                }

                if (! $nullable) {
                    Schema::table('inventory_logs', function (Blueprint $table): void {
                        $table->foreignId('user_id')->nullable()->change();
                    });
                }

                Schema::table('inventory_logs', function (Blueprint $table): void {
                    $table->foreign('user_id')->references('id')->on('users')->cascadeOnUpdate()->nullOnDelete();
                });
            }
        }

        foreach (['stock_before', 'stock_after'] as $column) {
            if (! Schema::hasColumn('inventory_logs', $column)) {
                Schema::table('inventory_logs', function (Blueprint $table) use ($column): void {
                    $table->decimal($column, 10, 3)->nullable();
                });
            }
        }

        if (! Schema::hasColumn('inventory_logs', 'notes')) {
            Schema::table('inventory_logs', function (Blueprint $table): void {
                $table->string('notes', 500)->nullable();
            });

            if (Schema::hasColumn('inventory_logs', 'note')) {
                DB::table('inventory_logs')->update(['notes' => DB::raw('note')]);
            }
        }
    }

    public function down(): void
    {
        // Keep nullable actors so existing system-generated inventory records remain valid.
    }
};
