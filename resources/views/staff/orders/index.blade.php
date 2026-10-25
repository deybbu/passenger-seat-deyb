@extends(auth()->user()->hasRole('cashier') ? 'layouts.pos' : 'layouts.staff')

@section('title', 'Orders')

@section('content')
    <div class="mb-4 flex flex-wrap items-center justify-between gap-3">
        <div>
            <h1 class="font-display text-4xl font-bold uppercase leading-none">Order board</h1>
            <p class="text-sm text-gray-500">Updates automatically every 15 seconds.</p>
        </div>
        <a href="{{ route('staff.orders.create') }}" class="inline-flex items-center gap-2 rounded-full bg-brand-600 px-5 py-2.5 font-semibold text-white hover:bg-brand-700">
            <x-icon name="plus" class="h-4 w-4" /> Walk-in order
        </a>
    </div>

    <div data-react-root="order-board">
        @include('staff.orders._board')
    </div>
@endsection
