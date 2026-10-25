@extends(auth()->user()->hasRole('cashier') ? 'layouts.pos' : 'layouts.staff')

@section('title', 'Walk-in order')

@section('content')
    @php
        $oldItems = old('items', []);
        $toReactItem = fn ($item) => [
            'id' => (string) $item->id,
            'name' => (string) $item->name,
            'price' => (float) $item->price,
            'orderable' => $item->isOrderable(),
            'initial' => (int) ($oldItems[$item->id] ?? 0),
        ];
        $reactCategories = $categories->map(fn ($category) => [
            'name' => (string) $category->name,
            'items' => $category->items->map($toReactItem)->values(),
        ])->values();
        $reactUncategorizedItems = $uncategorizedItems->map($toReactItem)->values();
    @endphp

    <h1 class="mb-4 font-display text-4xl font-bold uppercase leading-none">Walk-in order</h1>

    <form method="POST" action="{{ route('staff.orders.store') }}" class="grid gap-6 lg:grid-cols-3">
        @csrf
        <div class="contents" data-react-root="walk-in-order">
            <script type="application/json" data-props>
                @json([
                    'categories' => $reactCategories,
                    'uncategorizedItems' => $reactUncategorizedItems,
                    'type' => old('type', 'dine_in'),
                    'fields' => [
                        'tableNumber' => old('table_number'),
                        'customerName' => old('customer_name'),
                        'customerPhone' => old('customer_phone'),
                        'specialInstructions' => old('special_instructions'),
                    ],
                ])
            </script>
        </div>
    </form>
@endsection
