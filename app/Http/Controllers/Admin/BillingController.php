<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Order;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Schema;
use Illuminate\View\View;

class BillingController extends Controller
{
    public function index(Request $request): View
    {
        $search = trim($request->string('q')->toString());
        $query = Order::query()
            ->notHeld()
            ->where(function (Builder $orders): void {
                $orders->whereNotNull('table_session_id')
                    ->orWhere('source', 'table_qr');
            })
            ->with(['items', 'tableSession'])
            ->latest();

        if ($search !== '') {
            $term = '%'.addcslashes($search, '%_').'%';
            $query->where(function (Builder $orders) use ($term): void {
                $orders->where('reference_number', 'like', $term)
                    ->orWhere('table_number', 'like', $term)
                    ->orWhere('customer_name', 'like', $term);

                if (Schema::hasColumn('order_items', 'item_name') || Schema::hasColumn('order_items', 'name')) {
                    $orders->orWhereHas('items', function (Builder $items) use ($term): void {
                        $items->where(function (Builder $names) use ($term): void {
                            if (Schema::hasColumn('order_items', 'item_name')) {
                                $names->where('item_name', 'like', $term);
                            }
                            if (Schema::hasColumn('order_items', 'name')) {
                                $method = Schema::hasColumn('order_items', 'item_name') ? 'orWhere' : 'where';
                                $names->{$method}('name', 'like', $term);
                            }
                        });
                    });
                }
            });
        }

        $orders = $query->get();

        return view('admin.billing', compact('orders', 'search'));
    }
}
