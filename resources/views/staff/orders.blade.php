<!doctype html>
<html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"><title>Kitchen Order Board</title>@vite(['resources/css/app.css','resources/js/app.js'])</head>
<body class="min-h-screen bg-slate-100 text-slate-900">
<header class="bg-red-800 text-white"><div class="mx-auto flex max-w-7xl items-center justify-between px-4 py-5"><div><p class="text-xs font-bold uppercase tracking-[.2em] text-red-100">Passenger Seat · Staff</p><h1 class="text-2xl font-black">Order board</h1></div><a class="font-bold underline" href="{{ route('menu.index') }}">Public menu</a></div></header>
<main class="mx-auto max-w-7xl px-4 py-8">
@if (session('success'))<div class="mb-5 rounded-xl bg-emerald-50 p-3 font-semibold text-emerald-800">{{ session('success') }}</div>@endif
<div class="grid gap-5 md:grid-cols-2 xl:grid-cols-3">
@forelse ($orders as $order)
<article class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
<div class="flex items-start justify-between gap-3"><div><p class="text-xs font-bold uppercase tracking-widest text-red-700">Order reference</p><h2 class="mt-1 text-xl font-black">{{ $order->reference_number }}</h2></div><span class="rounded-full bg-amber-100 px-3 py-1 text-xs font-bold text-amber-900">{{ ucfirst($order->status) }}</span></div>
<p class="mt-3 text-sm text-slate-500">{{ $order->created_at->format('M d, Y · h:i A') }} · {{ $order->type === 'dine_in' ? 'Table ' . $order->table_number : 'Take-out' }}</p>
<ul class="mt-4 space-y-2 border-y border-slate-100 py-4">@foreach ($order->items as $item)<li class="flex justify-between gap-3 text-sm"><span><strong>{{ $item->quantity }}×</strong> {{ $item->item_name }}</span><span class="font-bold">₱{{ number_format((float) $item->price * $item->quantity, 2) }}</span></li>@endforeach</ul>
@if ($order->special_instructions)<div class="mt-4 rounded-lg bg-red-50 p-3 text-sm"><p class="font-bold text-red-900">Customer request</p><p class="mt-1 text-red-800">{{ $order->special_instructions }}</p></div>@endif
<p class="mt-4 text-lg font-black">₱{{ number_format((float) $order->total_amount, 2) }}</p>
<div class="mt-4 flex flex-wrap gap-2">
@if ($order->status === 'pending')<form method="POST" action="{{ route('staff.orders.status', $order) }}">@csrf @method('PATCH')<input type="hidden" name="status" value="preparing"><button class="rounded-lg bg-red-700 px-3 py-2 text-sm font-bold text-white">Start preparing</button></form>@endif
@if ($order->status === 'preparing')<form method="POST" action="{{ route('staff.orders.status', $order) }}">@csrf @method('PATCH')<input type="hidden" name="status" value="completed"><button class="rounded-lg bg-emerald-700 px-3 py-2 text-sm font-bold text-white">Mark completed</button></form>@endif
@if (in_array($order->status, ['pending','preparing'], true))<form method="POST" action="{{ route('staff.orders.status', $order) }}">@csrf @method('PATCH')<input type="hidden" name="status" value="cancelled"><button class="rounded-lg border border-red-200 px-3 py-2 text-sm font-bold text-red-700">Cancel</button></form>@endif
</div></article>
@empty<div class="col-span-full rounded-2xl border border-dashed border-slate-300 bg-white p-12 text-center"><h2 class="text-xl font-black">No active orders</h2><p class="mt-2 text-slate-500">New customer orders will appear here.</p></div>@endforelse
</div>
<div class="mt-6">{{ $orders->links() }}</div>
</main></body></html>
