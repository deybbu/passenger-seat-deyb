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
        Schema::create('roles', function (Blueprint $table): void {
            $table->id();
            $table->string('key', 40)->unique();
            $table->string('name', 80)->unique();
            $table->boolean('is_system')->default(true);
            $table->timestamps();
        });

        Schema::create('role_permissions', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('role_id')->constrained()->cascadeOnDelete();
            $table->string('module_key', 50);
            $table->boolean('can_view')->default(false);
            $table->timestamps();
            $table->unique(['role_id', 'module_key']);
        });

        $roles = [
            'super_admin' => 'Super Admin',
            'restaurant_manager' => 'Restaurant Manager',
            'head_chef' => 'Head Chef',
            'cashier' => 'Cashier',
            'inventory_user' => 'Inventory User',
        ];

        foreach ($roles as $key => $name) {
            DB::table('roles')->insert([
                'key' => $key,
                'name' => $name,
                'is_system' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        $modules = [
            'orders_pos',
            'menu_recipes',
            'inventory_receiving',
            'waste',
            'reservations',
            'reports',
            'settings',
        ];
        $permissions = [
            'super_admin' => [true, true, true, true, true, true, true],
            'restaurant_manager' => [true, true, true, true, true, true, false],
            'head_chef' => [false, true, true, true, false, false, false],
            'cashier' => [true, false, false, false, false, false, false],
            'inventory_user' => [false, false, false, true, false, false, false],
        ];

        foreach ($permissions as $roleKey => $access) {
            $roleId = DB::table('roles')->where('key', $roleKey)->value('id');
            foreach ($modules as $index => $module) {
                DB::table('role_permissions')->insert([
                    'role_id' => $roleId,
                    'module_key' => $module,
                    'can_view' => $access[$index],
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }
        }

        Schema::table('users', function (Blueprint $table): void {
            $table->foreignId('role_id')->nullable()->constrained()->nullOnDelete();
            $table->boolean('is_active')->default(true)->index();
        });

        $legacyRoleMap = [
            'admin' => 'super_admin',
            'kitchen' => 'head_chef',
            'cashier' => 'cashier',
            'inventory' => 'inventory_user',
        ];
        foreach ($legacyRoleMap as $legacyRole => $roleKey) {
            $roleId = DB::table('roles')->where('key', $roleKey)->value('id');
            DB::table('users')->where('role', $legacyRole)->update(['role_id' => $roleId]);
        }

        Schema::create('system_settings', function (Blueprint $table): void {
            $table->string('key', 120)->primary();
            $table->text('value')->nullable();
            $table->boolean('is_secret')->default(false);
            $table->string('group', 50)->nullable()->index();
            $table->timestamps();
        });

        Schema::create('purchase_orders', function (Blueprint $table): void {
            $table->id();
            $table->string('reference', 40)->unique();
            $table->string('supplier_name', 150);
            $table->string('status', 24)->default('open')->index();
            $table->string('invoice_number', 80)->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('ordered_at')->nullable();
            $table->date('expected_at')->nullable();
            $table->timestamp('closed_at')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();
        });

        Schema::create('purchase_order_items', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('purchase_order_id')->constrained()->cascadeOnDelete();
            $table->foreignId('ingredient_id')->constrained()->restrictOnDelete();
            $table->decimal('ordered_quantity', 12, 3);
            $table->decimal('received_quantity', 12, 3)->default(0);
            $table->decimal('expected_unit_cost', 12, 4)->nullable();
            $table->timestamps();
            $table->index(['purchase_order_id', 'ingredient_id']);
        });

        Schema::create('restaurant_tables', function (Blueprint $table): void {
            $table->id();
            $table->string('table_number', 10)->unique();
            $table->string('name', 80);
            $table->boolean('is_enabled')->default(false);
            $table->string('qr_token', 64)->nullable()->unique();
            $table->timestamps();
        });

        Schema::create('event_packages', function (Blueprint $table): void {
            $table->id();
            $table->string('name', 120)->unique();
            $table->text('description')->nullable();
            $table->decimal('price_per_guest', 10, 2)->nullable();
            $table->boolean('is_active')->default(true)->index();
            $table->timestamps();
        });

        Schema::create('event_package_menu_item', function (Blueprint $table): void {
            $table->foreignId('event_package_id')->constrained()->cascadeOnDelete();
            $table->foreignId('menu_item_id')->constrained()->restrictOnDelete();
            $table->decimal('quantity_per_guest', 10, 3)->default(1);
            $table->primary(['event_package_id', 'menu_item_id']);
        });

        Schema::table('event_inquiries', function (Blueprint $table): void {
            $table->decimal('budget', 12, 2)->nullable();
            $table->foreignId('event_package_id')->nullable()->constrained()->nullOnDelete();
        });

        Schema::create('event_inquiry_notes', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('event_inquiry_id')->constrained()->cascadeOnDelete();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->text('note');
            $table->timestamps();
        });

        Schema::create('event_blackout_dates', function (Blueprint $table): void {
            $table->id();
            $table->date('date')->unique();
            $table->string('reason', 255)->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        Schema::create('faqs', function (Blueprint $table): void {
            $table->id();
            $table->string('question', 500);
            $table->text('answer');
            $table->boolean('is_active')->default(true)->index();
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();
        });

        Schema::create('unanswered_questions', function (Blueprint $table): void {
            $table->id();
            $table->text('question');
            $table->string('status', 20)->default('unanswered')->index();
            $table->foreignId('faq_id')->nullable()->constrained()->nullOnDelete();
            $table->timestamp('answered_at')->nullable();
            $table->timestamps();
        });

        Schema::create('chatbot_knowledge_sources', function (Blueprint $table): void {
            $table->id();
            $table->string('title', 150);
            $table->string('disk', 40)->default('local');
            $table->string('path', 500)->nullable();
            $table->string('mime_type', 120)->nullable();
            $table->unsignedBigInteger('file_size')->nullable();
            $table->longText('content')->nullable();
            $table->boolean('is_active')->default(true)->index();
            $table->foreignId('uploaded_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        Schema::create('admin_alert_rules', function (Blueprint $table): void {
            $table->id();
            $table->string('rule_key', 80)->unique();
            $table->string('trigger', 180);
            $table->string('action', 180);
            $table->boolean('is_enabled')->default(true);
            $table->json('configuration')->nullable();
            $table->timestamps();
        });

        Schema::create('activity_logs', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('action', 120);
            $table->string('module', 80)->index();
            $table->string('subject_type', 150)->nullable();
            $table->unsignedBigInteger('subject_id')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();
            $table->index(['subject_type', 'subject_id']);
        });

        Schema::create('notifications', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->string('type');
            $table->morphs('notifiable');
            $table->text('data');
            $table->timestamp('read_at')->nullable();
            $table->timestamps();
        });

        Schema::table('menu_items', function (Blueprint $table): void {
            $table->timestamp('archived_at')->nullable()->index();
            $table->string('collection_type', 24)->default('Regular')->index();
        });

        Schema::create('menu_item_variants', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('menu_item_id')->constrained()->cascadeOnDelete();
            $table->string('name', 100);
            $table->decimal('price', 10, 2);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->unique(['menu_item_id', 'name']);
        });

        Schema::create('menu_tags', function (Blueprint $table): void {
            $table->id();
            $table->string('name', 80)->unique();
            $table->timestamps();
        });

        Schema::create('menu_item_menu_tag', function (Blueprint $table): void {
            $table->foreignId('menu_item_id')->constrained()->cascadeOnDelete();
            $table->foreignId('menu_tag_id')->constrained()->cascadeOnDelete();
            $table->primary(['menu_item_id', 'menu_tag_id']);
        });

        Schema::table('order_items', function (Blueprint $table): void {
            $table->foreignId('menu_item_variant_id')->nullable()->constrained('menu_item_variants')->nullOnDelete();
            $table->string('variant_name_snapshot', 100)->nullable();
            $table->string('category_name_snapshot', 100)->nullable();
        });

        Schema::table('orders', function (Blueprint $table): void {
            $table->timestamp('held_at')->nullable()->index();
        });

        Schema::table('inventory_logs', function (Blueprint $table): void {
            $table->string('stage', 40)->nullable()->index();
        });

        Schema::table('inventory_stock_lots', function (Blueprint $table): void {
            $table->foreignId('purchase_order_item_id')->nullable()
                ->constrained('purchase_order_items')->nullOnDelete();
            $table->string('supplier_invoice_number', 80)->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('inventory_stock_lots', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('purchase_order_item_id');
            $table->dropColumn('supplier_invoice_number');
        });
        Schema::table('inventory_logs', function (Blueprint $table): void {
            $table->dropIndex(['stage']);
            $table->dropColumn('stage');
        });
        Schema::table('orders', function (Blueprint $table): void {
            $table->dropIndex(['held_at']);
            $table->dropColumn('held_at');
        });
        Schema::table('order_items', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('menu_item_variant_id');
            $table->dropColumn(['variant_name_snapshot', 'category_name_snapshot']);
        });
        Schema::dropIfExists('menu_item_menu_tag');
        Schema::dropIfExists('menu_tags');
        Schema::dropIfExists('menu_item_variants');
        Schema::table('menu_items', function (Blueprint $table): void {
            $table->dropIndex(['archived_at']);
            $table->dropIndex(['collection_type']);
            $table->dropColumn(['archived_at', 'collection_type']);
        });
        Schema::dropIfExists('notifications');
        Schema::dropIfExists('activity_logs');
        Schema::dropIfExists('admin_alert_rules');
        Schema::dropIfExists('chatbot_knowledge_sources');
        Schema::dropIfExists('unanswered_questions');
        Schema::dropIfExists('faqs');
        Schema::dropIfExists('event_blackout_dates');
        Schema::dropIfExists('event_inquiry_notes');
        Schema::table('event_inquiries', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('event_package_id');
            $table->dropColumn('budget');
        });
        Schema::dropIfExists('event_package_menu_item');
        Schema::dropIfExists('event_packages');
        Schema::dropIfExists('restaurant_tables');
        Schema::dropIfExists('purchase_order_items');
        Schema::dropIfExists('purchase_orders');
        Schema::dropIfExists('system_settings');
        Schema::table('users', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('role_id');
            $table->dropIndex(['is_active']);
            $table->dropColumn('is_active');
        });
        Schema::dropIfExists('role_permissions');
        Schema::dropIfExists('roles');
    }
};
