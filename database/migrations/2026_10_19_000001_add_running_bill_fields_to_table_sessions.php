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
        if (! Schema::hasTable('table_sessions')) {
            return;
        }

        Schema::table('table_sessions', function (Blueprint $table): void {
            if (Schema::hasTable('restaurant_tables') && ! Schema::hasColumn('table_sessions', 'table_id')) {
                $table->foreignId('table_id')->nullable()
                    ->constrained('restaurant_tables')->nullOnDelete();
            }
            if (! Schema::hasColumn('table_sessions', 'status')) {
                $table->string('status', 24)->default('open');
            }
            if (! Schema::hasColumn('table_sessions', 'requested_payment_method')) {
                $table->string('requested_payment_method', 20)->nullable();
            }
            if (! Schema::hasColumn('table_sessions', 'subtotal')) {
                $table->decimal('subtotal', 10, 2)->default(0);
            }
            if (! Schema::hasColumn('table_sessions', 'discount')) {
                $table->decimal('discount', 10, 2)->default(0);
            }
            if (! Schema::hasColumn('table_sessions', 'vat')) {
                $table->decimal('vat', 10, 2)->default(0);
            }
            if (! Schema::hasColumn('table_sessions', 'total')) {
                $table->decimal('total', 10, 2)->default(0);
            }
            if (! Schema::hasColumn('table_sessions', 'paid_at')) {
                $table->timestamp('paid_at')->nullable();
            }
            if (! Schema::hasColumn('table_sessions', 'paid_payment_method')) {
                $table->string('paid_payment_method', 20)->nullable();
            }
            if (Schema::hasTable('users') && ! Schema::hasColumn('table_sessions', 'paid_by')) {
                $table->foreignId('paid_by')->nullable()->constrained('users')->nullOnDelete();
            }
        });

        if (Schema::hasColumn('table_sessions', 'status')) {
            DB::table('table_sessions')
                ->whereNotNull('closed_at')
                ->update(['status' => 'closed']);
            DB::table('table_sessions')
                ->whereNull('closed_at')
                ->whereNotNull('bill_requested_at')
                ->update(['status' => 'bill_requested']);
        }

        if (Schema::hasTable('restaurant_tables') && Schema::hasColumn('table_sessions', 'table_id')) {
            $tableIds = DB::table('restaurant_tables')->pluck('id', 'table_number');
            DB::table('table_sessions')
                ->whereNull('table_id')
                ->get(['id', 'table_number'])
                ->each(function (object $session) use ($tableIds): void {
                    $tableId = $tableIds->get($session->table_number);
                    if ($tableId !== null) {
                        DB::table('table_sessions')->where('id', $session->id)->update([
                            'table_id' => $tableId,
                        ]);
                    }
                });
        }

        if (Schema::hasColumn('table_sessions', 'table_id')) {
            $indexes = collect(Schema::getIndexes('table_sessions'));
            if (! $indexes->contains(fn (array $index): bool => $index['columns'] === ['table_id', 'status'])) {
                Schema::table('table_sessions', function (Blueprint $table): void {
                    $table->index(['table_id', 'status'], 'table_sessions_table_id_status_index');
                });
            }
        }
    }

    public function down(): void
    {
        if (! Schema::hasTable('table_sessions')) {
            return;
        }

        if (collect(Schema::getIndexes('table_sessions'))->contains(
            fn (array $index): bool => $index['name'] === 'table_sessions_table_id_status_index',
        )) {
            Schema::table('table_sessions', function (Blueprint $table): void {
                $table->dropIndex('table_sessions_table_id_status_index');
            });
        }

        if (Schema::hasColumn('table_sessions', 'paid_by')) {
            Schema::table('table_sessions', function (Blueprint $table): void {
                $table->dropConstrainedForeignId('paid_by');
            });
        }
        if (Schema::hasColumn('table_sessions', 'table_id')) {
            Schema::table('table_sessions', function (Blueprint $table): void {
                $table->dropConstrainedForeignId('table_id');
            });
        }

        $columns = array_values(array_filter([
            'status',
            'requested_payment_method',
            'subtotal',
            'discount',
            'vat',
            'total',
            'paid_at',
            'paid_payment_method',
        ], fn (string $column): bool => Schema::hasColumn('table_sessions', $column)));

        if ($columns !== []) {
            Schema::table('table_sessions', function (Blueprint $table) use ($columns): void {
                $table->dropColumn($columns);
            });
        }
    }
};
