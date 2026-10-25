<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('orders')) {
            Schema::table('orders', function (Blueprint $table): void {
                if (! Schema::hasColumn('orders', 'discount_type')) {
                    $table->string('discount_type', 30)->nullable();
                }

                if (! Schema::hasColumn('orders', 'discount_rate')) {
                    $table->decimal('discount_rate', 5, 2)->nullable();
                }

                if (! Schema::hasColumn('orders', 'discount_amount')) {
                    $table->decimal('discount_amount', 10, 2)->default(0);
                }

                if (! Schema::hasColumn('orders', 'discount_cardholder_name')) {
                    $table->string('discount_cardholder_name', 120)->nullable();
                }

                if (! Schema::hasColumn('orders', 'discount_id_number')) {
                    $table->string('discount_id_number', 80)->nullable();
                }

                if (! Schema::hasColumn('orders', 'discount_tin')) {
                    $table->string('discount_tin', 30)->nullable();
                }

                if (! Schema::hasColumn('orders', 'submission_token')) {
                    $table->string('submission_token', 64)->nullable();
                }
            });

            $hasSubmissionTokenUniqueIndex = collect(Schema::getIndexes('orders'))
                ->contains(fn (array $index): bool => ($index['unique'] ?? false)
                    && $index['columns'] === ['submission_token']);

            if (Schema::hasColumn('orders', 'submission_token') && ! $hasSubmissionTokenUniqueIndex) {
                Schema::table('orders', function (Blueprint $table): void {
                    $table->unique('submission_token', 'orders_submission_token_unique');
                });
            }
        }

        if (Schema::hasTable('inventory_logs') && Schema::hasTable('orders')
            && ! Schema::hasColumn('inventory_logs', 'order_id')) {
            Schema::table('inventory_logs', function (Blueprint $table): void {
                $table->foreignId('order_id')->nullable()->constrained()->nullOnDelete();
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('inventory_logs') && Schema::hasColumn('inventory_logs', 'order_id')) {
            Schema::table('inventory_logs', function (Blueprint $table): void {
                $table->dropConstrainedForeignId('order_id');
            });
        }

        if (Schema::hasTable('orders')) {
            $submissionTokenUniqueIndex = collect(Schema::getIndexes('orders'))
                ->first(fn (array $index): bool => ($index['unique'] ?? false)
                    && $index['columns'] === ['submission_token']);

            if ($submissionTokenUniqueIndex !== null) {
                Schema::table('orders', function (Blueprint $table) use ($submissionTokenUniqueIndex): void {
                    $table->dropUnique($submissionTokenUniqueIndex['name']);
                });
            }

            $columns = array_filter([
                Schema::hasColumn('orders', 'discount_type') ? 'discount_type' : null,
                Schema::hasColumn('orders', 'discount_rate') ? 'discount_rate' : null,
                Schema::hasColumn('orders', 'discount_amount') ? 'discount_amount' : null,
                Schema::hasColumn('orders', 'discount_cardholder_name') ? 'discount_cardholder_name' : null,
                Schema::hasColumn('orders', 'discount_id_number') ? 'discount_id_number' : null,
                Schema::hasColumn('orders', 'discount_tin') ? 'discount_tin' : null,
                Schema::hasColumn('orders', 'submission_token') ? 'submission_token' : null,
            ]);

            if ($columns !== []) {
                Schema::table('orders', function (Blueprint $table) use ($columns): void {
                    $table->dropColumn($columns);
                });
            }
        }
    }
};
