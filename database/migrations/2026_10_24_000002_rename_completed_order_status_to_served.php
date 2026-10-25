<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::table('orders')->where('status', 'completed')->update(['status' => 'served']);
        DB::table('order_status_histories')->where('from_status', 'completed')->update(['from_status' => 'served']);
        DB::table('order_status_histories')->where('to_status', 'completed')->update(['to_status' => 'served']);
    }

    public function down(): void
    {
        DB::table('orders')->where('status', 'served')->update(['status' => 'completed']);
        DB::table('order_status_histories')->where('from_status', 'served')->update(['from_status' => 'completed']);
        DB::table('order_status_histories')->where('to_status', 'served')->update(['to_status' => 'completed']);
    }
};
