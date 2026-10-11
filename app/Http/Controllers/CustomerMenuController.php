<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\MenuCategory;
use App\Models\MenuItem;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;

class CustomerMenuController extends Controller
{
    public function index(Request $request): View
    {
        $categories = MenuCategory::query()
            ->where('is_active', true)
            ->withCount([
                'items as active_items_count' => fn ($query) => $query
                    ->where('is_active', true)
                    ->where('is_available', true),
            ])
            ->orderBy('name')
            ->get();

        $requestedCategory = $request->string('category')->toString();
        $selectedCategory = $categories->contains('slug', $requestedCategory) ? $requestedCategory : '';
        $search = trim($request->string('q')->toString());

        $menuItems = MenuItem::query()
            ->with('category')
            ->where('is_active', true)
            ->where('is_available', true)
            ->when($selectedCategory !== '', fn ($query) => $query->whereHas(
                'category',
                fn ($categoryQuery) => $categoryQuery->where('slug', $selectedCategory)
            ))
            ->when($search !== '', fn ($query) => $query->where(
                fn ($searchQuery) => $searchQuery
                    ->where('name', 'like', "%{$search}%")
                    ->orWhere('description', 'like', "%{$search}%")
            ))
            ->orderBy('name')
            ->get();

        return view('customer.menu', compact('categories', 'menuItems', 'selectedCategory', 'search'));
    }
}
