<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\MenuItem;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class CartController extends Controller
{
    public function show(Request $request): View
    {
        return view('customer.cart', ['cart' => $this->cart($request)]);
    }

    public function add(Request $request, MenuItem $menuItem): RedirectResponse
    {
        abort_unless($menuItem->is_available, 422, 'This menu item is currently unavailable.');
        $cart = $this->cart($request);
        $id = (string) $menuItem->id;
        $cart[$id] = min(99, (int) ($cart[$id] ?? 0) + 1);
        $request->session()->put('cart', $cart);

        return back()->with('success', $menuItem->name . ' added to your cart.');
    }

    public function update(Request $request, MenuItem $menuItem): RedirectResponse
    {
        $validated = $request->validate(['quantity' => ['required', 'integer', 'min:0', 'max:99']]);
        $cart = $this->cart($request);
        $id = (string) $menuItem->id;

        if ((int) $validated['quantity'] === 0) {
            unset($cart[$id]);
        } else {
            abort_unless($menuItem->is_available, 422);
            $cart[$id] = (int) $validated['quantity'];
        }

        $request->session()->put('cart', $cart);

        return redirect()->route('cart.show')->with('success', 'Cart updated.');
    }

    public function remove(Request $request, MenuItem $menuItem): RedirectResponse
    {
        $cart = $this->cart($request);
        unset($cart[(string) $menuItem->id]);
        $request->session()->put('cart', $cart);

        return redirect()->route('cart.show')->with('success', 'Item removed.');
    }

    private function cart(Request $request): array
    {
        $raw = $request->session()->get('cart', []);
        if (!is_array($raw)) {
            return [];
        }

        $ids = array_values(array_filter(array_map('intval', array_keys($raw))));
        $availableIds = MenuItem::query()->whereIn('id', $ids)->where('is_available', true)->pluck('id')->map(fn ($id) => (string) $id)->all();
        $cart = [];

        foreach ($raw as $id => $quantity) {
            if (in_array((string) $id, $availableIds, true) && filter_var($quantity, FILTER_VALIDATE_INT) !== false) {
                $cart[(string) $id] = max(1, min(99, (int) $quantity));
            }
        }

        $request->session()->put('cart', $cart);

        return $cart;
    }
}
