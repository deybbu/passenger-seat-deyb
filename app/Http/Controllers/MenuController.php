<?php
namespace App\Http\Controllers;

use Illuminate\Support\Facades\DB;

class MenuController extends Controller
{
    public function index()
    {
        $menuItems = DB::table('menu_items')
            ->where('is_active', 1)
            ->where('is_available', 1)
            ->orderBy('name')
            ->get();

        return view('menu', compact('menuItems'));
    }
}
