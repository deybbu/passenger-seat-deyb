@extends('layouts.admin')

@section('title', 'Purchase orders')
@section('page-title', 'Purchase orders')

@section('content')
    <div class="admin-action-bar">
        <a href="{{ route('admin.inventory') }}" class="btn-outline">Inventory</a>
        <a href="{{ route('admin.barcode') }}" class="btn-red">Receive package</a>
    </div>
    <section class="admin-card">
        <div class="admin-card-header">
            <span class="admin-card-title">Purchase orders</span>
            <span class="admin-muted">Generate an order from an inventory alert.</span>
        </div>
        @include('admin.partials.purchase-orders-table')
    </section>
@endsection
