@extends('layouts.admin')

@section('title', 'Billing · Passenger Seat')
@section('page-title', 'Billing')

@section('content')
    <div class="admin-operation-page admin-billing">
        <header class="admin-operation-hero">
            <div>
                <h1>Billing · Customer QR orders</h1>
                <p>Orders placed by guests from enabled table QR menus</p>
            </div>
            <form method="GET" action="{{ route('admin.billing') }}" role="search">
                <input type="search" name="q" value="{{ $search }}"
                       placeholder="Search table, order or customer" aria-label="Search QR billing orders">
                <button type="submit" class="admin-operation-button">Search</button>
            </form>
        </header>

        <section class="admin-operation-card">
            <div class="admin-operation-card-heading">
                <h2>Table order billing</h2>
                <span class="admin-operation-count">{{ $orders->count() }} orders</span>
            </div>

            @if ($orders->isEmpty())
                <div class="admin-operation-empty">
                    <strong>No customer QR table orders yet.</strong>
                    <span>Orders from table QR menus will appear here for billing.</span>
                </div>
            @else
                <div class="admin-operation-table-wrap">
                    <table class="admin-operation-table">
                        <thead>
                            <tr>
                                <th scope="col">Order #</th>
                                <th scope="col">Table</th>
                                <th scope="col">Customer</th>
                                <th scope="col">Items</th>
                                <th scope="col">Order total</th>
                                <th scope="col">Payment</th>
                                <th scope="col">Status</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($orders as $order)
                                <tr>
                                    <td>#{{ $order->reference_number }}</td>
                                    <td>{{ $order->table_number ? 'Table QR · Table '.$order->table_number : 'Table QR · Table not recorded' }}</td>
                                    <td>{{ $order->customer_name ?: 'QR guest' }}</td>
                                    <td>{{ $order->items->sum('quantity') }} items</td>
                                    <td>₱{{ number_format((float) $order->total_amount, 2) }}</td>
                                    <td>
                                        {{ $order->payment_status === 'paid'
                                            ? match ($order->payment_method) {
                                                'cash' => 'Cash',
                                                'gcash' => 'GCash',
                                                'card' => 'Card',
                                                default => 'Paid',
                                            }
                                            : 'Unpaid' }}
                                    </td>
                                    <td>
                                        <span class="admin-operation-badge status-{{ $order->status }}">
                                            {{ str($order->status)->headline() }}
                                        </span>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif
        </section>
    </div>
@endsection
