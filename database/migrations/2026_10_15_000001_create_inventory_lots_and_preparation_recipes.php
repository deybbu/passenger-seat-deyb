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
        Schema::table('ingredients', function (Blueprint $table): void {
            if (! Schema::hasColumn('ingredients', 'category')) {
                $table->string('category', 80)->nullable();
            }

            if (! Schema::hasColumn('ingredients', 'unit_cost')) {
                $table->decimal('unit_cost', 12, 4)->nullable();
            }

            if (! Schema::hasColumn('ingredients', 'yield_loss_percent')) {
                $table->decimal('yield_loss_percent', 5, 2)->default(0);
            }
        });

        Schema::create('inventory_packages', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('ingredient_id')->constrained()->cascadeOnUpdate()->restrictOnDelete();
            $table->string('barcode', 64)->unique();
            $table->string('label', 120);
            $table->decimal('package_quantity', 12, 3);
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->index(['ingredient_id', 'is_active']);
        });

        Schema::create('inventory_stock_lots', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('ingredient_id')->constrained()->cascadeOnUpdate()->restrictOnDelete();
            $table->foreignId('package_id')->nullable()->constrained('inventory_packages')->cascadeOnUpdate()->nullOnDelete();
            $table->string('batch_number', 120)->nullable();
            $table->date('expires_at')->nullable();
            $table->decimal('received_quantity', 12, 3);
            $table->decimal('remaining_quantity', 12, 3);
            $table->decimal('unit_cost', 12, 4)->nullable();
            $table->string('receipt_token', 64)->unique();
            $table->foreignId('received_by')->nullable()->constrained('users')->cascadeOnUpdate()->nullOnDelete();
            $table->timestamp('received_at')->nullable();
            $table->timestamps();

            $table->index(['ingredient_id', 'expires_at', 'remaining_quantity'], 'inventory_lots_fefo_index');
        });

        DB::table('ingredients')
            ->select(['id', 'current_stock', 'created_at'])
            ->orderBy('id')
            ->chunkById(500, function ($ingredients): void {
                foreach ($ingredients as $ingredient) {
                    $quantity = (float) $ingredient->current_stock;

                    if ($quantity <= 0) {
                        continue;
                    }

                    $createdAt = $ingredient->created_at ?? now();
                    DB::table('inventory_stock_lots')->insert([
                        'ingredient_id' => $ingredient->id,
                        'batch_number' => null,
                        'expires_at' => null,
                        'received_quantity' => $quantity,
                        'remaining_quantity' => $quantity,
                        'unit_cost' => null,
                        'receipt_token' => 'opening-stock-'.$ingredient->id,
                        'received_at' => $createdAt,
                        'created_at' => $createdAt,
                        'updated_at' => now(),
                    ]);
                }
            });

        Schema::create('preparation_recipes', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('output_ingredient_id')->unique()->constrained('ingredients')->cascadeOnUpdate()->restrictOnDelete();
            $table->decimal('yield_quantity', 12, 3);
            $table->timestamps();
        });

        Schema::create('preparation_recipe_lines', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('preparation_recipe_id')->constrained()->cascadeOnUpdate()->cascadeOnDelete();
            $table->foreignId('ingredient_id')->constrained('ingredients')->cascadeOnUpdate()->restrictOnDelete();
            $table->decimal('quantity', 12, 3);
            $table->timestamps();

            $table->unique(['preparation_recipe_id', 'ingredient_id'], 'preparation_recipe_ingredient_unique');
        });

        Schema::table('inventory_logs', function (Blueprint $table): void {
            if (! Schema::hasColumn('inventory_logs', 'stock_lot_id')) {
                $table->foreignId('stock_lot_id')
                    ->nullable()
                    ->constrained('inventory_stock_lots')
                    ->cascadeOnUpdate()
                    ->nullOnDelete();
            }
        });
    }

    public function down(): void
    {
        if (Schema::hasColumn('inventory_logs', 'stock_lot_id')) {
            Schema::table('inventory_logs', function (Blueprint $table): void {
                $table->dropConstrainedForeignId('stock_lot_id');
            });
        }

        Schema::dropIfExists('preparation_recipe_lines');
        Schema::dropIfExists('preparation_recipes');
        Schema::dropIfExists('inventory_stock_lots');
        Schema::dropIfExists('inventory_packages');

        Schema::table('ingredients', function (Blueprint $table): void {
            $columns = array_filter([
                Schema::hasColumn('ingredients', 'category') ? 'category' : null,
                Schema::hasColumn('ingredients', 'unit_cost') ? 'unit_cost' : null,
                Schema::hasColumn('ingredients', 'yield_loss_percent') ? 'yield_loss_percent' : null,
            ]);

            if ($columns !== []) {
                $table->dropColumn($columns);
            }
        });
    }
};
