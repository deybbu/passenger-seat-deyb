<?php

namespace App\Http\Controllers;

use App\Models\MenuCategory;
use App\Models\MenuItem;
use Illuminate\Contracts\View\View;

class LandingController extends Controller
{
    public function index(): View
    {
        $categories = MenuCategory::query()
            ->where('is_active', true)
            ->orderBy('name')
            ->get();

        $featuredItems = MenuItem::query()
            ->with('category')
            ->where('is_active', true)
            ->where('is_available', true)
            ->where('is_featured', true)
            ->orderBy('name')
            ->limit(3)
            ->get();

        return view('landing.home', compact('categories', 'featuredItems'));
    }
}
