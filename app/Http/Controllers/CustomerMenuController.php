<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\Category;
use App\Models\MenuItem;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;

class CustomerMenuController extends Controller
{
    public function index(Request $request): View
    {
        $categories = Category::query()
            ->with(['menuItems' => fn ($query) => $query->orderBy('name')])
            ->orderBy('name')
            ->get();

        $selectedCategory = $request->string('category')->toString();

        $menuItems = MenuItem::query()
            ->with('category')
            ->where('is_available', true)
            ->when($selectedCategory !== '', fn ($query) =>
                $query->whereHas('category', fn ($q) => $q->where('slug', $selectedCategory)))
            ->orderBy('name')
            ->get();

        return view('customer.menu', compact('categories', 'menuItems', 'selectedCategory'));
    }
}
