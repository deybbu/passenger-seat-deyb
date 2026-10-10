<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Category;
use App\Models\MenuItem;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

class MenuAvailabilityController extends Controller
{
    public function index(): View
    {
        return view('admin.menu', [
            'categories' => Category::orderBy('sort_order')
                ->with(['items' => fn ($q) => $q->orderBy('sort_order')->with('ingredients')])
                ->get(),
        ]);
    }

    public function toggle(MenuItem $item): RedirectResponse
    {
        $item->update(['is_available' => ! $item->is_available]);

        return back()->with('status', $item->name . ($item->is_available ? ' is back on the menu.' : ' marked as sold out.'));
    }
}
