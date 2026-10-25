@extends('layouts.kitchen')

@section('title', 'Kitchen')

@section('content')
    <main class="wrap">
        <section id="v-orders" data-kitchen-panel="orders" role="tabpanel" aria-label="Kitchen orders">
            <div class="note">Orders appear here as soon as they are placed. Starting preparation deducts each order's reserved ingredients automatically.</div>
            <div class="bar" role="group" aria-label="Filter orders">
                <button type="button" class="chip on" data-order-filter="all" aria-pressed="true">All <span data-filter-count></span></button>
                <button type="button" class="chip" data-order-filter="pending" aria-pressed="false">Pending <span data-filter-count></span></button>
                <button type="button" class="chip" data-order-filter="preparing" aria-pressed="false">Preparing <span data-filter-count></span></button>
                <button type="button" class="chip" data-order-filter="ready" aria-pressed="false">Ready <span data-filter-count></span></button>
            </div>
            <div data-kitchen-board>
                @include('staff.kitchen._board', compact('pending', 'preparing', 'ready'))
            </div>
        </section>

        <section id="v-inv" data-kitchen-panel="inv" role="tabpanel" aria-label="Kitchen inventory" hidden>
            <div data-kitchen-inventory>
                @include('staff.kitchen._inventory', compact('ingredients', 'lowStockCount', 'menuItems', 'inventoryLogs'))
            </div>
        </section>
    </main>
@endsection
