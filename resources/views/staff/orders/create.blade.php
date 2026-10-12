@extends(auth()->user()->hasRole('cashier') ? 'layouts.pos' : 'layouts.staff')

@section('title', 'Walk-in order')

@section('content')
    @php
        $oldItems = old('items', []);
        $prices = [];
        $initial = [];
        foreach ($categories as $category) {
            foreach ($category->items as $item) {
                $prices[$item->id] = (float) $item->price;
                $initial[$item->id] = (int) ($oldItems[$item->id] ?? 0);
            }
        }
        foreach ($uncategorizedItems as $item) {
            $prices[$item->id] = (float) $item->price;
            $initial[$item->id] = (int) ($oldItems[$item->id] ?? 0);
        }
    @endphp

    <h1 class="mb-4 font-display text-4xl font-bold uppercase leading-none">Walk-in order</h1>

    <form method="POST" action="{{ route('staff.orders.store') }}" class="grid gap-6 lg:grid-cols-3"
          x-data="{
              qty: @js($initial),
              prices: @js($prices),
              type: @js(old('type', 'dine_in')),
              get total() { return Object.keys(this.qty).reduce((sum, id) => sum + (this.prices[id] || 0) * (parseInt(this.qty[id]) || 0), 0); },
              get count() { return Object.values(this.qty).reduce((sum, q) => sum + (parseInt(q) || 0), 0); },
              bump(id, by) { this.qty[id] = Math.min(20, Math.max(0, (parseInt(this.qty[id]) || 0) + by)); },
          }">
        @csrf

        <div class="space-y-6 lg:col-span-2">
            @if ($categories->isEmpty() && $uncategorizedItems->isEmpty())
                <p class="pass p-6 text-center text-sm text-gray-500">No active menu items are available to add to an order.</p>
            @else
            @foreach ($categories as $category)
                <section>
                    <h2 class="mb-2 border-l-8 border-brand-600 pl-3 font-display text-2xl font-bold uppercase">{{ $category->name }}</h2>
                    <div class="pass divide-y divide-gray-200">
                        @foreach ($category->items as $item)
                            @include('staff.orders._menu-item', ['item' => $item])
                        @endforeach
                    </div>
                </section>
            @endforeach
            @if ($uncategorizedItems->isNotEmpty())
                <section>
                    <h2 class="mb-2 border-l-8 border-brand-600 pl-3 font-display text-2xl font-bold uppercase">Uncategorized</h2>
                    <div class="pass divide-y divide-gray-200">
                        @foreach ($uncategorizedItems as $item)
                            @include('staff.orders._menu-item', ['item' => $item])
                        @endforeach
                    </div>
                </section>
            @endif
            @endif
        </div>

        <aside class="lg:col-span-1">
            <div class="pass sticky top-4 p-4">
                <h2 class="font-display text-2xl font-bold uppercase">Order details</h2>

                <div class="mt-3 grid grid-cols-2 gap-2">
                    <label class="flex cursor-pointer items-center gap-2 rounded-lg border-2 p-3 text-sm font-semibold" :class="type === 'dine_in' ? 'border-brand-600 bg-brand-50' : 'border-gray-200'">
                        <input type="radio" name="type" value="dine_in" x-model="type" class="accent-brand-600"> Dine-in
                    </label>
                    <label class="flex cursor-pointer items-center gap-2 rounded-lg border-2 p-3 text-sm font-semibold" :class="type === 'take_out' ? 'border-brand-600 bg-brand-50' : 'border-gray-200'">
                        <input type="radio" name="type" value="take_out" x-model="type" class="accent-brand-600"> Take-out
                    </label>
                </div>

                <div class="mt-3" x-show="type === 'dine_in'" x-cloak>
                    <label for="table_number" class="mb-1 block text-sm font-semibold">Table number</label>
                    <input id="table_number" name="table_number" type="text" maxlength="10" value="{{ old('table_number') }}" :required="type === 'dine_in'"
                           class="w-full rounded-lg border border-gray-300 px-3 py-2 focus:border-brand-600 focus:ring-2 focus:ring-brand-100">
                </div>

                <label for="customer_name" class="mb-1 mt-3 block text-sm font-semibold">Customer name <span class="font-normal text-gray-500">(optional)</span></label>
                <input id="customer_name" name="customer_name" type="text" maxlength="60" value="{{ old('customer_name') }}"
                       class="w-full rounded-lg border border-gray-300 px-3 py-2 focus:border-brand-600 focus:ring-2 focus:ring-brand-100">

                <label for="customer_phone" class="mb-1 mt-3 block text-sm font-semibold">Mobile number <span class="font-normal text-gray-500">(optional)</span></label>
                <input id="customer_phone" name="customer_phone" type="tel" maxlength="20" value="{{ old('customer_phone') }}"
                       class="w-full rounded-lg border border-gray-300 px-3 py-2 focus:border-brand-600 focus:ring-2 focus:ring-brand-100">

                <label for="special_instructions" class="mb-1 mt-3 block text-sm font-semibold">Special instructions</label>
                <textarea id="special_instructions" name="special_instructions" rows="3" maxlength="500"
                          class="w-full rounded-lg border border-gray-300 px-3 py-2 focus:border-brand-600 focus:ring-2 focus:ring-brand-100">{{ old('special_instructions') }}</textarea>

                <div class="pass-divider -mx-4 my-4"></div>

                <div class="flex items-center justify-between">
                    <span class="text-sm text-gray-600"><span x-text="count"></span> item(s)</span>
                    <span class="font-display text-3xl font-bold text-brand-700" x-text="'₱' + total.toLocaleString('en-PH', { minimumFractionDigits: 2, maximumFractionDigits: 2 })"></span>
                </div>

                <button type="submit" class="mt-4 w-full rounded-full bg-brand-600 px-6 py-3 font-bold text-white hover:bg-brand-700 focus:ring-4 focus:ring-brand-100">Create order</button>
                <p class="mt-2 text-center text-xs text-gray-500">The final total is recalculated by the server.</p>
            </div>
        </aside>
    </form>
@endsection
